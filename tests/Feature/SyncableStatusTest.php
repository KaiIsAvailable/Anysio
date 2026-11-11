<?php

namespace Tests\Feature;

use App\Models\{Property, Room, Unit};
use App\Http\Controllers\PropertyController;
use App\Services\LeaseService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, DB, Gate, Schema};
use Tests\TestCase;

class SyncableStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        foreach (['properties', 'units', 'rooms'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->string('id')->primary();
                $table->string('status')->default('Vacant');
                if ($name === 'units') {
                    $table->string('property_id');
                }
                if ($name === 'rooms') {
                    $table->string('unit_id');
                }
                $table->timestamps();
            });
        }
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            foreach (['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'] as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamps();
        });

        DB::table('properties')->insert(['id' => 'p', 'status' => 'Vacant']);
        DB::table('units')->insert([
            ['id' => 'u1', 'property_id' => 'p', 'status' => 'Vacant'],
            ['id' => 'u2', 'property_id' => 'p', 'status' => 'Vacant'],
        ]);
        DB::table('rooms')->insert([
            ['id' => 'r1', 'unit_id' => 'u1', 'status' => 'Vacant'],
            ['id' => 'r2', 'unit_id' => 'u1', 'status' => 'Vacant'],
            ['id' => 'r3', 'unit_id' => 'u2', 'status' => 'Vacant'],
        ]);
    }

    public function test_property_changes_reach_every_unit_and_room(): void
    {
        foreach (['Occupied', 'maintenance', 'Vacant'] as $status) {
            Property::findOrFail('p')->update(['status' => $status]);
            $this->assertSame([$status], Unit::query()->distinct()->pluck('status')->all());
            $this->assertSame([$status], Room::query()->distinct()->pluck('status')->all());
        }
    }

    public function test_room_leases_only_occupy_parents_when_all_children_are_occupied(): void
    {
        Room::findOrFail('r1')->update(['status' => 'Occupied']);
        $this->assertSame('Vacant', Unit::find('u1')->status);
        $this->assertSame('Vacant', Room::find('r2')->status);

        Room::findOrFail('r2')->update(['status' => 'occupied']);
        $this->assertSame('Occupied', Unit::find('u1')->status);
        $this->assertSame('Vacant', Property::find('p')->status);

        Room::findOrFail('r3')->update(['status' => 'Occupied']);
        $this->assertSame('Occupied', Property::find('p')->status);

        Room::findOrFail('r1')->update(['status' => 'Vacant']);
        $this->assertSame('Vacant', Unit::find('u1')->status);
        $this->assertSame('Vacant', Property::find('p')->status);
        $this->assertSame('occupied', Room::find('r2')->status);
        $this->assertSame('Occupied', Room::find('r3')->status);
    }

    public function test_unit_lease_updates_rooms_and_checkout_updates_ancestors(): void
    {
        $service = app(LeaseService::class);
        $method = new \ReflectionMethod($service, 'updateLeasableStatus');
        $unit = Unit::findOrFail('u1');
        $method->invoke($service, $unit, 'New');
        $this->assertSame(['Occupied', 'Occupied'], $unit->rooms()->pluck('status')->all());
        $this->assertSame('Vacant', Property::find('p')->status);

        Unit::findOrFail('u2')->update(['status' => 'Occupied']);
        $this->assertSame('Occupied', Property::find('p')->status);

        $method->invoke($service, $unit, 'Check Out');
        $this->assertSame(['Maintenance', 'Maintenance'], $unit->rooms()->pluck('status')->all());
        $this->assertSame('Occupied', Property::find('p')->status);
        $this->assertSame('Occupied', Room::find('r3')->status);

        $method->invoke($service, Room::findOrFail('r3'), 'End');
        $this->assertSame('Vacant', Unit::find('u2')->status);
        $this->assertSame('Vacant', Property::find('p')->status);
    }

    public function test_availability_updates_both_ancestors_for_every_supported_status(): void
    {
        $previous = 'Vacant';
        foreach (['Occupied', 'Maintenance', 'Vacant'] as $status) {
            Room::findOrFail('r1')->update(['status' => strtolower($status)]);
            $this->assertSame(in_array('Vacant', [$previous, $status], true) ? 'Vacant' : 'Occupied', Unit::find('u1')->status);
            $this->assertSame($previous, Room::find('r2')->status);

            Room::findOrFail('r2')->update(['status' => $status]);
            $this->assertSame($status, Unit::find('u1')->status);
            $this->assertSame(in_array('Vacant', [$previous, $status], true) ? 'Vacant' : 'Occupied', Property::find('p')->status);
            $this->assertSame(strtolower($status), Room::find('r1')->status);

            Room::findOrFail('r3')->update(['status' => $status]);
            $this->assertSame($status, Unit::find('u2')->status);
            $this->assertSame($status, Property::find('p')->status);
            $previous = $status;
        }
    }

    public function test_sync_continues_to_parent_when_unit_is_unchanged_and_handles_empty_units(): void
    {
        DB::table('properties')->where('id', 'p')->update(['status' => 'Occupied']);
        Unit::findOrFail('u1')->syncStatus();
        $this->assertSame('Vacant', Property::find('p')->status);

        DB::table('rooms')->where('unit_id', 'u2')->delete();
        Unit::findOrFail('u2')->update(['status' => 'Occupied']);
        Unit::findOrFail('u2')->syncStatus();
        $this->assertSame('Occupied', Unit::find('u2')->status);
    }

    public function test_four_unit_property_uses_the_most_common_status(): void
    {
        DB::table('units')->insert([
            ['id' => 'u3', 'property_id' => 'p', 'status' => 'Occupied'],
            ['id' => 'u4', 'property_id' => 'p', 'status' => 'Occupied'],
        ]);
        Property::findOrFail('p')->update(['status' => 'Occupied']);
        Unit::findOrFail('u1')->update(['status' => 'Vacant']);
        Unit::findOrFail('u4')->update(['status' => 'Maintenance']);

        $this->assertSame('Occupied', Property::find('p')->status);
        $this->assertSame('Vacant', Room::find('r1')->status);
        $this->assertSame('Occupied', Room::find('r3')->status);
        $this->assertSame('Maintenance', Unit::find('u4')->status);

        Unit::findOrFail('u1')->update(['status' => 'Maintenance']);
        $this->assertSame('Occupied', Property::find('p')->status);

        foreach (['u2', 'u3'] as $id) {
            Unit::findOrFail($id)->update(['status' => 'Maintenance']);
        }
        $this->assertSame('Maintenance', Property::find('p')->status);
    }

    public function test_property_index_uses_live_counts_and_availability_for_display_search_and_sort(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
        });
        Schema::table('properties', function (Blueprint $table) {
            foreach (['owner_id', 'created_by', 'name', 'type', 'address', 'city', 'postcode', 'state'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::table('units', function (Blueprint $table) {
            $table->string('owner_id')->nullable();
            $table->string('unit_no')->nullable();
            foreach (['type', 'block', 'floor', 'sqft', 'electricity_acc_no', 'water_acc_no', 'indah_water_acc_no'] as $column) {
                $table->string($column)->nullable();
            }
        });
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('room_no')->nullable();
        });
        $user = new \App\Models\User();
        $user->id = 'admin';
        $user->role = 'admin';
        $this->actingAs($user);
        Gate::before(fn () => true);

        // Deliberately leave the stored status stale, as in existing records.
        DB::table('properties')->where('id', 'p')->update(['status' => 'Occupied']);
        DB::table('units')->where('id', 'u2')->update(['status' => 'Occupied']);
        DB::table('units')->insert([
            ['id' => 'u3', 'property_id' => 'p', 'status' => 'Occupied'],
            ['id' => 'u4', 'property_id' => 'p', 'status' => 'maintenance'],
            ['id' => 'removed', 'property_id' => 'p', 'status' => 'Removed'],
        ]);
        $controller = app(PropertyController::class);
        $properties = $controller->index(Request::create('/', 'GET', ['search' => 'Occupied', 'sort' => 'st_asc']))->getData()['properties'];
        $property = $properties->first();
        $this->assertNotNull($property);
        $this->assertSame('Occupied', $property->availability_status);
        $this->assertEquals(1, $property->vacant_units_count);
        $this->assertEquals(2, $property->occupied_units_count);
        $this->assertEquals(1, $property->maintenance_units_count);

        $source = file_get_contents(resource_path('views/adminSide/rooms/property/index.blade.php'));
        preg_match('/<td[^>]*>\s*<span class="px-2\.5.*?<\/td>/s', $source, $match);
        $html = Blade::render($match[0], ['property' => $property, 'status' => $property->availability_status, 'badge' => '']);
        foreach (['Vacant - 1', 'Occupied - 2', 'Maintenance - 1'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        foreach (['Vacant' => 'text-yellow-500', 'Occupied' => 'text-green-600', 'Maintenance' => 'text-red-600'] as $status => $expectedColor) {
            $color = get_status_color($status);
            $this->assertSame($expectedColor, $color);
            $this->assertStringContainsString('class="font-medium '.$color.'">'.$status.' -', $html);
        }
        $this->assertStringNotContainsString('bg-', $html);
        $this->assertStringContainsString('border-t', $html);

        $emptyProperty = new Property();
        $html = Blade::render($match[0], ['property' => $emptyProperty, 'status' => 'Vacant', 'badge' => get_status_badge('Vacant')]);
        $this->assertStringNotContainsString('border-t', $html);
        $this->assertStringNotContainsString('Vacant -', $html);
        $this->assertStringContainsString('bg-amber-100', $html);

        DB::table('units')->where('id', 'u1')->update(['status' => 'Maintenance']);
        $properties = $controller->index(Request::create('/', 'GET', ['search' => 'Occupied', 'sort' => 'st_desc']))->getData()['properties'];
        $this->assertSame('Occupied', $properties->first()->availability_status);
        $properties = $controller->index(Request::create('/', 'GET', ['search' => 'Vacant']))->getData()['properties'];
        $this->assertCount(0, $properties);

        DB::table('rooms')->where('id', 'r1')->update(['status' => 'Occupied']);
        DB::table('rooms')->where('id', 'r2')->update(['status' => 'maintenance']);
        $unitRows = $controller->show(Request::create('/'), Property::findOrFail('p'))->getData()['units'];
        $unit = $unitRows->firstWhere('id', 'u1');
        $this->assertEquals(0, $unit->vacant_rooms_count);
        $this->assertEquals(1, $unit->occupied_rooms_count);
        $this->assertEquals(1, $unit->maintenance_rooms_count);
        $showSource = file_get_contents(resource_path('views/adminSide/rooms/property/show.blade.php'));
        preg_match('/<td[^>]*data-unit-status-summary>.*?<\/td>/s', $showSource, $unitMatch);
        $unitHtml = Blade::render($unitMatch[0], ['unit' => $unit, 'uStatus' => $unit->status, 'uBadge' => get_status_badge($unit->status)]);
        $this->assertStringNotContainsString('Vacant -', $unitHtml);
        $this->assertStringContainsString('class="font-medium '.get_status_color('Occupied').'">Occupied - 1', $unitHtml);
        $this->assertStringContainsString('class="font-medium '.get_status_color('Maintenance').'">Maintenance - 1', $unitHtml);
        $this->assertStringContainsString('border-t', $unitHtml);

        $emptyUnit = $unitRows->firstWhere('id', 'u4');
        $unitHtml = Blade::render($unitMatch[0], ['unit' => $emptyUnit, 'uStatus' => $emptyUnit->status, 'uBadge' => get_status_badge($emptyUnit->status)]);
        $this->assertStringNotContainsString('border-t', $unitHtml);

        $unitShowSource = file_get_contents(resource_path('views/adminSide/rooms/unit/show.blade.php'));
        preg_match('/<section[^>]*data-unit-header-status-summary[^>]*>.*?<\/section>/s', $unitShowSource, $unitHeaderMatch);
        $unitHeaderHtml = Blade::render($unitHeaderMatch[0], ['unit' => $unit, 'unitBadge' => get_status_badge($unit->status)]);
        $this->assertStringContainsString('Occupied - 1', $unitHeaderHtml);
        $this->assertStringContainsString('Maintenance - 1', $unitHeaderHtml);
        $this->assertStringNotContainsString('Vacant -', $unitHeaderHtml);
        $this->assertStringContainsString('border-l', $unitHeaderHtml);
        $this->assertStringNotContainsString('border-t', $unitHeaderHtml);
        $unitHeaderHtml = Blade::render($unitHeaderMatch[0], ['unit' => $emptyUnit, 'unitBadge' => get_status_badge($emptyUnit->status)]);
        $this->assertStringNotContainsString('border-l', $unitHeaderHtml);

        foreach ([
            [1, 10, 11, 'Maintenance'],
            [1, 11, 10, 'Occupied'],
            [11, 10, 1, 'Vacant'],
            [2, 2, 1, 'Vacant'],
            [0, 2, 2, 'Occupied'],
            [0, 0, 3, 'Maintenance'],
            [3, 0, 0, 'Vacant'],
        ] as [$vacant, $occupied, $maintenance, $expected]) {
            DB::table('units')->delete();
            foreach (['Vacant' => $vacant, 'Occupied' => $occupied, 'Maintenance' => $maintenance] as $status => $count) {
                for ($i = 0; $i < $count; $i++) {
                    DB::table('units')->insert(['id' => "$status-$i", 'property_id' => 'p', 'status' => $status]);
                }
            }

            Property::findOrFail('p')->syncStatus();
            $this->assertSame($expected, Property::find('p')->status);
            // Query calculation must agree even if an existing saved status is stale.
            DB::table('properties')->where('id', 'p')->update(['status' => 'Outdated']);
            $properties = $controller->index(Request::create('/', 'GET', ['search' => $expected, 'sort' => 'st_asc']))->getData()['properties'];
            $property = $properties->first();
            $this->assertNotNull($property);
            $this->assertSame($expected, $property->availability_status);
            $html = Blade::render($match[0], ['property' => $property, 'status' => $expected, 'badge' => get_status_badge($expected)]);
            foreach (['Vacant' => $vacant, 'Occupied' => $occupied, 'Maintenance' => $maintenance] as $status => $count) {
                if ($count === 0) {
                    $this->assertStringNotContainsString("$status -", $html);
                } else {
                    $this->assertStringContainsString("$status - $count", $html);
                }
            }

            // Detail summary uses every unit even when the listing is searched/paginated.
            $showData = $controller->show(Request::create('/', 'GET', ['search' => 'Occupied']), Property::findOrFail('p'))->getData();
            $detailProperty = $showData['property'];
            $this->assertSame($expected, $detailProperty->unit_summary_status);
            $showSource = file_get_contents(resource_path('views/adminSide/rooms/property/show.blade.php'));
            preg_match('/<section[^>]*data-property-status-summary[^>]*>.*?<\/section>/s', $showSource, $showMatch);
            $detailHtml = Blade::render($showMatch[0], [
                'property' => $detailProperty,
                'propertyStatus' => $detailProperty->unit_summary_status,
                'propertyBadge' => get_status_badge($detailProperty->unit_summary_status),
            ]);
            foreach (['Vacant' => $vacant, 'Occupied' => $occupied, 'Maintenance' => $maintenance] as $status => $count) {
                if ($count === 0) {
                    $this->assertStringNotContainsString("$status -", $detailHtml);
                } else {
                    $this->assertStringContainsString("$status - $count", $detailHtml);
                }
            }
        }
        DB::table('units')->delete();
        $detailProperty = $controller->show(Request::create('/'), Property::findOrFail('p'))->getData()['property'];
        $detailHtml = Blade::render($showMatch[0], ['property' => $detailProperty, 'propertyStatus' => 'Vacant', 'propertyBadge' => get_status_badge('Vacant')]);
        $this->assertStringNotContainsString('border-t', $detailHtml);
    }
}
