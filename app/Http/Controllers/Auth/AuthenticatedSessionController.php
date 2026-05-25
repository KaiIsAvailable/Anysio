<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentsController;
use App\Models\UserPayment;
use App\Models\Payment;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use \Illuminate\Support\Str;
use App\Providers\AppServiceProvider;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $user = Auth::user();

        // 1. 支付状态优先拦截 (仅限 Admin)
        if (in_array($user->role, ['ownerAdmin', 'agentAdmin'])) {
            $mgmt = $user->user_management;
            $package = $mgmt->package;

            // 获取各项判断因子
            $status = $mgmt ? $mgmt->subscription_status : 'no_record';
            $endDate = $mgmt ? $mgmt->end_date : null;
            
            $isNotExpired = $mgmt && $endDate && Carbon::parse($endDate)->isFuture();

            $packageDetails = DB::table('ref_code_packages')
                                ->where('ref_code', $package->ref_code)
                                ->first(); 

            if ($mgmt && !$isNotExpired && $status === 'active') {
                try {
                    // 使用事务包裹所有操作
                    DB::transaction(function () use ($mgmt, $package, $packageDetails) {
                        // 1. 先修改状态
                        $mgmt->update(['subscription_status' => 'pending']);

                        // 2. 计算金额 (之前的逻辑)
                        $amountDue = 0;
                        if ($packageDetails && $mgmt->tot_price > 0) {
                            $amountDue = $mgmt->tot_price;
                        } elseif ($packageDetails && $mgmt->tot_price === 0) {
                            $startDate = Carbon::parse($mgmt->start_date)->format('Y-m-d');
                            $endDate = Carbon::parse($mgmt->end_date)->format('Y-m-d');

                            $totalPaidRent = Payment::where('status', 'paid')
                                ->whereBetween('payment_date', [$startDate, $endDate])
                                ->whereHas('tenant', function ($query) {
                                    $query->where('created_by', Auth::id());
                                })
                                ->sum('amount_paid');

                            $amountDue = (int) ($totalPaidRent * ($packageDetails->commission_rate / 100) / 100);
                            //dd($mgmt->start_date, $mgmt->end_date, $totalPaidRent, $amountDue, $packageDetails->commission_rate / 100);
                        }

                        // 3. 生成账单
                        if ($packageDetails) {
                            $subscriptionType = 'SUBSCRIPTION';
                            $newInvoiceNo = PaymentsController::generateSequenceInvoiceNo($subscriptionType, UserPayment::class);

                            UserPayment::create([
                                'id'           => (string) Str::ulid(),
                                'user_id'      => Auth::id(),
                                'ref_code'     => $package->ref_code,
                                'invoice_no'   => $newInvoiceNo,
                                'payment_type' => strtolower($subscriptionType),
                                'amount_due'   => $amountDue * 100, // 注意：如果这里失败，上面的 update 也会回滚
                                'amount_paid'  => 0,
                                'status'       => 'unpaid',
                            ]);
                        }
                    });
                } catch (\Exception $e) {
                    // 如果上面任何一步失败，事务会自动回滚，这里记录日志
                    Log::error("订阅账单生成失败，回滚操作", [
                        'user_id' => Auth::id(),
                        'error'   => $e->getMessage()
                    ]);
                }
            }
            return redirect()->route('dashboard');
        }

        // 2. 角色分流 (确保每个角色去正确的地方)
        // 使用 switch 可以避免多个 if 逻辑重叠
        Log::info("Redirecting based on role", ['role' => $user->role]);
        switch ($user->role) {
            case 'tenant':
                return redirect()->route('admin.tenants.dashboard');

            case 'owner':
                return redirect()->route('admin.owners.dashboard');

            case 'ownerAdmin':
            case 'agentAdmin':
            case 'admin':
                return redirect()->route('dashboard');

            default:
                // 其他未知角色去首页
                return redirect()->route('welcome');
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login')->with('status', 'You have been logged out.');
    }
}
