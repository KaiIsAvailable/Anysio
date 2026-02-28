<?php

namespace App\Http\Controllers;

use App\Models\Tenants;
use App\Models\User;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class TenantsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // 1. 预加载所有页面需要的关联，特别是 leases 和 room
        $query = Tenants::with(['user', 'emergencyContacts', 'leases.room'])
                ->where('owner_id', Auth::id())
                ->where('status', 'active');

        // 2. 搜索逻辑
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%');
            });
        }

        // 3. 排序逻辑
        $sort = $request->get('sort', 'oldest'); // 给个默认值

        // 如果涉及姓名排序，我们需要 Join
        if (in_array($sort, ['name_asc', 'name_desc'])) {
            $query->join('users', 'tenants.user_id', '=', 'users.id')
                ->select('tenants.*'); // 确保不拿 users 的 id
        }

        switch ($sort) {
            case 'name_asc':  $query->orderBy('users.name', 'asc'); break;
            case 'name_desc': $query->orderBy('users.name', 'desc'); break;
            case 'newest':    $query->orderBy('tenants.created_at', 'desc'); break;
            case 'oldest':    
            default:          $query->orderBy('tenants.created_at', 'asc'); break;
        }

        // 4. 分页 (5 条非常少，通常慢是因为查询本身慢)
        $tenants = $query->paginate(10)->withQueryString();

        return view('adminSide.tenants.index', compact('tenants'));
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        $managedOwners = [];

        // 如果是 Agent，获取他名下的所有 Owner
        if (in_array($user->role, ['agent', 'agentAdmin'])) {
            $managedOwners = \App\Models\Owners::where('agent_id', $user->id)
                ->with('user') // 预加载 user 拿到名字
                ->get();
        }

        return view('adminSide.tenants.create', compact('managedOwners'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $currentUser = Auth::user();

        // 1. Pre-processing for Random Email
        if ($request->has('random_email') && $request->random_email == '1') {
            $request->merge(['email' => 'tenant_' . time() . '_' . Str::random(5) . '@anysio.local']);
        }

        // 2. Validation (增加对 owner_id 的校验)
        $error = $this->validateTenantData($request);
        if ($error) {
            return back()->withInput()->withErrors(['emergency_contacts' => $error]);
        }

        // --- 核心改动：确定租客所属的真正的 Owner ID ---
        $finalOwnerId = null;

        if (in_array($currentUser->role, ['agent', 'agentAdmin'])) {
            // 如果是 Agent，必须从 Request 获取选择的 Owner
            $request->validate([
                'owner_id' => 'required|exists:owners,id',
            ]);

            // 安全校验：确保这个 Owner 确实归该 Agent 管辖
            $isLegit = \App\Models\Owners::where('id', $request->owner_id)
                ->where('agent_id', $currentUser->id)
                ->exists();

            if (!$isLegit) {
                return back()->withErrors(['owner_id' => 'You are not authorized to assign tenants to this owner.']);
            }
            $finalOwnerId = $request->owner_id;
        } else {
            // 如果是 Owner 本人录入，获取该 User 对应的 Owner 记录 ID
            $finalOwnerId = $currentUser->owner?->id;

            if (!$finalOwnerId) {
                return back()->withErrors(['error' => 'Owner profile not found.']);
            }
        }

        // 3. Create User (逻辑保持不变)
        $password = Str::random(10); 
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($password),
            'role' => 'tenant',
        ]);

        // 4. 清理并准备数据
        $data = $request->only(['phone', 'ic_number', 'passport', 'nationality', 'gender', 'occupation']);
        
        // 确保身份类型数据干净
        if ($request->identity_type === 'ic') { 
            $data['passport'] = null; 
        } else { 
            $data['ic_number'] = null; 
        }

        $data['user_id'] = $user->id;
        $data['owner_id'] = $finalOwnerId; // 使用上面确定的真正 Owner ID

        // 处理图片上传 (逻辑保持不变)
        if ($request->hasFile('ic_photo_path')) {
            $path = $request->file('ic_photo_path')->store('tenants/ic_path'); 
            $data['ic_photo_path'] = $path;
        }

        // 5. Create Tenant
        $tenant = Tenants::create($data);

        // 6. Handle Emergency Contacts (逻辑保持不变)
        if ($request->has('emergency_contacts') && is_array($request->emergency_contacts)) {
            foreach ($request->emergency_contacts as $contact) {
                if (empty($contact['name']) || empty($contact['phone'])) continue;

                $tenant->emergencyContacts()->create([
                    'name' => $contact['name'],
                    'phone' => $contact['phone'],
                    'relationship' => $contact['relationship'] ?? 'Friend',
                ]);
            }
        }

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant created successfully');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tenants $tenant)
    {
        // No need to fetch users list as the user field is disabled/readonly
        $tenant->load('emergencyContacts'); // Eager load contacts
        return view('adminSide.tenants.edit', compact('tenant'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Tenants $tenant)
    {
        $error = $this->validateTenantData($request, $tenant->id, $tenant->user_id);
        if ($error) {
            return back()->withInput()->withErrors(['emergency_contacts' => $error]);
        }

        // 1. Update User
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
        ];
        // Password update logic removed

        $tenant->user->update($userData);

        // 2. Update Tenant
        $data = $request->only(['phone', 'ic_number', 'passport', 'nationality', 'gender', 'occupation']);
        if ($request->identity_type === 'ic') { $data['passport'] = null; } 
        else { $data['ic_number'] = null; }

        // Clean data based on identity type
        if ($request->identity_type === 'ic') {
            $data['passport'] = null;
        } else {
            $data['ic_number'] = null;
        }

        if ($request->hasFile('ic_photo_path')) {
            // 1. 物理删除旧照片 (不管它是存放在 storage 还是旧的 resources)
            if ($tenant->ic_photo_path) {
                // 先尝试用 Storage 删除 (新标准)
                if (Storage::exists($tenant->ic_photo_path)) {
                    Storage::delete($tenant->ic_photo_path);
                } 
                // 兼容旧路径删除 (如果你之前存的是 resources/views/...)
                else {
                    $oldPath = base_path($tenant->ic_photo_path);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }
            }

            // 2. 存储新照片到 storage/app/tenants/ics
            // store() 会自动处理：创建目录、生成唯一文件名、保持私有
            $path = $request->file('ic_photo_path')->store('tenants/ics');

            // 3. 更新数据库路径 (例如: tenants/ics/abc123xyz.jpg)
            $data['ic_photo_path'] = $path;
        }

        $tenant->update($data);

        // 3. Handle Emergency Contacts
        if ($request->has('emergency_contacts')) {
            // 第一步：直接清空该租客所有的旧联系人
            $tenant->emergencyContacts()->delete();

            // 第二步：重新插入目前表单里剩下的联系人
            foreach ($request->emergency_contacts as $contact) {
                $name = trim($contact['name'] ?? '');
                
                // 只有名字不为空，且没有被标记为删除（如果你还在用标记删除逻辑）才插入
                if ($request->has('emergency_contacts')) {
                    $tenant->emergencyContacts()->delete();
                    foreach ($request->emergency_contacts as $contact) {
                        $name = trim($contact['name'] ?? '');
                        if ($name !== '' && ($contact['delete'] ?? '0') !== '1') {
                            $tenant->emergencyContacts()->create([
                                'name'         => $name,
                                'phone'        => $contact['phone'] ?? '',
                                'relationship' => $contact['relationship'] ?? '',
                            ]);
                        }
                    }
                }
            }
        }

        return redirect()->route('admin.tenants.index')->with('success', 'Tenant and User updated successfully.');
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
            'leases' => function($query) {
                $query->with('room:id,room_no')->orderBy('start_date', 'desc');
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
        $path = resource_path('views/adminSide/tenants/ic_path/' . $filename);

        if (!File::exists($path)) {
            abort(404);
        }

        $file = File::get($path);
        $type = File::mimeType($path);

        $response = Response::make($file, 200);
        $response->header("Content-Type", $type);

        return $response;
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
            'phone' => 'required|string|max:20',
            'identity_type' => 'required|in:ic,passport',
            'ic_number' => 'nullable|required_if:identity_type,ic|unique:tenants,ic_number,' . $tenantId,
            'passport' => 'nullable|required_if:identity_type,passport|unique:tenants,passport,' . $tenantId,
            'nationality' => 'required|string|max:100',
            'gender' => 'required|string|in:Male,Female',
            'occupation' => 'nullable|string|max:100',
            'ic_photo_path' => 'nullable|image|max:2048|mimes:jpeg,png,jpg,gif',
            'emergency_contacts' => 'required|array|min:1',
            'emergency_contacts.0.name' => 'required|string|max:255',
            'emergency_contacts.0.phone' => 'required|string|max:20',
        ];

        $request->validate($rules);

        // 2. 紧急联系人电话重复性检查
        $tenantPhone = trim($request->phone);
        foreach ($request->emergency_contacts as $contact) {
            if (($contact['delete'] ?? '0') === '1') continue;

            $emergencyPhone = trim($contact['phone'] ?? '');
            if ($emergencyPhone !== '' && $emergencyPhone === $tenantPhone) {
                // 返回错误消息字符串
                return 'Emergency contact phone number cannot be the same as the tenant\'s phone number.';
            }
        }

        return null; // 代表验证通过
    }
}
