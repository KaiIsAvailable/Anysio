<?php

namespace App\Services;

use App\Contracts\BulkSaveHandler;
use App\Models\Owners;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PropertyBulkService implements BulkSaveHandler
{
    public const ENTITIES = ['property', 'unit', 'room'];

    public function revision(Model $record): string
    {
        return hash('sha256', json_encode([$record->getAttributes(), UtilitySetupService::canManageWaterMeters()
            ? $record->utilityServices->where('type', 'water')->map->getAttributes()->values()->all() : []], JSON_THROW_ON_ERROR));
    }

    public function permissions(): array
    {
        $super = Gate::allows('super-admin');
        $permissions = [];
        foreach (self::ENTITIES as $entity) {
            $view = $entity === 'property' ? 'property.tab' : ($entity === 'unit' ? 'property.show unit' : 'unit.show room');
            $permissions[$entity] = ['view' => $super || Gate::allows($view)];
            foreach (['create', 'edit', 'delete'] as $action) {
                $permissions[$entity][$action] = $permissions[$entity]['view'] && ($super || Gate::allows($entity.'.'.$action));
            }
        }

        return $permissions;
    }

    public function selectableOwners(User $manager): Builder
    {
        $query = User::whereIn('role', ['owner', 'ownerAdmin']);
        if (Gate::allows('super-admin')) {
            return $query;
        }

        return $manager->role === 'agentAdmin'
            ? $query->whereIn('id', Owners::where('agent_id', $manager->id)->select('user_id'))
            : $query->whereKey($manager->id);
    }

    public function managedRecords(User $manager, string $entity): Builder
    {
        $active = fn ($query, $table) => $query->whereRaw("LOWER(TRIM({$table}.status)) NOT IN ('removed', 'inactive')");
        $properties = fn ($query) => $active($query->forManagement($manager), 'properties');

        $query = match ($entity) {
            'property' => $properties(Property::query()),
            'unit' => $active(Unit::whereHas('property', $properties), 'units'),
            'room' => $active(Room::whereHas('unit', fn ($q) => $active($q->whereHas('property', $properties), 'units')), 'rooms'),
            default => throw new AuthorizationException,
        };
        return UtilitySetupService::canManageWaterMeters() ? $query->with(['utilityServices' => fn ($q) => $q->where('type', 'water')->where('management_user_id', $manager->id)]) : $query;
    }

    public function authorizeRows(array $rows, User $manager): array
    {
        if (Gate::denies('super-admin') && (Gate::denies('property.tab') || Gate::denies('property.bulk edit'))) {
            throw new AuthorizationException;
        }
        $permissions = $this->permissions();
        foreach ($rows as $row) {
            $entity = $row['entity'] ?? 'property';
            $action = ['create' => 'create', 'update' => 'edit', 'remove' => 'delete'][$row['action']] ?? '';
            if (array_intersect(UtilitySetupService::WATER_FIELDS, array_keys($row)) && !UtilitySetupService::canManageWaterMeters()) {
                throw new AuthorizationException;
            }
            if (! ($permissions[$entity][$action] ?? false)) {
                throw new AuthorizationException;
            }
        }

        return [Gate::allows('super-admin')];
    }

    public function assertManagedRows(array $rows, User $manager): void
    {
        foreach (self::ENTITIES as $entity) {
            $ids = collect($rows)->filter(fn ($row) => ($row['entity'] ?? 'property') === $entity && $row['action'] !== 'create')->pluck('id');
            if ($ids->isNotEmpty() && $this->managedRecords($manager, $entity)->whereIn('id', $ids)->count() !== $ids->count()) {
                throw new AuthorizationException;
            }
        }
    }

    public function row(Model $record, string $entity): array
    {
        $row = ['key' => $record->id, 'entity' => $entity, 'version' => $this->revision($record)];
        $map = $this->fields($entity);
        foreach ($map as $input => $column) {
            $row[$input] = $record->getAttribute($column) ?? '';
        }
        if ($entity !== 'property') {
            $row['parentKey'] = $record->getAttribute($entity === 'unit' ? 'property_id' : 'unit_id');
        }

        if (UtilitySetupService::canManageWaterMeters()) {
            $meter = app(UtilitySetupService::class)->waterMeterData($record);
            $row += array_combine(UtilitySetupService::WATER_FIELDS, array_values($meter));
            $service = $record->utilityServices->firstWhere('type', 'water');
            $row['waterSetupLocked'] = $service && $service->billing_method !== 'metered';
        }
        return $row;
    }

    public function fields(string $entity): array
    {
        return match ($entity) {
            'property' => ['name' => 'name', 'ownerId' => 'owner_id', 'propertyType' => 'type', 'address' => 'address', 'postcode' => 'postcode', 'city' => 'city', 'state' => 'state'],
            'unit' => ['unitNo' => 'unit_no', 'ownerId' => 'owner_id', 'unitType' => 'type', 'block' => 'block', 'floor' => 'floor', 'sqft' => 'sqft', 'electricityNo' => 'electricity_acc_no', 'waterNo' => 'water_acc_no', 'indahWaterNo' => 'indah_water_acc_no'],
            'room' => ['roomNo' => 'room_no', 'roomType' => 'room_type'],
            default => throw new AuthorizationException,
        };
    }

    private function existingAndParents(array $rows, User $manager, bool $lock): array
    {
        $groups = collect($rows)->groupBy(fn ($row) => $row['entity'] ?? 'property');
        $ids = fn ($entity) => ($groups[$entity] ?? collect())->where('action', '!=', 'create')->pluck('id');
        $parents = fn ($entity) => ($groups[$entity] ?? collect())->pluck('parentKey')->filter(fn ($key) => is_string($key) && Str::isUlid($key));
        $roomUnits = Room::whereIn('id', $ids('room'))->pluck('unit_id');
        $unitIds = $ids('unit')->merge($parents('room'))->merge($roomUnits)->unique();
        $propertyIds = $ids('property')->merge($parents('unit'))->merge(Unit::whereIn('id', $unitIds)->pluck('property_id'))->unique();
        $records = [];
        foreach (['property' => $propertyIds, 'unit' => $unitIds, 'room' => $ids('room')] as $entity => $keys) {
            $records[$entity] = $this->managedRecords($manager, $entity)->whereIn('id', $keys)->orderBy('id')
                ->when($lock, fn ($q) => $q->lockForUpdate())->get()->keyBy('id');
            if ($ids($entity)->contains(fn ($id) => ! $records[$entity]->has($id))) {
                throw new AuthorizationException;
            }
        }

        return $records;
    }

    public function validateRows(array $rows, User $manager, bool $lock = false): array
    {
        $this->authorizeRows($rows, $manager);
        $this->assertManagedRows($rows, $manager);
        if ($lock) {
            User::whereKey($manager->id)->lockForUpdate()->firstOrFail();
        }
        $records = $this->existingAndParents($rows, $manager, $lock);
        $groups = collect($rows)->groupBy(fn ($row) => $row['entity'] ?? 'property');
        $references = [];
        foreach ($rows as $index => $row) {
            $entity = $row['entity'] ?? 'property';
            $key = $row['action'] === 'create' ? ($row['clientKey'] ?? 'legacy-'.$index) : $row['id'];
            if (isset($references[$entity][$key])) {
                $this->fail($index, $row, 'clientKey', 'This record appears more than once in the batch.');
            }
            if ($row['action'] === 'create' && isset($row['entity']) && (! isset($row['clientKey']) || ! str_starts_with($row['clientKey'], 'new-'))) {
                $this->fail($index, $row, 'clientKey', 'The new record reference is invalid. Reload the editor.');
            }
            $references[$entity][$key] = $row;
        }
        $ownerIds = collect($rows)->pluck('ownerId')->filter()->merge($records['property']->pluck('owner_id'))->unique();
        $owners = $this->selectableOwners($manager)->whereIn('id', $ownerIds)->orderBy('id')
            ->when($lock, fn ($q) => $q->lockForUpdate())->pluck('id');
        $databaseNumbers = [];
        foreach (['unit', 'room'] as $entity) {
            $changes = ($groups[$entity] ?? collect())->where('action', '!=', 'remove');
            $field = $entity === 'unit' ? 'unitNo' : 'roomNo';
            $column = $entity === 'unit' ? 'unit_no' : 'room_no';
            $parentColumn = $entity === 'unit' ? 'property_id' : 'unit_id';
            $model = $entity === 'unit' ? Unit::class : Room::class;
            $parents = $changes->pluck('parentKey')->filter(fn ($key) => is_string($key) && Str::isUlid($key))->unique();
            $values = $changes->pluck($field)->map(fn ($number) => strtolower(trim((string) $number)))->unique();
            $updates = $changes->where('action', 'update')->pluck('id');
            foreach ($model::whereIn($parentColumn, $parents)->whereIn(DB::raw('LOWER(TRIM('.$column.'))'), $values)->whereNotIn('id', $updates)->get([$parentColumn, $column]) as $record) {
                $databaseNumbers[$entity.'|'.$record->{$parentColumn}.'|'.strtolower(trim($record->{$column}))] = true;
            }
        }
        $numbers = [];
        foreach ($rows as $index => $row) {
            $entity = $row['entity'] ?? 'property';
            $record = $row['action'] === 'create' ? null : $records[$entity][$row['id']];
            if ($record && ! hash_equals($this->revision($record), $row['version'])) {
                $this->fail($index, $row, 'version', 'This '.$entity.' changed since the grid loaded. Reload before editing it again.');
            }
            if ($entity !== 'property') {
                $parentEntity = $entity === 'unit' ? 'property' : 'unit';
                $parentKey = $row['parentKey'] ?? $record?->getAttribute($entity === 'unit' ? 'property_id' : 'unit_id');
                $parent = $references[$parentEntity][$parentKey] ?? $records[$parentEntity][$parentKey] ?? null;
                if (! $parent) {
                    $this->fail($index, $row, 'parentKey', 'Choose an available '.$parentEntity.' from your management account.');
                }
                if (is_array($parent) && $parent['action'] === 'remove') {
                    $this->fail($index, $row, 'parentKey', 'The parent is marked for removal. Remove the parent alone to remove its children.');
                }
                if ($entity === 'room') {
                    $propertyKey = is_array($parent) ? $parent['parentKey'] : $parent->property_id;
                    if (($references['property'][$propertyKey]['action'] ?? '') === 'remove') {
                        $this->fail($index, $row, 'parentKey', 'The property is marked for removal. Remove the property alone.');
                    }
                }
                if ($record && $parentKey !== $record->getAttribute($entity === 'unit' ? 'property_id' : 'unit_id')) {
                    $this->fail($index, $row, 'parentKey', 'Existing records must stay under their current parent.');
                }
                if ($entity === 'unit') {
                    $inheritedOwner = is_array($parent) ? ($parent['ownerId'] ?? null) : $parent->owner_id;
                    $ownerId = ($row['ownerId'] ?? null) ?: $inheritedOwner;
                    if ($row['action'] !== 'remove' && ! $ownerId) {
                        $this->fail($index, $row, 'ownerId', 'Choose an owner for this unit because its property has no owner.');
                    }
                }
                if ($row['action'] !== 'remove') {
                    $field = $entity === 'unit' ? 'unitNo' : 'roomNo';
                    $number = strtolower(trim($row[$field]));
                    $numberKey = $entity.'|'.$parentKey.'|'.$number;
                    if (isset($numbers[$numberKey])) {
                        $this->fail($index, $row, $field, 'This number is repeated under the same '.$parentEntity.'.');
                    }
                    $numbers[$numberKey] = true;
                    if (isset($databaseNumbers[$numberKey])) {
                        $this->fail($index, $row, $field, 'This number already exists under the selected '.$parentEntity.'.');
                    }
                }
            } else {
                $ownerId = $row['ownerId'] ?? null;
            }
            if ($entity !== 'room' && $row['action'] !== 'remove' && $ownerId && ! $owners->contains($ownerId)) {
                $this->fail($index, $row, 'ownerId', 'Select an owner available to your management account.');
            }
        }

        return [$records, $references];
    }

    public function save(array $rows, User $manager, array $uploads = [], ?callable $onProgress = null, ?callable $beforeCommit = null): array
    {
        return DB::transaction(function () use ($rows, $manager, $onProgress, $beforeCommit) {
            [$records] = $this->validateRows($rows, $manager, true);
            $counts = ['created' => 0, 'updated' => 0, 'removed' => 0];
            $affectedUnits = [];
            $affectedProperties = [];
            $processed = 0;
            foreach (self::ENTITIES as $entity) {
                foreach ($rows as $index => $row) {
                    if (($row['entity'] ?? 'property') !== $entity) {
                        continue;
                    }
                    $new = $row['action'] === 'create';
                    $key = $new ? ($row['clientKey'] ?? 'legacy-'.$index) : $row['id'];
                    $model = ['property' => Property::class, 'unit' => Unit::class, 'room' => Room::class][$entity];
                    $record = $new ? new $model : $records[$entity][$key];
                    if ($row['action'] === 'remove') {
                        if ($entity === 'property') {
                            $unitIds = Unit::where('property_id', $record->id)->orderBy('id')->lockForUpdate()->pluck('id');
                            Room::whereIn('unit_id', $unitIds)->orderBy('id')->lockForUpdate()->get(['id']);
                        } elseif ($entity === 'unit') {
                            Room::where('unit_id', $record->id)->orderBy('id')->lockForUpdate()->get(['id']);
                        } else {
                            RoomAsset::where('room_id', $record->id)->where('status', 'Active')->update(['status' => 'Inactive']);
                        }
                        $record->update(['status' => $entity === 'room' ? 'Inactive' : 'Removed']);
                        $counts['removed']++;
                    } else {
                        $data = [];
                        foreach ($this->fields($entity) as $input => $column) {
                            $data[$column] = ($row[$input] ?? '') === '' ? null : $row[$input];
                        }
                        if ($entity !== 'property') {
                            $parentEntity = $entity === 'unit' ? 'property' : 'unit';
                            $parent = $records[$parentEntity][$row['parentKey']];
                            $data[$entity === 'unit' ? 'property_id' : 'unit_id'] = $parent->id;
                            if ($entity === 'unit') {
                                $data['owner_id'] = $data['owner_id'] ?: $parent->owner_id;
                            }
                        }
                        if ($new) {
                            $data += ['created_by' => $manager->id, 'status' => 'Vacant'];
                        }
                        $record->fill($data)->saveWithoutStatusSynchronization();
                        if (array_key_exists('waterHasMeter', $row)) {
                            try {
                                app(UtilitySetupService::class)->saveWaterMeter($record, [
                                    'has_meter' => $row['waterHasMeter'], 'meter_number' => $row['waterMeterNumber'] ?? null,
                                    'opening_reading' => $row['waterOpeningReading'] ?? null, 'opening_reading_date' => $row['waterOpeningDate'] ?? null,
                                ]);
                            } catch (ValidationException $exception) {
                                $errors = [];
                                $fields = array_combine(['has_meter', 'meter_number', 'opening_reading', 'opening_reading_date'], UtilitySetupService::WATER_FIELDS);
                                foreach ($exception->errors() as $field => $messages) {
                                    $errors['rows.'.$index.'.'.($fields[str_replace('water_meter.', '', $field)] ?? 'waterHasMeter')] = $messages;
                                }
                                throw ValidationException::withMessages($errors);
                            }
                        }
                        $records[$entity][$key] = $record;
                        $counts[$new ? 'created' : 'updated']++;
                    }
                    if ($entity === 'property') {
                        $affectedProperties[$record->id] = true;
                    }
                    if ($entity === 'unit') {
                        $affectedUnits[$record->id] = true;
                        $affectedProperties[$record->property_id] = true;
                    }
                    if ($entity === 'room') {
                        $affectedUnits[$record->unit_id] = true;
                    }
                    if ($onProgress) {
                        $onProgress(++$processed);
                    }
                }
            }
            $roomCounts = Room::whereIn('unit_id', array_keys($affectedUnits))->whereRaw("LOWER(TRIM(status)) NOT IN ('removed', 'inactive')")
                ->selectRaw('unit_id, COUNT(*) as total')->groupBy('unit_id')->pluck('total', 'unit_id');
            foreach (Unit::whereIn('id', array_keys($affectedUnits))->whereRaw("LOWER(TRIM(status)) NOT IN ('removed', 'inactive')")->get() as $unit) {
                $count = (int) ($roomCounts[$unit->id] ?? 0);
                $unit->fill(['has_rooms' => $count > 0, 'total_rooms' => $count])->saveWithoutStatusSynchronization();
                $unit->syncStatus(false);
                $affectedProperties[$unit->property_id] = true;
            }
            foreach (Property::whereIn('id', array_keys($affectedProperties))->get() as $property) {
                $property->syncStatus(false);
            }
            if ($beforeCommit) {
                $beforeCommit($counts);
            }

            return $counts;
        });
    }

    private function fail(int $index, array $row, string $field, string $message): never
    {
        $entity = ucfirst($row['entity'] ?? 'property');
        throw ValidationException::withMessages(["rows.{$index}.{$field}" => $entity.' row '.($row['rowNumber'] ?? $index + 1).': '.$message]);
    }
}
