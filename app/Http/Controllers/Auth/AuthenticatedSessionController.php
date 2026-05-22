<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentsController;
use App\Models\UserPayment;
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

        Log::info("User Login Attempt", [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role
        ]);

        // 1. 支付状态优先拦截 (仅限 Admin)
        if (in_array($user->role, ['ownerAdmin', 'agentAdmin'])) {
            $mgmt = $user->user_management;
            $package = $mgmt->package;

            // 获取各项判断因子
            $status = $mgmt ? $mgmt->subscription_status : 'no_record';
            $endDate = $mgmt ? $mgmt->end_date : null;
            
            $isNotExpired = $mgmt && $endDate && Carbon::parse($endDate)->isFuture();

            if ($mgmt && !$isNotExpired && $status === 'active') {
                $mgmt->update(['subscription_status' => 'pending']);
                $status = 'pending';

                $packageDetails = DB::table('ref_code_packages')
                    ->where('ref_code', $package->ref_code)
                    ->first(); 

                // 3. 生成订阅账单 (套用你的 Payment 逻辑)
                if (isset($packageDetails) && $packageDetails->price > 0 || $packageDetails->commission_rate > 0){        
                    $subscriptionType = 'SUBSCRIPTION';
                    $newInvoiceNo = PaymentsController::generateSequenceInvoiceNo($subscriptionType, UserPayment::class);

                    UserPayment::create([
                        'id'           => (string) Str::ulid(),
                        'user_id'      => Auth::id(),
                        'ref_code'     => $package->ref_code,
                        'invoice_no'   => $newInvoiceNo,
                        'payment_type' => strtolower($subscriptionType),
                        'amount_due'   => $packageDetails->price,
                        'amount_paid'  => 0,
                        'status'       => 'unpaid',
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
