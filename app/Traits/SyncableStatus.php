<?php

namespace App\Traits;

trait SyncableStatus
{
    protected bool $syncingStatusFromChildren = false;

    public static function bootSyncableStatus(): void
    {
        static::created(function ($model) {
            $model->syncParentStatus();
        });

        static::updated(function ($model) {
            if (! $model->syncingStatusFromChildren && $model->wasChanged('status')) {
                $model->propagateStatus($model->getAttribute('status'));
                $model->syncParentStatus();
            }
        });
    }

    // Apply explicit status changes downward without firing child save hooks.
    public function propagateStatus($status): void
    {
        if (! method_exists($this, 'childrenItems')) {
            return;
        }

        $relation = $this->childrenItems();
        $relation->update(['status' => $status]);

        foreach ($relation->get() as $child) {
            $child->propagateStatus($status);
        }
    }

    public function syncStatus(): void
    {
        if (method_exists($this, 'childrenItems')) {
            $statuses = $this->childrenItems()->pluck('status')
                ->map(fn ($status) => strtolower(trim((string) $status)))
                ->filter(fn ($status) => in_array($status, ['vacant', 'occupied', 'maintenance'], true));

            // Do not reinterpret administrative statuses such as Removed/Inactive.
            if ($statuses->isNotEmpty()
                && ! in_array(strtolower((string) $this->getAttribute('status')), ['removed', 'inactive'], true)) {
                // Availability: maintenance children cannot be rented either.
                $newStatus = $this->statusFromChildren($statuses);

                if ($this->getAttribute('status') !== $newStatus) {
                    // Keep update/audit events without pushing an aggregate downward.
                    $this->syncingStatusFromChildren = true;
                    try {
                        $this->update(['status' => $newStatus]);
                    } finally {
                        $this->syncingStatusFromChildren = false;
                    }
                }
            }
        }

        // Continue even for a room, or when this model's status has not changed.
        $this->syncParentStatus();
    }

    protected function syncParentStatus(): void
    {
        if (method_exists($this, 'parentItem')) {
            // Avoid a stale parent from an already loaded relationship.
            $parent = $this->parentItem()->first();
            if ($parent) {
                $parent->syncStatus();
            }
        }
    }

    protected function statusFromChildren($statuses): string
    {
        if ($statuses->contains('vacant')) {
            return 'Vacant';
        }

        return $statuses->every(fn ($status) => $status === 'maintenance')
            ? 'Maintenance'
            : 'Occupied';
    }
}
