<?php

namespace Tests\Feature;

use App\Http\Controllers\UnitController;
use App\Models\{Room, Unit, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoomSearchTest extends TestCase
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
        Schema::create('properties', fn (Blueprint $table) => $table->string('id')->primary());
        Schema::create('units', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('property_id');
            $table->string('owner_id');
        });
        Schema::create('rooms', function (Blueprint $table) {
            $table->string('id')->primary();
            foreach (['unit_id', 'owner_id', 'room_no', 'room_type', 'status'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('assets', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
        });
        Schema::create('asset_room', function (Blueprint $table) {
            $table->id();
            foreach (['asset_id', 'room_id', 'status', 'condition', 'last_maintenance', 'remark'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        DB::table('users')->insert([
            ['id' => 'alice', 'name' => 'Alice Tan', 'email' => 'alice@example.test'],
            ['id' => 'bob', 'name' => 'Bob Lee', 'email' => 'bob@example.test'],
        ]);
        DB::table('properties')->insert(['id' => 'p']);
        DB::table('units')->insert([
            ['id' => 'u1', 'property_id' => 'p', 'owner_id' => 'alice'],
            ['id' => 'u2', 'property_id' => 'p', 'owner_id' => 'bob'],
        ]);
        DB::table('rooms')->insert([
            ['id' => 'r1', 'unit_id' => 'u1', 'room_no' => 'Master-101', 'room_type' => 'Suite', 'status' => 'Vacant', 'created_at' => '2026-11-09 09:00:00'],
            ['id' => 'r2', 'unit_id' => 'u1', 'room_no' => 'Single-B', 'room_type' => 'Single', 'status' => 'Occupied', 'created_at' => '2025-01-02 09:00:00'],
            ['id' => 'r3', 'unit_id' => 'u2', 'room_no' => 'Master-101', 'room_type' => 'Suite', 'status' => 'Maintenance', 'created_at' => '2026-11-09 09:00:00'],
        ]);
        DB::table('assets')->insert([
            ['id' => 'fan', 'name' => 'Ceiling Fan'],
            ['id' => 'tv', 'name' => 'Television'],
            ['id' => 'old', 'name' => 'Retired Heater'],
        ]);
        DB::table('asset_room')->insert([
            ['asset_id' => 'fan', 'room_id' => 'r1', 'status' => 'Active'],
            ['asset_id' => 'tv', 'room_id' => 'r1', 'status' => 'Active'],
            ['asset_id' => 'old', 'room_id' => 'r2', 'status' => 'Inactive'],
            ['asset_id' => 'fan', 'room_id' => 'r3', 'status' => 'Active'],
        ]);
        $admin = new User();
        $admin->id = 'admin';
        $admin->role = 'admin';
        $this->actingAs($admin);
        Gate::before(fn () => true);
    }

    public static function searchTerms(): array
    {
        return [
            ['Master', ['r1']], ['Alice', ['r1', 'r2']], ['alice@example.test', ['r1', 'r2']],
            ['suite', ['r1']], ['Vacant', ['r1']], ['Fan', ['r1']], ['Television', ['r1']],
            ['09/11/2026', ['r1']], ['9/11/2026', ['r1']], ['2026-11-09', ['r1']],
            ['09 Nov 2026', ['r1']], ['9 November 2026', ['r1']], ['2026-11', ['r1']],
            ['  Master  ', ['r1']], ['0', ['r1', 'r2']], ['Bob', []], ['Retired Heater', []],
            ['Maintenance', []], ['31/02/2026', []], ['31 Feb 2026', []], ['unknown', []],
            ['', ['r1', 'r2']],
        ];
    }

    #[DataProvider('searchTerms')]
    public function test_room_scope_and_unit_page_search_match_displayed_data(string $search, array $expected): void
    {
        $this->assertSame($expected, Room::where('unit_id', 'u1')->search($search)->orderBy('id')->pluck('id')->all());
        $data = app(UnitController::class)->show(Request::create('/', 'GET', ['search' => $search, 'sort' => 'r_asc']), Unit::findOrFail('u1'))->getData();
        $this->assertSame($expected, $data['rooms']->pluck('id')->all());
        $this->assertSame(count($expected), $data['rooms']->total());
        $this->assertEquals(1, $data['unit']->vacant_rooms_count);
        $this->assertEquals(1, $data['unit']->occupied_rooms_count);
    }
}
