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
            $table->string('owner_id')->nullable();
            $table->string('unit_no');
            $table->string('status');
        });
        Schema::create('owners', function (Blueprint $table) {
            $table->string('user_id');
            $table->string('agent_id');
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
            ['id' => 'u1', 'property_id' => 'p1', 'owner_id' => 'owner', 'unit_no' => 'A-08-01', 'status' => 'Vacant'],
            ['id' => 'u2', 'property_id' => 'p1', 'owner_id' => 'owner', 'unit_no' => 'A-08-02', 'status' => 'Maintenance'],
            ['id' => 'u3', 'property_id' => 'p2', 'owner_id' => 'other-owner', 'unit_no' => 'Private-Unit', 'status' => 'Maintenance'],
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

    private function actAsAgentWithSharedProperty(): User
    {
        $this->superAdmin = false;
        $agent = (new User)->forceFill(['id' => 'agent-a', 'role' => 'agentAdmin', 'account_permissions' => ['property.tab', 'property.show unit', 'property.edit', 'property.delete']]);
        $this->actingAs($agent);
        Gate::before(fn ($user, $ability) => $ability === 'super-admin' ? false : in_array($ability, ['property.tab', 'property.show unit', 'property.edit', 'property.delete']));
        DB::table('owners')->insert(['user_id' => 'owner', 'agent_id' => $agent->id]);
        DB::table('properties')->where('id', 'p1')->update(['owner_id' => null, 'created_by' => 'super-admin']);
        DB::table('units')->where('id', 'u2')->update(['owner_id' => 'other-owner']);

        return $agent;
    }

    public function test_agent_sees_ownerless_super_admin_property_through_managed_unit_only(): void
    {
        $agent = $this->actAsAgentWithSharedProperty();
        $controller = app(PropertyController::class);
        $properties = $controller->index(Request::create('/'))->getData()['properties'];
        $this->assertSame(['p1'], $properties->pluck('id')->all());
        $property = $properties->first();
        $this->assertSame('super-admin', $property->created_by);
        $this->assertNull($property->owner_id);
        $this->assertSame(1, $property->units_count);
        $this->assertSame(1, $property->vacant_units_count);
        $this->assertSame(0, $property->maintenance_units_count);
        $this->assertSame('Vacant', $property->availability_status);
        $this->assertFalse($property->can_manage);
        $this->assertFalse(Property::forManagement($agent)->whereKey('p1')->exists());

        $data = $controller->show(Request::create('/'), Property::findOrFail('p1'))->getData();
        $this->assertSame(['u1'], $data['units']->pluck('id')->all());
        $this->assertSame(['u1'], $data['property']->units->pluck('id')->all());
        $this->assertSame(0, $data['property']->maintenance_units_count);
        $this->assertFalse($data['canManageProperty']);
        $this->assertSame('errors.403', $controller->show(Request::create('/'), Property::findOrFail('p2'))->name());
    }

    public function test_shared_property_searches_and_hints_do_not_expose_other_owners_units_or_rooms(): void
    {
        $this->actAsAgentWithSharedProperty();
        $controller = app(PropertyController::class);
        foreach (['A-08-02', 'Master-102', 'Private-Unit', 'Private-Room', 'Maintenance'] as $search) {
            $properties = $controller->index(Request::create('/', 'GET', ['search' => $search]))->getData()['properties'];
            $this->assertSame(0, $properties->total(), $search);
        }
        foreach (['A-08', 'Master'] as $search) {
            $property = $controller->index(Request::create('/', 'GET', ['search' => $search]))->getData()['properties']->sole();
            $html = Blade::render('<x-table.property-search-matches :property="$property" />', compact('property'));
            $this->assertStringNotContainsString('A-08-02', $html);
            $this->assertStringNotContainsString('Master-102', $html);
            $this->assertStringContainsString($search === 'Master' ? 'Master-101' : 'A-08-01', $html);
        }
    }

    public function test_unit_management_does_not_authorize_modifying_or_deleting_shared_parent(): void
    {
        $this->actAsAgentWithSharedProperty();
        $controller = app(PropertyController::class);
        $property = Property::findOrFail('p1');
        $this->assertSame('errors.403', $controller->edit($property)->name());
        $this->assertSame('You have no permission.', $controller->update(Request::create('/'), $property)->getSession()->get('error'));
        $this->assertSame('You have no permission.', $controller->destroy('p1')->getSession()->get('error'));
        $this->assertSame('You have no permission.', $controller->restore('p1')->getSession()->get('error'));
        $this->assertSame('Occupied', $property->fresh()->status);
        $this->assertSame('Maintenance', DB::table('units')->where('id', 'u2')->value('status'));
    }
}
