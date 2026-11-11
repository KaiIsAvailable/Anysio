<?php

namespace Tests\Feature;

use App\Http\Controllers\PropertyController;
use App\Models\{Property, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, DB, Gate, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    private bool $superAdmin = true;

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
            foreach (['name', 'owner_id', 'created_by', 'type', 'status', 'address', 'city', 'postcode', 'state'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('units', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('property_id');
            $table->string('unit_no');
            $table->string('status');
        });
        Schema::create('rooms', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('unit_id');
            $table->string('room_no');
        });
        DB::table('users')->insert([
            ['id' => 'owner', 'name' => 'Alice Tan', 'email' => 'alice@example.test'],
            ['id' => 'other-owner', 'name' => 'Bob Lee', 'email' => 'bob@example.test'],
        ]);
        DB::table('properties')->insert([
            ['id' => 'p1', 'owner_id' => 'owner', 'name' => 'Sunrise Residence', 'type' => 'Condo', 'status' => 'Occupied', 'address' => '88 Orchid Street', 'city' => 'Petaling Jaya', 'postcode' => '47301', 'state' => 'Selangor', 'created_at' => '2026-11-09 09:30:00'],
            ['id' => 'p2', 'owner_id' => 'other-owner', 'name' => 'Hidden House', 'type' => 'Landed', 'status' => 'Maintenance', 'address' => '90 Another Street', 'city' => 'Ipoh', 'postcode' => '30000', 'state' => 'Perak', 'created_at' => '2025-01-02 09:30:00'],
        ]);
        DB::table('units')->insert([
            ['id' => 'u1', 'property_id' => 'p1', 'unit_no' => 'A-08-01', 'status' => 'Vacant'],
            ['id' => 'u2', 'property_id' => 'p1', 'unit_no' => 'A-08-02', 'status' => 'Maintenance'],
            ['id' => 'u3', 'property_id' => 'p2', 'unit_no' => 'Private-Unit', 'status' => 'Maintenance'],
        ]);
        DB::table('rooms')->insert([
            ['id' => 'r1', 'unit_id' => 'u1', 'room_no' => 'Master-101'],
            ['id' => 'r2', 'unit_id' => 'u2', 'room_no' => 'Master-102'],
            ['id' => 'r3', 'unit_id' => 'u3', 'room_no' => 'Private-Room'],
        ]);
        $user = new User();
        $user->id = 'owner';
        $user->role = 'ownerAdmin';
        $user->account_permissions = ['property.tab'];
        $this->actingAs($user);
        Gate::before(fn ($user, $ability) => $ability === 'super-admin' ? $this->superAdmin : $ability === 'property.tab');
    }

    public static function searchTerms(): array
    {
        return array_map(fn ($term) => [$term], [
            'Sunrise', 'Alice', 'alice@example.test', 'Condo', 'Vacant',
            'Orchid', 'Petaling', '47301', 'Selangor', 'A-08', 'Master',
            '09/11/2026', '9/11/2026', '2026-11-09', '2026-11', '  Sunrise  ', '0',
        ]);
    }

    #[DataProvider('searchTerms')]
    public function test_search_matches_property_fields_owner_children_and_creation_date(string $term): void
    {
        $properties = app(PropertyController::class)->index(Request::create('/', 'GET', ['search' => $term, 'sort' => 'n_asc']))->getData()['properties'];
        $this->assertSame($properties->pluck('id')->all(), Property::query()->search($term)->orderBy('name')->pluck('id')->all());
        if ($term === '0') {
            $this->assertSame(['p2', 'p1'], $properties->pluck('id')->all());
        } else {
            $this->assertSame(['p1'], $properties->pluck('id')->all());
        }
    }

    public function test_unmatched_and_invalid_dates_return_no_properties(): void
    {
        foreach (['unknown-value', '31/02/2026', 'Occupied'] as $term) {
            $properties = app(PropertyController::class)->index(Request::create('/', 'GET', ['search' => $term]))->getData()['properties'];
            $this->assertSame(0, $properties->total());
        }
    }

    public function test_related_unit_and_room_searches_do_not_bypass_account_visibility(): void
    {
        $this->superAdmin = false;
        foreach (['Private-Unit', 'Private-Room', 'bob@example.test', '2025-01-02'] as $term) {
            $properties = app(PropertyController::class)->index(Request::create('/', 'GET', ['search' => $term]))->getData()['properties'];
            $this->assertSame(0, $properties->total());
        }
        $properties = app(PropertyController::class)->index(Request::create('/', 'GET', ['search' => 'Master']))->getData()['properties'];
        $property = $properties->first();
        $html = Blade::render('<x-table.property-search-matches :property="$property" :colspan="6" />', compact('property'));
        $this->assertStringContainsString('Master-101', $html);
        $this->assertStringContainsString('colspan="6"', $html);
        $this->assertStringNotContainsString('Open room', $html);
    }

    public function test_duplicate_unit_and_room_numbers_show_matches_under_the_correct_property(): void
    {
        DB::table('units')->where('id', 'u3')->update(['unit_no' => 'A-08-01']);
        $controller = app(PropertyController::class);
        $properties = $controller->index(Request::create('/', 'GET', ['search' => 'A-08-01']))->getData()['properties'];
        $this->assertSame(2, $properties->total());
        foreach ($properties as $property) {
            $this->assertSame(['A-08-01'], $property->units->pluck('unit_no')->all());
            $this->assertTrue($property->rooms->isEmpty());
            $this->assertTrue($property->units->every(fn ($unit) => $unit->property_id === $property->id));
            $html = Blade::render('<x-table.property-search-matches :property="$property" />', compact('property'));
            $this->assertStringContainsString('Matches in '.$property->name, $html);
            $this->assertStringContainsString(route('admin.units.show', $property->units->first()->id), $html);
            $this->assertStringContainsString('A-08-01', $html);
            $this->assertStringNotContainsString('A-08-02', $html);
        }

        DB::table('units')->where('id', 'u3')->update(['unit_no' => 'Private-Unit']);
        DB::table('rooms')->where('id', 'r3')->update(['room_no' => 'Master-101']);
        $properties = $controller->index(Request::create('/', 'GET', ['search' => 'Master-101']))->getData()['properties'];
        $this->assertSame(2, $properties->total());
        foreach ($properties as $property) {
            $html = Blade::render('<x-table.property-search-matches :property="$property" />', compact('property'));
            $this->assertStringContainsString('Matches in '.$property->name, $html);
            $this->assertStringContainsString(route('admin.rooms.show', $property->rooms->first()->id), $html);
            $this->assertStringContainsString('Master-101', $html);
            $this->assertStringContainsString('Unit '.($property->id === 'p1' ? 'A-08-01' : 'Private-Unit'), $html);
            $this->assertStringNotContainsString('Master-102', $html);
        }
    }

    public function test_normal_property_search_and_empty_search_show_no_child_match_hint(): void
    {
        foreach (['Sunrise', ''] as $search) {
            $properties = app(PropertyController::class)->index(Request::create('/', 'GET', ['search' => $search]))->getData()['properties'];
            foreach ($properties as $property) {
                $html = Blade::render('<x-table.property-search-matches :property="$property" />', compact('property'));
                $this->assertStringNotContainsString('Matches in', $html);
                $this->assertStringNotContainsString('<tr', $html);
            }
        }
    }
}
