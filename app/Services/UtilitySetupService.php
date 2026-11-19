<?php

namespace App\Services;

use App\Models\{Lease, Owners, Property, Room, Unit, User, UtilityService};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\{Auth, DB, Gate, Validator};
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

    public function managedLocations(string $model, User $manager, bool $onlyActive = false)
    {
        return $this->locations($model, $manager)->when($onlyActive, fn ($q) => $q->forActiveHierarchy())->get()
            ->filter(fn ($location) => $this->managerFor($location)?->id === $manager->id)->values();
    }

    public const WATER_FIELDS = ['waterHasMeter', 'waterMeterNumber', 'waterOpeningReading', 'waterOpeningDate'];

    public static function canManageWaterMeters(): bool
    {
        return Gate::allows('super-admin') || (Gate::allows('utilities.manage meters')
            && !in_array(Auth::user()?->role, [User::ROLE_TENANT, User::ROLE_OWNER], true));
    }

    public function waterMeterData(Model $location): array
    {
        $service = $location->utilityServices->first(fn ($meter) => $meter->type === 'water'
            && (Gate::allows('super-admin') || $meter->management_user_id === get_effective_user()?->id));
        return ['has_meter' => $service && $service->billing_method === 'metered' ? '1' : '0',
            'meter_number' => $service?->meter_number,
            'opening_reading' => $service?->opening_reading,
            'opening_reading_date' => $service?->opening_reading_date?->toDateString()];
    }

    public function validateWaterMeter(array $input, ?Model $location = null): array
    {
        if (!self::canManageWaterMeters()) {
            throw ValidationException::withMessages(['water_meter' => 'You have no permission.']);
        }
        if ($location && ((!empty($input['has_meter'])) || $location->utilityServices()->where('type', 'water')->exists())) $this->waterManager($location);
        return Validator::make(['water_meter' => $input], [
            'water_meter.has_meter' => ['required', 'boolean'],
            'water_meter.meter_number' => ['exclude_unless:water_meter.has_meter,1', 'nullable', 'string', 'max:255'],
            'water_meter.opening_reading' => ['exclude_unless:water_meter.has_meter,1', 'nullable', 'numeric', 'min:0', 'max:999999999999.999', 'decimal:0,3', 'required_with:water_meter.opening_reading_date'],
            'water_meter.opening_reading_date' => ['exclude_unless:water_meter.has_meter,1', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today', 'required_with:water_meter.opening_reading'],
        ])->validate()['water_meter'];
    }

    private function waterManager(Model $location): User
    {
        $manager = $this->managerFor($location);
        if (!$manager || (!Gate::allows('super-admin') && $manager->id !== get_effective_user()?->id)) {
            throw ValidationException::withMessages(['water_meter' => 'This location is outside your management account or its ownership needs review.']);
        }
        return $manager;
    }

    public function saveWaterMeter(Model $location, array $input): void
    {
        $data = $this->validateWaterMeter($input, $location);
        if (!$data['has_meter'] && !$location->utilityServices()->where('type', 'water')->exists()) return;
        $manager = $this->waterManager($location);
        DB::transaction(function () use ($location, $data, $manager) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            $service = $location->utilityServices()->withTrashed()->where('type', 'water')->lockForUpdate()->first();
            if ($service && $service->management_user_id !== $manager->id) {
                throw ValidationException::withMessages(['water_meter' => 'The existing meter belongs to another management account.']);
            }
            if (!$data['has_meter']) {
                if ($service && !$service->trashed()) {
                    if ($service->records()->exists()) {
                        throw ValidationException::withMessages(['water_meter.has_meter' => 'This meter has reading history. Manage or deactivate it in Utilities instead of removing its setup.']);
                    }
                    $this->delete($service);
                }
                return;
            }
            if ($service?->trashed()) {
                if ($service->records()->exists()) {
                    throw ValidationException::withMessages(['water_meter.has_meter' => 'This removed meter has history. Review it in Utilities before reusing its setup.']);
                }
                $service->restore();
            }
            if (!$service) {
                $service = $location->utilityServices()->create([
                    'management_user_id' => $manager->id, 'type' => 'water', 'billing_method' => 'metered',
                    'billing_scope' => 'individual', 'status' => 'active',
                    'label' => class_basename($location).' '.($location->name ?? $location->unit_no ?? $location->room_no).' water',
                ]);
            }
            if ($service->billing_method !== 'metered') {
                throw ValidationException::withMessages(['water_meter' => 'Manage this shared or fixed water service in Utilities.']);
            }
            $this->update($service, [
                'label' => $service->label, 'billing_method' => $service->billing_method,
                'billing_scope' => $service->billing_scope, 'parent_service_id' => $service->parent_service_id,
                'status' => $service->status === 'inactive' ? 'inactive' : 'active',
                'meter_number' => $data['meter_number'] ?? null,
                'opening_reading' => $data['opening_reading'] ?? null,
                'opening_reading_date' => $data['opening_reading_date'] ?? null,
            ]);
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
