<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Lease;
use App\Models\Invoice;
use App\Observers\LeaseObserver;
use App\Observers\InvoiceObserver;

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
        Invoice::observe(InvoiceObserver::class);

        Gate::define('super-admin', function (User $user) {
            return $user->getRoleLevel() >= 5;
        });

        Gate::define('agent-admin', function (User $user) {
            return $user->getRoleLevel() >= 4;
        });

        Gate::define('owner-admin', function (User $user) {
            return $user->getRoleLevel() >= 3;
        });

        Gate::define('is-staff', function (User $user) {
            return $user->role === User::ROLE_STAFF;
        });

        Gate::define('is-owner', function (User $user) {
            return $user->getRoleLevel() === 2;
        });
        
        Gate::define('is-tenant', function (User $user) {
            return $user->getRoleLevel() === 1;
        });
    }
}
