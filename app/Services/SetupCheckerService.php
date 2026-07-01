<?php
namespace App\Services;

use App\Models\{Property, Tenants, DocumentTemplate, Owners, User, Asset};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class SetupCheckerService
{
    public function check(array $requirements, $type = 'exists')
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
            $builder = match ($requirement) {
                'property' => $this->checkProperty($user, $userId, $ownerIds),
                'tenant'   => $this->checkTenant($user, $userId),
                'template' => $this->checkAgreement($user, $userId, $ownerIds),
                'owner'    => $this->checkOwner($user, $userId),
                'asset'    => $this->checkAsset($user, $userId, $ownerIds),
                default    => false,
            };

            $results[$requirement] = ($builder === true) 
                ? true 
                : ($builder instanceof \Illuminate\Database\Eloquent\Builder ? $builder->exists() : false);
        }

        return $results;
    }

    private function checkProperty(User $user, string $userId, Collection $ownerIds)
    {
        if ($user->hasRole('admin')) return true;
        
        return Property::where(function($q) use ($userId, $ownerIds, $user) {
            $q->where(function($sub) use ($userId, $ownerIds, $user) {
                $sub->where('created_by', $userId);
                
                if ($user->hasRole('agentAdmin') && $ownerIds->isNotEmpty()) {
                    $sub->orWhereIn('owner_id', $ownerIds);
                }
            });

            $q->whereNot('status', 'Removed');
        });
    }

    private function checkTenant(User $user, string $userId)
    {
        if ($user->hasRole('admin')) return true;

        return Tenants::where('created_by', $userId)
            ->where('status', 'active');
    }

    private function checkAgreement(User $user, string $userId, Collection $ownerIds)
    {
        if ($user->hasRole('admin')) return true;

        return DocumentTemplate::where(function($q) use ($userId, $ownerIds, $user) {
            $q->where('user_id', $userId);
            if ($user->hasRole('agentAdmin')) {
                $q->orWhereIn('user_id', $ownerIds);
            }
        });
    }

    private function checkOwner(User $user, string $userId)
    {
        if ($user->hasRole('admin')) return true;

        if ($user->hasRole('agentAdmin')){
            return Owners::where('agent_id', $userId);
        }
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
        });
    }
}