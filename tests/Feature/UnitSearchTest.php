<?php

namespace Tests\Feature;

use App\Http\Controllers\PropertyController;
use App\Models\{Property, Unit, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, DB, Gate, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UnitSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email');
        });
        Schema::create('properties', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('owner_id')->nullable();
            $table->string('status');
        });
        Schema::create('units', function (Blueprint $table) {
            $table->string('id')->primary();
            foreach (['property_id', 'owner_id', 'unit_no', 'type', 'block', 'floor', 'electricity_acc_no', 'water_acc_no', 'indah_water_acc_no', 'status'] as $column) {
                $table->string($column)->nullable();
            }
            $table->decimal('sqft')->nullable();
        });
        Schema::create('rooms', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('unit_id');
            $table->string('room_no');
            $table->string('status');
        });
        DB::table('users')->insert([
            ['id' => 'alice', 'name' => 'Alice Tan', 'email' => 'alice@example.test'],
            ['id' => 'bob', 'name' => 'Bob Lee', 'email' => 'bob@example.test'],
        ]);
        DB::table('properties')->insert([
            ['id' => 'p1', 'status' => 'Vacant'],
            ['id' => 'p2', 'status' => 'Occupied'],
        ]);
        DB::table('units')->insert([
            'id' => 'u1', 'property_id' => 'p1', 'owner_id' => 'alice', 'unit_no' => 'A-12-08',
            'type' => 'Studio', 'block' => 'North Tower', 'floor' => 'Level Twelve', 'sqft' => 875,
            'electricity_acc_no' => '123456789012', 'water_acc_no' => '2345678901234567',
            'indah_water_acc_no' => '345678901234', 'status' => 'Vacant',
        ]);
        DB::table('units')->insert([
            ['id' => 'u2', 'property_id' => 'p1', 'owner_id' => 'bob', 'unit_no' => 'B', 'status' => 'Maintenance'],
            ['id' => 'u3', 'property_id' => 'p2', 'owner_id' => 'bob', 'unit_no' => 'A-12-08', 'status' => 'Occupied'],
        ]);
        DB::table('rooms')->insert([
            ['id' => 'r1', 'unit_id' => 'u1', 'room_no' => 'Master-101', 'status' => 'Vacant'],
            ['id' => 'r2', 'unit_id' => 'u1', 'room_no' => 'Master-102', 'status' => 'Occupied'],
            ['id' => 'r3', 'unit_id' => 'u3', 'room_no' => 'Master-101', 'status' => 'Occupied'],
        ]);
        $admin = new User();
        $admin->id = 'admin';
        $admin->role = 'admin';
        $this->actingAs($admin);
        Gate::before(fn () => true);
    }

    public static function searchTerms(): array
    {
        return array_map(fn ($term) => [$term], [
            'A-12', 'Alice', 'alice@example.test', 'studio', 'North', 'Twelve', '875',
            '123456789012', '2345678901234567', '345678901234', 'Vacant', 'Master', '  A-12  ', '0',
        ]);
    }

    #[DataProvider('searchTerms')]
    public function test_reusable_scope_and_property_detail_search_match_unit_fields(string $search): void
    {
        $this->assertSame(['u1'], Unit::where('property_id', 'p1')->search($search)->pluck('id')->all());
        $data = app(PropertyController::class)->show(Request::create('/', 'GET', ['search' => $search, 'sort' => 'u_asc']), Property::findOrFail('p1'))->getData();
        $this->assertSame(['u1'], $data['units']->pluck('id')->all());
        $this->assertSame(1, $data['units']->total());
        $this->assertEquals(1, $data['property']->vacant_units_count);
        $this->assertEquals(1, $data['property']->maintenance_units_count);
    }

    public function test_search_cannot_return_units_from_another_property(): void
    {
        $data = app(PropertyController::class)->show(Request::create('/', 'GET', ['search' => 'Occupied']), Property::findOrFail('p1'))->getData();
        $this->assertSame(0, $data['units']->total());
    }

    public function test_blank_search_keeps_units_and_unknown_search_returns_none(): void
    {
        $this->assertSame(['u1', 'u2'], Unit::where('property_id', 'p1')->search('   ')->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, Unit::search('no-such-value')->count());
    }

    public function test_room_matches_share_the_panel_and_keep_total_room_count(): void
    {
        DB::table('rooms')->insert(['id' => 'r4', 'unit_id' => 'u2', 'room_no' => 'Master-101', 'status' => 'Vacant']);
        $data = app(PropertyController::class)->show(Request::create('/', 'GET', ['search' => 'Master-101']), Property::findOrFail('p1'))->getData();
        $this->assertSame(2, $data['units']->total());
        foreach ($data['units'] as $unit) {
            $this->assertTrue($unit->relationLoaded('rooms'));
            $this->assertSame(['Master-101'], $unit->rooms->pluck('room_no')->all());
            $this->assertEquals($unit->id === 'u1' ? 2 : 1, $unit->rooms_count);
            $this->assertTrue($unit->rooms->every(fn ($room) => $room->unit_id === $unit->id));
            $html = Blade::render('<x-table.search-matches :label="\'Unit \'.$unit->unit_no" :rooms="$unit->rooms" :colspan="6" />', compact('unit'));
            $this->assertStringContainsString('Matches in Unit '.$unit->unit_no, $html);
            $this->assertStringContainsString('Master-101', $html);
            $this->assertStringNotContainsString('Master-102', $html);
            $this->assertStringContainsString(route('admin.rooms.show', $unit->rooms->first()->id), $html);
            $this->assertStringContainsString('colspan="6"', $html);
            $this->assertStringNotContainsString('0 units', $html);
        }
    }

    public function test_unit_fields_and_empty_search_do_not_show_room_match_panel(): void
    {
        foreach (['Alice', ''] as $search) {
            $data = app(PropertyController::class)->show(Request::create('/', 'GET', ['search' => $search]), Property::findOrFail('p1'))->getData();
            foreach ($data['units'] as $unit) {
                $rooms = $unit->relationLoaded('rooms') ? $unit->rooms : collect();
                $html = Blade::render('<x-table.search-matches label="Unit" :rooms="$rooms" />', compact('rooms'));
                $this->assertStringNotContainsString('<tr', $html);
            }
        }
    }
}
