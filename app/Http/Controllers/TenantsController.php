<?php

namespace App\Http\Controllers;

use App\Models\{Tenants, UserManagement};
use App\Services\{LeaseService};
use App\Models\User;
use App\Models\Room;
use App\Models\Unit;
use App\Models\Property;
use App\Http\Controllers\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\{Auth, Gate, Log, DB};
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Traits\RoleBasedDataTrait;
use App\Services\FileService;
use App\Models\Lease;
use App\Models\Invoice;

class TenantsController extends Controller
{
    use RoleBasedDataTrait;

    public const IC_DOCUMENT_RULES = 'nullable|file|max:2048|mimes:jpeg,png,jpg,gif,pdf|mimetypes:image/jpeg,image/png,image/gif,application/pdf';

    public function leases()
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        $leases = Lease::with([
            'tenant.user',
            'charges',
            'documentTemplate',

            // 根据不同 Lease 类型加载对应资料
            'leasable' => function ($morphTo) {
                $morphTo->morphWith([
                    Room::class => ['unit.owner'],
                    Unit::class => ['owner'],
                    Property::class => ['owner'],
                ]);
            },
        ])
            // Tenant 只能查看属于自己的 Lease
            ->where('tenant_id', $tenant->id)
            ->where('is_current', true)
            ->latest()
            ->get();

        return view('tenantSide.tenants.leases.index', compact('leases'));
    }

    public function leaseShow(Lease $lease)
    {
        $user = Auth::user();

        $tenant = $user->tenant;

        if (!$tenant) {
            abort(404, 'Tenant profile not found.');
        }

        // 防止 Tenant 透過修改 URL 查看其他 Tenant 的 Lease
        if ($lease->tenant_id !== $tenant->id) {
            abort(403);
        }

        $lease->load([
            'tenant.user',
            'charges.feeType',
            'documentTemplate',

            'leasable' => function ($morphTo) {
                $morphTo->morphWith([
                    Room::class => ['unit.owner.owner'],
                    Unit::class => ['owner.owner'],
                    Property::class => ['owner.owner'],
                ]);
            },
        ]);

        return view('tenantSide.tenants.leases.show', compact('lease'));
    }

    public function index(Request $request)
    {
        if (Gate::denies('owner-admin') && Gate::denies('tenant.tab')) {
            return view('errors.403');
        }

        $user = get_effective_user();
        $users = UserManagement::with('user')->get()->pluck('user');

        if ($user->role === 'tenant') {
            return view('adminSide.tenants.dashboard');
        }

        // 1. 预加载关联
        $query = Tenants::with(['user', 'emergencyContacts', 'leases.room'])
            ->where('tenants.status', 'active');

        // --- 权限范围过滤重构 ---
        if (in_array($user->role, ['agent', 'agentAdmin', 'ownerAdmin'])) {
            $query->where('tenants.created_by', $user->id);
        } elseif ($user->role === ['owner']) {
            $query->where('tenants.owner_id', $user->id);
        }
        // ----------------------------

        // 2. 搜索逻辑 (包含展示的所有字段)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('users.name', 'like', '%' . $search . '%')
                        ->orWhere('users.email', 'like', '%' . $search . '%');
                })
                    ->orWhere('tenants.phone', 'like', '%' . $search . '%')
                    ->orWhere('tenants.ic_number', 'like', '%' . $search . '%')
                    ->orWhere('tenants.passport', 'like', '%' . $search . '%')
                    ->orWhere('tenants.nationality', 'like', '%' . $search . '%')
                    ->orWhere('tenants.gender', 'like', '%' . $search . '%')
                    ->orWhere('tenants.occupation', 'like', '%' . $search . '%');
            });
        }

        // 3. 排序逻辑 (Details: n, Contact Info: p, Joined Date: jd)
        $sort = $request->get('sort');
        if ($sort) {
            if (str_starts_with($sort, 'n_')) {
                $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
                $query->join('users', 'tenants.user_id', '=', 'users.id')
                    ->select('tenants.*')
                    ->orderBy('users.name', $direction);
            } elseif (str_starts_with($sort, 'p_')) {
                $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
                $query->orderBy('tenants.phone', $direction);
            } elseif (str_starts_with($sort, 'jd_')) {
                $direction = str_ends_with($sort, '_asc') ? 'asc' : 'desc';
                $query->orderBy('tenants.created_at', $direction);
            } else {
                $query->orderBy('tenants.created_at', 'asc');
            }
        } else {
            $query->orderBy('tenants.created_at', 'asc');
        }

        $tenants = $query->paginate(10)->onEachSide(1)->withQueryString();

        return view('adminSide.tenants.index', compact('tenants', 'users'));
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (Gate::denies('owner-admin') && Gate::denies('tenant.create')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        return view('adminSide.tenants.create');
    }

    public function store(Request $request, FileService $fileService)
    {
        if (Gate::denies('owner-admin') && Gate::denies('tenant.create')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        // 1. 处理随机 Email
        if ($request->has('random_email') && $request->random_email == '1') {
            $request->merge(['email' => 'tenant_' . time() . '_' . Str::random(5) . '@anysio.local']);
        }

        // 2. 校验数据 (统一抛出 ValidationException)
        $this->validateTenantData($request);

        $effectiveUser = get_effective_user();
        $effectiveUserId = $effectiveUser ? $effectiveUser->id : Auth::id();

        return DB::transaction(function () use ($request, $fileService, $effectiveUserId) {

            // 4. 创建租客 User 账号
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make(Str::random(10)),
                'role' => 'tenant',
            ]);

            // 5. 准备 Tenant 基础数据
            $tenantType = $request->input('tenant_type', 'individual');
            
            $data = [
                'user_id' => $user->id,
                'created_by' => $effectiveUserId,
                'tenant_type' => $tenantType,
                'phone' => $request->phone,
            ];

            // 根据类型区分字段写入
            if ($tenantType === 'corporate') {
                $data['company_name'] = $request->name; // 名字存在 name / 公司名
                $data['ssm_number'] = $request->ssm_number;
                $data['company_address'] = $request->company_address;
                
                // 清空个人字段
                $data['ic_number'] = null;
                $data['passport'] = null;
                $data['nationality'] = null;
                $data['gender'] = null;
                $data['occupation'] = null;

                // 公司 SSM 证书上传处理
                if ($request->hasFile('ssm_cert_path')) {
                    $userId = Auth::id();
                    $data['ssm_cert_path'] = $fileService->upload(
                        $request->file('ssm_cert_path'),
                        $userId,
                        'tenant_ssm'
                    );
                }
            } else {
                // 个人租客数据
                $data['nationality'] = $request->nationality;
                $data['gender'] = $request->gender;
                $data['occupation'] = $request->occupation;

                // 身份类型清理
                if ($request->identity_type === 'ic') {
                    $data['ic_number'] = $request->ic_number;
                    $data['passport'] = null;
                } else {
                    $data['passport'] = $request->passport;
                    $data['ic_number'] = null;
                }

                // 个人证件图片处理
                if ($request->hasFile('ic_photo_path')) {
                    $userId = Auth::id();
                    $data['ic_photo_path'] = $fileService->upload(
                        $request->file('ic_photo_path'),
                        $userId,
                        'tenant_ic'
                    );
                }
            }

            $tenant = Tenants::create($data);

            // 6. 紧急联系人处理
            if ($request->has('emergency_contacts')) {
                foreach ($request->emergency_contacts as $contact) {
                    if (empty($contact['name']) || empty($contact['phone']))
                        continue;
                    $tenant->emergencyContacts()->create([
                        'name' => $contact['name'],
                        'phone' => $contact['phone'],
                        'relationship' => $contact['relationship'] ?? '',
                    ]);
                }
            }

            return redirect()->route('admin.tenants.index')->with('success', 'Tenant created successfully');
        });
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tenants $tenant)
    {
        if (Gate::denies('owner-admin') && Gate::denies('tenant.edit')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        // No need to fetch users list as the user field is disabled/readonly
        $tenant->load('emergencyContacts'); // Eager load contacts
        //dd($tenant->emergencyContacts->toArray());
        return view('adminSide.tenants.edit', compact('tenant'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tenants $tenant, FileService $fileService)
    {
        if (Gate::denies('owner-admin') && Gate::denies('tenant.edit')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        // 1. 执行验证（内部若失败会抛出异常，自动返回）
        $this->validateTenantData($request, $tenant->id, $tenant->user_id);

        return DB::transaction(function () use ($tenant, $request, $fileService) {

            // 2. 更新关联的 User 账号
            $tenant->user->update([
                'name' => $request->name,
                'email' => $request->email,
                'email_verified_at' => $request->boolean('is_email_verified')
                    ? ($tenant->user->email_verified_at ?? now())
                    : null,
            ]);

            // 3. 准备 Tenant 基础数据
            $data = [
                'phone' => $request->phone,
            ];

            // 4. 根据租客类型分流更新字段
            if ($tenant->tenant_type === 'corporate') {
                $data['company_name'] = $request->name;
                $data['ssm_number'] = $request->ssm_number;
                $data['company_address'] = $request->company_address;

                // 清理个人字段
                $data['ic_number'] = null;
                $data['passport'] = null;
                $data['nationality'] = null;
                $data['gender'] = null;
                $data['occupation'] = null;

                // 公司 SSM 证书更新处理
                if ($request->hasFile('ssm_cert_path')) {
                    $userId = Auth::id();

                    if (!empty($tenant->ssm_cert_path)) {
                        $fileService->delete($tenant->ssm_cert_path);
                    }

                    $data['ssm_cert_path'] = $fileService->upload(
                        $request->file('ssm_cert_path'),
                        $userId,
                        'tenant_ssm'
                    );
                }
            } else {
                // 个人租客数据
                $data['nationality'] = $request->nationality;
                $data['gender'] = $request->gender;
                $data['occupation'] = $request->occupation;

                // 身份类型与号码清理
                $data['ic_number'] = $request->identity_type === 'ic' ? $request->ic_number : null;
                $data['passport'] = $request->identity_type === 'passport' ? $request->passport : null;

                // 个人证件图片处理
                if ($request->hasFile('ic_photo_path')) {
                    $userId = Auth::id();

                    if (!empty($tenant->ic_photo_path)) {
                        $fileService->delete($tenant->ic_photo_path);
                    }

                    $data['ic_photo_path'] = $fileService->upload(
                        $request->file('ic_photo_path'),
                        $userId,
                        'tenant_ic'
                    );
                }
            }

            $tenant->update($data);

            // 5. 处理紧急联系人 (逻辑修正)
            if ($request->has('emergency_contacts')) {
                // 先一次性清空旧的
                $tenant->emergencyContacts()->delete();

                // 一次性插入新的
                foreach ($request->emergency_contacts as $contact) {
                    $name = trim($contact['name'] ?? '');

                    // 过滤掉被标记删除或名字为空的行
                    if ($name !== '' && ($contact['delete'] ?? '0') !== '1') {
                        $tenant->emergencyContacts()->create([
                            'name' => $name,
                            'phone' => $contact['phone'] ?? '',
                            'relationship' => $contact['relationship'] ?? '',
                        ]);
                    }
                }
            }

            return redirect()->route('admin.tenants.index')
                ->with('success', 'Tenant and User updated successfully.');
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tenants $tenant)
    {
        if (Gate::denies('owner-admin') && Gate::denies('tenant.delete')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }
        // 1. 检查是否还有正在进行的有效租约
        // 假设你的 Lease 模型有一个 status 字段（如 'active'）
        // 或者通过日期判断：$tenant->leases()->where('end_date', '>=', now())->exists()
        $hasActiveLease = $tenant->leases()->where('status', 'active')->exists();

        if ($hasActiveLease) {
            return redirect()->back()->with('error', '该租客仍有未完成的有效租约，无法将其设为不活跃。请先终止相关租约。');
        }

        // 2. 如果没有有效租约，才允许修改状态
        $tenant->update(['status' => 'inactive']);

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant is inactive.');
    }

    public function dashboard(\App\Services\TenantDashboardService $dashboard)
    {
        return view('tenantSide.tenants.dashboard', $dashboard->data(Auth::user()));
    }

    public function invoices()
    {
        $user = Auth::user();

        $tenant = $user->tenant;

        if (!$tenant) {
            abort(404, 'Tenant profile not found.');
        }

        /*
    |--------------------------------------------------------------------------
    | Tenant Invoices
    |--------------------------------------------------------------------------
    | Tenant 只能看到属于自己 Lease 的 Invoice
    */

        $invoices = app(\App\Services\TenantDashboardService::class)->invoices($user)->with([
            'documentTemplate',
            'lease.tenant.user',
            'lease.leasable',
            'items.feeType',
            'transactions.documentTemplate',
            'transactions.approver',
        ])
            ->latest()
            ->paginate(10);

        return view(
            'tenantSide.tenants.invoices.index',
            compact('invoices')
        );
    }
    public function show(Tenants $tenant, LeaseService $leaseService, Request $request)
    {
        if (Gate::denies('owner-admin') && Gate::denies('tenant.show')) {
            return view('errors.403');
        }

        // 1. 先加载租户的基础关联（不需要分页的）
        $tenant->load([
            'emergencyContacts',
            'user:id,name,email'
        ]);

        // 2. 单独处理 Lease 的分页查询 (核心修正点)
        $leases = $tenant->leases()
            ->with([
                'charges',
                'leasable' => function ($morph) {
                    $morph->morphWith([
                        Room::class => ['unit'],
                        Unit::class,
                        Property::class,
                    ]);
                }
            ])
            ->orderBy('start_date', 'desc')
            ->paginate(5, ['*'], 'lease_page')
            ->onEachSide(1);

        $latestLease = $leases->first(); // 注意：从分页结果中获取第一个

        $user = get_effective_user();
        $leaseModalData = $leaseService->getLeaseFormPayload($user, $request);

        return view('adminSide.tenants.show', array_merge(
            $leaseModalData,
            [
                'tenant' => $tenant,
                'leases' => $leases,
                'latestLease' => $latestLease,
            ]
        ));
    }

    public function showIcPhoto(Request $request, $filename, FileService $fileService)
    {
        // Search by filename in either ic_photo_path or ssm_cert_path
        $tenant = Tenants::where('ic_photo_path', 'LIKE', '%' . $filename)
            ->orWhere('ssm_cert_path', 'LIKE', '%' . $filename)
            ->first();

        if (!$tenant) {
            abort(404, 'Tenant record not found.');
        }

        // Determine which path matches the filename and stream it
        $filePath = str_contains($tenant->ic_photo_path ?? '', $filename) 
            ? $tenant->ic_photo_path 
            : $tenant->ssm_cert_path;

        if (!$filePath) {
            abort(404, 'Document path not found.');
        }

        return $fileService->getStreamResponse($filePath);
    }

    public function viewIc(Tenants $tenant)
    {
        if (Gate::denies('owner-admin') && Gate::denies('tenant.view IC')) {
            return redirect()->back()->with('error', 'You have no permission.');
        }

        // Check if neither document is uploaded
        if (empty($tenant->ic_photo_path) && empty($tenant->ssm_cert_path)) {
            abort(404, 'No identity document or SSM certificate uploaded.');
        }

        return view('adminSide.tenants.view-ic', compact('tenant'));
    }


    /**
     * 统一处理 Tenant 和 Emergency Contacts 的验证逻辑 (支援 Individual 与 Corporate)
     */
    private function validateTenantData(Request $request, $tenantId = null, $userId = null)
    {
        $tenantType = $request->input('tenant_type', 'individual');

        // 数据清洗处理
        if ($tenantType === 'individual') {
            if ($request->identity_type === 'ic') {
                $request->merge(['passport' => null]);
            } else {
                $request->merge(['ic_number' => null]);
            }
        }

        // 1. 基础字段通用验证
        $rules = [
            'tenant_type' => 'required|in:individual,corporate',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $userId,
            'phone' => 'required|numeric',
            'emergency_contacts' => 'required|array|min:1',
            'emergency_contacts.*.name' => 'required|string|max:255',
            'emergency_contacts.*.phone' => 'required|numeric',
        ];

        // 2. 根据类型动态追加验证规则
        if ($tenantType === 'corporate') {
            $rules['ssm_number'] = 'required|string|max:50|unique:tenants,ssm_number,' . $tenantId;
            $rules['company_address'] = 'required|string|max:500';
            $rules['ssm_cert_path'] = self::IC_DOCUMENT_RULES; // 复用文件验证规则
        } else {
            $rules['identity_type'] = 'required|in:ic,passport';
            $rules['ic_number'] = [
                'nullable',
                'numeric',
                'digits:12',
                'required_if:identity_type,ic',
                Rule::unique('tenants', 'ic_number')
                    ->where(function ($query) {
                        return $query->where('created_by', Auth::id())
                            ->where('status', 'active');
                    })
                    ->ignore($tenantId),
            ];
            $rules['passport'] = 'nullable|string|max:30|regex:/^[A-Z0-9]+$/|required_if:identity_type,passport|unique:tenants,passport,' . $tenantId;
            $rules['nationality'] = 'required|string|max:100';
            $rules['gender'] = 'required|string|in:Male,Female,MALE,FEMALE';
            $rules['occupation'] = 'nullable|string|max:100';
            $rules['ic_photo_path'] = self::IC_DOCUMENT_RULES;
        }

        $request->validate($rules);

        // 3. 紧急联系人电话重复性检查
        $tenantPhone = trim($request->phone);
        foreach ($request->emergency_contacts as $index => $contact) {
            if (($contact['delete'] ?? '0') === '1')
                continue;

            $emergencyPhone = trim($contact['phone'] ?? '');
            if ($emergencyPhone !== '' && $emergencyPhone === $tenantPhone) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "emergency_contacts.{$index}.phone" => 'Emergency contact phone number cannot be the same as the tenant\'s phone number.'
                ]);
            }
        }
    }
}
