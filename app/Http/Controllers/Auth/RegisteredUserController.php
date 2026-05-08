<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentsController;
use App\Models\User;
use App\Models\UserPayment;
use App\Models\Agreements;
use App\Models\Owners;
use App\Models\Tenants;
use App\Models\UserManagement;
use App\Models\RefCodePackage;
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

        $latestTos = Agreements::where('type', 'tos')->where('status', 'active')->latest()->first();
        $latestPrivacy = Agreements::where('type', 'privacy')->where('status', 'active')->latest()->first();

        return DB::transaction(function () use ($request, $latestTos, $latestPrivacy) {
            $user = null;
            $packageId = null;
            if ($request->filled('ref_code')) {
                $package = RefCodePackage::where('ref_code', $request->ref_code)
                            ->where('status', 'active')
                            ->first();
                $packageId = $package ? $package->id : null;
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

                $packageDetails = DB::table('ref_code_packages')
                        ->where('ref_code', $request->ref_code)
                        ->first();
                
                $user = User::create(array_merge([
                    'name' => toupper($request->name),
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => $finalRole,
                ], $complianceData));

                $startDate = now(); 

                $endDate = ($packageDetails->price_mode === 'monthly') 
                    ? $startDate->copy()->addMonth() 
                    : $startDate->copy()->addYear();

                // 同时也要在 UserManagement 创建记录
                UserManagement::create([
                    'user_id' => $user->id,
                    'package_id' => $packageId,
                    'role' => $finalRole,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'subscription_status' => 'pending', // 这些人是要付钱的
                ]);
            }

            // 3. 生成订阅账单 (套用你的 Payment 逻辑)
            $subscriptionType = 'SUBSCRIPTION'; // 对应你的 payment_type
            $newInvoiceNo = PaymentsController::generateSequenceInvoiceNo($subscriptionType);

            UserPayment::create([
                'id'           => (string) Str::ulid(),
                'user_id'      => $user->id, // 确保你的 payment 表有 user_id 字段
                'ref_code'     => $request->ref_code,
                'invoice_no'   => $newInvoiceNo,
                'payment_type' => strtolower($subscriptionType),
                'amount_due'   => $packageDetails->price,
                'amount_paid'  => 0,
                'status'       => 'unpaid',
            ]);

            event(new Registered($user));
            Auth::login($user);

            return redirect(route('login'));
        });
    }
}
