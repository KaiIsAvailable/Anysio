<?php

namespace App\Http\Controllers;

use App\Models\Tenants;
use App\Models\Owners;
use App\Models\User;
use App\Models\Room;
use App\Models\Unit;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class TenantsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->role === 'tenant') {
            return view('adminSide.tenants.dashboard');
        }

        // 1. 预加载关联
        $query = Tenants::with(['user', 'emergencyContacts', 'leases.room'])
            ->where('status', 'active');

        // --- 权限范围过滤重构 ---
        if (in_array($user->role, ['agent', 'agentAdmin', 'ownerAdmin'])) {
            $query->where('created_by', $user->id);
        } elseif ($user->role === ['owner']) {
            $query->where('owner_id', $user->id);
        }
        // ----------------------------

        // 2. 搜索逻辑 (保持不变)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        // 3. 排序逻辑 (保持不变)
        $sort = $request->get('sort', 'oldest');
        if (in_array($sort, ['name_asc', 'name_desc'])) {
            // 注意：Join 的时候 select tenants.* 避免 ID 冲突
            $query->join('users', 'tenants.user_id', '=', 'users.id')
                ->select('tenants.*');
        }

        switch ($sort) {
            case 'name_asc':
                $query->orderBy('users.name', 'asc');
                break;
            case 'name_desc':
                $query->orderBy('users.name', 'desc');
                break;
            case 'newest':
                $query->orderBy('tenants.created_at', 'desc');
                break;
            default:
                $query->orderBy('tenants.created_at', 'asc');
                break;
        }

        $tenants = $query->paginate(10)->withQueryString();

        return view('adminSide.tenants.index', compact('tenants'));
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('adminSide.tenants.create');
    }

    public function store(Request $request)
    {
        // 1. 处理随机 Email
        if ($request->has('random_email') && $request->random_email == '1') {
            $request->merge(['email' => 'tenant_' . time() . '_' . Str::random(5) . '@anysio.local']);
        }

        // 2. 校验数据 (统一抛出 ValidationException)
        $this->validateTenantData($request);

        return DB::transaction(function () use ($request) {

            // 4. 创建租客 User 账号
            $user = User::create([
                'name' => strtoupper($request->name),
                'email' => $request->email,
                'password' => Hash::make(Str::random(10)),
                'role' => 'tenant',
            ]);

            // 5. 准备 Tenant 详细数据
            $data = $request->only(['phone', 'ic_number', 'passport', 'nationality', 'gender', 'occupation']);
            $data['gender'] = strtoupper($request->gender);
            $data['occupation'] = strtoupper($request->occupation);
            $data['nationality'] = strtoupper($request->nationality);

            // 身份类型清理
            if ($request->identity_type === 'ic') {
                $data['passport'] = null;
            } else {
                $data['ic_number'] = null;
            }

            $data['user_id'] = $user->id;
            $data['created_by'] = Auth::id();

            // 图片处理
            if ($request->hasFile('ic_photo_path')) {
                $data['ic_photo_path'] = $request->file('ic_photo_path')->store('tenants/ic_path', 'local');
            }

            $tenant = Tenants::create($data);

            // 6. 紧急联系人处理
            if ($request->has('emergency_contacts')) {
                foreach ($request->emergency_contacts as $contact) {
                    if (empty($contact['name']) || empty($contact['phone']))
                        continue;
                    $tenant->emergencyContacts()->create([
                        'name' => strtoupper($contact['name']),
                        'phone' => $contact['phone'],
                        'relationship' => strtoupper($contact['relationship']) ?? '',
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
        // No need to fetch users list as the user field is disabled/readonly
        $tenant->load('emergencyContacts'); // Eager load contacts
        //dd($tenant->emergencyContacts->toArray());
        return view('adminSide.tenants.edit', compact('tenant'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tenants $tenant)
    {
        // 1. 执行验证（内部若失败会抛出异常，自动返回）
        $this->validateTenantData($request, $tenant->id, $tenant->user_id);

        return DB::transaction(function () use ($tenant, $request) {

            // 2. 更新关联的 User 账号
            $tenant->user->update([
                'name' => strtoupper($request->name),
                'email' => $request->email,
            ]);

            // 3. 准备 Tenant 数据
            $data = $request->only(['phone', 'nationality', 'gender', 'occupation']);
            $data['gender'] = strtoupper($request->gender);
            $data['occupation'] = strtoupper($request->occupation);
            $data['nationality'] = strtoupper($request->nationality);

            // 身份类型与号码清理
            $data['ic_number'] = $request->identity_type === 'ic' ? $request->ic_number : null;
            $data['passport'] = $request->identity_type === 'passport' ? $request->passport : null;

            // 4. 图片处理逻辑
            if ($request->hasFile('ic_photo_path')) {
                // 删除旧照片
                if ($tenant->ic_photo_path) {
                    Storage::disk('local')->delete($tenant->ic_photo_path);
                }
                // 存储新照片
                $data['ic_photo_path'] = $request->file('ic_photo_path')->store('tenants/ic_path', 'local');
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
                            'name' => strtoupper($name),
                            'phone' => $contact['phone'] ?? '',
                            'relationship' => strtoupper($contact['relationship']) ?? '',
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

    public function dashboard()
    {
        return view('adminSide.tenants.dashboard');
    }

    public function show(Tenants $tenant)
    {
        // 1. 保留原本的关联加载，但把 payments 移出去单独处理
        $tenant->load([
            'emergencyContacts',
            'user:id,name,email',
            'leases' => function ($query) {
                $query->with([
                    'leasable' => function ($morphTo) {
                        // 这里处理多态加载
                        $morphTo->morphWith([
                            Room::class => ['unit'], // 如果是房间，顺便带出它的单位
                            Unit::class,
                            Property::class,
                        ]);
                    }
                ])->orderBy('start_date', 'desc');
            }
        ]);

        // 2. 单独为 Rent 账单进行分页查询 (使用 payments() 方法)
        $rentPayments = $tenant->payments()
            ->where('payment_type', 'rent')
            ->where('status', '!=', 'void')
            ->orderBy('period', 'desc')
            ->latest()
            ->paginate(5, ['*'], 'rent_page'); // 指定分页参数名为 rent_page

        // 3. 单独为其他账单进行分页查询
        $otherPayments = $tenant->payments()
            ->where('payment_type', '!=', 'rent')
            ->where('status', '!=', 'void')
            ->latest()
            ->paginate(5, ['*'], 'other_page'); // 指定分页参数名为 other_page

        $latestLease = $tenant->leases->first();

        // 4. 将变量传回 View
        return view('adminSide.tenants.show', compact('tenant', 'latestLease', 'rentPayments', 'otherPayments'));
    }

    public function showIcPhoto($filename)
    {
        $path = 'tenants/ic_path/' . $filename;

        // 向后兼容：检查是否存放在带有 'private' 前缀的旧路径中
        if (!Storage::disk('local')->exists($path)) {
            $oldPath = 'private/tenants/ic_path/' . $filename;
            if (Storage::disk('local')->exists($oldPath)) {
                $path = $oldPath;
            } else {
                abort(404);
            }
        }

        return Storage::disk('local')->response($path);
    }

    /**
     * 统一处理 Tenant 和 Emergency Contacts 的验证逻辑
     */
    private function validateTenantData(Request $request, $tenantId = null, $userId = null)
    {
        // 1. 基础字段验证
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $userId,
            'phone' => 'required|numeric',
            'identity_type' => 'required|in:ic,passport',
            'ic_number' => [
                'nullable',
                'numeric',
                'digits:12',
                'required_if:identity_type,ic',
                Rule::unique('tenants', 'ic_number')
                    ->where(function ($query) {
                        return $query->where('created_by', Auth::id()) // 限制在当前 Owner (房东)
                            ->where('status', 'active');     // 只有处于 active 状态的才算冲突
                    })
                    ->ignore($tenantId), // 更新时排除掉当前租客 ID
            ],
            'passport' => 'nullable|string|max:30|regex:/^[A-Z0-9]+$/|required_if:identity_type,passport|unique:tenants,passport,' . $tenantId,
            'nationality' => 'required|string|max:100',
            'gender' => 'required|string|in:Male,Female,MALE,FEMALE',
            'occupation' => 'nullable|string|max:100',
            'ic_photo_path' => 'nullable|image|max:2048|mimes:jpeg,png,jpg,gif',
            'emergency_contacts' => 'required|array|min:1',
            'emergency_contacts.*.name' => 'required|string|max:255',
            'emergency_contacts.*.phone' => 'required|numeric',
        ];

        $request->validate($rules);

        // 2. 紧急联系人电话重复性检查
        $tenantPhone = trim($request->phone);
        foreach ($request->emergency_contacts as $index => $contact) {
            if (($contact['delete'] ?? '0') === '1')
                continue;

            $emergencyPhone = trim($contact['phone'] ?? '');
            if ($emergencyPhone !== '' && $emergencyPhone === $tenantPhone) {
                // 抛出异常，让 Laravel 自动处理错误返回
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "emergency_contacts.{$index}.phone" => 'Emergency contact phone number cannot be the same as the tenant\'s phone number.'
                ]);
            }
        }
    }
}
