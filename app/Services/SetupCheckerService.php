<?php
namespace App\Services;

use App\Models\{Property, Tenants, Agreements, Owners, User, Asset};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class SetupCheckerService
{
    public function check(array $requirements)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user) return array_fill_keys($requirements, false);
        $userId = $user->id;

        // 获取 ownerIds (使用 collect 以便后续判断是否为空)
        $ownerIds = ($user->hasRole('agentAdmin')) 
            ? Owners::where('agent_id', $userId)->pluck('user_id') 
            : collect();

        $results = [];
        foreach ($requirements as $requirement) {
            $results[$requirement] = match ($requirement) {
                'property' => $this->checkProperty($user, $userId, $ownerIds),
                'tenant'   => $this->checkTenant($user, $userId),
                'template' => $this->checkAgreement($user, $userId, $ownerIds),
                'owner'    => $this->checkOwner($user, $userId),
                'asset'    => $this->checkAsset($user, $userId, $ownerIds),
                default    => false,
            };
        }

        return $results;
    }

    private function checkProperty(User $user, string $userId, Collection $ownerIds)
    {
        if ($user->hasRole('admin')) return true;
        
        return Property::where(function($q) use ($userId, $ownerIds, $user) {
            $q->where('created_by', $userId);
            
            // 如果是 agentAdmin，允许查看属于其名下 owner 的房产
            if ($user->hasRole('agentAdmin') && $ownerIds->isNotEmpty()) {
                $q->orWhereIn('owner_id', $ownerIds);
            }
        })->exists();
    }

    private function checkTenant(User $user, string $userId)
    {
        if ($user->hasRole('admin')) return true;

        return Tenants::where('created_by', $userId)
            ->where('status', 'active')
            ->exists();
    }

    private function checkAgreement(User $user, string $userId, Collection $ownerIds)
    {
        if ($user->hasRole('admin')) return true;

        return Agreements::where(function($q) use ($userId, $ownerIds, $user) {
            $q->where('user_id', $userId);
            if ($user->hasRole('agentAdmin')) {
                $q->orWhereIn('user_id', $ownerIds);
            }
        })->exists();
    }

    private function checkOwner(User $user, string $userId): bool
    {
        // Admin 和 OwnerAdmin 默认被视为拥有/不需要检查 owner 权限
        if ($user->hasRole('admin') || $user->hasRole('ownerAdmin')) {
            return true;
        }

        // AgentAdmin 需要检查是否有名下关联的 owner
        if ($user->hasRole('agentAdmin')) {
            return Owners::where('agent_id', $userId)->exists();
        }

        return false;
    }

    private function checkAsset(User $user, string $userId, Collection $ownerIds)
    {
        // Only owner-admin level roles manage assets
        if (!$user->hasRole('admin') && !$user->hasRole('agentAdmin') && !$user->hasRole('ownerAdmin')) {
            return true;
        }

        return Asset::where(function($q) use ($userId, $ownerIds, $user) {
            $q->where('user_id', $userId);
            
            if ($user->hasRole('agentAdmin') && $ownerIds->isNotEmpty()) {
                $q->orWhereIn('user_id', $ownerIds);
            }
        })->exists();
    }
}