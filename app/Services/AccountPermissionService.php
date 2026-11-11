<?php

namespace App\Services;

use App\Models\{Role, Staff, User, UserManagement};

class AccountPermissionService
{
    public static function names(): array
    {
        $names = [];
        foreach (config('permissions.modules', []) as $module => $actions) {
            foreach ($actions as $key => $value) {
                foreach (is_array($value) ? array_merge([$key], $value) : [$value] as $action) {
                    $names[] = "{$module}.{$action}";
                }
            }
        }

        return array_values(array_unique($names));
    }

    public function allows(User $user, string $ability): bool
    {
        if (!in_array($ability, self::names(), true)) {
            return false;
        }

        if ($user->role === User::ROLE_ADMIN) {
            return true;
        }

        if (in_array($user->role, [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN], true)) {
            return in_array($ability, $user->account_permissions ?? [], true);
        }

        if ($user->role !== User::ROLE_STAFF) {
            return false;
        }

        // Resolve from the actor, including Gate::forUser(), rather than the session user.
        $staff = Staff::where('user_id', $user->id)->first();
        $manager = $staff ? UserManagement::find($staff->user_mgnt_id)?->user : null;
        if (!$manager || !in_array($manager->role, [User::ROLE_ADMIN, User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN], true)) {
            return false;
        }

        // Staff access comes from assigned roles in their own workspace, independently
        // of the manager's personal grants. Direct grants cannot bypass those roles.
        return Role::where('team_id', $manager->id)
            ->whereHas('permissions', fn ($query) => $query->where('name', $ability)->where('guard_name', 'web'))
            ->whereHas('users', function ($query) use ($user, $manager) {
                $query->where('model_id', $user->id)->where('team_id', $manager->id);
            })->exists();
    }
}
