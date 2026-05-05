<?php

namespace App\Http\Controllers;
use App\Models\Owners;
use App\Models\Agreements;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;
use Termwind\Components\Raw;

class AgreementController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $query = Agreements::with(['owner.user', 'historyVersions']) // 关键：关联历史版本
                ->where('status', 'active');

        if ($search) {
            $query->where(function($q) use ($search) {
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
        // 1. 抓取所有房东数据，只需要 ID 和 Name 即可
        $user = Auth::user();
        $isOwnerAgentAdmin = false;
        $owners = Owners::with('user:id,name')->get(['id', 'user_id']);

        if ($user->role === 'ownerAdmin' || $user->role === 'agentAdmin'){
            $isOwnerAgentAdmin = true;
        }

        $placeholders = self::getAvailablePlaceholders();

        $sourceAgreement = null;
        if ($request->has('from_id')) {
            $sourceAgreement = Agreements::findOrFail($request->from_id);
        }

        // 2. 用 compact 把变量传给视图
        return view('adminSide.setting.agreement.create', compact('owners', 'isOwnerAgentAdmin', 'placeholders', 'sourceAgreement'));
    }

    public function store(Request $request)
    {
        // 1. 基础验证
        $validated = $request->validate([
            'parent_agreement_id' => 'nullable|string',
            'type' => 'required|string',
            'owner_id' => 'nullable|exists:owners,id',
            'title' => 'required|string|max:255',
            'version' => 'required|string',
            'content' => 'required|string',
        ]);

        // 2. 版本冲突检查（保持你原有的逻辑）
        $parentIdFromRequest = $request->input('parent_agreement_id');
        if ($parentIdFromRequest) {
            $versionExists = Agreements::where(function($query) use ($parentIdFromRequest) {
                    $query->where('parent_agreement_id', $parentIdFromRequest)
                        ->orWhere('id', $parentIdFromRequest);
                })
                ->where('version', strtoupper($request->version))
                ->exists();

            if ($versionExists) {
                return back()->withErrors(['version' => 'This version already exists for this agreement tree.'])->withInput();
            }
        }

        // 3. 数据规范化处理
        $validated['status'] = 'active';
        $validated['title']  = strtoupper($validated['title']);
        $validated['version'] = strtoupper($validated['version']);

        if ($validated['type'] !== 'rental_lease') {
            $validated['owner_id'] = null;
        }

        // 4. 【核心修正】状态联动与 ID 继承处理
        if ($validated['status'] === 'active') {
            // 先找出那个目前还是 active 的“老前辈”
            $oldActive = Agreements::where('type', $validated['type'])
                ->where('owner_id', $validated['owner_id'])
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
                ['label' => 'End Agreement Date','value' => '{end_agreement_date}'],
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
