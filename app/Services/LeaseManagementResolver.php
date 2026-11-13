<?php

namespace App\Services;

use App\Models\{Lease, Owners, Property, Room, Unit, User};

class LeaseManagementResolver
{
    /** Resolve the recorded management relationship; ambiguous records need review. */
    public function resolve(Lease $lease): ?User
    {
        [$creatorId, $ownerId] = $this->participantIds($lease);
        $agentId = $ownerId ? Owners::where('user_id', $ownerId)->value('agent_id') : null;
        return $this->uniqueManager(User::whereIn('id', array_filter([$creatorId, $agentId, $ownerId]))->get());
    }

    /** Bulk database inputs, using exactly the single-lease resolution rule. */
    public function resolveMany(\Illuminate\Database\Eloquent\Collection $leases): array
    {
        if ($leases->isEmpty()) return [];
        $leases->loadMorph('leasable', [Room::class => ['unit.property'], Unit::class => ['property']]);
        $participants = $leases->mapWithKeys(fn ($lease) => [$lease->id => $this->participantIds($lease)]);
        $owners = Owners::whereIn('user_id', $participants->pluck(1)->filter()->unique())->get()->groupBy('user_id');
        $ids = $participants->flatten()->merge($owners->map(fn ($rows) => $rows->first()->agent_id));
        $users = User::whereIn('id', $ids->filter()->unique())->get()->keyBy('id');
        return $participants->map(function ($pair) use ($owners, $users) {
            [$creatorId, $ownerId] = $pair;
            $agentId = $owners->get($ownerId)?->first()?->agent_id;
            return $this->uniqueManager($users->only(array_filter([$creatorId, $agentId, $ownerId])))?->id;
        })->all();
    }

    private function participantIds(Lease $lease): array
    {
        $model = $lease->leasable;
        $asset = $model instanceof Room ? $model->unit : $model;
        if (!$asset instanceof Property && !$asset instanceof Unit) return [null, null];
        $property = $asset instanceof Unit ? $asset->property : $asset;
        $creatorId = $asset->created_by ?: $property?->created_by;
        $ownerId = $asset->owner_id ?: $property?->owner_id;
        return [$creatorId, $ownerId];
    }

    private function uniqueManager($candidates): ?User
    {
        $managers = $candidates->filter(fn ($u) => in_array($u->role, [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN]));
        return $managers->count() === 1 ? $managers->sole() : null;
    }
}
