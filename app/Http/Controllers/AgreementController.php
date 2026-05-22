<?php

namespace App\Http\Controllers;

use App\Models\Owners;
use App\Models\User;
use App\Models\Agreements;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

use Illuminate\Http\Request;
use Termwind\Components\Raw;

class AgreementController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $query = Agreements::with(['user', 'historyVersions']) // 关键：关联历史版本
            ->where('status', 'active');

        // 权限判断逻辑
        $user = Auth::user();
        if (!Gate::allows('super-admin')) {
            if ($user->role === 'agentAdmin') {
                // 如果是 Agent，先去 Owners 表里找出他代理的所有 Owner 的 user_id
                $managedOwnerUserIds = \App\Models\Owners::where('agent_id', $user->id)->pluck('user_id');
                
                // Agent 可以看到：自己的协议 OR 他代理的 Owner 的协议
                $query->where(function($q) use ($user, $managedOwnerUserIds) {
                    $q->where('user_id', $user->id)
                      ->orWhereIn('user_id', $managedOwnerUserIds);
                });
            } else {
                // 其他普通角色 (Owner, OwnerAdmin 等) 只能看属于自己的协议
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

        // 关键修正：因为 $agreements 是一个集合，需要循环处理每一项
        $agreements->getCollection()->transform(function ($agreement) {
            // 将 3 个或以上的换行符缩减为 2 个，保持段落感但去除冗余空白
            $agreement->content = preg_replace("/(\r\n|\r|\n){3,}/", "\n\n", $agreement->content);
            return $agreement;
        });

        return view('adminSide.setting.agreement.index', compact('agreements'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();

        // 权限布尔值判断
        $isOwnerAdmin = $user->role === 'ownerAdmin';
        $isOwnerAgentAdmin = in_array($user->role, ['ownerAdmin', 'agentAdmin']);
        $ownerAdmin = $user->name;

        // 💡 核心修复：根据不同角色拉取不同的 Owners 数据
        if ($user->role === 'agentAdmin') {
            // AgentAdmin: 通过 agent_id 关联查找该 Agent 负责的所有 Owners
            $owners = Owners::with('user')->where('agent_id', $user->id)->get();
        } elseif ($user->role === 'admin' || $user->role === 'superadmin') {
            // Admin / Superadmin: 可以看到所有的 owner
            $owners = Owners::with('user')->get();
        } else {
            // OwnerAdmin (或其他没有权限查看下拉框的角色): 传个空的集合过去节省数据库性能
            $owners = collect();
        }

        // 获取动态变量占位符
        $placeholders = self::getAvailablePlaceholders();

        // 处理继承/编辑逻辑
        $sourceAgreement = null;
        if ($request->has('from_id')) {
            $sourceAgreement = Agreements::findOrFail($request->from_id);
        }

        // 将所有变量（包括 $user 实例本身）传递给前端 Blade 视图
        return view('adminSide.setting.agreement.create', compact(
            'owners',
            'isOwnerAgentAdmin',
            'placeholders',
            'sourceAgreement',
            'isOwnerAdmin',
            'ownerAdmin',
            'user' // 把 $user 传给前端，前端就可以通过 $user->role 来判断显示逻辑了
        ));
    }

    public function store(Request $request)
    {
        // 1. 基础验证
        $validated = $request->validate([
            'parent_agreement_id' => 'nullable|string',
            'type' => 'required|string',
            'user_id' => 'nullable|exists:users,id',
            'title' => 'required|string|max:255',
            'version' => 'required|string',
            'content' => 'required|string',
        ]);

        // 2. 版本冲突检查（保持你原有的逻辑）
        $parentIdFromRequest = $request->input('parent_agreement_id');
        if ($parentIdFromRequest) {
            $versionExists = Agreements::where(function ($query) use ($parentIdFromRequest) {
                $query->where('parent_agreement_id', $parentIdFromRequest)
                    ->orWhere('id', $parentIdFromRequest);
            })
                ->where('version', $request->version)
                ->exists();

            if ($versionExists) {
                return back()->withErrors(['version' => 'This version already exists for this agreement tree.'])->withInput();
            }
        }

        // 3. 数据规范化处理
        $validated['status'] = 'active';

        if ($validated['type'] !== 'rental_lease') {
            $validated['user_id'] = null;
        }

        // 4. 【核心修正】状态联动与 ID 继承处理
        if ($validated['status'] === 'active') {
            // 先找出那个目前还是 active 的“老前辈”
            $oldActive = Agreements::where('type', $validated['type'])
                ->where('user_id', $validated['user_id'])
                ->where('status', 'active')
                ->first();

            if ($oldActive) {
                // 关键：把新记录的 parent_id 指向这个老记录的 ID
                $validated['parent_agreement_id'] = $oldActive->id;

                // 把老记录变退休 (inactive)
                $oldActive->update(['status' => 'inactive']);
            }
        }

        // 5. 执行创建
        Agreements::create($validated);

        return redirect()->route('admin.agreements.index')
            ->with('success', 'Agreement version created and activated successfully!');
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
    public function activate(Agreements $agreement)
    {
        try {
            DB::transaction(function () use ($agreement) {
                // 1. 将同类型、同房东的所有协议设为 inactive
                Agreements::where('type', $agreement->type)
                    ->where('owner_id', $agreement->owner_id)
                    ->update(['status' => 'inactive']);

                // 2. 将当前选中的协议设为 active
                $agreement->update(['status' => 'active']);
            });

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false], 500);
        }
    }
}
