<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $currentMgntId = optional($request->user()->user_management)->id;

        $staff = Staff::query()
            ->with(['user_management.user'])
            ->when($currentMgntId, fn($q) => $q->where('staff.user_mgnt_id', $currentMgntId))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->get('search');
                $q->where(function ($sub) use ($s) {
                    $sub->whereHas('user_management.user', function ($uq) use ($s) {
                        $uq->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%");
                    })->orWhere('staff.role', 'like', "%{$s}%");
                });
            })
            ->orderByDesc('staff.created_at')
            ->paginate(5);

        return view('adminSide.userManagement.staff.index', compact('staff'));
    }

    public function create(Request $request)
    {
        abort_unless(optional($request->user()->user_management)->id, 403);

        return view('adminSide.userManagement.staff.create');
    }

    public function store(Request $request)
    {
        $currentMgntId = optional($request->user()->user_management)->id;
        abort_unless($currentMgntId, 403);

        $request->merge([
            'random_email' => $request->has('random_email'),
        ]);

        // 3. 验证规则
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'random_email' => 'boolean',
            'email' => 'required_without:random_email|nullable|email|unique:users,email',
            // 注意：Blade 里 role 是 disabled 的，所以这里我们通过后端逻辑处理
        ], [
            'email.required_without' => 'Please provide an email or check the "Generate Random" option.'
        ]);

        DB::beginTransaction();
        try {
            // 4. 处理 Email 和 随机密码
            $email = $request->random_email 
                ? strtolower(Str::random(8)) . '@system.com' 
                : $request->email;
            
            $plainPassword = Str::random(10); 

            // 5. 创建 User 记录 (用于登录)
            $newUser = User::create([
                'name' => $data['name'],
                'email' => $email,
                'password' => Hash::make($plainPassword),
                'role' => 'staff', // 统一角色为 staff
            ]);

            // 6. 创建 Staff 记录 (业务逻辑关联)
            Staff::create([
                'user_id' => $newUser->id,
                'user_mgnt_id' => $currentMgntId, // 绑定到当前老板
                'role' => 'staff',                // 岗位设为 staff
                'is_active' => 'active',          // 默认激活
            ]);

            DB::commit();

            // 7. 返回并闪存密码 (Session Flash)
            return redirect()->route('admin.staff.index')->with('status', [
                'message' => 'Staff created successfully!',
                'email' => $email
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['error' => 'Failed to create staff: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display staff details.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $currentMgntId = $user->user_management->id ?? null;

        $staff = Staff::with(['user', 'user_management.user'])
            ->where('user_mgnt_id', $currentMgntId)
            ->findOrFail($id);

        return view('adminSide.userManagement.staff.details', compact('staff'));
    }

    /**
     * Show edit form.
     */
    public function edit(string $id)
    {
        $user = Auth::user();
        $currentMgntId = $user->user_management->id ?? null;

        // 确保只能编辑自己的员工
        $staff = Staff::with('user')
            ->where('user_mgnt_id', $currentMgntId)
            ->findOrFail($id);

        return view('adminSide.userManagement.staff.edit', compact('staff'));
    }

    /**
     * Update staff & user data.
     */
    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        $currentMgntId = $user->user_management->id ?? null;
        $staff = Staff::where('user_mgnt_id', $currentMgntId)->findOrFail($id);
        $user = $staff->user;

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|string', // 这里的 role 是 staff 表里的岗位
            'is_active' => 'required|in:active,inactive',
        ]);

        DB::transaction(function () use ($request, $user, $staff) {
            // 1. 更新 User 表 (基本信息)
            $userData = [
                'name' => $request->name,
                'email' => $request->email,
            ];

            if ($user->email !== $request->email) {
                // 假设你的字段名是 email_verified_at (Laravel标准) 或 email_verify_by
                $userData['email_verified_at'] = null; 
            }

            $user->update($userData);

            // 2. 更新 Staff 表 (岗位信息和状态)
            $staff->update([
                'role' => $request->role,
                'is_active' => $request->is_active,
            ]);
        });

        return redirect()->route('admin.staff.index')
                         ->with('success', 'Staff updated successfully');
    }

    /**
     * Remove staff and user.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $currentMgntId = $user->user_management->id ?? null;

        try {
            DB::transaction(function () use ($id, $currentMgntId) {
                $staff = Staff::where('user_mgnt_id', $currentMgntId)->findOrFail($id);
                $user = $staff->user;

                // 先删 staff 记录
                $staff->delete();
                // 再删 user 账号
                if ($user) {
                    $user->delete();
                }
            });

            return redirect()->route('admin.staff.index')
                             ->with('success', 'Staff deleted successfully.');

        } catch (\Exception $e) {
            return redirect()->back()
                             ->with('error', 'Error: ' . $e->getMessage());
        }
    }
}
