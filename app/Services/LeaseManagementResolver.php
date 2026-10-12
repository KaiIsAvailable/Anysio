<?php

namespace App\Services;

use App\Models\{Lease, Owners, Property, Room, Unit, User};

class LeaseManagementResolver
{
    /** Resolve the recorded management relationship; ambiguous records need review. */
    public function resolve(Lease $lease): ?User
    {
        $model = $lease->leasable;
        $asset = $model instanceof Room ? $model->unit : $model;
        if (!$asset instanceof Property && !$asset instanceof Unit) return null;
        $property = $asset instanceof Unit ? $asset->property : $asset;
        $creatorId = $asset->created_by ?: $property?->created_by;
        $ownerId = $asset->owner_id ?: $property?->owner_id;
        $agentId = $ownerId ? Owners::where('user_id', $ownerId)->value('agent_id') : null;
        $candidates = User::whereIn('id', array_filter([$creatorId, $agentId, $ownerId]))->get();
        $managers = $candidates->filter(fn ($u) => in_array($u->role, [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN]));
        return $managers->count() === 1 ? $managers->sole() : null;
    }
}
