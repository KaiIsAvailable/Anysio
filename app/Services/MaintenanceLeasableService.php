<?php

namespace App\Services;

use App\Models\{Lease, Maintenance, Property, Room, Unit, User};
use Illuminate\Database\Eloquent\Model;

class MaintenanceLeasableService
{
    public const TYPES = ['property' => Property::class, 'unit' => Unit::class, 'room' => Room::class];

    public function options(User $manager): array
    {
        $options = [];
        $resolver = app(LeaseManagementResolver::class);
        foreach (self::TYPES as $type => $class) {
            $query = $class::query();
            // Resolve using the same recorded management relationship as leases.
            $query->with(match ($type) { 'room' => ['unit.property'], 'unit' => ['property'], default => [] });
            $query->chunkById(200, function ($assets) use ($manager, $resolver, $type, &$options) {
                $leases = new \Illuminate\Database\Eloquent\Collection($assets->map(fn ($asset) =>
                    (new Lease(['leasable_type' => $asset->getMorphClass(), 'leasable_id' => $asset->id]))
                        ->forceFill(['id' => $asset->id])->setRelation('leasable', $asset))->all());
                $managers = $resolver->resolveMany($leases);
                foreach ($assets as $asset) {
                    if (($managers[$asset->id] ?? null) !== $manager->id || $this->unavailable($asset)) continue;
                    $options[] = ['value' => $asset->id, 'type' => $type, 'label' => match ($type) {
                        'property' => $asset->name,
                        'unit' => $asset->unit_no,
                        'room' => $asset->room_no,
                    }];
                }
            });
        }
        usort($options, fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));
        return $options;
    }

    public function selected(string $type, string $id, User $manager): Model
    {
        $asset = self::TYPES[$type]::whereKey($id)->lockForUpdate()->firstOrFail();
        abort_unless(app(LeaseManagementResolver::class)->resolveLeasable($asset)?->id === $manager->id, 403);
        abort_if($this->unavailable($asset), 409, 'This location is unavailable.');
        return $asset;
    }

    private function unavailable(Model $asset): bool
    {
        if (in_array(strtolower($asset->status), ['removed', 'inactive'], true)) return true;
        $parent = $asset instanceof Room ? $asset->unit : ($asset instanceof Unit ? $asset->property : null);
        return $parent ? $this->unavailable($parent) : false;
    }

    public function location(Model $asset): string
    {
        return match (true) {
            $asset instanceof Room => implode(' / ', array_filter([$asset->unit?->property?->name, $asset->unit?->unit_no, $asset->room_no])),
            $asset instanceof Unit => implode(' / ', array_filter([$asset->property?->name, $asset->unit_no])),
            default => $asset->name,
        };
    }

    /** Called inside the work-order transaction; keep availability and completion atomic. */
    public function sync(Model $target): void
    {
        $propertyId = match (true) {
            $target instanceof Room => $target->unit?->property_id,
            $target instanceof Unit => $target->property_id,
            default => $target->id,
        };
        $root = $propertyId ? Property::whereKey($propertyId)->lockForUpdate()->first() : null;
        if (!$root) return;
        $root->load('units.rooms');
        $assets = collect([$root]);
        foreach ($root->units as $unit) {
            $assets->push($unit);
            foreach ($unit->rooms as $room) $assets->push($room);
        }
        $key = fn ($asset) => $asset->getMorphClass().':'.$asset->id;
        $ids = $assets->pluck('id');
        $open = Maintenance::whereIn('leasable_id', $ids)->whereRaw('LOWER(status) != ?', ['completed'])
            ->get(['leasable_type', 'leasable_id'])->mapWithKeys(fn ($order) => [$order->leasable_type.':'.$order->leasable_id => true]);
        $occupied = Lease::whereIn('leasable_id', $ids)->where('is_current', true)->whereIn('status', ['New', 'Renew'])
            ->get(['leasable_type', 'leasable_id'])->mapWithKeys(fn ($lease) => [$lease->leasable_type.':'.$lease->leasable_id => true]);
        $visit = function ($asset, bool $inheritedWork = false, bool $inheritedOccupancy = false, bool $affected = false) use (&$visit, $target, $key, $open, $occupied) {
            if (in_array(strtolower($asset->status), ['removed', 'inactive'], true)) return $asset->status;
            $affected = $affected || $key($asset) === $key($target);
            $work = $inheritedWork || $open->has($key($asset));
            $leased = $inheritedOccupancy || $occupied->has($key($asset));
            $children = $asset instanceof Property ? $asset->units : ($asset instanceof Unit ? $asset->rooms : collect());
            $statuses = $children->map(fn ($child) => strtolower($visit($child, $work, $leased, $affected)));
            // Unaffected siblings retain their recorded availability.
            $ancestor = ($target instanceof Room && $asset instanceof Unit && $target->unit_id === $asset->id)
                || ($asset instanceof Property);
            if (!$affected && !$ancestor) return $asset->status;
            $status = match (true) {
                $leased => 'Occupied',
                $work => 'Maintenance',
                $statuses->isEmpty(), $statuses->contains('vacant') => 'Vacant',
                $statuses->every(fn ($status) => $status === 'maintenance') => 'Maintenance',
                default => 'Occupied',
            };
            if ($asset->status !== $status) {
                $asset->status = $status;
                // Availability derived here must not overwrite occupied children.
                $asset->saveWithoutStatusSynchronization();
            }
            return $status;
        };
        $visit($root);
    }
}
