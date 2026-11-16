<?php
namespace App\Services;

use App\Models\{User, UtilityService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Gate};

class UtilityBillingAccess
{
    public function manager(Request $request, bool $platform = false): ?User
    {
        if (Gate::allows('super-admin')) {
            $id = $request->validate(['management_user_id' => ['nullable', 'ulid']])['management_user_id'] ?? null;
            return $id ? User::whereIn('role', [User::ROLE_OWNER_ADMIN, User::ROLE_AGENT_ADMIN])->findOrFail($id)
                : ($platform ? Auth::user() : null);
        }
        $user = get_effective_user();
        abort_unless($user && in_array($user->role, [User::ROLE_OWNER_ADMIN, User::ROLE_AGENT_ADMIN])
            && !in_array(Auth::user()?->role, [User::ROLE_TENANT, User::ROLE_OWNER]), 403);
        return $user;
    }

    public function meter(User $manager, UtilityService $service): void
    {
        abort_unless($service->management_user_id === $manager->id && $service->location
            && app(UtilitySetupService::class)->managerFor($service->location)?->id === $manager->id, 404);
    }
}
