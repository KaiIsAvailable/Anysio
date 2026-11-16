<?php

namespace Tests\Feature;

use App\Http\Controllers\PropertyBulkController;
use App\Http\Requests\BulkPropertyRequest;
use App\Jobs\SaveBulkOperation;
use App\Models\BulkOperation;
use App\Models\Owners;
use App\Models\Property;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Services\PropertyBulkOperationService;
use App\Services\PropertyBulkService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PropertyBulkSaveTest extends TestCase
{
    private User $manager;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'users' => ['name', 'email', 'password', 'role', 'email_verified_at', 'account_permissions', 'status'],
            'owners' => ['user_id', 'agent_id'],
            'properties' => ['name', 'owner_id', 'created_by', 'type', 'address', 'postcode', 'city', 'state', 'status'],
            'units' => ['property_id', 'owner_id', 'unit_no', 'type', 'block', 'floor', 'sqft', 'electricity_acc_no', 'water_acc_no', 'indah_water_acc_no', 'has_rooms', 'total_rooms', 'created_by', 'status'],
            'rooms' => ['unit_id', 'room_no', 'room_type', 'created_by', 'status'],
            'asset_room' => ['room_id', 'unit_id', 'asset_id', 'status'],
            'audit_logs' => ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'],
        ] as $name => $fields) {
            Schema::create($name, function (Blueprint $table) use ($fields) {
                $table->string('id')->primary();
                foreach ($fields as $field) {
                    $table->text($field)->nullable();
                }
                $table->timestamps();
            });
        }
        (require database_path('migrations/2026_11_16_000001_create_bulk_operations_table.php'))->up();
        Storage::fake('local');
        $this->manager = User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'role' => 'agentAdmin', 'password' => 'secret', 'status' => 'active', 'email_verified_at' => now()]);
        $this->grant(['property.tab', 'property.create', 'property.edit', 'property.delete']);
        $this->actingAs($this->manager);
        $this->owner = User::create(['name' => 'Owner', 'email' => 'owner@example.test', 'role' => 'owner', 'password' => 'secret', 'status' => 'active']);
        Owners::create(['user_id' => $this->owner->id, 'agent_id' => $this->manager->id]);
    }

    private function grant(array $permissions): void
    {
        $this->manager->forceFill(['account_permissions' => $permissions])->save();
    }

    private function row(array $extra = []): array
    {
        return array_replace([
            'action' => 'create', 'id' => null, 'version' => null, 'rowNumber' => 1,
            'name' => 'Property', 'ownerId' => $this->owner->id, 'propertyType' => 'Condo',
            'address' => '12 Main Street', 'postcode' => '01234', 'city' => 'Ipoh', 'state' => 'Perak',
        ], $extra);
    }

    private function validated(array $rows): array
    {
        $request = BulkPropertyRequest::create('/', 'POST', ['rows_json' => json_encode($rows), 'page' => 1]);
        $request->validateRowDetails = true;
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return $request->validated('rows');
    }

    private function create(array $extra = []): Property
    {
        app(PropertyBulkService::class)->save($this->validated([$this->row($extra)]), $this->manager);

        return Property::latest('id')->firstOrFail();
    }

    private function editRow(Property $property, array $extra = []): array
    {
        return $this->row(array_replace(['action' => 'update', 'id' => $property->id,
            'version' => app(PropertyBulkService::class)->revision($property->fresh())], $extra));
    }

    public function test_grid_uses_shared_editor_and_owner_and_type_dropdowns(): void
    {
        $this->create();
        $view = app(PropertyBulkController::class)->index(Request::create('/'), app(PropertyBulkService::class), app(PropertyBulkOperationService::class));
        $source = str_replace(['<x-app-layout>', '</x-app-layout>'], '', file_get_contents(resource_path('views/adminSide/rooms/property/bulk-edit.blade.php')));
        $html = Blade::render($source, $view->getData());
        foreach (['Bulk Edit Properties', 'propertyBulkEditor', 'row.ownerIdSelection', 'row.propertyTypeSelection', 'Save All Changes', 'property-bulk-saving'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('No owner', $html);
        $this->assertStringContainsString('Apartment', $html);
        $this->assertStringNotContainsString('Emergency Contacts', $html);
    }

    public function test_mixed_batch_is_queued_once_and_removal_reaches_descendants(): void
    {
        Queue::fake();
        $edit = $this->create();
        $remove = $this->create();
        $unitId = (string) Str::ulid();
        $roomId = (string) Str::ulid();
        DB::table('units')->insert(['id' => $unitId, 'property_id' => $remove->id, 'status' => 'Occupied']);
        DB::table('rooms')->insert(['id' => $roomId, 'unit_id' => $unitId, 'status' => 'Maintenance']);
        $key = (string) Str::uuid();
        $rows = [$this->editRow($edit, ['name' => 'Edited', 'ownerId' => '']),
            $this->editRow($remove, ['action' => 'remove', 'rowNumber' => 2]), $this->row(['rowNumber' => 3])];
        $payload = ['rows_json' => json_encode($rows), 'page' => 1, 'request_key' => $key];
        $this->post(route('admin.properties.bulk-save'), $payload)->assertSessionHasNoErrors()->assertRedirect(route('admin.properties.bulk-edit', ['page' => 1, 'operation' => $key]));
        $this->post(route('admin.properties.bulk-save'), $payload)->assertSessionHasNoErrors();
        Queue::assertPushed(SaveBulkOperation::class, fn ($job) => $job->module === 'property');
        Queue::assertPushed(SaveBulkOperation::class, 1);
        $operation = BulkOperation::firstOrFail();
        $this->assertSame('property', $operation->module);
        $this->assertStringNotContainsString('Main Street', DB::table('bulk_operations')->value('payload'));
        // Exercise the shared job allowlist as the actual worker does.
        (new SaveBulkOperation('property', $operation->id))->handle();
        $this->assertSame('succeeded', $operation->fresh()->status, json_encode($operation->fresh()->errors));
        $this->assertSame(['created' => 1, 'updated' => 1, 'removed' => 1], $operation->fresh()->counts);
        $this->assertSame('Edited', $edit->fresh()->name);
        $this->assertNull($edit->fresh()->owner_id);
        $this->assertSame('Removed', $remove->fresh()->status);
        $this->assertSame('Removed', DB::table('units')->where('id', $unitId)->value('status'));
        $this->assertSame('Removed', DB::table('rooms')->where('id', $roomId)->value('status'));
        $this->assertNull($operation->fresh()->payload);
        $this->get(route('admin.properties.bulk-complete', $key))->assertRedirect(route('admin.properties.index'))
            ->assertSessionHas('success', 'Saved: 1 new, 1 edited, 1 marked removed.')->assertSessionHas('property_bulk_saved_draft_key');
        (new SaveBulkOperation('property', $operation->id))->handle();
        $this->assertDatabaseCount('properties', 3);
    }

    public function test_owner_selection_cannot_assign_another_accounts_owner(): void
    {
        Queue::fake();
        $foreign = User::create(['name' => 'Foreign', 'email' => 'foreign@example.test', 'role' => 'owner', 'password' => 'secret']);
        Owners::create(['user_id' => $foreign->id, 'agent_id' => (string) Str::ulid()]);
        foreach ([$foreign->id, $this->manager->id, (string) Str::ulid()] as $ownerId) {
            $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->row(['ownerId' => $ownerId])]), 'page' => 1])
                ->assertSessionHasErrors('rows.0.ownerId');
        }
        $this->assertDatabaseCount('bulk_operations', 0);
        Queue::assertNothingPushed();
    }

    public function test_rows_and_recovered_drafts_are_scoped_and_removed_records_are_excluded(): void
    {
        $rows = [];
        for ($i = 0; $i < 53; $i++) {
            $rows[] = $this->row(['name' => 'Property '.$i, 'rowNumber' => $i + 1]);
        }
        app(PropertyBulkService::class)->save($this->validated($rows), $this->manager);
        $foreign = $this->create();
        $foreign->update(['created_by' => (string) Str::ulid(), 'owner_id' => null]);
        $removed = $this->create();
        $removed->update(['status' => 'Removed']);
        $first = $this->getJson(route('admin.properties.bulk-edit'))->assertOk()->assertJsonCount(50, 'rows');
        $this->getJson(route('admin.properties.bulk-edit', ['after' => $first->json('nextCursor')]))->assertOk()->assertJsonCount(3, 'rows')->assertJsonPath('nextCursor', null);
        $id = $first->json('rows.0.key');
        $this->getJson(route('admin.properties.bulk-edit', ['ids' => [$id, $foreign->id, $removed->id]]))->assertOk()->assertJsonCount(1, 'rows');
        Queue::fake();
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->editRow($foreign)]), 'page' => 1])
            ->assertSessionHas('error', 'You have no permission.');
        Queue::assertNothingPushed();
    }

    public function test_worker_rechecks_permissions_versions_and_owner_management(): void
    {
        Queue::fake();
        $property = $this->create();
        $operations = app(PropertyBulkOperationService::class);
        $operation = $operations->start($this->validated([$this->editRow($property, ['name' => 'Queued']), $this->row()]), $this->manager, [], (string) Str::uuid(), 1, 'draft');
        $property->update(['name' => 'Changed elsewhere']);
        $operations->process($operation->id);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertDatabaseCount('properties', 1);
        $this->assertSame('Changed elsewhere', $property->fresh()->name);

        $operation = $operations->start($this->validated([$this->row()]), $this->manager, [], (string) Str::uuid(), 1, 'draft');
        $this->grant(['property.tab']);
        $operations->process($operation->id);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->grant(['property.tab', 'property.create']);
        $operation = $operations->start($this->validated([$this->row()]), $this->manager, [], (string) Str::uuid(), 1, 'draft');
        Owners::where('user_id', $this->owner->id)->update(['agent_id' => 'other']);
        $operations->process($operation->id);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertDatabaseCount('properties', 1);
    }

    public function test_failures_rollback_property_updates_removals_and_children(): void
    {
        $property = $this->create();
        $unit = (string) Str::ulid();
        $room = (string) Str::ulid();
        DB::table('units')->insert(['id' => $unit, 'property_id' => $property->id, 'status' => 'Occupied']);
        DB::table('rooms')->insert(['id' => $room, 'unit_id' => $unit, 'status' => 'Vacant']);
        try {
            app(PropertyBulkService::class)->save($this->validated([$this->editRow($property, ['action' => 'remove']), $this->row()]), $this->manager, [], null,
                fn () => throw new \RuntimeException('Rollback'));
            $this->fail('Expected transaction failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback', $exception->getMessage());
        }
        $this->assertSame('Vacant', $property->fresh()->status);
        $this->assertSame('Occupied', DB::table('units')->where('id', $unit)->value('status'));
        $this->assertSame('Vacant', DB::table('rooms')->where('id', $room)->value('status'));
        $this->assertDatabaseCount('properties', 1);
    }

    public function test_invalid_fields_and_oversized_batches_never_queue(): void
    {
        Queue::fake();
        foreach (['name' => '', 'propertyType' => 'Unknown', 'postcode' => '1234', 'city' => '', 'state' => '', 'address' => str_repeat('a', 256)] as $field => $value) {
            $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->row([$field => $value])]), 'page' => 1])
                ->assertSessionHasErrors('rows.0.'.$field);
        }
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode(array_fill(0, 301, $this->row())), 'page' => 1])->assertSessionHasErrors('rows');
        Queue::assertNothingPushed();
    }

    public function test_progress_is_actor_and_module_scoped_and_below_one_hundred_until_commit(): void
    {
        Queue::fake();
        $operation = app(PropertyBulkOperationService::class)->start($this->validated([$this->row()]), $this->manager, [], (string) Str::uuid(), 1, 'draft');
        Cache::store('file')->put('property-bulk-progress:'.$operation->id, 1, 60);
        $this->getJson(route('admin.properties.bulk-progress', $operation->request_key))->assertOk()->assertJsonPath('percent', 99);
        $otherKey = (string) Str::uuid();
        BulkOperation::create(['module' => 'owner', 'actor_id' => $this->manager->id, 'manager_id' => $this->manager->id, 'request_key' => $otherKey, 'total' => 1, 'page' => 1, 'draft_key' => 'other']);
        $this->getJson(route('admin.properties.bulk-progress', $otherKey))->assertNotFound();
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'role' => 'agentAdmin', 'password' => 'secret', 'email_verified_at' => now()]);
        $this->actingAs($other)->getJson(route('admin.properties.bulk-progress', $operation->request_key))->assertNotFound();
        Cache::store('file')->forget('property-bulk-progress:'.$operation->id);
    }

    public function test_new_properties_allow_no_owner_and_metadata_changes_preserve_occupancy(): void
    {
        $property = $this->create(['ownerId' => '']);
        $this->assertNull($property->owner_id);
        $this->assertSame($this->manager->id, $property->created_by);
        $property->update(['status' => 'Maintenance']);
        app(PropertyBulkService::class)->save($this->validated([$this->editRow($property)]), $this->manager);
        $this->assertSame('Maintenance', $property->fresh()->status);
        $this->assertSame('01234', $property->fresh()->postcode);
    }

    public function test_create_only_permission_cannot_edit_or_remove_existing_properties(): void
    {
        Queue::fake();
        $property = $this->create();
        $this->grant(['property.tab', 'property.create']);
        foreach (['update', 'remove'] as $action) {
            $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->editRow($property, ['action' => $action])]), 'page' => 1])
                ->assertSessionHas('error', 'You have no permission.');
        }
        $this->grant(['property.tab']);
        $view = app(PropertyBulkController::class)->index(Request::create('/'), app(PropertyBulkService::class), app(PropertyBulkOperationService::class));
        $this->assertSame('errors.403', $view->name());
        Queue::assertNothingPushed();
    }

    private function hierarchyPermissions(): void
    {
        $this->grant(['property.tab', 'property.create', 'property.edit', 'property.delete', 'property.show unit',
            'unit.create', 'unit.edit', 'unit.delete', 'unit.show room', 'room.create', 'room.edit', 'room.delete']);
    }

    private function unitRow(string $parent, array $extra = []): array
    {
        return array_replace(['entity' => 'unit', 'clientKey' => 'new-unit-1', 'action' => 'create', 'rowNumber' => 1,
            'parentKey' => $parent, 'ownerId' => '', 'unitNo' => 'A-01', 'unitType' => 'Studio',
            'block' => 'A', 'floor' => '2', 'sqft' => '1200.50', 'electricityNo' => '012345678901',
            'waterNo' => '0123456789012345', 'indahWaterNo' => '00123456'], $extra);
    }

    private function roomRow(string $parent, array $extra = []): array
    {
        return array_replace(['entity' => 'room', 'clientKey' => 'new-room-1', 'action' => 'create', 'rowNumber' => 1,
            'parentKey' => $parent, 'roomNo' => '101', 'roomType' => 'Single'], $extra);
    }

    public function test_ground_floor_saves_in_bulk_and_numeric_floors_remain_supported(): void
    {
        $this->hierarchyPermissions();
        $property = $this->create();
        app(PropertyBulkService::class)->save($this->validated([$this->unitRow($property->id, ['floor' => 'G'])]), $this->manager);
        $unit = Unit::firstOrFail();
        $this->assertSame('G', $unit->floor);
        $row = app(PropertyBulkService::class)->row($unit, 'unit');
        $row = array_merge($row, ['action' => 'update', 'id' => $unit->id, 'floor' => '-2', 'rowNumber' => 1]);
        app(PropertyBulkService::class)->save($this->validated([$row]), $this->manager);
        $this->assertSame('-2', $unit->fresh()->floor);
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->unitRow($property->id, ['floor' => '1.5'])]), 'page' => 1])
            ->assertSessionHasErrors('rows.0.floor');
    }

    public function test_normal_unit_create_and_update_accept_ground_and_numeric_floors(): void
    {
        $this->hierarchyPermissions();
        $property = $this->create();
        $data = ['property_id' => $property->id, 'owner_id' => $this->owner->id, 'unit_no' => 'G-01',
            'floor' => 'G', 'has_rooms' => 0, 'status' => 'Vacant'];
        $this->post(route('admin.units.store'), $data)->assertSessionHasNoErrors()->assertSessionHas('success');
        $unit = Unit::firstOrFail();
        $this->assertSame('G', $unit->floor);
        $this->put(route('admin.units.update', $unit), array_replace($data, ['floor' => '12']))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('12', $unit->fresh()->floor);
        $this->put(route('admin.units.update', $unit), array_replace($data, ['floor' => 'Ground']))->assertSessionHasErrors('floor');
        $this->assertSame('12', $unit->fresh()->floor);
    }

    public function test_one_save_creates_the_entire_hierarchy_even_when_children_are_submitted_first(): void
    {
        $this->hierarchyPermissions();
        Queue::fake();
        $rows = [$this->roomRow('new-unit-1'), $this->unitRow('new-property-1'),
            $this->row(['entity' => 'property', 'clientKey' => 'new-property-1'])];
        $key = (string) Str::uuid();
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode($rows), 'page' => 1, 'request_key' => $key])
            ->assertSessionHasNoErrors()->assertRedirect();
        $operation = BulkOperation::firstOrFail();
        (new SaveBulkOperation('property', $operation->id))->handle();
        $this->assertSame('succeeded', $operation->fresh()->status, json_encode($operation->fresh()->errors));
        $this->assertSame(['created' => 3, 'updated' => 0, 'removed' => 0], $operation->fresh()->counts);
        $property = Property::firstOrFail();
        $unit = Unit::firstOrFail();
        $room = Room::firstOrFail();
        $this->assertSame($property->id, $unit->property_id);
        $this->assertSame($unit->id, $room->unit_id);
        $this->assertSame($this->owner->id, $unit->owner_id);
        $this->assertSame('00123456', $unit->indah_water_acc_no);
        $this->assertSame('012345678901', $unit->electricity_acc_no);
        $this->assertSame($this->manager->id, $room->created_by);
        $this->assertEquals(1, $unit->total_rooms);
        $this->assertTrue((bool) $unit->has_rooms);
        $this->assertSame('Vacant', $property->status);
        $this->assertSame('Vacant', $unit->status);
        $this->assertSame('Vacant', $room->status);
    }

    public function test_room_numbers_are_unique_per_unit_and_duplicate_rows_fail_atomically(): void
    {
        $this->hierarchyPermissions();
        $property = $this->create();
        $rows = [$this->unitRow($property->id), $this->unitRow($property->id, ['clientKey' => 'new-unit-2', 'unitNo' => 'A-02']),
            $this->roomRow('new-unit-1'), $this->roomRow('new-unit-2', ['clientKey' => 'new-room-2'])];
        app(PropertyBulkService::class)->save($this->validated($rows), $this->manager);
        $this->assertDatabaseCount('rooms', 2);
        $unit = Unit::where('unit_no', 'A-01')->firstOrFail();
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->roomRow($unit->id)]), 'page' => 1])->assertSessionHasErrors('rows.0.roomNo');
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->unitRow($property->id)]), 'page' => 1])->assertSessionHasErrors('rows.0.unitNo');
        $duplicates = [$this->unitRow($property->id, ['unitNo' => 'B']), $this->roomRow('new-unit-1'), $this->roomRow('new-unit-1', ['clientKey' => 'new-room-2'])];
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode($duplicates), 'page' => 1])->assertSessionHasErrors('rows.2.roomNo');
        $this->assertDatabaseCount('units', 2);
    }

    public function test_parent_references_cannot_cross_accounts_or_reference_the_wrong_entity(): void
    {
        $this->hierarchyPermissions();
        $foreign = $this->create();
        $foreign->update(['owner_id' => null, 'created_by' => 'another-manager']);
        Queue::fake();
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->unitRow($foreign->id)]), 'page' => 1])->assertSessionHasErrors('rows.0.parentKey');
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->roomRow($foreign->id)]), 'page' => 1])->assertSessionHasErrors('rows.0.parentKey');
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->unitRow('new-missing')]), 'page' => 1])->assertSessionHasErrors('rows.0.parentKey');
        Queue::assertNothingPushed();
    }

    public function test_each_entity_requires_its_own_permission_and_worker_rechecks_child_permissions(): void
    {
        Queue::fake();
        $property = $this->create();
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->unitRow($property->id)]), 'page' => 1])->assertSessionHas('error', 'You have no permission.');
        $this->hierarchyPermissions();
        $rows = [$this->unitRow($property->id), $this->roomRow('new-unit-1')];
        $operations = app(PropertyBulkOperationService::class);
        $operation = $operations->start($this->validated($rows), $this->manager, [], (string) Str::uuid(), 1, 'draft');
        $this->grant(['property.tab', 'property.show unit', 'unit.create', 'unit.show room']);
        $operations->process($operation->id);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('rooms', 0);
    }

    public function test_hierarchy_failure_rolls_back_every_record_and_audit(): void
    {
        $this->hierarchyPermissions();
        $rows = [$this->row(['entity' => 'property', 'clientKey' => 'new-property-1']), $this->unitRow('new-property-1'), $this->roomRow('new-unit-1')];
        $audits = DB::table('audit_logs')->count();
        try {
            app(PropertyBulkService::class)->save($this->validated($rows), $this->manager, [], null, fn () => throw new \RuntimeException('Rollback hierarchy'));
            $this->fail('Expected failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback hierarchy', $exception->getMessage());
        }
        $this->assertDatabaseCount('properties', 0);
        $this->assertDatabaseCount('units', 0);
        $this->assertDatabaseCount('rooms', 0);
        $this->assertSame($audits, DB::table('audit_logs')->count());
    }

    public function test_units_without_a_property_owner_require_an_explicit_authorized_owner(): void
    {
        $this->hierarchyPermissions();
        $property = $this->create(['ownerId' => '']);
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode([$this->unitRow($property->id)]), 'page' => 1])->assertSessionHasErrors('rows.0.ownerId');
        app(PropertyBulkService::class)->save($this->validated([$this->unitRow($property->id, ['ownerId' => $this->owner->id])]), $this->manager);
        $this->assertSame($this->owner->id, Unit::firstOrFail()->owner_id);
    }

    public function test_existing_child_edits_preserve_occupancy_and_room_removal_updates_counts_and_assets(): void
    {
        $this->hierarchyPermissions();
        $property = $this->create();
        app(PropertyBulkService::class)->save($this->validated([$this->unitRow($property->id), $this->roomRow('new-unit-1')]), $this->manager);
        $unit = Unit::firstOrFail();
        $room = Room::firstOrFail();
        $room->update(['status' => 'Occupied']);
        $row = $this->roomRow($unit->id, ['action' => 'update', 'id' => $room->id, 'clientKey' => $room->id, 'version' => app(PropertyBulkService::class)->revision($room->fresh()), 'roomType' => 'Master']);
        app(PropertyBulkService::class)->save($this->validated([$row]), $this->manager);
        $this->assertSame('Occupied', $room->fresh()->status);
        $this->assertSame('Master', $room->fresh()->room_type);
        DB::table('asset_room')->insert(['id' => (string) Str::ulid(), 'room_id' => $room->id, 'status' => 'Active']);
        $row['action'] = 'remove';
        $row['version'] = app(PropertyBulkService::class)->revision($room->fresh());
        app(PropertyBulkService::class)->save($this->validated([$row]), $this->manager);
        $this->assertSame('Inactive', $room->fresh()->status);
        $this->assertEquals(0, $unit->fresh()->total_rooms);
        $this->assertSame('Inactive', DB::table('asset_room')->value('status'));
    }

    public function test_hierarchy_grid_has_three_tabs_and_recovered_children_include_authorized_parents(): void
    {
        $this->hierarchyPermissions();
        $property = $this->create();
        app(PropertyBulkService::class)->save($this->validated([$this->unitRow($property->id), $this->roomRow('new-unit-1')]), $this->manager);
        $unit = Unit::firstOrFail();
        $room = Room::firstOrFail();
        $this->getJson(route('admin.properties.bulk-edit', ['entity' => 'unit']))->assertOk()->assertJsonPath('rows.0.entity', 'unit')->assertJsonPath('parents.0.key', $property->id);
        $this->getJson(route('admin.properties.bulk-edit', ['entity' => 'all', 'ids' => [$room->id]]))->assertOk()->assertJsonPath('rows.0.entity', 'room')->assertJsonCount(2, 'parents');
        $view = app(PropertyBulkController::class)->index(Request::create('/'), app(PropertyBulkService::class), app(PropertyBulkOperationService::class));
        $source = str_replace(['<x-app-layout>', '</x-app-layout>'], '', file_get_contents(resource_path('views/adminSide/rooms/property/bulk-edit.blade.php')));
        $html = Blade::render($source, $view->getData());
        $this->assertSame(3, substr_count($html, 'role="tab"'));
        $this->assertSame(3, substr_count($html, 'aria-label="Validation errors"'));
        $this->assertSame(3, substr_count($html, '<thead class="sticky top-0'));
        $this->assertLessThan(strpos($html, 'x-ref="gridViewport"'), strpos($html, 'role="tablist"'));
        $this->assertStringContainsString('Choose property', $html);
        $this->assertStringContainsString('Choose unit', $html);
        $this->assertStringNotContainsString('<x-form.', $html);
    }

    public function test_real_database_queue_worker_saves_hierarchy_and_completes_with_session_message(): void
    {
        (require database_path('migrations/0001_01_01_000002_create_jobs_table.php'))->up();
        $this->hierarchyPermissions();
        $key = (string) Str::uuid();
        $rows = [$this->roomRow('new-unit-1'), $this->unitRow('new-property-1'), $this->row(['entity' => 'property', 'clientKey' => 'new-property-1'])];
        $this->post(route('admin.properties.bulk-save'), ['rows_json' => json_encode($rows), 'page' => 1, 'request_key' => $key])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('bulk', DB::table('jobs')->value('queue'));
        $this->getJson(route('admin.properties.bulk-progress', $key))->assertJsonPath('status', 'queued')->assertJsonPath('processed', 0);
        $this->assertSame(0, Artisan::call('queue:work', ['connection' => 'bulk', '--queue' => 'bulk', '--once' => true, '--timeout' => 600, '--tries' => 1]));
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->getJson(route('admin.properties.bulk-progress', $key))->assertJsonPath('status', 'succeeded')->assertJsonPath('processed', 3)->assertJsonPath('percent', 100);
        $this->assertSame(1, Room::count());
        $this->get(route('admin.properties.bulk-complete', $key))->assertRedirect(route('admin.properties.index'))->assertSessionHas('success', 'Saved: 3 new, 0 edited, 0 marked removed.');
    }

    public function test_missing_worker_and_stalled_processing_fail_clearly_and_late_jobs_cannot_save(): void
    {
        Queue::fake();
        $operations = app(PropertyBulkOperationService::class);
        foreach (['queued' => 301, 'processing' => 661] as $status => $seconds) {
            $operation = $operations->start([$this->row()], $this->manager, [], (string) Str::uuid(), 1, 'draft');
            $operation->update(['status' => $status, 'updated_at' => now()->subSeconds($seconds)]);
            $this->getJson(route('admin.properties.bulk-progress', $operation->request_key))->assertJsonPath('status', 'failed');
            $this->assertNull($operation->fresh()->payload);
            $this->assertStringContainsString($status === 'queued' ? 'worker' : 'time limit', $operation->fresh()->errors['bulk'][0]);
            $operations->process($operation->id);
            $this->assertSame(0, Property::count());
            $this->get(route('admin.properties.bulk-complete', $operation->request_key))->assertSessionHasErrors('bulk');
        }
    }

    public function test_pending_progress_outage_returns_message_without_cancelling_or_acknowledging_job(): void
    {
        Queue::fake();
        $operations = app(PropertyBulkOperationService::class);
        $operation = $operations->start([$this->row()], $this->manager, [], (string) Str::uuid(), 1, 'draft');
        $this->get(route('admin.properties.bulk-complete', ['requestKey' => $operation->request_key, 'progress_unavailable' => 1]))
            ->assertRedirect(route('admin.properties.index'))->assertSessionHas('error');
        $this->assertSame('queued', $operation->fresh()->status);
        $this->assertNull($operation->fresh()->acknowledged_at);
        $this->assertNotNull($operation->fresh()->payload);
        $operations->process($operation->id);
        $operations->fail($operation, ['bulk' => ['Late failure']]);
        $this->assertSame('succeeded', $operation->fresh()->status);
    }

    public function test_cancellation_at_commit_rolls_back_all_hierarchy_records(): void
    {
        Queue::fake();
        $operations = app(PropertyBulkOperationService::class);
        $operation = $operations->start([$this->row()], $this->manager, [], (string) Str::uuid(), 1, 'draft');
        $records = app(PropertyBulkService::class);
        $auditCount = DB::table('audit_logs')->count();
        $mock = \Mockery::mock(PropertyBulkService::class)->makePartial();
        $mock->shouldReceive('save')->once()->andReturnUsing(function ($rows, $manager, $uploads, $onProgress, $beforeCommit) use ($records, $operation) {
            return $records->save($rows, $manager, $uploads, $onProgress, function ($counts) use ($operation, $beforeCommit) {
                DB::table('bulk_operations')->where('id', $operation->id)->update(['status' => 'failed']);
                $beforeCommit($counts);
            });
        });
        (new PropertyBulkOperationService($mock))->process($operation->id);
        $this->assertSame(0, Property::count());
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        $this->assertSame('failed', $operation->fresh()->status);
    }
}
