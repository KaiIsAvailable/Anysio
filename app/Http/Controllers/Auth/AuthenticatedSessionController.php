<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
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
        $request->session()->put('subscription_notice_login', (string) \Illuminate\Support\Str::uuid());
        $user = get_effective_user();

        // 1. 支付状态优先拦截 (仅限 Admin)
        if (in_array($user->role, ['ownerAdmin', 'agentAdmin'])) {
            app(SubscriptionService::class)->ensureRenewalInvoice($user);

            return redirect()->route(app(\App\Services\LandingPageService::class)->routeFor(Auth::user()));
        }

        // 2. 角色分流 (确保每个角色去正确的地方)
        // 使用 switch 可以避免多个 if 逻辑重叠
        Log::info("Redirecting based on role", ['role' => $user->role]);
        switch ($user->role) {
            case 'tenant':
                return redirect()->route('tenants.dashboard');

            case 'owner':
                return redirect()->route('admin.owners.dashboard');

            case 'ownerAdmin':
            case 'agentAdmin':
            case 'admin':
            case 'staff':
                return redirect()->route(app(\App\Services\LandingPageService::class)->routeFor(Auth::user()));

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
