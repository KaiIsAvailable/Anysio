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

            // 获取各项判断因子
            $status = $mgmt ? $mgmt->subscription_status : 'no_record';
            $endDate = $mgmt ? $mgmt->end_date : null;
            
            $isActive = $mgmt && ($status === 'active');
            $isNotExpired = $mgmt && $endDate && Carbon::parse($endDate)->isFuture();

            if ($mgmt && !$isNotExpired && $status === 'active') {
                $mgmt->update(['subscription_status' => 'pending']);
                $status = 'pending';
                $isActive = false;  
            }

            if (!$isActive || !$isNotExpired) {
                return redirect()->route('dashboard');
            }
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
                // 已经过上面 if 检查，说明是 active，去总后台
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
