<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        // 1. 验证字段
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:owner,tenant,agent'],
            'has_agent' => ['required_if:role,owner', 'in:yes,no'],
            // 增加对 IC 的验证逻辑
            'ic' => [Rule::requiredIf($request->role === 'owner' && $request->has_agent === 'yes')],
            'tenant_ic' => [Rule::requiredIf($request->role === 'tenant')],
        ]);

        return DB::transaction(function () use ($request) {
            $user = null;

            // --- 逻辑 A：有 Agent 的 Owner 激活 ---
            if ($request->role === 'owner' && $request->has_agent === 'yes') {
                $ownerData = \App\Models\Owners::where('ic_number', $request->ic)
                    ->whereNotNull('agent_id') // 确保是有 Agent 管理的
                    ->first();

                if (!$ownerData) {
                    return back()->withErrors(['ic' => 'Your IC does not match our records. Please contact your agent.']);
                }

                // 更新对应的 User 记录
                $user = User::findOrFail($ownerData->user_id);
                $user->update([
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);
            } 
            
            // --- 逻辑 B：Tenant 激活 ---
            elseif ($request->role === 'tenant') {
                $tenantData = \App\Models\Tenants::where('ic_number', $request->tenant_ic)->first();

                if (!$tenantData) {
                    return back()->withErrors(['tenant_ic' => 'Your IC does not match any tenancy records.']);
                }

                $user = User::findOrFail($tenantData->user_id);
                $user->update([
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ]);
            } 
            
            // --- 逻辑 C：全新的注册 (ownerAdmin 或 agentAdmin) ---
            else {
                $finalRole = ($request->role === 'owner') ? 'ownerAdmin' : 'agentAdmin';
                
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => $finalRole,
                ]);

                // 同时也要在 UserManagement 创建记录
                \App\Models\UserManagement::create([
                    'user_id' => $user->id,
                    'role' => $finalRole,
                    'subscription_status' => 'pending', // 这些人是要付钱的
                ]);
            }

            event(new Registered($user));
            Auth::login($user);

            // 4. 跳转逻辑
            // 如果是新注册的 Admin，且状态是 pending，拦截
            if (in_array($user->role, ['ownerAdmin', 'agentAdmin'])) {
                return redirect()->route('payment.index'); // 跳转到支付扫码页
            }

            return redirect(route('dashboard'));
        });
    }
}
