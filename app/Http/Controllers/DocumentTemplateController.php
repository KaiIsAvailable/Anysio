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
        $categoryFilter = $request->input('category'); // 💡 1. 接收分类筛选参数
        
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

        // 搜索过滤
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('version', 'like', "%{$search}%");
            });
        }

        // 💡 2. 应用类别过滤 (如果选了 specific category 且不是 'all')
        if ($categoryFilter && $categoryFilter !== 'all') {
            $query->where('category', $categoryFilter);
        }

        // 💡 3. 使用 appends($request->query()) 确保翻页时不会丢失 category 筛选和 search 参数
        $agreements = $query->latest()->paginate(10)->appends($request->query());

        // 獲取本頁所有協議的「始祖 ID」 (Root Parent ID)
        $rootIds = $agreements->map(function ($a) {
            return $a->parent_id ?: $a->id;
        })->unique()->toArray();

        // 一次性把這些家族的所有成員（包含始祖 v1.0）全部撈出來，完美解決 N+1 效能問題
        $allHistories = collect();
        if (!empty($rootIds)) {
            $allHistories = DocumentTemplate::whereIn('id', $rootIds)
                ->orWhereIn('parent_id', $rootIds)
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // 💡 4. 將資料整理並綁定給前端
        $agreements->getCollection()->transform(function ($agreement) use ($allHistories) {
            // 💡 修正：把 content 改為真實的欄位 html_template
            $agreement->html_template = preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", $agreement->html_template ?? '');
            
            // 將屬於這個家族的成員（含始祖）過濾出來
            $rootId = $agreement->parent_id ?: $agreement->id;
            $agreement->full_history = $allHistories->filter(function ($h) use ($rootId) {
                return $h->id == $rootId || $h->parent_id == $rootId;
            })->values();

            return $agreement;
        });

        // 💡 4. 生成当前用户有权限看到的分类 Tabs 列表
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
                'id' => Auth::id(),
                'name' => 'System Admin',
            ]);
        }

        // 处理继承/编辑逻辑
        $sourceAgreement = null;
        if ($request->has('from_id')) {
            $sourceAgreement = DocumentTemplate::findOrFail($request->from_id);
        }

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
        // 略...保留你原有的变量逻辑
        return [];
    }

    public function activate(DocumentTemplate $documentTemplate)
    {
        try {
            DB::transaction(function () use ($documentTemplate) {
                $rootParentId = $documentTemplate->parent_id ?: $documentTemplate->id;

                DocumentTemplate::where(function ($q) use ($rootParentId) {
                    $q->where('id', $rootParentId)
                      ->orWhere('parent_id', $rootParentId);
                })
                ->update(['status' => 'inactive']);

                $documentTemplate->update(['status' => 'active']);
            });

            // 💡 成功後直接刷新當前頁面，並帶著 success session
            return redirect()->back()->with('success', 'Version activated successfully!');
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
            // 💡 若版本號重複，使用 back()->with('error') 讓系統元件顯示錯誤
            return back()->with('error', 'Version ' . $validated['version'] . ' already exists! Please use a different version number.')->withInput();
        }

        $this->documentTemplateService->createNewVersion($documentTemplate, $validated);

        // 💡 完美對接你的 Laravel Component
        return redirect()->route('admin.document-templates.index')
                         ->with('success', 'New template version saved successfully!');
    }
}