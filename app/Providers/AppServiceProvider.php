<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\{User, Lease, Invoice};
use App\Observers\{LeaseObserver, InvoiceObserver};
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Schema;

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

        // Invoice previews must also remain read-only during application bootstrap.
        $recurringPreview = $this->app->runningInConsole()
            && in_array('invoices:generate-recurring', $_SERVER['argv'] ?? [], true)
            && in_array('--dry-run', $_SERVER['argv'] ?? [], true);
        if (!$recurringPreview && Schema::hasTable('permissions')) {
            // Optional: Delete old hyphenated permissions automatically on boot
            Permission::where('name', 'like', '%-%')->delete();

            // Auto-register config permissions (supporting nested structures)
            foreach (config('permissions.modules') as $module => $actions) {
                foreach ($actions as $key => $value) {
                    if (is_array($value)) {
                        // $key is 'record payment', and $value is the sub-permissions array
                        // 1. Register the parent action itself (e.g., 'invoice.record payment')
                        Permission::firstOrCreate(['name' => "{$module}.{$key}"]);

                        // 2. Register each nested sub-action (e.g., 'invoice.edit penalty', 'invoice.remove penalty')
                        foreach ($value as $subAction) {
                            Permission::firstOrCreate(['name' => "{$module}.{$subAction}"]);
                        }
                    } else {
                        // Standard string action (e.g., 'invoice.tab', 'invoice.void')
                        Permission::firstOrCreate(['name' => "{$module}.{$value}"]);
                    }
                }
            }
        }

        Gate::before(function ($user, $ability) {
            $structuralGates = ['super-admin', 'owner-admin', 'agent-admin', 'is-staff', 'is-owner', 'is-tenant', 'maintenance-tickets'];
            if (in_array($ability, $structuralGates)) {
                return null; 
            }

            return app(\App\Services\AccountPermissionService::class)->allows($user, $ability);
        });

        Gate::define('maintenance-tickets', function (User $user) {
            $gate = Gate::forUser($user);
            return $gate->denies('is-tenant')
                && $gate->any(['is-owner', 'maintenance.tab']);
        });

        Gate::define('super-admin', function (User $user) {
            return $user->getRoleLevel() >= 5;
        });

        Gate::define('owner-admin', function (User $user) {
            return $user->role !== User::ROLE_STAFF && $user->getRoleLevel() >= 3;
        });

        Gate::define('agent-admin', function (User $user) {
            return $user->role !== User::ROLE_STAFF && $user->getRoleLevel() >= 4;
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
