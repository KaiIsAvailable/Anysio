<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\SubscriptionService;

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
        $user = get_effective_user();

        // 1. 支付状态优先拦截 (仅限 Admin)
        if (in_array($user->role, ['ownerAdmin', 'agentAdmin'])) {
            $mgmt = $user->user_management;
            
            if ($mgmt && $mgmt->end_date) {
                $isExpired = Carbon::parse($mgmt->end_date)->startOfDay()->isPast();

                // Log the check details to the 'testing' channel
                Log::channel('testing')->info('Subscription Expiry Check on Login', [
                    'user_id'     => $user->id,
                    'end_date'    => $mgmt->end_date,
                    'status'      => $mgmt->subscription_status,
                    'is_expired'  => $isExpired,
                ]);

                if ($isExpired && $mgmt->subscription_status === 'active') {
                    try {
                        DB::transaction(function () use ($user, $mgmt) {
                            $mgmt->update(['subscription_status' => 'pending']);

                            $subscriptionService = new SubscriptionService();
                            $invoice = $subscriptionService->generateInvoice($user, $mgmt->package);

                            Log::channel('testing')->info('Renewal Invoice Generated Successfully', [
                                'user_id'    => $user->id,
                                'invoice_no' => $invoice->invoice_no,
                                'amount'     => $invoice->total_amount,
                            ]);
                        });
                    } catch (\Exception $e) {
                        Log::channel('testing')->error('Subscription Renewal Failed', [
                            'user_id' => $user->id,
                            'error'   => $e->getMessage(),
                            'trace'   => $e->getTraceAsString(),
                        ]);
                    }
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
            case 'staff':
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
