<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Gate, Auth, Log};
use App\Models\{User, Lease, Invoice, Staff, UserManagement};
use App\Observers\{LeaseObserver, InvoiceObserver};
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
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

        if (Schema::hasTable('permissions')) {
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

            try {
                $effectiveBossId = get_effective_user()?->id;
                if (!$effectiveBossId) {
                    Log::channel('testing')->warning('Gate::before Denied: No effective boss ID', [
                        'user_id' => $user->id,
                        'ability' => $ability,
                    ]);
                    return false;
                }

                // Direct, foolproof multi-tenant permission check:
                $hasPermission = \App\Models\Role::where('team_id', $effectiveBossId)
                    ->whereHas('permissions', function ($q) use ($ability) {
                        $q->where('name', $ability);
                    })
                    ->whereHas('users', function ($q) use ($user, $effectiveBossId) {
                        $q->where('model_id', $user->id)
                        ->where('team_id', $effectiveBossId);
                    })
                    ->exists();

                return $hasPermission;

            } catch (\Throwable $e) {
                Log::channel('testing')->error('Gate::before Exception', [
                    'user_id' => $user->id ?? null,
                    'ability' => $ability,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
        });

        Gate::define('maintenance-tickets', function (User $user) {
            $gate = Gate::forUser($user);
            return $gate->denies('is-tenant')
                && $gate->any(['owner-admin', 'is-owner', 'maintenance.tab']);
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
