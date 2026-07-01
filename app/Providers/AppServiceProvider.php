<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Lease;
use App\Models\Invoice;
use App\Observers\LeaseObserver;
use App\Observers\PaymentObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (file_exists(app_path('Helpers/functions.php'))) {
            require_once app_path('Helpers/functions.php');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Lease::observe(LeaseObserver::class);
        //Invoice::observe(InvoiceObserver::class);

        // 定义角色等级
        $levels = [
            User::ROLE_ADMIN       => 5,
            User::ROLE_AGENT_ADMIN => 4,
            User::ROLE_OWNER_ADMIN => 3,
            User::ROLE_OWNER       => 2,
            User::ROLE_TENANT      => 1,
        ];

        // 定义权限映射
        Gate::define('super-admin', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) >= $levels[User::ROLE_ADMIN];
        });

        Gate::define('agent-admin', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) >= $levels[User::ROLE_AGENT_ADMIN];
        });

        Gate::define('owner-admin', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) >= $levels[User::ROLE_OWNER_ADMIN];
        });

        Gate::define('is-owner', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) === $levels[User::ROLE_OWNER];
        });
        
        Gate::define('is-tenant', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) === $levels[User::ROLE_TENANT];
        });
    }
}
