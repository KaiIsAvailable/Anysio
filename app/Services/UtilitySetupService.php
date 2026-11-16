<?php

namespace App\Services;

use App\Models\{Lease, Owners, Property, Room, Unit, User, Utility, UtilityService};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\{Rule, ValidationException};

class UtilitySetupService
{
    public function managerFor(Model $location): ?User
    {
        // Reuse the recorded ownership resolution, including its ambiguity checks.
        $lease = new Lease;
        $lease->setRelation('leasable', $location);
        return app(LeaseManagementResolver::class)->resolve($lease);
    }

    private function locations(string $model, User $manager): Builder
    {
        $ownerIds = Owners::where('agent_id', $manager->id)->select('user_id');
        $owned = fn ($q) => $q->where('created_by', $manager->id)
            ->orWhere('owner_id', $manager->id)->orWhereIn('owner_id', $ownerIds);
        $query = $model::query();
        return match ($model) {
            Property::class => $query->where($owned),
            Unit::class => $query->with('property')->where(fn ($q) => $q->where($owned)->orWhereHas('property', $owned)),
            Room::class => $query->with('unit.property')->whereHas('unit', fn ($q) => $q->where($owned)->orWhereHas('property', $owned)),
        };
    }

    public function managedLocations(string $model, User $manager)
    {
        return $this->locations($model, $manager)->get()
            ->filter(fn ($location) => $this->managerFor($location)?->id === $manager->id)->values();
    }

    public function initialize(User $manager, string $type = 'water'): array
    {
        Validator::make(['type' => $type], ['type' => ['required', Rule::in(UtilityService::TYPES)]])->validate();
        abort_unless(in_array($manager->role, [User::ROLE_AGENT_ADMIN, User::ROLE_OWNER_ADMIN]), 422, 'Select a management account.');

        return DB::transaction(function () use ($manager, $type) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $result = ['created' => 0, 'existing' => 0, 'review' => 0, 'linked' => 0];
            foreach ([Property::class, Unit::class, Room::class] as $model) {
                $this->locations($model, $manager)->chunkById(100, function ($locations) use ($manager, $type, &$result) {
                    foreach ($locations as $location) {
                        if ($this->managerFor($location)?->id !== $manager->id) {
                            $result['review']++;
                            continue;
                        }
                        $service = UtilityService::withTrashed()->where('location_type', $location->getMorphClass())
                            ->where('location_id', $location->id)->where('type', $type)->first();
                        if ($service && $service->management_user_id !== $manager->id) {
                            $result['review']++;
                            continue;
                        }
                        if ($service?->trashed()) {
                            $result['existing']++;
                            continue;
                        }
                        if (!$service) {
                            $service = UtilityService::create([
                                'management_user_id' => $manager->id,
                                'location_type' => $location->getMorphClass(), 'location_id' => $location->id,
                                'type' => $type, 'billing_method' => $type === 'indah_water' ? 'fixed' : 'metered',
                                'label' => class_basename($location).' '.($location->name ?? $location->unit_no ?? $location->room_no).' '.str_replace('_', ' ', $type),
                                'status' => 'unconfirmed',
                            ]);
                            $result['created']++;
                        } else {
                            $result['existing']++;
                        }
                        // Link legacy rows only when the saved lease resolves to this account.
                        // Do not invent reading dates, rates, invoices or a physical parent connection.
                        $leases = Lease::where('leasable_type', $location->getMorphClass())->where('leasable_id', $location->id)->get();
                        $leaseIds = $leases->filter(function ($lease) use ($location, $manager) {
                            $lease->setRelation('leasable', $location);
                            return app(LeaseManagementResolver::class)->resolve($lease)?->id === $manager->id;
                        })->modelKeys();
                        $aliases = match ($type) {
                            'electricity' => ['electricity', 'electric'],
                            'indah_water' => ['indah_water', 'indah water', 'iwk'],
                            default => [$type],
                        };
                        $result['linked'] += DB::table('utilities')->whereNull('utility_service_id')->whereIn('lease_id', $leaseIds)
                            ->whereIn(DB::raw('LOWER(TRIM(type))'), $aliases)->update(['utility_service_id' => $service->id]);
                    }
                });
            }
            return $result;
        });
    }

    public function update(UtilityService $service, array $input): UtilityService
    {
        return DB::transaction(function () use ($service, $input) {
            User::whereKey($service->management_user_id)->lockForUpdate()->firstOrFail();
            $service = UtilityService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            $data = Validator::make($input, [
                'label' => ['required', 'string', 'max:255'],
                'meter_number' => ['nullable', 'string', 'max:255'],
                'billing_method' => ['required', Rule::in(UtilityService::METHODS)],
                'parent_service_id' => ['nullable', 'ulid'],
                'opening_reading' => ['nullable', 'numeric', 'min:0', 'max:999999999999.999', 'decimal:0,3', 'required_with:opening_reading_date'],
                'opening_reading_date' => ['nullable', 'date_format:Y-m-d'],
                'status' => ['sometimes', Rule::in(UtilityService::STATUSES)],
                'billing_scope' => ['sometimes', Rule::in(['individual', 'shared'])],
            ])->validate();
            // Saving setup confirms the service exists; explicit deactivation remains available.
            $data['status'] = ($data['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
            $data['billing_scope'] ??= $service->billing_scope ?? 'individual';
            if ($data['billing_scope'] !== $service->billing_scope && ($service->records()->whereNotNull('invoice_item_id')->exists()
                || \App\Models\UtilityAllocation::whereHas('reading', fn ($q) => $q->where('utility_service_id', $service->id))->whereNotNull('invoice_item_id')->exists())) {
                throw ValidationException::withMessages(['billing_scope' => 'This meter has issued charges. Use corrections before changing its billing scope.']);
            }
            foreach (['meter_number', 'parent_service_id', 'opening_reading', 'opening_reading_date'] as $field) {
                $data[$field] = ($data[$field] ?? '') === '' ? null : $data[$field];
            }
            if (!$service->location || $this->managerFor($service->location)?->id !== $service->management_user_id) {
                throw ValidationException::withMessages(['location' => 'The location management relationship has changed or needs review.']);
            }
            $parent = null;
            if ($data['parent_service_id']) {
                $parent = UtilityService::forManagement($service->management_user_id)->where('type', $service->type)
                    ->whereKey($data['parent_service_id'])->first();
                if (!$parent || $parent->billing_method !== 'metered' || !$this->isAncestor($parent, $service)) {
                    throw ValidationException::withMessages(['parent_service_id' => 'Choose an upstream service of the same utility type at this location’s unit or property.']);
                }
            }
            if ($data['billing_method'] === 'shared' && (!$parent || ($data['status'] === 'active' && $parent->status !== 'active'))) {
                throw ValidationException::withMessages(['parent_service_id' => 'A shared service needs an upstream service; activate it before activating this shared service.']);
            }
            if ($data['billing_method'] !== 'metered' && ($data['opening_reading'] !== null || $data['opening_reading_date'])) {
                throw ValidationException::withMessages(['opening_reading' => 'Opening readings apply only to metered services.']);
            }
            if (($data['status'] !== 'active' || $data['billing_method'] !== 'metered')
                && $service->children()->where('status', 'active')->where('billing_method', 'shared')->exists()) {
                throw ValidationException::withMessages(['status' => 'Deactivate dependent shared services first.']);
            }
            // Physical history must not be reinterpreted by changing setup afterwards.
            if ($service->records()->exists()) {
                $candidate = clone $service;
                $candidate->fill($data);
                foreach (['billing_method', 'parent_service_id', 'opening_reading', 'opening_reading_date', 'meter_number'] as $field) {
                    if ($candidate->isDirty($field) && ($field === 'billing_method' || $service->getRawOriginal($field) !== null)) {
                        throw ValidationException::withMessages([$field => 'This service already has history. Its reading setup is locked.']);
                    }
                }
            }
            $service->update($data);
            return $service;
        });
    }

    public function deleteWaterMeters(User $manager, array $ids): int
    {
        Validator::make(['ids' => $ids], [
            'ids' => ['required', 'array', 'min:1', 'max:2000'],
            'ids.*' => ['required', 'ulid', 'distinct'],
        ])->validate();
        return DB::transaction(function () use ($manager, $ids) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $services = UtilityService::forManagement($manager->id)->where('type', 'water')
                ->where('billing_method', 'metered')->whereIn('status', ['active', 'unconfirmed'])
                ->whereIn('id', $ids)->lockForUpdate()->get();
            if ($services->count() !== count($ids) || $services->contains(fn ($service) => !$service->location
                || $this->managerFor($service->location)?->id !== $manager->id)) {
                throw ValidationException::withMessages(['selected_services' => 'A selected meter is unavailable or outside this management account. Reload the page.']);
            }
            if (UtilityService::whereIn('parent_service_id', $ids)->whereNotIn('id', $ids)->exists()) {
                throw ValidationException::withMessages(['selected_services' => 'A selected parent meter still has connected services. Select its child meters too, or unlink them first.']);
            }
            foreach ($services as $service) $service->delete();
            return $services->count();
        });
    }

    public function delete(UtilityService $service): void
    {
        DB::transaction(function () use ($service) {
            User::whereKey($service->management_user_id)->lockForUpdate()->firstOrFail();
            $service = UtilityService::whereKey($service->id)->lockForUpdate()->firstOrFail();
            if ($service->children()->exists()) {
                throw ValidationException::withMessages(['utility_service' => 'Remove or unlink child services before deleting this parent service.']);
            }
            $service->delete();
        });
    }

    public function isAncestor(UtilityService $parent, UtilityService $child): bool
    {
        if ($parent->id === $child->id || $parent->management_user_id !== $child->management_user_id || $parent->type !== $child->type) return false;
        $location = $child->location;
        $unit = $location instanceof Room ? $location->unit : ($location instanceof Unit ? $location : null);
        $property = $unit?->property;
        return ($location instanceof Room && $unit && $parent->location_type === $unit->getMorphClass() && $parent->location_id === $unit->id)
            || ($property && $parent->location_type === $property->getMorphClass() && $parent->location_id === $property->id);
    }
}
