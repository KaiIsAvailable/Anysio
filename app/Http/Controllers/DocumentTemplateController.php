<?php

namespace App\Http\Controllers;

use App\Models\Owners;
use App\Models\User;
use App\Models\DocumentTemplate;
use App\Traits\RoleBasedDataTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use App\Services\DocumentTemplateService;

use Illuminate\Http\Request;
use Termwind\Components\Raw;

class DocumentTemplateController extends Controller
{
    use RoleBasedDataTrait;
    
    public function __construct(
        protected DocumentTemplateService $documentTemplateService
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        
        // 💡 1. 移除原本的 historyVersions 預載入，改由下方手動精準加載
        $query = DocumentTemplate::with(['user']) 
            ->where('status', 'active');

        // 权限判断逻辑
        $user = Auth::user();
        if (!Gate::allows('super-admin')) {
            if ($user->role === 'agentAdmin') {
                $managedOwnerUserIds = Owners::where('agent_id', $user->id)->pluck('user_id');
                
                $query->where(function($q) use ($user, $managedOwnerUserIds) {
                    $q->where('user_id', $user->id)
                      ->orWhereIn('user_id', $managedOwnerUserIds);
                });
            } else {
                $query->where('user_id', $user->id);
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('version', 'like', "%{$search}%");
            });
        }

        $agreements = $query->latest()->paginate(10);

        // 💡 2. 獲取本頁所有協議的「始祖 ID」 (Root Parent ID)
        $rootIds = $agreements->map(function ($a) {
            return $a->parent_agreement_id ?: $a->id;
        })->unique()->toArray();

        // 💡 3. 一次性把這些家族的所有成員（包含始祖 v1.0）全部撈出來，完美解決 N+1 效能問題
        $allHistories = collect();
        if (!empty($rootIds)) {
            $allHistories = DocumentTemplate::whereIn('id', $rootIds)
                ->orWhereIn('parent_agreement_id', $rootIds)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // 💡 4. 將資料整理並綁定給前端
        $agreements->getCollection()->transform(function ($agreement) use ($allHistories) {
            $agreement->content = preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", $agreement->content);
            
            // 將屬於這個家族的成員（含始祖）過濾出來，綁定到自定義的 full_history 屬性
            $rootId = $agreement->parent_agreement_id ?: $agreement->id;
            $agreement->full_history = $allHistories->filter(function ($h) use ($rootId) {
                return $h->id == $rootId || $h->parent_agreement_id == $rootId;
            })->values();

            return $agreement;
        });

        return view('adminSide.setting.document_template.index', compact('agreements'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();

        // 权限布尔值判断
        $isOwnerAdmin = $user->role === 'ownerAdmin';
        $isAgentAdmin = $user->role === 'agentAdmin';
        $ownerAdmin = [$user->id, $user->name];

        if ($isOwnerAdmin) {
            $ownerOptions = collect([$user]);
        } elseif ($isAgentAdmin) {
            $ownerOptions = $this->getAuthorizedOwnersOnly();
        } else {
            $ownerOptions = $this->getAuthorizedOwners();

            $ownerOptions->prepend((object) [
                'id' => '',
                'name' => 'System Admin',
            ]);
        }

        // 处理继承/编辑逻辑
        $sourceAgreement = null;
        if ($request->has('from_id')) {
            $sourceAgreement = DocumentTemplate::findOrFail($request->from_id);
        }

        // 将所有变量（包括 $user 实例本身）传递给前端 Blade 视图
        return view('adminSide.setting.document_template.create', compact(
            'ownerOptions',
            'isAgentAdmin',
            'sourceAgreement',
            'isOwnerAdmin',
            'ownerAdmin',
            'user'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'category' => 'required|string',
            'title' => 'required|string|max:255',
            'version' => 'required|string|max:50',
            'details' => 'nullable|string',
            'html_template' => 'required|string',
        ]);

        $this->documentTemplateService->create($validated);

        return redirect()
            ->route('admin.document-templates.index')
            ->with('success', 'Document template created successfully.');
    }

    private static function getAvailablePlaceholders()
    {
        return [
            'Status' => [
                ['label' => 'Status', 'value' => '{status}'],
            ],
            'Personal Info' => [
                ['label' => 'Tenant Name',       'value' => '{tenant_name}'],
                ['label' => 'Tenant IC',         'value' => '{tenant_ic}'],
                ['label' => 'Owner Name',        'value' => '{owner_name}'],
                ['label' => 'Owner IC',          'value' => '{owner_ic}'],
            ],
            'Property Info' => [
                ['label' => 'Address',           'value' => '{property_address}'],
                ['label' => 'Property Type',     'value' => '{property_type}'],
                ['label' => 'Property Name',     'value' => '{property_name}'],
            ],
            'Rental & Deposit' => [
                ['label' => 'Rent Mode',         'value' => '{rent_mode}'],
                ['label' => 'Rent Price',        'value' => '{rent_price}'],
                ['label' => 'Deposit Mode',      'value' => '{deposit_mode}'],
                ['label' => 'Security Deposit',  'value' => '{security_deposit}'],
                ['label' => 'Utility Deposit',   'value' => '{utilities_deposit}'],
            ],
            'Dates & Others' => [
                ['label' => 'Start Date',        'value' => '{start_date}'],
                ['label' => 'End Date',          'value' => '{end_date}'],
                ['label' => 'Check Out Date',    'value' => '{check_out_date}'],
                ['label' => 'End Agreement Date', 'value' => '{end_agreement_date}'],
            ],
        ];
    }

    public function activate(DocumentTemplate $documentTemplate)
    {
        try {
            DB::transaction(function () use ($documentTemplate) {
                // 【核心修正】在历史纪录切换版本时，只影响同一个家族树的协议
                // 获取家族树的 Root ID
                $rootParentId = $documentTemplate->parent_id ?: $documentTemplate->id;

                // 1. 将这棵家族树里的所有协议都设为 inactive
                DocumentTemplate::where(function ($q) use ($rootParentId) {
                    $q->where('id', $rootParentId)
                      ->orWhere('parent_id', $rootParentId);
                })
                ->update(['status' => 'inactive']);

                // 2. 将当前选中的版本恢复为 active
                $documentTemplate->update(['status' => 'active']);
            });

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false], 500);
        }
    }
}