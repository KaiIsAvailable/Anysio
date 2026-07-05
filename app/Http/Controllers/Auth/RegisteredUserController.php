<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{User, DocumentTemplate, Owners, Tenants, RefCodePackage};
use App\Services\SubscriptionService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Str;
use Carbon\Carbon;

class RegisteredUserController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {}

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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:owner,tenant,agent'],
            'has_agent' => ['required_if:role,owner', 'in:yes,no'],
            // 增加对 IC 的验证逻辑
            'ic' => [Rule::requiredIf($request->role === 'owner' && $request->has_agent === 'yes')],
            'tenant_ic' => [Rule::requiredIf($request->role === 'tenant')],
            'ref_code' => [
                // 只有 ownerAdmin (owner + no agent) 或 agentAdmin 需要验证
                Rule::requiredIf(($request->role === 'owner' && $request->has_agent === 'no') || $request->role === 'agent'),
                'nullable',
                'string',
                // 检查 ref_code 必须存在于 ref_code_packages 表的 ref_code 字段中
                Rule::exists('ref_code_packages', 'ref_code')->where(function ($query) {
                    $query->where('status', 'active');
                }),
            ],
            'terms' => ['accepted'],
        ]);

        $existingUser = User::where('email', $request->email)->first();
        if ($existingUser && $existingUser->status === 'active') {
            return back()->withErrors(['email' => 'This account is already active.']);
        }

        $latestTos = DocumentTemplate::where('category', 'tos')->where('status', 'active')->latest()->first();
        $latestPrivacy = DocumentTemplate::where('category', 'privacy')->where('status', 'active')->latest()->first();

        return DB::transaction(function () use ($request, $latestTos, $latestPrivacy) {
            $user = null;
            $existingUser = User::where('email', $request->email)->first();
            $package = null;
            if ($request->filled('ref_code')) {
                $package = RefCodePackage::where('ref_code', $request->ref_code)
                    ->where('status', 'active')
                    ->first();
            }

            $complianceData = [
                'is_agree' => true,
                'tos_id' => $latestTos?->id,
                'privacy_id' => $latestPrivacy?->id,
                'agreed_at' => now(),
            ];

            // --- 逻辑 A：有 Agent 的 Owner 激活 ---
            if ($request->role === 'owner' && $request->has_agent === 'yes') {
                $ownerData = Owners::where('ic_number', $request->ic)
                    ->whereNotNull('agent_id') // 确保是有 Agent 管理的
                    ->first();

                if (!$ownerData) {
                    return back()->withErrors(['ic' => 'Your IC does not match our records. Please contact your agent.']);
                }

                // 更新对应的 User 记录
                $user = User::findOrFail($ownerData->user_id);
                $user->update(array_merge([
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ], $complianceData));
            } 
            
            // --- 逻辑 B：Tenant 激活 ---
            elseif ($request->role === 'tenant') {
                $tenantData = Tenants::where('ic_number', $request->tenant_ic)->first();

                if (!$tenantData) {
                    return back()->withErrors(['tenant_ic' => 'Your IC does not match any tenancy records.']);
                }

                $user = User::findOrFail($tenantData->user_id);
                $user->update(array_merge([
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                ], $complianceData));
            } 
            
            // --- 逻辑 C：全新的注册 (ownerAdmin 或 agentAdmin) ---
            else {
                $finalRole = ($request->role === 'owner') ? 'ownerAdmin' : 'agentAdmin';
                
                $user = $existingUser ?: User::create(array_merge([
                    'name'     => $request->name,
                    'email'    => $request->email,
                    'password' => Hash::make($request->password),
                    'role'     => $finalRole,
                ], $complianceData));

                // 2. 如果是旧用户，确保数据被更新（同步新密码、状态等）
                if ($existingUser) {
                    $user->update(array_merge([
                        'name'     => $request->name,
                        'password' => Hash::make($request->password),
                        'role'     => $finalRole,
                        'email_verified_at' => null,
                        'status' => 'active',                
                    ], $complianceData));
                }

                if ($package) {
                    $this->subscriptionService->setupSubscription(
                        $user,
                        $package,
                        $finalRole
                    );
                }
            }

            event(new Registered($user));
            Auth::login($user);

            return redirect(route('login'));
        });
    }
}
