<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 定义角色等级
        $levels = [
            'admin'      => 5,
            'agentAdmin' => 4,
            'ownerAdmin' => 3,
            'owner'      => 2,
            'tenant'     => 1,
        ];

        // 定义权限映射
        Gate::define('super-admin', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) >= 5;
        });

        Gate::define('agent-admin', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) >= 4;
        });

        Gate::define('owner-admin', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) >= 3;
        });

        Gate::define('is-owner', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) >= 2;
        });
        
        Gate::define('is-tenant', function (User $user) use ($levels) {
            return ($levels[$user->role] ?? 0) >= 1;
        });
    }
}
