<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\{Gate, Auth, Log};
use App\Models\{User, lease, Invoice, Staff, UserManagement};
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

            // Auto-register config permissions
            foreach (config('permissions.modules') as $module => $actions) {
                foreach ($actions as $action) {
                    Permission::firstOrCreate(['name' => "{$module}.{$action}"]);
                }
            }
        }

        Gate::before(function ($user, $ability) {
            $structuralGates = ['super-admin', 'owner-admin', 'agent-admin', 'is-staff', 'is-owner', 'is-tenant'];
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

                // Fetch the user's assigned roles under this specific boss/team context for logging
                $userRoles = $user->roles()
                    ->where('model_has_roles.team_id', $effectiveBossId) // 💡 Specify table name to avoid ambiguity
                    ->pluck('name')
                    ->toArray();

                // 📝 Log full role and permission evaluation details to the testing channel
                Log::channel('testing')->info('Gate::before Permission Evaluation', [
                    'user_id' => $user->id,
                    'user_email' => $user->email ?? null,
                    'ability_checked' => $ability,
                    'effective_boss_id' => $effectiveBossId,
                    'user_roles_in_team' => $userRoles,
                    'has_permission_result' => $hasPermission,
                ]);

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
