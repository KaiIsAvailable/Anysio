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
        $categoryFilter = $request->input('category'); 
        
        // 🌟 核心修復：移除 ->where('status', 'active')
        // 改成：利用原生 SQL 子查詢，抓取每個「範本家族」中最新建立的那一筆！
        $query = DocumentTemplate::with(['user']) 
            ->whereIn('id', function($q) {
                $q->selectRaw('MAX(id)')
                  ->from('document_templates')
                  ->groupByRaw('COALESCE(parent_id, id)');
            });

        // 权限判断逻辑
        $user = get_effective_user();
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

        // 搜索过滤
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('version', 'like', "%{$search}%");
            });
        }

        // 💡 应用类别过滤
        if ($categoryFilter && $categoryFilter !== 'all') {
            $query->where('category', $categoryFilter);
        }

        // 💡 使用 appends($request->query())
        $agreements = $query->latest()->paginate(10)->appends($request->query());

        // 獲取本頁所有協議的「始祖 ID」 (Root Parent ID)
        $rootIds = $agreements->map(function ($a) {
            return $a->parent_id ?: $a->id;
        })->unique()->toArray();

        // 一次性把這些家族的所有成員全部撈出來
        $allHistories = collect();
        if (!empty($rootIds)) {
            $allHistories = DocumentTemplate::whereIn('id', $rootIds)
                ->orWhereIn('parent_id', $rootIds)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // 💡 將資料整理並綁定給前端
        $agreements->getCollection()->transform(function ($agreement) use ($allHistories) {
            
            $rootId = $agreement->parent_id ?: $agreement->id;
            
            // 將屬於這個家族的成員（含始祖）過濾出來
            $family = $allHistories->filter(function ($h) use ($rootId) {
                return $h->id == $rootId || $h->parent_id == $rootId;
            })->values();

            // 🌟 核心修復：在家族中尋找是否有 'active' 的版本
            $activeMember = $family->firstWhere('status', 'active');

            // 🌟 對調邏輯 (Swapping)：如果有 active 版本，強制讓它成為主卡片；沒有的話，維持最新的 $agreement
            $representative = $activeMember ?: $agreement;

            $representative->html_template = preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", $representative->html_template ?? '');
            
            // 把整個家族的歷史塞進去，並確保 Active 版本永遠排在陣列的第一位
            $representative->full_history = $family->sortBy(function ($h) {
                return $h->status === 'active' ? 0 : 1;
            })->values();

            return $representative;
        });

        // 生成当前用户有权限看到的分类 Tabs 列表
        $isAdmin = in_array($user->role, ['admin', 'superadmin', 'super-admin']);
        $availableCategories = [
            'agreement' => 'Agreement',
            'invoice'   => 'Invoice',
            'receipt'   => 'Receipt'
        ];
        if ($isAdmin) {
            $availableCategories = [
                'tos'     => 'Terms of Service',
                'privacy' => 'Privacy Policy'
            ] + $availableCategories;
        }

        return view('adminSide.setting.document_template.index', compact('agreements', 'availableCategories', 'categoryFilter'));
    }

    public function create(Request $request)
    {
        $user = get_effective_user();

        $isOwnerAdmin = $user->role === 'ownerAdmin';
        $isAgentAdmin = $user->role === 'agentAdmin';
        $ownerAdmin = [$user->id, $user->name];

        if ($isOwnerAdmin) {
            $ownerOptions = $this->getAuthorizedOwners();
        } elseif ($isAgentAdmin) {
            $ownerOptions = $this->getAuthorizedOwnersOnly();
        } else {
            $ownerOptions = $this->getAuthorizedOwners();
        }

        // Ensure the current user's account is always available as an option
        if (!$ownerOptions->contains('id', $user->id)) {
            $ownerOptions->prepend($user);
        }

        $sourceAgreement = null;
        if ($request->has('from_id')) {
            $sourceAgreement = DocumentTemplate::findOrFail($request->from_id);
        }

        // 🌟 核心修改：獲取當前所有 active 的模板分布，以供前端做「防呆檢查」
        // 結構: [ 'user_id_1' => ['invoice', 'agreement'], 'system' => ['invoice'] ]
        $activeTemplatesList = DocumentTemplate::where('status', 'active')
            ->select('user_id', 'category')
            ->get();
            
        $activeMap = [];
        foreach ($activeTemplatesList as $t) {
            $uid = $t->user_id ?? 'system';
            if (!isset($activeMap[$uid])) {
                $activeMap[$uid] = [];
            }
            if (!in_array($t->category, $activeMap[$uid])) {
                $activeMap[$uid][] = $t->category;
            }
        }

        return view('adminSide.setting.document_template.create', compact(
            'ownerOptions',
            'isAgentAdmin',
            'sourceAgreement',
            'isOwnerAdmin',
            'ownerAdmin',
            'user',
            'activeMap' // 傳給前端
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

    public function activate(DocumentTemplate $documentTemplate)
    {
        try {
            DB::transaction(function () use ($documentTemplate) {
                
                // 🌟 核心防呆：全域替換！
                // 尋找「同一個使用者」且「同一個分類」下的所有 active 範本
                $query = DocumentTemplate::where('category', $documentTemplate->category)
                                         ->where('status', 'active');
                                         
                // 區分是房東自訂範本，還是系統預設範本
                if ($documentTemplate->user_id) {
                    $query->where('user_id', $documentTemplate->user_id);
                } else {
                    $query->whereNull('user_id');
                }

                // 將它們全部強制退休 (Inactive)
                $query->update(['status' => 'inactive']);

                // 最後，將當前點擊的這份範本霸氣登基 (Active)
                $documentTemplate->update(['status' => 'active']);
            });

            return redirect()->back()->with('success', 'Template activated successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Status update failed: ' . $e->getMessage());
        }
    }

    public function edit(DocumentTemplate $documentTemplate)
    {
        $user = Auth::user();

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
                'id' => Auth::id(),
                'name' => 'System Admin',
            ]);
        }

        return view('adminSide.setting.document_template.edit', compact(
            'documentTemplate',
            'ownerOptions',
            'isAgentAdmin',
            'isOwnerAdmin',
            'ownerAdmin',
            'user'
        ));
    }

    public function update(Request $request, DocumentTemplate $documentTemplate)
    {
        $validated = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'category' => 'required|string',
            'title' => 'required|string|max:255',
            'version' => 'required|string|max:50',
            'details' => 'nullable|string',
            'html_template' => 'required|string',
        ]);

        $rootParentId = $documentTemplate->parent_id ?: $documentTemplate->id;
        $versionExists = DocumentTemplate::where(function($q) use ($rootParentId) {
            $q->where('id', $rootParentId)->orWhere('parent_id', $rootParentId);
        })->where('version', $validated['version'])->exists();

        if ($versionExists) {
            return back()->with('error', 'Version ' . $validated['version'] . ' already exists! Please use a different version number.')->withInput();
        }

        $this->documentTemplateService->createNewVersion($documentTemplate, $validated);

        return redirect()->route('admin.document-templates.index')
                         ->with('success', 'New template version saved successfully!');
    }
}