<?php

namespace Tests\Feature;

use App\Models\{Property, Room, Unit, User, Utility, UtilityService, Invoice};
use App\Services\{UtilitySetupService, UtilityReadingService, UtilityAllocationService, UtilityInvoiceService, UtilityCorrectionService, UtilityTariffManagementService};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UtilitySetupTest extends TestCase
{
    private array $ids = [];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'users' => ['name', 'role', 'email_verified_at'], 'owners' => ['user_id', 'agent_id'],
            'properties' => ['name', 'owner_id', 'created_by', 'status'],
            'units' => ['unit_no', 'property_id', 'owner_id', 'created_by', 'status', 'water_acc_no', 'electricity_acc_no', 'indah_water_acc_no'],
            'rooms' => ['room_no', 'unit_id', 'owner_id', 'created_by', 'status'],
            'tenants' => ['user_id', 'created_by'],
            'leases' => ['tenant_id', 'leasable_type', 'leasable_id', 'status', 'start_date', 'end_date', 'cancelled_at', 'checked_out_at', 'agreement_ended_at', 'parent_lease_id'],
            'invoice_items' => ['invoice_id', 'fee_type_id', 'lease_charge_id', 'description', 'amount'],
            'invoices' => ['context', 'billable_type', 'billable_id', 'lease_id', 'user_id', 'invoice_no', 'document_template_id', 'type', 'period', 'due_date', 'total_amount', 'amount_paid', 'amount_balance', 'status', 'remarks', 'deleted_at', 'parent_id', 'settlement_refund_id', 'adjustment_reason'],
            'document_templates' => ['user_id', 'category', 'status'],
            'document_sequences' => ['user_id', 'category', 'sequence'],
            'fee_types' => ['user_id', 'category', 'name', 'original_price', 'sell_price', 'is_system', 'is_active'],
            'refunds' => ['lease_id', 'status'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($table, $columns) {
                $t->ulid('id')->primary(); $t->timestamps();
                foreach ($columns as $column) $t->string($column)->nullable();
                if ($table === 'invoices') $t->unique('invoice_no');
                if ($table === 'leases') $t->boolean('is_current')->default(true);
            });
        }
        Schema::create('settings', function (Blueprint $table) {
            $table->id(); $table->string('user_id'); $table->string('key');
            $table->text('value'); $table->boolean('is_active')->default(true); $table->timestamps();
        });
        (require database_path('migrations/2026_01_03_144644_create_utilities_table.php'))->up();
        foreach (['a', 'b'] as $key) {
            $this->insert('users', $key, ['name' => 'Manager '.$key, 'role' => User::ROLE_OWNER_ADMIN, 'email_verified_at' => now()]);
            $this->insert('properties', 'p'.$key, ['name' => 'Property '.$key, 'owner_id' => $this->ids[$key], 'created_by' => $this->ids[$key]]);
            $this->insert('units', 'u'.$key, ['unit_no' => 'Unit '.$key, 'property_id' => $this->ids['p'.$key], 'owner_id' => $this->ids[$key], 'created_by' => $this->ids[$key], 'water_acc_no' => 'ACCOUNT-'.$key]);
            $this->insert('rooms', 'r'.$key, ['room_no' => 'Room '.$key, 'unit_id' => $this->ids['u'.$key], 'owner_id' => $this->ids[$key], 'created_by' => $this->ids[$key]]);
        }
        $this->insert('tenants', 'occupant-a', ['created_by' => $this->ids['a']]);
        $this->insert('tenants', 'occupant-b', ['created_by' => $this->ids['b']]);
        $this->insert('leases', 'occupied-b', ['tenant_id' => $this->ids['occupant-b'], 'leasable_type' => Unit::class, 'leasable_id' => $this->ids['ub'], 'status' => 'New', 'start_date' => '2020-01-01']);
        $this->insert('leases', 'lease', ['tenant_id' => $this->ids['occupant-a'], 'leasable_type' => Unit::class, 'leasable_id' => $this->ids['ua'], 'status' => 'New', 'start_date' => '2020-01-01']);
        $this->insert('utilities', 'legacy', ['lease_id' => $this->ids['lease'], 'type' => 'water', 'prev_reading' => 100, 'curr_reading' => 110, 'amount' => 2500]);
        (require database_path('migrations/2026_11_13_000001_create_utility_services_table.php'))->up();
        (require database_path('migrations/2026_11_13_000002_extend_utilities_reading_history.php'))->up();
        (require database_path('migrations/2026_11_13_000003_add_soft_deletes_to_utility_services.php'))->up();
        (require database_path('migrations/2026_11_14_000001_version_utility_billing.php'))->up();
        // Explicit landlord-configured test schedules, never verified official tariff claims.
        foreach (['a', 'b'] as $account) {
            $tariffId = (string) Str::ulid();
            DB::table('utility_tariffs')->insert(['id' => $tariffId, 'management_user_id' => $this->ids[$account],
                'kind' => 'custom', 'reference_key' => 'water_perak_domestic', 'name' => 'Test landlord schedule',
                'utility_type' => 'water', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            $definition = config('utility_tariffs.profiles.water_perak_domestic') + ['minimum_policy' => 'monthly'];
            DB::table('utility_tariff_versions')->insert(['id' => (string) Str::ulid(), 'utility_tariff_id' => $tariffId,
                'version' => 1, 'effective_from' => '2020-01-01', 'definition' => json_encode($definition),
                'reason' => 'Sample configured rates for automated tests only.', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('settings')->insert(['user_id' => $this->ids[$account], 'key' => 'utility_tariff_config',
                'value' => json_encode(['default_tariff_id' => $tariffId]), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }

        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        // Render the real utility views without unrelated subscription/navigation tables.
        view()->getFinder()->prependLocation(__DIR__.'/../Fixtures/utilities');
        Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
        foreach (['property.show unit', 'unit.show room', 'room.show', 'tenant.show', 'utilities.tab', 'utilities.enter readings', 'utilities.reading history', 'utilities.manage charges', 'utilities.tariff tab', 'utilities.edit tariffs', 'owner-admin', 'utilities.manage meters', 'settings.edit utility meter prices'] as $ability) Gate::define($ability, fn () => true);
        foreach (['is-tenant', 'is-owner', 'super-admin'] as $ability) Gate::define($ability, fn () => false);
        $this->actingAs(User::findOrFail($this->ids['a']));
    }

    private function insert(string $table, string $key, array $data): void
    {
        $this->ids[$key] = (string) Str::ulid();
        DB::table($table)->insert(['id' => $this->ids[$key], 'created_at' => now(), 'updated_at' => now(), ...$data]);
    }

    public function test_utilities_only_recorder_can_save_without_access_to_other_modules(): void
    {
        $row = $this->enableWaterEntry();
        foreach (['property.show unit', 'unit.show room', 'room.show', 'tenant.show', 'invoice.tab', 'property.edit', 'leases.add manual invoice', 'owner-admin',
            'settings.edit utility meter prices', 'utilities.manage meters', 'utilities.reading history',
            'utilities.generate invoices', 'utilities.manage charges', 'utilities.correct invoices',
            'utilities.tariff tab', 'utilities.edit tariffs'] as $ability) Gate::define($ability, fn () => false);
        $this->get(route('admin.utilities.index'))->assertOk()->assertSee('Enter Readings')
            ->assertDontSee('Initialize Water Services')->assertDontSee('Reading History');
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk()
            ->assertDontSee('Water Tariffs')->assertDontSee('Preview Invoices')->assertDontSee('Delete Selected')
            ->assertDontSee('title="Edit water meter setup"', false);
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.save'), $this->readingInput($row, ['rate' => ''])))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('12.470', $this->service('ra')->records()->sole()->curr_reading);
        $this->get(route('admin.utilities.history'))->assertViewIs('errors.403');
        $this->get(route('admin.utilities.edit', $row['id']))->assertViewIs('errors.403');
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.utilities.initialize'));
        $this->post(route('admin.utilities.readings.invoices.generate'), [])->assertRedirect()
            ->assertSessionHas('error', 'You have no permission.');
    }

    public function test_water_tariffs_use_settings_utility_tab_and_keep_utilities_only_access_scoped(): void
    {
        $this->enableWaterEntry();
        $destination = route('admin.settings.index', ['tab' => 'utility', 'management_user_id' => $this->ids['a']]);
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk()->assertSee($destination);
        $this->get(route('admin.utilities.tariffs', ['management_user_id' => $this->ids['a']]))->assertRedirect($destination);
        Gate::define('settings.payment tab', fn () => true);
        Gate::define('settings.user role tab', fn () => true);
        $response = $this->get($destination);
        $response->assertOk()->assertViewIs('adminSide.setting.index')->assertViewHas('utilityOnly', true)
            ->assertSee('Official Provider Tariffs')->assertDontSee("activeTab = 'payment'", false)->assertDontSee('Staff Role Permissions');
        $this->assertSame($this->ids['a'], $response->viewData('tariffManager')->id);
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.tariffs.store'), ['tariff' => [
            'name' => 'Shared settings tariff', 'effective_from' => '2026-11-01', 'reason' => 'Save rates from the shared settings screen',
            'contract_confirmed' => true, 'definition' => ['calculation' => 'flat', 'minimum_policy' => 'none',
                'minimum_charge' => '0', 'tiers' => [['limit' => null, 'rate' => '1']]],
        ]]))->assertRedirect($destination)->assertSessionHas('success')->assertSessionHasNoErrors();
        $response = $this->get(route('admin.settings.index', ['tab' => 'utility', 'management_user_id' => $this->ids['b']]));
        $response->assertOk();
        $this->assertSame($this->ids['a'], $response->viewData('tariffManager')->id, 'Staff and account users cannot select another management account');
        Gate::define('super-admin', fn () => true);
        $response = $this->get(route('admin.settings.index', ['tab' => 'utility', 'management_user_id' => $this->ids['b']]));
        $response->assertOk()->assertViewIs('adminSide.setting.index');
        $this->assertSame($this->ids['b'], $response->viewData('tariffManager')->id);
        Gate::define('super-admin', fn () => false);
        Gate::define('utilities.tariff tab', fn () => false);
        Gate::define('settings.edit utility meter prices', fn () => false);
        $this->get($destination)->assertViewIs('errors.403');
    }

    public function test_utility_tab_and_each_action_are_required_before_processing_requests(): void
    {
        $row = $this->enableWaterEntry();
        Gate::define('utilities.tab', fn () => false);
        $this->get(route('admin.utilities.index'))->assertViewIs('errors.403');
        $this->get(route('admin.utilities.readings'))->assertViewIs('errors.403');
        $this->post(route('admin.utilities.readings.save'), $this->readingInput($row))->assertRedirect()
            ->assertSessionHas('error', 'You have no permission.');
        $this->assertSame(0, $this->service('ra')->records()->count());
        Gate::define('utilities.tab', fn () => true);
        foreach (['utilities.manage charges', 'utilities.correct invoices', 'utilities.manage meters',
            'utilities.edit tariffs', 'settings.edit utility meter prices'] as $ability) Gate::define($ability, fn () => false);
        $this->saveReading($this->readingInput($row, ['rate' => '']));
        $record = $this->service('ra')->records()->sole();
        foreach (['admin.utilities.allocations.store', 'admin.utilities.allocations.revoke',
            'admin.utilities.adjustments.store', 'admin.utilities.corrections.store'] as $route) {
            $method = $route === 'admin.utilities.allocations.revoke' ? 'delete' : 'post';
            $this->{$method}(route($route, $record), [])->assertRedirect()
                ->assertSessionHas('error', 'You have no permission.');
        }
        $this->post(route('admin.utilities.events.store', $row['id']), [])->assertRedirect()
            ->assertSessionHas('error', 'You have no permission.');
        $this->post(route('admin.utilities.tariffs.store'), [])->assertRedirect()
            ->assertSessionHas('error', 'You have no permission.');
    }

    public function test_water_meter_registration_is_explicit_repeatable_and_supports_zero_opening(): void
    {
        $setup = app(UtilitySetupService::class);
        $property = Property::findOrFail($this->ids['pa']);
        Model::withoutEvents(fn () => $setup->saveWaterMeter($property, ['has_meter' => '0']));
        $this->assertSame(0, UtilityService::count());
        foreach ([Property::findOrFail($this->ids['pa']), Unit::findOrFail($this->ids['ua']), Room::findOrFail($this->ids['ra'])] as $location) {
            Model::withoutEvents(fn () => $setup->saveWaterMeter($location, ['has_meter' => '1',
                'meter_number' => 'M-'.$location->id, 'opening_reading' => '0', 'opening_reading_date' => '2026-11-01']));
            $service = $location->utilityServices()->sole();
            $this->assertSame('active', $service->status);
            $this->assertSame('0.000', $service->opening_reading);
            $this->assertTrue(Str::isUlid($service->id));
            Model::withoutEvents(fn () => $setup->saveWaterMeter($location, ['has_meter' => '1',
                'meter_number' => 'M-'.$location->id, 'opening_reading' => '0', 'opening_reading_date' => '2026-11-01']));
            $this->assertSame($service->id, $location->utilityServices()->sole()->id);
        }
        $this->assertSame(3, UtilityService::count());
        $this->assertSame(0, UtilityService::forManagement($this->ids['b'])->count());
    }

    public function test_location_water_meter_details_show_opening_readings_and_saved_charges(): void
    {
        $setup = app(UtilitySetupService::class);
        foreach ([Property::findOrFail($this->ids['pa']), Unit::findOrFail($this->ids['ua']), Room::findOrFail($this->ids['ra'])] as $location) {
            $render = fn () => \Illuminate\Support\Facades\Blade::render('<x-utilities.water-meter-details :location="$location" />', ['location' => $location]);
            $this->assertStringContainsString('Not installed', $render());
            Model::withoutEvents(fn () => $setup->saveWaterMeter($location, ['has_meter' => '1', 'meter_number' => 'METER-01',
                'opening_reading' => '0', 'opening_reading_date' => '2026-11-01']));
            $opening = $render();
            foreach (['METER-01', '0.000', '01/11/2026', 'Opening reading', 'Not calculated'] as $text) $this->assertStringContainsString($text, $opening);
            $record = Model::withoutEvents(fn () => $location->utilityServices()->sole()->records()->create([
                'type' => 'water', 'prev_reading' => '0', 'curr_reading' => '3.125', 'amount' => 1250, 'status' => 'confirmed',
                'prev_reading_date' => '2026-11-01', 'curr_reading_date' => '2026-11-08', 'period' => '2026-11-01',
            ]));
            foreach (['3.125', 'RM 12.50', '08/11/2026'] as $text) $this->assertStringContainsString($text, $render());
            Model::withoutEvents(fn () => $record->update(['amount' => 0]));
            $this->assertStringContainsString('RM 0.00', $render());
            Model::withoutEvents(fn () => $record->update(['status' => 'draft']));
            $this->assertStringContainsString('Not calculated', $render());
        }
    }

    public function test_water_meter_details_respect_utilities_permission_and_management_boundaries(): void
    {
        $location = Unit::findOrFail($this->ids['ua']);
        Model::withoutEvents(fn () => app(UtilitySetupService::class)->saveWaterMeter($location, ['has_meter' => '1', 'meter_number' => 'PRIVATE-METER']));
        $render = fn () => \Illuminate\Support\Facades\Blade::render('<x-utilities.water-meter-details :location="$location" />', ['location' => $location]);
        $this->assertStringContainsString('PRIVATE-METER', $render());
        $this->actingAs(User::findOrFail($this->ids['b']));
        $this->assertStringNotContainsString('Water Meter', $render());
        $this->actingAs(User::findOrFail($this->ids['a']));
        Gate::define('utilities.tab', fn () => false);
        $this->assertStringNotContainsString('PRIVATE-METER', $render());
    }

    public function test_water_meter_registration_requires_permission_account_ownership_and_a_complete_opening_pair(): void
    {
        $setup = app(UtilitySetupService::class);
        $location = Room::findOrFail($this->ids['ra']);
        foreach ([['has_meter' => '1', 'opening_reading' => '0'], ['has_meter' => '1', 'opening_reading_date' => '2026-11-01']] as $input) {
            try { $setup->saveWaterMeter($location, $input); $this->fail('Incomplete opening pair accepted.'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        try { $setup->saveWaterMeter(Room::findOrFail($this->ids['rb']), ['has_meter' => '1']); $this->fail('Foreign location accepted.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('water_meter', $e->errors()); }
        Gate::define('utilities.manage meters', fn () => false);
        try { $setup->saveWaterMeter($location, ['has_meter' => '1']); $this->fail('Permission denial bypassed.'); }
        catch (ValidationException $e) { $this->assertSame(['You have no permission.'], $e->errors()['water_meter']); }
        $this->assertSame(0, UtilityService::count());
    }

    public function test_registration_preserves_reading_history_and_can_restore_only_unused_meter_setup(): void
    {
        $setup = app(UtilitySetupService::class);
        $location = Room::findOrFail($this->ids['ra']);
        Model::withoutEvents(fn () => $setup->saveWaterMeter($location, ['has_meter' => '1']));
        $service = $location->utilityServices()->sole();
        Model::withoutEvents(fn () => $setup->saveWaterMeter($location, ['has_meter' => '0']));
        $this->assertTrue($service->fresh()->trashed());
        Model::withoutEvents(fn () => $setup->saveWaterMeter($location, ['has_meter' => '1', 'opening_reading' => '0', 'opening_reading_date' => '2026-11-01']));
        $this->assertSame($service->id, $location->utilityServices()->sole()->id);
        $this->insert('utilities', 'registered-reading', ['utility_service_id' => $service->id, 'type' => 'water', 'period' => '2026-11-01']);
        foreach ([['has_meter' => '0'], ['has_meter' => '1', 'opening_reading' => '99', 'opening_reading_date' => '2026-11-01']] as $input) {
            try { Model::withoutEvents(fn () => $setup->saveWaterMeter($location, $input)); $this->fail('History protection bypassed.'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertSame('0.000', $service->fresh()->opening_reading);
        $this->assertSame(1, $service->records()->count());
    }

    public function test_shared_water_meter_form_uses_existing_fields_and_preserves_saved_values(): void
    {
        $location = Room::findOrFail($this->ids['ra']);
        Model::withoutEvents(fn () => app(UtilitySetupService::class)->saveWaterMeter($location, ['has_meter' => '1',
            'meter_number' => 'ROOM-123', 'opening_reading' => '0', 'opening_reading_date' => '2026-11-01']));
        $html = \Illuminate\Support\Facades\Blade::render('<x-utilities.water-meter-fields :location="$location" />', compact('location'));
        foreach (['Water Meter', 'Has physical water meter?', 'Physical Meter Number', 'Opening Reading',
            'water_meter[has_meter]', 'water_meter[meter_number]', 'water_meter[opening_reading]', 'water_meter[opening_reading_date]', 'ROOM-123', 'DD/MM/YYYY'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        Gate::define('utilities.manage meters', fn () => false);
        $this->assertStringNotContainsString('water_meter[', \Illuminate\Support\Facades\Blade::render('<x-utilities.water-meter-fields />'));
    }

    public function test_utilities_hide_inactive_leasables_and_their_descendants_without_losing_history(): void
    {
        $this->initialize();
        $readings = app(UtilityReadingService::class);
        $manager = User::findOrFail($this->ids['a']);
        $initialRows = $readings->rows($manager, '2026-11');
        $this->assertCount(3, $initialRows);
        $roomRow = collect($initialRows)->firstWhere('locationType', 'room');
        foreach ([['rooms', 'ra', ' Inactive ', 2], ['units', 'ua', 'inactive', 1], ['properties', 'pa', 'Removed', 0]] as [$table, $key, $status, $remaining]) {
            foreach (['properties' => 'pa', 'units' => 'ua', 'rooms' => 'ra'] as $resetTable => $resetKey) {
                DB::table($resetTable)->where('id', $this->ids[$resetKey])->update(['status' => 'Vacant']);
            }
            DB::table($table)->where('id', $this->ids[$key])->update(['status' => $status]);
            try { $this->saveReading($this->readingInput($roomRow)); $this->fail('Inactive location accepted a reading.'); }
            catch (ValidationException $exception) { $this->assertArrayHasKey('readings', $exception->errors()); }
            $response = $this->get(route('admin.utilities.index'))->assertOk();
            $this->assertSame($remaining, $response->viewData('services')->total());
            $rows = $readings->rows($manager, '2026-11');
            $this->assertCount($remaining, $rows);
            $nodes = $readings->hierarchy($manager, $rows);
            $this->assertCount($remaining, $nodes);
        }
        $this->assertSame(3, UtilityService::forManagement($manager->id)->count());
        $this->assertSame(1, Utility::count());
        foreach (['properties' => 'pa', 'units' => 'ua', 'rooms' => 'ra'] as $table => $key) {
            DB::table($table)->where('id', $this->ids[$key])->update(['status' => 'Vacant']);
        }
        $this->assertCount(3, $readings->rows($manager, '2026-11'));
        $this->get(route('admin.utilities.index'))->assertOk()->assertViewHas('services', fn ($services) => $services->total() === 3);
    }

    private function initialize(string $manager = 'a', string $type = 'water'): array
    {
        return Model::withoutEvents(function () use ($manager, $type) {
            $manager = User::findOrFail($this->ids[$manager]);
            $setup = app(UtilitySetupService::class);
            $result = ['created' => 0, 'existing' => 0, 'review' => 0, 'linked' => 0];
            foreach ([Property::class, Unit::class, Room::class] as $model) {
                foreach ($setup->managedLocations($model, $manager) as $location) {
                    $service = $location->utilityServices()->withTrashed()->where('type', $type)->first();
                    if ($service) { $result['existing']++; continue; }
                    $service = $location->utilityServices()->create([
                        'management_user_id' => $manager->id, 'type' => $type,
                        'billing_method' => $type === 'indah_water' ? 'fixed' : 'metered',
                        'label' => class_basename($location).' '.($location->name ?? $location->unit_no ?? $location->room_no).' '.$type,
                        'status' => 'unconfirmed',
                    ]);
                    $result['created']++;
                    $leaseIds = \App\Models\Lease::where('leasable_type', $location->getMorphClass())->where('leasable_id', $location->id)->pluck('id');
                    $result['linked'] += DB::table('utilities')->whereNull('utility_service_id')->whereIn('lease_id', $leaseIds)->where('type', $type)->update(['utility_service_id' => $service->id]);
                }
            }
            return $result;
        });
    }

    private function service(string $location): UtilityService
    {
        return UtilityService::where('location_id', $this->ids[$location])->firstOrFail();
    }

    private function update(UtilityService $service, array $overrides): UtilityService
    {
        return Model::withoutEvents(fn () => app(UtilitySetupService::class)->update($service, [
            'label' => $service->label, 'billing_method' => $service->billing_method,
            'status' => 'unconfirmed', ...$overrides,
        ]));
    }

    public function test_migrations_preserve_legacy_values_and_existing_service_history(): void
    {
        $legacy = Utility::findOrFail($this->ids['legacy']);
        $this->assertSame('100.000', $legacy->prev_reading);
        $this->assertSame(2500, $legacy->amount);
        $this->assertSame('confirmed', $legacy->status);
        DB::table('utilities')->where('id', $legacy->id)->update(['updated_at' => '2026-01-01 00:00:00']);
        $result = $this->initialize();
        $this->assertSame(3, $result['created']);
        $this->assertSame(1, $result['linked']);
        $this->assertSame(3, UtilityService::count());
        $this->assertTrue(Str::isUlid($this->service('ua')->id));
        $this->assertSame('unconfirmed', $this->service('ua')->status);
        $this->assertNull($this->service('ua')->opening_reading);
        $this->assertNull($this->service('ra')->parent_service_id);
        $this->assertSame('ACCOUNT-a', $this->service('ra')->account_number);
        $this->assertSame($this->service('ua')->id, $legacy->fresh()->utility_service_id);
        $this->assertSame('2026-01-01 00:00:00', $legacy->fresh()->getRawOriginal('updated_at'));
        $this->assertSame(0, $this->initialize()['created']);
        $this->assertSame(1, Utility::count());
        $this->assertSame(0, UtilityService::forManagement($this->ids['b'])->count());
    }

    public function test_ambiguous_locations_are_not_claimed_and_fixed_services_have_no_opening_reading(): void
    {
        DB::table('properties')->where('id', $this->ids['pa'])->update(['created_by' => $this->ids['b']]);
        $result = $this->initialize();
        $this->assertNull(app(UtilitySetupService::class)->managerFor(Property::findOrFail($this->ids['pa'])));
        $this->assertSame(2, $result['created']);
        $this->initialize('b', 'indah_water');
        $this->assertSame('fixed', $this->service('ub')->billing_method);
        $this->assertNull($this->service('ub')->opening_reading);
    }

    public function test_setup_save_activates_without_opening_reading_and_shared_service_requires_valid_upstream(): void
    {
        $this->initialize(); $this->initialize('b');
        $unit = $this->service('ua');
        // The legacy history locks this service, so use the unused property meter as upstream.
        $property = $this->update($this->service('pa'), ['status' => 'active', 'opening_reading' => '0.125', 'opening_reading_date' => '2026-11-01']);
        $this->assertSame('0.125', $property->opening_reading);
        $room = $this->update($this->service('ra'), ['billing_method' => 'shared', 'parent_service_id' => $property->id, 'status' => 'active']);
        $this->assertSame('active', $room->status);
        foreach ([
            [$room, ['billing_method' => 'shared', 'parent_service_id' => $this->service('pb')->id]],
            [$property, ['parent_service_id' => $room->id]],
            [$property, ['status' => 'inactive']],
            [$property, ['status' => 'active', 'billing_method' => 'fixed']],
            [$unit, ['billing_method' => 'fixed']],
        ] as [$service, $changes]) {
            try { $this->update($service, $changes); $this->fail('Unsafe setup was accepted'); }
            catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        }
        $this->assertSame('active', $this->update($this->service('ub'), [])->status);
        // Previously missing legacy setup can be completed once, then remains immutable.
        $unit = $this->update($unit, ['opening_reading' => '2', 'opening_reading_date' => '2026-11-01']);
        $this->expectException(ValidationException::class);
        $this->update($unit, ['opening_reading' => '3', 'opening_reading_date' => '2026-11-01']);
    }

    public function test_history_survives_lease_deletion_and_decimal_nullable_records_are_supported(): void
    {
        $this->initialize();
        DB::table('leases')->where('id', $this->ids['lease'])->delete();
        $legacy = Utility::findOrFail($this->ids['legacy']);
        $this->assertNull($legacy->lease_id);
        $this->assertSame(2500, $legacy->amount);
        $reading = Model::withoutEvents(fn () => $this->service('ra')->records()->create([
            'type' => 'water', 'status' => 'draft', 'prev_reading' => '12.125', 'curr_reading' => null,
            'amount' => null, 'period' => '2026-11-01',
        ]));
        $this->assertTrue(Str::isUlid($reading->id));
        $this->assertSame('12.125', $reading->fresh()->prev_reading);
        $this->assertNull($reading->fresh()->curr_reading);
        $this->assertNull($reading->fresh()->amount);
    }

    public function test_index_uses_real_services_with_blank_readings_and_denies_cross_account_edits(): void
    {
        $this->initialize(); $this->initialize('b');
        $response = $this->get(route('admin.utilities.index'));
        $response->assertOk()->assertSee('Property a')->assertDontSee('Property b')->assertSee('ACCOUNT-a');
        $response->assertSee('data-history-url="'.e(route('admin.utilities.history', ['management_user_id' => $this->ids['a']])).'"', false)
            ->assertDontSee('@js(', false);
        $this->assertSame(3, $response->viewData('services')->total());
        $this->get(route('admin.utilities.edit', $this->service('ub')))->assertNotFound();
        $this->patch(route('admin.utilities.update', $this->service('ub')), ['label' => 'Changed'])->assertNotFound();
        $this->assertNotSame('Changed', $this->service('ub')->label);
        // Supplying another manager ID cannot switch a regular user's account.
        $response = $this->get(route('admin.utilities.index', ['management_user_id' => $this->ids['b']]));
        $this->assertSame($this->ids['a'], $response->viewData('manager')->id);
        $this->get(route('admin.utilities.history'))->assertOk()->assertSee('25.00');
    }

    public function test_super_admin_must_select_account_and_initialization_route_is_removed(): void
    {
        $this->initialize();
        Gate::define('super-admin', fn () => true);
        $response = $this->get(route('admin.utilities.index'));
        $response->assertOk();
        $this->assertSame(0, $response->viewData('services')->total());
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.utilities.initialize'));

    }

    public function test_setup_form_and_mutation_routes_save_only_to_the_effective_management_account(): void
    {
        $this->initialize();
        $this->assertSame(3, UtilityService::forManagement($this->ids['a'])->count());
        $this->assertSame(0, UtilityService::forManagement($this->ids['b'])->count());
        $service = $this->service('ra');
        $this->get(route('admin.utilities.edit', $service))->assertOk()->assertSee('Opening reading');
        Model::withoutEvents(fn () => $this->patch(route('admin.utilities.update', $service), [
            'management_user_id' => $this->ids['b'], 'label' => 'Room water meter',
            'billing_method' => 'metered', 'status' => 'active', 'meter_number' => 'ROOM-001',
            'opening_reading' => '0', 'opening_reading_date' => '2026-11-01',
        ]))->assertRedirect()->assertSessionHas('success', 'Utility service updated.');
        $this->assertSame('0.000', $service->fresh()->opening_reading);
        $this->assertSame('active', $service->fresh()->status);
        $this->assertSame($this->ids['a'], $service->fresh()->management_user_id);
    }

    public function test_rollback_refuses_to_discard_service_linked_history(): void
    {
        $this->initialize();
        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2026_11_13_000002_extend_utilities_reading_history.php'))->down();
    }

    public function test_validation_errors_redirect_with_session_errors_and_preserve_form_input(): void
    {
        $this->initialize();
        $service = $this->service('ra');
        $editUrl = route('admin.utilities.edit', $service);
        $this->from($editUrl)->patch(route('admin.utilities.update', $service), [
            'label' => 'Room water meter', 'billing_method' => 'metered', 'status' => 'active', 'opening_reading' => '-1',
        ])->assertRedirect($editUrl)->assertSessionHasErrors('opening_reading')
            ->assertSessionHasInput('label', 'Room water meter');
        $this->assertSame('unconfirmed', $service->fresh()->status);
    }

    public function test_current_tenant_links_use_the_tenant_record_id(): void
    {
        $this->insert('users', 'tenant-login', ['name' => 'Current Tenant', 'role' => User::ROLE_TENANT]);
        $this->insert('tenants', 'tenant-record', ['user_id' => $this->ids['tenant-login'], 'created_by' => $this->ids['a']]);
        DB::table('leases')->where('id', $this->ids['lease'])->update(['tenant_id' => $this->ids['tenant-record']]);
        $this->initialize();
        $this->get(route('admin.utilities.index'))->assertOk()->assertSee('Current Tenant')
            ->assertSee('href="'.e(route('admin.tenants.show', $this->ids['tenant-record'])).'"', false)
            ->assertDontSee('href="'.e(route('admin.tenants.show', $this->ids['tenant-login'])).'"', false);
    }

    public function test_service_search_matches_displayed_setup_fields_and_current_tenants(): void
    {
        $this->initialize();
        $this->insert('users', 'current-login', ['name' => 'Current Resident', 'role' => User::ROLE_TENANT]);
        $this->insert('tenants', 'current-tenant', ['user_id' => $this->ids['current-login'], 'created_by' => $this->ids['a']]);
        DB::table('leases')->where('id', $this->ids['lease'])->update(['tenant_id' => $this->ids['current-tenant']]);
        DB::table('utility_services')->where('id', $this->service('pa')->id)->update(['label' => 'Upstream supply']);
        DB::table('utility_services')->where('id', $this->service('ra')->id)->update([
            'parent_service_id' => $this->service('pa')->id, 'meter_number' => 'METER-XYZ', 'label' => 'Bedroom supply',
        ]);
        foreach ([
            'Property a' => ['pa', 'ua', 'ra'], 'Unit a' => ['ua', 'ra'], 'Room a' => ['ra'],
            'ACCOUNT-a' => ['ua', 'ra'], 'METER-XYZ' => ['ra'], 'Bedroom supply' => ['ra'],
            'Upstream supply' => ['pa', 'ra'], 'Current Resident' => ['ua'],
            'Unconfirmed' => ['pa', 'ua', 'ra'], 'Metered' => ['pa', 'ua', 'ra'],
        ] as $term => $locations) {
            $actual = UtilityService::forManagement($this->ids['a'])->where('type', 'water')->search($term)->pluck('location_id')->all();
            $this->assertEqualsCanonicalizing(array_map(fn ($key) => $this->ids[$key], $locations), $actual, $term);
        }
        DB::table('leases')->where('id', $this->ids['lease'])->update(['checked_out_at' => '2026-11-01']);
        $this->assertSame(0, UtilityService::forManagement($this->ids['a'])->search('Current Resident')->count());
    }

    public function test_reading_search_uses_only_the_latest_displayed_record_and_formats_numbers_and_dates(): void
    {
        $this->initialize();
        $this->insert('utilities', 'latest-reading', [
            'utility_service_id' => $this->service('ua')->id, 'lease_id' => $this->ids['lease'], 'type' => 'water',
            'prev_reading' => '100.125', 'curr_reading' => '111.375', 'amount' => 123456,
            'created_at' => '2026-11-13 09:08:00', 'updated_at' => '2026-11-14 10:09:00',
        ]);
        foreach (['100.125', '111.375', '11.250', 'RM 1,234.56', '1,234.56', '13/11/2026', '13/11/2026 09:08', '14/11/2026 10:09'] as $term) {
            $this->assertSame([$this->ids['ua']], UtilityService::forManagement($this->ids['a'])->where('type', 'water')
                ->search($term)->pluck('location_id')->all(), $term);
        }
        $this->assertSame(0, UtilityService::forManagement($this->ids['a'])->search('25.00')->count(), 'Old amounts are not displayed');
        $this->insert('utilities', 'zero-reading', [
            'utility_service_id' => $this->service('ra')->id, 'type' => 'water', 'prev_reading' => 0, 'curr_reading' => 0, 'amount' => 0,
        ]);
        $this->assertSame([$this->ids['ra']], UtilityService::forManagement($this->ids['a'])->search('0.000')->pluck('location_id')->all());
        $this->assertSame('100.000', Utility::search('RM 25.00')->firstOrFail()->prev_reading);
    }

    public function test_search_never_bypasses_management_or_utility_type_and_index_uses_shared_search(): void
    {
        $this->initialize(); $this->initialize('b'); $this->initialize('a', 'gas');
        DB::table('utility_services')->where('management_user_id', $this->ids['b'])->update(['meter_number' => 'FOREIGN-ONLY']);
        DB::table('utility_services')->where('management_user_id', $this->ids['a'])->where('type', 'gas')->update(['meter_number' => 'GAS-ONLY']);
        DB::table('units')->where('id', $this->ids['ua'])->update(['electricity_acc_no' => 'HIDDEN-ELECTRICITY-ACCOUNT']);
        foreach (['FOREIGN-ONLY', 'GAS-ONLY', 'HIDDEN-ELECTRICITY-ACCOUNT', "' OR 1=1 --"] as $term) {
            $response = $this->get(route('admin.utilities.index', ['search' => $term, 'type' => 'water']));
            $response->assertOk()->assertSee('No services match your search.');
            $this->assertSame(0, $response->viewData('services')->total());
        }
        $response = $this->get(route('admin.utilities.index', ['search' => 'Room a', 'type' => 'water']));
        $response->assertOk()->assertSee('Search locations, tenants, meters, readings, amounts...');
        $this->assertSame(1, $response->viewData('services')->total());
        $this->assertSame('water', $response->viewData('type'));
        $this->assertStringContainsString('search=Room', $response->viewData('services')->url(2));
        $this->assertSame(3, UtilityService::forManagement($this->ids['a'])->where('type', 'water')->search('  ')->count());
    }

    public function test_advanced_location_tenant_and_service_filters_combine_with_and(): void
    {
        $this->initialize(); $this->initialize('b');
        $this->insert('users', 'advanced-login', ['name' => 'Alice Resident', 'role' => User::ROLE_TENANT]);
        $this->insert('tenants', 'advanced-tenant', ['user_id' => $this->ids['advanced-login'], 'created_by' => $this->ids['a']]);
        $this->insert('leases', 'room-lease', ['tenant_id' => $this->ids['advanced-tenant'], 'leasable_type' => Room::class,
            'leasable_id' => $this->ids['ra'], 'status' => 'New', 'start_date' => '2020-01-01']);
        DB::table('utility_services')->where('id', $this->service('pa')->id)->update(['label' => 'Main property water']);
        DB::table('utility_services')->where('id', $this->service('ra')->id)->update([
            'label' => 'Bedroom water', 'meter_number' => 'ROOM-A-123', 'status' => 'active',
            'parent_service_id' => $this->service('pa')->id,
        ]);
        $filters = ['type' => 'water', 'utility_property' => 'Property a', 'utility_unit' => 'Unit a',
            'utility_room' => 'Room a', 'utility_tenant' => 'Alice', 'utility_location_type' => 'room',
            'utility_status' => 'active', 'utility_method' => 'metered', 'utility_account' => 'ACCOUNT-a',
            'utility_meter' => 'ROOM-A', 'utility_label' => 'Bedroom', 'utility_parent' => 'Main property',
            'utility_readings' => 'missing'];
        $response = $this->get(route('admin.utilities.index', $filters));
        $response->assertOk()->assertSee('Advanced Filters')->assertSee('Apply Filters')->assertSee('Alice Resident');
        $this->assertSame([$this->service('ra')->id], $response->viewData('services')->pluck('id')->all());
        $this->assertStringContainsString('utility_tenant=Alice', $response->viewData('services')->url(2));
        foreach (['utility_room' => 'Other room', 'utility_tenant' => 'Other tenant', 'utility_property' => 'Property b', 'utility_status' => 'inactive'] as $field => $value) {
            $response = $this->get(route('admin.utilities.index', array_replace($filters, [$field => $value])));
            $response->assertOk()->assertSee('No services match your filters.');
            $this->assertSame(0, $response->viewData('services')->total());
        }
        $this->assertSame(0, UtilityService::forManagement($this->ids['a'])->where('type', 'gas')->applyFilters($filters)->count());
    }

    public function test_advanced_ranges_use_latest_reading_include_zero_and_allow_open_date_ranges(): void
    {
        $this->initialize();
        $this->insert('utilities', 'range-reading', [
            'utility_service_id' => $this->service('ua')->id, 'lease_id' => $this->ids['lease'], 'type' => 'water',
            'prev_reading' => '20.125', 'curr_reading' => '30.375', 'amount' => 7525,
            'created_at' => '2026-11-15 23:59:59', 'updated_at' => '2026-11-16 12:00:00',
        ]);
        $filters = ['utility_previous_min' => '20', 'utility_previous_max' => '21',
            'utility_current_min' => '30', 'utility_current_max' => '31',
            'utility_usage_min' => '10.25', 'utility_usage_max' => '10.25',
            'utility_amount_min' => '75.25', 'utility_amount_max' => '75.25',
            'utility_recorded_from' => '2026-11-15', 'utility_recorded_to' => '2026-11-15',
            'utility_updated_from' => '2026-11-16', 'utility_updated_to' => '2026-11-16', 'utility_readings' => 'recorded'];
        $response = $this->get(route('admin.utilities.index', $filters));
        $response->assertOk();
        $this->assertSame([$this->service('ua')->id], $response->viewData('services')->pluck('id')->all());
        // The older row satisfies this range, but the displayed latest row does not.
        $this->assertSame(0, UtilityService::forManagement($this->ids['a'])->applyFilters(['utility_previous_min' => '99'])->count());
        $this->assertSame(1, UtilityService::forManagement($this->ids['a'])->applyFilters(['utility_recorded_to' => '2026-11-15'])->count());
        $this->insert('utilities', 'range-zero', ['utility_service_id' => $this->service('ra')->id,
            'type' => 'water', 'prev_reading' => 0, 'curr_reading' => 0, 'amount' => 0,
            'created_at' => '2026-12-01 00:00:00', 'updated_at' => '2026-12-01 00:00:00']);
        $this->assertSame([$this->service('ra')->id], UtilityService::forManagement($this->ids['a'])
            ->applyFilters(['utility_usage_min' => '0', 'utility_usage_max' => '0', 'utility_amount_max' => '0'])->pluck('id')->all());
        $response = $this->get(route('admin.utilities.index', ['utility_updated_to' => '2026-11-16']));
        $response->assertOk();
        $this->assertSame(1, $response->viewData('services')->total());
    }

    public function test_advanced_filters_validate_values_and_preserve_session_errors(): void
    {
        foreach ([['utility_status' => 'unknown'], ['utility_location_type' => 'users'], ['utility_method' => ['metered']],
            ['utility_amount_min' => '10.123'], ['utility_usage_min' => '12', 'utility_usage_max' => '2'],
            ['utility_recorded_from' => '2026-11-16', 'utility_recorded_to' => '2026-11-15'],
            ['utility_tenant' => ['invalid']]] as $filters) {
            $this->from(route('admin.utilities.index'))->get(route('admin.utilities.index', $filters))
                ->assertRedirect(route('admin.utilities.index'))->assertSessionHasErrors();
        }
    }

    public function test_deleting_a_service_hides_setup_but_preserves_history_and_initialization_does_not_recreate_it(): void
    {
        $this->initialize();
        $service = $this->service('ua');
        Model::withoutEvents(fn () => $this->delete(route('admin.utilities.destroy', $service)))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertNull(UtilityService::find($service->id));
        $this->assertTrue(UtilityService::withTrashed()->findOrFail($service->id)->trashed());
        $this->assertSame($service->id, Utility::findOrFail($this->ids['legacy'])->utilityService->id);
        $this->get(route('admin.utilities.history'))->assertOk()->assertSee('25.00');
        $this->assertSame(0, $this->initialize()['created']);
        $this->assertSame(2, UtilityService::count());
        $this->assertSame(3, UtilityService::withTrashed()->count());
        $this->assertSame(1, Utility::count());
        $this->get(route('admin.utilities.index'))->assertOk()->assertSee('Action')->assertSee('Delete utility service')->assertDontSee('Status / Setup');
    }

    public function test_service_deletion_enforces_account_permissions_and_parent_connections(): void
    {
        $this->initialize(); $this->initialize('b');
        $this->delete(route('admin.utilities.destroy', $this->service('ub')))->assertNotFound();
        $parent = $this->service('pa');
        DB::table('utility_services')->where('id', $this->service('ra')->id)->update(['parent_service_id' => $parent->id]);
        $this->from(route('admin.utilities.index'))->delete(route('admin.utilities.destroy', $parent))
            ->assertRedirect(route('admin.utilities.index'))->assertSessionHasErrors('utility_service');
        $this->assertNotNull(UtilityService::find($parent->id));
        Gate::define('owner-admin', fn () => false);
        Gate::define('utilities.manage meters', fn () => false);
        $this->delete(route('admin.utilities.destroy', $this->service('ua')))->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        $this->assertSame(6, UtilityService::count());
    }

    public function test_opening_reading_is_displayed_and_searchable_until_reading_history_exists(): void
    {
        $this->initialize();
        $service = $this->update($this->service('ra'), [
            'opening_reading' => '987.625', 'opening_reading_date' => '2026-11-01', 'status' => 'active',
        ]);
        $this->get(route('admin.utilities.index'))->assertOk()->assertSee('987.625');
        $this->assertSame(1, Utility::count(), 'Saving the opening reading must not create usage history');
        $this->assertSame([$service->id], UtilityService::forManagement($this->ids['a'])->search('987.625')->pluck('id')->all());
        $this->assertSame([$service->id], UtilityService::forManagement($this->ids['a'])->applyFilters([
            'utility_previous_min' => '987', 'utility_previous_max' => '988',
        ])->pluck('id')->all());
        $this->assertSame(0, UtilityService::forManagement($this->ids['a'])->applyFilters([
            'utility_previous_min' => '987', 'utility_current_min' => '0',
        ])->count());
        $this->update($service, ['opening_reading' => '0', 'opening_reading_date' => '2026-11-01']);
        $this->get(route('admin.utilities.index'))->assertOk()->assertSee('0.000');
        $this->insert('utilities', 'opening-followup', ['utility_service_id' => $service->id, 'type' => 'water',
            'prev_reading' => '1.250', 'curr_reading' => '2.500', 'amount' => 125]);
        $this->get(route('admin.utilities.index'))->assertOk()->assertSee('1.250');
        $this->assertSame(0, UtilityService::forManagement($this->ids['a'])->search('0.000')->count(), 'History takes precedence over the opening value');
    }

    private function readingRows(string $month = '2026-11', string $manager = 'a'): array
    {
        return app(UtilityReadingService::class)->rows(User::findOrFail($this->ids[$manager]), $month);
    }

    private function readingRow(string $month = '2026-11', string $manager = 'a', string $location = 'ra'): array
    {
        return collect($this->readingRows($month, $manager))->firstWhere('id', $this->service($location)->id);
    }

    private function enableWaterEntry(): array
    {
        Carbon::setTestNow('2026-12-31 12:00:00');
        $this->initialize();
        $this->update($this->service('ra'), ['status' => 'active', 'opening_reading' => '10.125', 'opening_reading_date' => '2026-11-01']);
        return $this->readingRow();
    }

    private function readingInput(array $row, array $changes = [], string $mode = 'draft'): array
    {
        return ['flat_override_confirmed' => true, 'month' => '2026-11', 'reading_date' => '2026-11-13', 'mode' => $mode, 'readings' => json_encode([[
            'id' => $row['id'], 'revision' => $row['revision'], 'previous' => $row['previous'],
            'previousDate' => $row['previousDate'], 'current' => '12.470', 'rate' => '2.50', ...$changes,
        ]])];
    }

    private function saveReading(array $input): array
    {
        return Model::withoutEvents(fn () => app(UtilityReadingService::class)->save(User::findOrFail($this->ids['a']), $input));
    }

    public function test_water_entry_page_uses_real_active_meters_and_session_save_flow(): void
    {
        $row = $this->enableWaterEntry();
        $this->initialize('b');
        $this->update($this->service('rb'), ['status' => 'active', 'opening_reading' => '5', 'opening_reading_date' => '2026-11-01']);
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11']));
        $response->assertOk()->assertSee('Water Reading Entry')->assertSee('Save Readings')->assertDontSee('Save Draft')->assertDontSee('Confirm Readings');
        $this->assertEqualsCanonicalizing([$this->service('pa')->id, $this->service('ua')->id, $row['id']], array_column($response->viewData('readingRows'), 'id'));
        $this->assertSame('10.125', $row['previous']);
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.save'), $this->readingInput($row)))
            ->assertRedirect()->assertSessionHas('success');
        $record = $this->service('ra')->records()->firstOrFail();
        $this->assertSame('confirmed', $record->status);
        $this->assertSame(586, $record->amount);
        $this->assertSame('2.345', $record->calculation_details['usage']);
        $this->assertNull($record->invoice_item_id);
        $this->assertTrue(Str::isUlid($record->id));
        Gate::define('owner-admin', fn () => false);
        Gate::define('utilities.manage meters', fn () => false);
        Gate::define('utilities.enter readings', fn () => false);
        $this->get(route('admin.utilities.readings'))->assertViewIs('errors.403');
        $this->post(route('admin.utilities.readings.save'), $this->readingInput($row))->assertRedirect()->assertSessionHas('error', 'You have no permission.');
    }

    public function test_saved_readings_update_without_duplicates_carry_forward_and_lock_after_later_save(): void
    {
        $row = $this->enableWaterEntry();
        $this->saveReading($this->readingInput($row));
        $this->assertFalse($this->readingRow()['locked']);
        $this->saveReading($this->readingInput($this->readingRow(), ['current' => '13.000']));
        $this->assertSame(1, $this->service('ra')->records()->count());
        $this->assertSame('2026-11-01', $this->readingRow()['previousDate']);
        $next = $this->readingRow('2026-12');
        $this->assertSame('13.000', $next['previous']);
        $this->assertSame('2026-11-13', $next['previousDate']);
        $this->assertSame('', $next['current']);
        $input = $this->readingInput($next, ['current' => '15']);
        $input['month'] = '2026-12'; $input['reading_date'] = '2026-12-13';
        $this->saveReading($input);
        $this->assertTrue($this->readingRow()['locked']);
        $this->expectException(ValidationException::class);
        $this->saveReading($this->readingInput($this->readingRow(), ['current' => '99']));
    }

    public function test_lower_actual_reading_is_saved_for_review_without_a_billable_charge(): void
    {
        $row = $this->enableWaterEntry();
        $this->saveReading($this->readingInput($row, ['current' => '9']));
        $record = $this->service('ra')->records()->firstOrFail();
        $this->assertSame('9.000', $record->curr_reading);
        $this->assertSame('10.125', $record->prev_reading);
        $this->assertNull($record->consumption);
        $this->assertNull($record->amount);
        $this->assertStringContainsString('lower than previous', $record->calculation_details['review_reason']);
        $preview = app(UtilityInvoiceService::class)->preview(User::findOrFail($this->ids['a']), '2026-11');
        $this->assertSame([], $preview['entries']);
        $this->assertStringContainsString('lower than previous', $preview['skipped'][0]['reason']);
        try {
            app(UtilityInvoiceService::class)->generate(User::findOrFail($this->ids['a']), '2026-11', $preview['digest']);
            $this->fail('An unresolved lower reading must not generate an invoice');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoices', $exception->errors());
        }
        $this->saveReading($this->readingInput($this->readingRow(), ['current' => '12.470']));
        $record->refresh();
        $this->assertSame('2.345', $record->consumption);
        $this->assertSame(586, $record->amount);
        $this->assertArrayNotHasKey('review_reason', $record->calculation_details);
    }

    public function test_recalculation_repairs_legacy_saved_details_and_clears_the_repair_flag(): void
    {
        $row = $this->enableWaterEntry();
        $this->assertFalse($row['calculationNeedsRepair']);
        $this->saveReading($this->readingInput($row, ['rate' => '']));
        $record = $this->service('ra')->records()->sole();
        DB::table('utilities')->where('id', $record->id)->update(['calculation_details' => null]);
        $legacy = $this->readingRow();
        $this->assertTrue($legacy['calculationNeedsRepair']);
        $this->assertNull($legacy['savedTariff']);
        $this->assertFalse($legacy['locked']);
        $this->saveReading($this->readingInput($legacy, ['rate' => '', 'reprice' => true]));
        $repaired = $this->readingRow();
        $this->assertFalse($repaired['calculationNeedsRepair']);
        $this->assertTrue($repaired['savedTariff']['billable']);
        $this->assertSame(750, $record->fresh()->amount);
    }

    public function test_water_reading_validation_and_stale_saves_do_not_overwrite_existing_records(): void
    {
        $row = $this->enableWaterEntry();
        foreach ([['previous' => '1'], ['previousDate' => '2026-11-14'], ['rate' => '2.1234567'], ['current' => '1e3']] as $changes) {
            try { $this->saveReading($this->readingInput($row, $changes)); $this->fail('Invalid reading accepted'); }
            catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        }
        $this->saveReading($this->readingInput($row));
        try { $this->saveReading($this->readingInput($row, ['current' => '30'])); $this->fail('Stale draft accepted'); }
        catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        $this->assertSame('12.470', $this->service('ra')->records()->firstOrFail()->curr_reading);
        $this->assertSame(1, $this->service('ra')->records()->count());
    }

    public function test_water_readings_reject_other_accounts_atomically_and_calculate_zero_and_half_sen(): void
    {
        $row = $this->enableWaterEntry();
        $this->initialize('b');
        $this->update($this->service('rb'), ['status' => 'active', 'opening_reading' => '1', 'opening_reading_date' => '2026-11-01']);
        $foreign = $this->readingRow('2026-11', 'b', 'rb');
        $input = $this->readingInput($row);
        $input['readings'] = json_encode([...json_decode($input['readings'], true), ...json_decode($this->readingInput($foreign)['readings'], true)]);
        try { $this->saveReading($input); $this->fail('Foreign meter accepted'); }
        catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        $this->assertSame(0, $this->service('ra')->records()->count(), 'The entire submission must roll back');
        $readingService = app(UtilityReadingService::class);
        $this->assertSame(['usage' => '0.001', 'amount' => 1], $readingService->calculate('0', '0.001', '5'));
        $this->assertSame(['usage' => '0.000', 'amount' => 0], $readingService->calculate('10', '10', '2.5'));
        $this->saveReading($this->readingInput($row, ['current' => '10.125', 'rate' => '0'], 'confirm'));
        $this->assertSame(0, $this->service('ra')->records()->firstOrFail()->amount);
    }

    public function test_water_entry_does_not_rewrite_invoiced_rows_or_periods_before_later_confirmed_readings(): void
    {
        $row = $this->enableWaterEntry();
        $this->saveReading($this->readingInput($row));
        $this->insert('invoice_items', 'water-item', []);
        DB::table('utilities')->where('utility_service_id', $row['id'])->update(['invoice_item_id' => $this->ids['water-item']]);
        $this->assertTrue($this->readingRow()['locked']);
        try { $this->saveReading($this->readingInput($row)); $this->fail('Invoiced draft overwritten'); }
        catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
        DB::table('utilities')->where('utility_service_id', $row['id'])->update(['invoice_item_id' => null]);
        $next = $this->readingRow('2026-12');
        $input = $this->readingInput($next, [], 'confirm');
        $input['month'] = '2026-12'; $input['reading_date'] = '2026-12-13';
        $this->saveReading($input);
        try { $this->saveReading($this->readingInput($this->readingRow())); $this->fail('Backdated edit accepted'); }
        catch (ValidationException $exception) { $this->assertStringContainsString('later reading', $exception->getMessage()); }
    }

    public function test_water_lease_assignment_requires_one_lease_covering_the_whole_interval(): void
    {
        $row = $this->enableWaterEntry();
        $this->insert('leases', 'covering-water-lease', ['leasable_type' => Room::class, 'leasable_id' => $this->ids['ra'],
            'status' => 'New', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $this->saveReading($this->readingInput($row));
        $this->assertSame($this->ids['covering-water-lease'], $this->service('ra')->records()->firstOrFail()->lease_id);
        $this->insert('leases', 'partial-water-lease', ['leasable_type' => Room::class, 'leasable_id' => $this->ids['ra'],
            'status' => 'Check Out', 'start_date' => '2026-10-01', 'end_date' => '2026-11-05', 'checked_out_at' => '2026-11-05']);
        $this->saveReading($this->readingInput($this->readingRow()));
        $record = $this->service('ra')->records()->firstOrFail();
        $this->assertNull($record->lease_id);
        $this->assertSame('allocation_required', $record->calculation_details['lease_resolution']);
    }

    public function test_reading_page_lists_all_managed_properties_units_and_only_metered_rooms_in_order(): void
    {
        $this->enableWaterEntry(); $this->initialize('b');
        $this->insert('units', 'no-meter-unit', ['unit_no' => 'A-02', 'property_id' => $this->ids['pa'],
            'owner_id' => $this->ids['a'], 'created_by' => $this->ids['a']]);
        $this->insert('rooms', 'no-meter-room', ['room_no' => 'Hidden room', 'unit_id' => $this->ids['no-meter-unit'],
            'owner_id' => $this->ids['a'], 'created_by' => $this->ids['a']]);
        $this->insert('properties', 'empty-property', ['name' => 'Z Empty property',
            'owner_id' => $this->ids['a'], 'created_by' => $this->ids['a']]);
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'search' => 'ignored-old-filter']));
        $response->assertOk();
        $nodes = $response->viewData('readingHierarchy');
        $this->assertSame(['property-'.$this->ids['pa'], 'unit-'.$this->ids['no-meter-unit'],
            'unit-'.$this->ids['ua'], 'room-'.$this->ids['ra'], 'property-'.$this->ids['empty-property']], array_column($nodes, 'key'));
        $this->assertSame([0, 1, 1, 2, 0], array_column($nodes, 'level'));
        $this->assertNull($nodes[1]['rowId']);
        $this->assertNull($nodes[4]['rowId']);
        $this->assertSame($this->service('ra')->id, $nodes[3]['rowId']);
        $response->assertDontSee('Location / Meter')->assertDontSee('Search property, unit, room or meter...');
        Model::withoutEvents(fn () => app(UtilitySetupService::class)->delete($this->service('ra')));
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11']));
        $this->assertNotContains('room-'.$this->ids['ra'], array_column($response->viewData('readingHierarchy'), 'key'));
    }

    public function test_month_picker_renders_valid_change_handler_and_loads_selected_month_readings(): void
    {
        $row = $this->enableWaterEntry();
        $this->saveReading($this->readingInput($row, [], 'confirm'));
        $november = $this->get(route('admin.utilities.readings', ['month' => '2026-11']));
        $november->assertOk()->assertSee('data-current-month="2026-11"', false)
            ->assertSee('$el.dataset.currentMonth', false)->assertDontSee('@js($month)', false)
            ->assertDontSee('Load Month');
        $novemberRow = collect($november->viewData('readingRows'))->firstWhere('id', $row['id']);
        $this->assertFalse($novemberRow['locked']);
        $this->assertSame('12.470', $novemberRow['current']);
        $december = $this->get(route('admin.utilities.readings', ['month' => '2026-12']));
        $december->assertOk()->assertSee('data-current-month="2026-12"', false);
        $decemberRow = collect($december->viewData('readingRows'))->firstWhere('id', $row['id']);
        $this->assertFalse($decemberRow['locked']);
        $this->assertSame('12.470', $decemberRow['previous']);
        $this->assertSame('', $decemberRow['current']);
    }

    public function test_measured_previous_dates_remain_text_and_cannot_be_reinterpreted(): void
    {
        $row = $this->enableWaterEntry();
        $this->saveReading($this->readingInput($row));
        $service = $this->service('ra');
        $context = ['return_to' => 'readings', 'month' => '2026-11'];
        $response = $this->get(route('admin.utilities.edit', ['utilityService' => $service->id, ...$context]));
        $response->assertOk()->assertSee('Previous reading date for 11/2026')->assertDontSee('name="previous_reading_date"', false);
        $payload = [...$context, 'label' => $service->label, 'billing_method' => 'metered', 'status' => 'active',
            'opening_reading' => $service->opening_reading, 'opening_reading_date' => '2026-11-01',
            'previous_reading_date' => '2026-11-02', 'reading_revision' => $response->viewData('readingRow')['revision']];
        Model::withoutEvents(fn () => $this->patch(route('admin.utilities.update', $service), $payload))
            ->assertRedirect()->assertSessionHasErrors('previous_reading_date');
        $this->assertSame('2026-11-01', $this->readingRow()['previousDate']);
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk()->assertDontSee('meter-previous-date');
    }

    public function test_bulk_meter_delete_preserves_history_and_pending_readings_and_returns_to_month(): void
    {
        $row = $this->enableWaterEntry();
        $this->saveReading($this->readingInput($row));
        $room = $this->service('ra'); $unit = $this->service('ua');
        $historyCount = Utility::count();
        $pending = $this->readingInput($this->readingRow('2026-11', 'a', 'pa'), [
            'previous' => '1', 'previousDate' => '2026-11-01', 'current' => '2',
        ])['readings'];
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk()
            ->assertSee('Select all water meters')->assertSee('Delete Selected');
        Model::withoutEvents(fn () => $this->delete(route('admin.utilities.readings.meters.delete'), [
            'month' => '2026-11', 'selected_services' => json_encode([$room->id, $unit->id]),
            'readings' => $pending, 'reading_date' => '2026-11-13',
        ]))->assertRedirect(route('admin.utilities.readings', ['management_user_id' => $this->ids['a'], 'month' => '2026-11']))
            ->assertSessionHas('success')->assertSessionHasInput('readings', $pending);
        $this->assertTrue($room->fresh()->trashed());
        $this->assertTrue($unit->fresh()->trashed());
        $this->assertSame($historyCount, Utility::count());
        $this->assertSame(1, DB::table('rooms')->where('id', $this->ids['ra'])->count());
        $this->assertSame(0, $this->service('pa')->records()->count());
    }

    public function test_bulk_meter_delete_rejects_foreign_ids_and_invalid_selection_atomically(): void
    {
        $this->initialize(); $this->initialize('b');
        foreach ([[$this->service('ra')->id, $this->service('rb')->id], [], [$this->service('ra')->id, $this->service('ra')->id]] as $ids) {
            Model::withoutEvents(fn () => $this->from(route('admin.utilities.readings'))->delete(route('admin.utilities.readings.meters.delete'), [
                'month' => '2026-11', 'selected_services' => json_encode($ids),
            ]))->assertRedirect()->assertSessionHasErrors();
            $this->assertFalse($this->service('ra')->trashed());
            $this->assertFalse($this->service('rb')->trashed());
        }
        Gate::define('owner-admin', fn () => false); Gate::define('utilities.manage meters', fn () => false);
        $this->delete(route('admin.utilities.readings.meters.delete'), ['month' => '2026-11',
            'selected_services' => json_encode([$this->service('ra')->id])])->assertRedirect()->assertSessionHas('error', 'You have no permission.');
    }

    public function test_bulk_meter_delete_requires_connected_children_and_can_delete_selected_family_together(): void
    {
        $this->initialize();
        $parent = $this->update($this->service('pa'), []);
        $child = $this->update($this->service('ra'), ['parent_service_id' => $parent->id]);
        $url = route('admin.utilities.readings.meters.delete');
        Model::withoutEvents(fn () => $this->delete($url, ['month' => '2026-11',
            'selected_services' => json_encode([$parent->id])]))->assertSessionHasErrors('selected_services');
        $this->assertFalse($parent->fresh()->trashed());
        $this->assertFalse($child->fresh()->trashed());
        Model::withoutEvents(fn () => $this->delete($url, ['month' => '2026-11',
            'selected_services' => json_encode([$parent->id, $child->id])]))->assertSessionHas('success');
        $this->assertTrue($parent->fresh()->trashed());
        $this->assertTrue($child->fresh()->trashed());
    }

    public function test_nullable_readings_save_independently_without_inventing_usage_or_zero(): void
    {
        Carbon::setTestNow('2026-12-31');
        $this->initialize();
        $this->saveReading($this->readingInput($this->readingRow(), [
            'previous' => null, 'previousDate' => null, 'current' => '5',
        ]));
        $record = $this->service('ra')->records()->firstOrFail();
        $this->assertNull($record->prev_reading);
        $this->assertNull($record->amount);
        $this->assertNull($record->calculation_details['usage']);
        $this->assertNull($this->service('ra')->opening_reading);
        $this->assertSame('5.000', $this->readingRow('2026-12')['previous']);
        $this->saveReading($this->readingInput($this->readingRow('2026-11', 'a', 'pa'), [
            'previous' => '0', 'previousDate' => null, 'current' => null,
        ]));
        $property = $this->service('pa');
        $this->assertSame('0.000', $property->opening_reading);
        $record = $property->records()->firstOrFail();
        $this->assertNull($record->curr_reading);
        $this->assertNull($record->curr_reading_date);
        $this->assertNull($record->amount);
        $this->assertSame('0.000', $this->readingRow('2026-12', 'a', 'pa')['previous']);
        $this->assertSame(['usage' => null, 'amount' => null], app(UtilityReadingService::class)->calculate(null, '5', '2'));
        $this->assertSame(['usage' => null, 'amount' => null], app(UtilityReadingService::class)->calculate('5', null, '2'));
    }

    public function test_default_perak_calculation_uses_account_prices_and_preserves_saved_tariff_snapshot(): void
    {
        Carbon::setTestNow('2026-12-31');
        $this->initialize(); $this->initialize('b');
        $tariffs = app(\App\Services\UtilityTariffService::class);
        $custom = ['profile_id' => 'water_perak_domestic'] + config('utility_tariffs.profiles.water_perak_domestic');
        $custom['calculation'] = 'flat'; $custom['tiers'] = [['limit' => null, 'rate' => 9]]; $custom['minimum_charge'] = 0;
        $this->actingAs(User::findOrFail($this->ids['b']));
        Model::withoutEvents(fn () => $tariffs->save($this->ids['b'], $custom));
        $this->actingAs(User::findOrFail($this->ids['a']));
        $row = $this->readingRow();
        $this->assertSame('water_perak_domestic', $row['tariff']['profile_id']);
        $this->saveReading($this->readingInput($row, ['previous' => '0', 'previousDate' => '2026-11-01', 'current' => '30', 'rate' => '']));
        $record = $this->service('ra')->records()->firstOrFail();
        $this->assertSame(2580, $record->amount);
        $this->assertNull($record->applied_rate);
        $this->assertSame('water_perak_domestic', $record->calculation_details['tariff']['profile_id']);
        $custom['tiers'] = [['limit' => null, 'rate' => 2]];
        Model::withoutEvents(fn () => $tariffs->save($this->ids['a'], $custom));
        $this->saveReading($this->readingInput($this->readingRow(), ['current' => '35', 'rate' => '']));
        $this->assertSame(3120, $record->fresh()->amount, 'Saved period retains its tariff schedule.');
        $next = $this->readingInput($this->readingRow('2026-12'), ['current' => '40', 'rate' => '']);
        $next['month'] = '2026-12'; $next['reading_date'] = '2026-12-13';
        $this->saveReading($next);
        $this->assertSame(1000, $this->service('ra')->records()->whereDate('period', '2026-12-01')->firstOrFail()->amount);
        $this->assertSame(750, $tariffs->waterAmount($row['tariff'], '0'));
        $this->assertSame(3795, $tariffs->waterAmount($row['tariff'], '40'));
    }

    private function invoiceableWaterReading(): array
    {
        $row = $this->enableWaterEntry();
        $this->insert('users', 'water-user', ['name' => 'Water Tenant', 'role' => User::ROLE_TENANT, 'email_verified_at' => now()]);
        $this->insert('tenants', 'water-tenant', ['user_id' => $this->ids['water-user'], 'created_by' => $this->ids['a']]);
        $this->insert('leases', 'water-lease', ['tenant_id' => $this->ids['water-tenant'],
            'leasable_type' => Room::class, 'leasable_id' => $this->ids['ra'], 'status' => 'New', 'start_date' => '2026-11-01']);
        $this->saveReading($this->readingInput($row));
        Gate::define('utilities.generate invoices', fn () => true);
        return $this->readingRow();
    }

    public function test_november_can_be_invoiced_after_december_reading_is_recorded(): void
    {
        $this->invoiceableWaterReading();
        $this->insert('utilities', 'later-water', ['utility_service_id' => $this->service('ra')->id, 'type' => 'water',
            'period' => '2026-12-01', 'status' => 'confirmed', 'prev_reading' => '12.470', 'curr_reading' => '20',
            'prev_reading_date' => '2026-11-13', 'curr_reading_date' => '2026-12-13']);
        $later = Utility::findOrFail($this->ids['later-water'])->getAttributes();
        $this->assertTrue($this->readingRow()['locked']);
        $invoices = app(UtilityInvoiceService::class);
        $preview = $invoices->preview(auth()->user(), '2026-11');
        $this->assertSame(1, $preview['lease_count']);
        $this->assertSame(586, $preview['total']);
        Model::withoutEvents(fn () => $invoices->generate(auth()->user(), '2026-11', $preview['digest']));
        $this->assertSame(586, Invoice::sole()->total_amount);
        $this->assertSame($later, Utility::findOrFail($this->ids['later-water'])->getAttributes());
    }

    private function legacyInvoiceableReading(): Utility
    {
        $this->invoiceableWaterReading();
        $record = $this->service('ra')->records()->sole();
        DB::table('utilities')->where('id', $record->id)->update([
            'calculation_details' => json_encode(['tariff' => config('utility_tariffs.profiles.water_perak_domestic')]),
            'utility_tariff_version_id' => null, 'consumption' => null, 'applied_rate' => null, 'amount' => 750,
            'prev_reading_date' => '2026-11-13',
        ]);
        return $record->fresh();
    }

    public function test_matching_legacy_charge_is_previewed_with_later_reading_and_preserved_until_confirmation(): void
    {
        $record = $this->legacyInvoiceableReading();
        $original = $record->getAttributes();
        $this->insert('utilities', 'later-water', ['utility_service_id' => $record->utility_service_id, 'type' => 'water',
            'period' => '2026-12-01', 'status' => 'confirmed', 'prev_reading' => $record->curr_reading, 'curr_reading' => '20',
            'prev_reading_date' => '2026-11-13', 'curr_reading_date' => '2026-12-13']);
        $later = Utility::findOrFail($this->ids['later-water'])->getAttributes();
        $invoices = app(UtilityInvoiceService::class);
        $preview = $invoices->preview(auth()->user(), '2026-11');
        $this->assertSame(1, $preview['lease_count']);
        $this->assertSame(750, $preview['total']);
        $this->assertSame('2.345', $preview['entries'][0]['consumption']);
        $this->assertSame($original, $record->fresh()->getAttributes(), 'Preview must not modify financial or physical history');
        $this->assertSame(0, Invoice::count());
        Model::withoutEvents(fn () => $invoices->generate(auth()->user(), '2026-11', $preview['digest']));
        $record->refresh();
        $this->assertSame(750, Invoice::sole()->total_amount);
        $this->assertSame('validated_legacy_charge', $record->calculation_details['calculation_origin']);
        $this->assertSame(json_decode($original['calculation_details'], true), $record->calculation_details['legacy_original_snapshot']);
        foreach (['prev_reading', 'curr_reading', 'prev_reading_date', 'curr_reading_date', 'amount'] as $field) {
            $this->assertSame($original[$field], $record->getAttributes()[$field]);
        }
        $this->assertSame($later, Utility::findOrFail($this->ids['later-water'])->getAttributes());
        $this->assertSame([], $invoices->preview(auth()->user(), '2026-11')['entries']);
        try {
            Model::withoutEvents(fn () => $invoices->generate(auth()->user(), '2026-11', $preview['digest']));
            $this->fail('The legacy charge was invoiced twice');
        } catch (ValidationException $exception) { $this->assertArrayHasKey('invoices', $exception->errors()); }
        $this->assertSame(1, Invoice::count());
    }

    public function test_legacy_validation_rejects_mismatched_rates_amounts_and_decreasing_readings(): void
    {
        $record = $this->legacyInvoiceableReading();
        $original = $record->getAttributes();
        $different = config('utility_tariffs.profiles.water_perak_domestic');
        $different['tiers'][0]['rate'] = '0.76';
        foreach ([['amount' => 751], ['curr_reading' => '9'],
            ['calculation_details' => json_encode(['tariff' => $different])],
            ['calculation_details' => json_encode(['schema_version' => 2, 'final_amount' => 751, 'tariff' => $different])],
        ] as $change) {
            DB::table('utilities')->where('id', $record->id)->update($original);
            DB::table('utilities')->where('id', $record->id)->update($change);
            $this->assertSame([], app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11')['entries']);
        }
        $this->assertSame(0, Invoice::count());
    }

    public function test_legacy_preview_is_invalidated_when_the_saved_charge_changes_before_generation(): void
    {
        $record = $this->legacyInvoiceableReading();
        $invoices = app(UtilityInvoiceService::class);
        $preview = $invoices->preview(auth()->user(), '2026-11');
        $this->assertSame(1, $preview['lease_count']);
        DB::table('utilities')->where('id', $record->id)->update(['amount' => 751]);
        try {
            Model::withoutEvents(fn () => $invoices->generate(auth()->user(), '2026-11', $preview['digest']));
            $this->fail('A stale legacy charge preview was accepted');
        } catch (ValidationException $exception) { $this->assertArrayHasKey('invoices', $exception->errors()); }
        $this->assertSame(0, Invoice::count());
    }

    public function test_water_generation_returns_wallet_results_to_the_shared_summary_modal(): void
    {
        $this->invoiceableWaterReading();
        $this->insert('users', 'second-water-user', ['name' => 'Second Water Tenant', 'role' => User::ROLE_TENANT, 'email_verified_at' => now()]);
        $this->insert('tenants', 'second-water-tenant', ['user_id' => $this->ids['second-water-user'], 'created_by' => $this->ids['a']]);
        $this->insert('leases', 'second-water-lease', ['tenant_id' => $this->ids['second-water-tenant'],
            'leasable_type' => Property::class, 'leasable_id' => $this->ids['pa'], 'status' => 'New', 'start_date' => '2026-11-01']);
        $this->saveReading($this->readingInput($this->readingRow('2026-11', 'a', 'pa'), [
            'previous' => '0', 'previousDate' => '2026-11-01', 'current' => '10', 'rate' => '',
        ]));
        (require database_path('migrations/2026_01_03_145020_create_transactions_table.php'))->up();
        (require database_path('migrations/2026_01_03_145021_create_wallets_table.php'))->up();
        (require database_path('migrations/2026_01_03_145022_create_wallet_transactions_table.php'))->up();
        Schema::table('wallets', fn (Blueprint $table) => $table->string('reserved_refund_id')->nullable());
        $this->insert('document_templates', 'water-receipt', ['user_id' => $this->ids['a'], 'category' => 'receipt', 'status' => 'active']);
        $this->insert('wallets', 'water-wallet', ['user_id' => $this->ids['water-user'], 'balance' => 1000]);
        $this->insert('wallets', 'second-water-wallet', ['user_id' => $this->ids['second-water-user'], 'balance' => 1000]);
        DB::table('settings')->insert(['user_id' => $this->ids['a'], 'key' => 'payment_config',
            'value' => json_encode(['auto_pay_from_wallet' => true]), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $preview = $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]));
        $preview->assertOk();
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.invoices.generate'), ['preview_token' => $preview->viewData('invoicePreviewToken')]))
            ->assertRedirect()->assertSessionHas('success')->assertSessionHas('wallet_auto_payment_summary');
        $summary = session('wallet_auto_payment_summary');
        $this->assertSame(1336, $summary['wallet_used_cents']);
        $this->assertSame(664, $summary['wallet_remaining_cents']);
        $this->assertSame(2, $summary['wallet_count']);
        $this->assertCount(2, $summary['payments']);
        $this->assertSame(0, Invoice::get()->sum('amount_balance'));
        $this->assertSame(2, DB::table('transactions')->count());
        $html = \Illuminate\Support\Facades\Blade::render('<x-modals.wallet-payment-summary />');
        $this->assertStringContainsString('walletPaymentSummary: true', $html);
        foreach (Invoice::get() as $invoice) $this->assertStringContainsString($invoice->invoice_no, $html);
        $this->assertStringContainsString('RM13.36', $html);
        $this->assertStringContainsString('RM6.64', $html);
        $this->assertStringContainsString('all affected wallets', $html);
    }

    public function test_generated_invoice_number_uses_shared_document_preview_with_permission_checks(): void
    {
        $this->invoiceableWaterReading();
        $invoices = app(UtilityInvoiceService::class);
        $preview = $invoices->preview(auth()->user(), '2026-11');
        Model::withoutEvents(fn () => $invoices->generate(auth()->user(), '2026-11', $preview['digest']));
        $invoice = Invoice::sole();
        Gate::define('invoice.view invoice', fn () => true);
        $this->assertNull($this->readingRow()['invoiceDocumentUrl'], 'An invoice without a document template is not a broken preview link');
        $this->insert('document_templates', 'water-document', ['user_id' => $this->ids['a'], 'category' => 'invoice', 'status' => 'active']);
        DB::table('invoices')->where('id', $invoice->id)->update(['document_template_id' => $this->ids['water-document']]);
        $row = $this->readingRow();
        $this->assertSame($invoice->invoice_no, $row['invoiceNo']);
        $this->assertSame(route('admin.invoices.document-data', $invoice), $row['invoiceDocumentUrl']);
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk()
            ->assertSee("window.openLeaseInvoiceDocument(row.invoiceDocumentUrl, 'Invoice: ' + row.invoiceNo)", false);
        foreach (['admin.utilities.index', 'admin.utilities.history'] as $route) {
            $this->get(route($route))->assertOk()
                ->assertSee('data-document-url="'.route('admin.invoices.document-data', $invoice).'"', false)
                ->assertSee('window.openLeaseInvoiceDocument($el.dataset.documentUrl, $el.dataset.documentTitle)', false)
                ->assertDontSee(route('admin.invoices.index', ['search' => $invoice->invoice_no]));
        }
        Gate::define('invoice.view invoice', fn () => false);
        $row = $this->readingRow();
        $this->assertNull($row['invoiceDocumentUrl']);
        $this->assertSame($invoice->invoice_no, $row['invoiceNo']);
        $this->get(route('admin.utilities.index'))->assertOk()->assertSee($invoice->invoice_no)
            ->assertDontSee('data-document-url=', false);
    }

    public function test_saved_calculation_display_explains_meter_events_and_signed_adjustments(): void
    {
        $tariff = ['calculation' => 'flat', 'minimum_policy' => 'none', 'minimum_charge' => '0',
            'tiers' => [['limit' => null, 'rate' => '0.75']]];
        foreach ([
            [['type' => 'rollover', 'capacity' => '100'], '99', '5', '100 − 99 + 5 = 6 m³'],
            [['type' => 'replacement', 'old_closing' => '102', 'new_opening' => '1'], '100', '5', '(102 − 100) + (5 − 1) = 6 m³'],
        ] as [$event, $previous, $current, $equation]) {
            $usage = app(\App\Services\UtilityCalculationService::class)->consumption($previous, $current, $event);
            $snapshot = app(\App\Services\UtilityCalculationService::class)->calculate($tariff, $usage,
                ['adjustments' => [['amount' => '-0.01', 'reason' => 'Manual rounding correction']]])
                + ['tariff' => $tariff, 'meter_event' => $event];
            $html = \Illuminate\Support\Facades\Blade::render('<x-utilities.calculation-details :snapshot="$snapshot" :previous="$previous" :current="$current" :consumption="$usage" :amount="$amount" />',
                compact('snapshot', 'previous', 'current', 'usage') + ['amount' => $snapshot['amount']]);
            $text = preg_replace('/\s+/u', ' ', strip_tags($html));
            $this->assertStringContainsString($equation, $text);
            $this->assertStringContainsString('6 m³ × RM 0.75 = RM 4.5', $text);
            $this->assertStringContainsString('Manual rounding correction: −RM 0.01', $text);
            $this->assertStringContainsString('Calculated charge: RM 4.49', $text);
        }
    }

    public function test_water_invoice_preview_and_generation_link_history_and_prevent_duplicates(): void
    {
        $row = $this->invoiceableWaterReading();
        Gate::define('leases.show', fn () => true);
        $this->saveReading($this->readingInput($this->readingRow('2026-11', 'a', 'pa'), ['previous' => '0', 'previousDate' => null, 'current' => null]));
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]));
        $response->assertOk()->assertSee('Preview Water Invoices')->assertSee('x-init="openInvoicePreview()"', false)
            ->assertSee('Water Tenant')->assertSee('01/11/2026')->assertSee('13/11/2026');
        $preview = $response->viewData('invoicePreview');
        $response->assertSee('Room a - Room')->assertSee('2.345 m³ × RM 2.5 = RM 5.8625')
            ->assertSee('Invoices to generate')->assertSee('Meters included')->assertSee('Total amount')
            ->assertSee('Review these charges before generating invoices.')->assertSee('Property: Property a')
            ->assertSee('href="'.route('admin.tenants.show', $this->ids['water-tenant']).'"', false)
            ->assertSee('Calculated charge: RM 5.86')->assertSee('target="_blank"', false)
            ->assertSee('data-lease-url="'.route('admin.leases.show', $this->ids['water-lease']).'"', false)
            ->assertSee('@keydown.enter.stop', false);
        preg_match('/Preview Water Invoices.*?(<table\b[^>]*>.*?<\/table>)/s', $response->getContent(), $tables);
        $visibleTable = strip_tags($tables[1]);
        preg_match_all('/<th\b[^>]*>(.*?)<\/th>/s', $tables[1], $headers);
        $this->assertCount(4, $headers[1]);
        $this->assertStringNotContainsString('Calculation', implode('', $headers[1]));
        $this->assertStringContainsString('x-teleport="body"', $tables[1]);
        $this->assertStringContainsString('@keydown.enter.prevent.stop="showInfo()"', $tables[1]);
        $this->assertStringNotContainsString($this->ids['water-lease'], $visibleTable);
        $this->assertStringNotContainsString('Meter:', $visibleTable);
        $this->assertSame($this->service('ra')->records()->sole()->calculation_details, $preview['entries'][0]['calculation_details']);
        $this->assertSame($this->ids['water-tenant'], $preview['entries'][0]['tenant_id']);
        $this->assertSame('Property a', $preview['entries'][0]['property_name']);
        $this->assertSame(1, $preview['lease_count']);
        $this->assertSame(586, $preview['total']);
        $this->assertSame(0, \App\Models\Invoice::count());
        $this->assertSame(0, DB::table('fee_types')->count());
        $this->assertSame(0, DB::table('document_sequences')->count());
        $token = $response->viewData('invoicePreviewToken');
        Gate::define('leases.show', fn () => false);
        $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]))->assertOk()
            ->assertDontSee('data-lease-url=', false)
            ->assertSee('href="'.route('admin.tenants.show', $this->ids['water-tenant']).'"', false);
        Gate::define('tenant.show', fn () => false);
        $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]))->assertOk()
            ->assertDontSee('href="'.route('admin.tenants.show', $this->ids['water-tenant']).'"', false);
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.invoices.generate'), ['preview_token' => $token]))
            ->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();
        $invoice = \App\Models\Invoice::sole();
        $this->assertSame($this->ids['a'], $invoice->user_id);
        $this->assertSame($this->ids['water-lease'], $invoice->lease_id);
        $this->assertSame($this->ids['water-tenant'], $invoice->billable_id);
        $this->assertSame(586, $invoice->total_amount);
        $this->assertSame(586, $invoice->amount_balance);
        $this->assertNull($this->service('pa')->records()->firstOrFail()->invoice_item_id);
        $this->assertSame('2027-01-07', $invoice->due_date->toDateString());
        $record = $this->service('ra')->records()->firstOrFail();
        $this->assertSame($invoice->items()->sole()->id, $record->invoice_item_id);
        $description = $invoice->items()->sole()->description;
        $this->assertStringStartsWith('Water charges; ', $description);
        $this->assertStringNotContainsString($record->utility_service_id, $description);
        $this->assertStringNotContainsString('Water meter', $description);
        $this->assertTrue($this->readingRow()['locked']);
        $this->get(route('admin.utilities.index'))->assertOk()->assertSee('Invoice generated')->assertSee($invoice->invoice_no);
        $this->get(route('admin.utilities.history'))->assertOk()->assertSee('Invoice generated')->assertSee($invoice->invoice_no);
        $this->post(route('admin.utilities.readings.invoices.generate'), ['preview_token' => $token])->assertSessionHasErrors('invoices');
        $this->assertSame(1, \App\Models\Invoice::count());
        $preview = app(\App\Services\UtilityInvoiceService::class)->preview(User::findOrFail($this->ids['a']), '2026-11');
        $this->assertSame(0, $preview['lease_count']);
        $this->assertSame('Invoice already generated', $preview['skipped'][0]['reason']);
    }

    public function test_official_default_can_bill_a_lease_without_any_account_tariff_setup(): void
    {
        $row = $this->invoiceableWaterReading();
        $oldSnapshot = $this->service('ra')->records()->sole()->calculation_details;
        (require database_path('migrations/2026_11_15_000001_publish_default_perak_water_tariff.php'))->up();
        $this->assertSame($oldSnapshot, $this->service('ra')->records()->sole()->calculation_details);
        DB::table('settings')->where('user_id', $this->ids['a'])->where('key', 'utility_tariff_config')->delete();
        Gate::define('settings.edit utility meter prices', fn () => false);
        Gate::define('utilities.edit tariffs', fn () => false);
        $this->saveReading($this->readingInput($this->readingRow(), ['rate' => '', 'reprice' => true]));
        $reading = $this->service('ra')->records()->sole();
        $this->assertSame('official', data_get($reading->calculation_details, 'tariff.kind'));
        $this->assertSame(750, $reading->amount);
        $preview = app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11');
        $this->assertSame(1, $preview['lease_count']);
        $this->assertSame(750, $preview['total']);
        Model::withoutEvents(fn () => app(UtilityInvoiceService::class)->generate(auth()->user(), '2026-11', $preview['digest']));
        $this->assertSame(750, Invoice::sole()->total_amount);
        $this->assertSame(0, DB::table('settings')->where('user_id', $this->ids['a'])->where('key', 'utility_tariff_config')->count());
    }

    public function test_water_invoice_rejects_stale_preview_and_another_accounts_token(): void
    {
        $this->invoiceableWaterReading();
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]));
        $token = $response->viewData('invoicePreviewToken');
        $this->saveReading($this->readingInput($this->readingRow(), ['current' => '15']));
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.invoices.generate'), ['preview_token' => $token]))
            ->assertSessionHasErrors('invoices');
        $this->assertSame(0, \App\Models\Invoice::count());
        $this->assertNull($this->service('ra')->records()->firstOrFail()->invoice_item_id);
        $this->actingAs(User::findOrFail($this->ids['b']));
        $this->post(route('admin.utilities.readings.invoices.generate'), ['preview_token' => $token])->assertSessionHasErrors('invoices');
        $this->assertSame(0, \App\Models\Invoice::count());
    }

    public function test_water_invoice_requires_invoice_permission_and_skips_incomplete_or_ambiguous_leases(): void
    {
        $this->invoiceableWaterReading();
        Gate::define('utilities.generate invoices', fn () => false);
        $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]))->assertViewIs('errors.403');
        $this->post(route('admin.utilities.readings.invoices.generate'), [])->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        Gate::define('utilities.generate invoices', fn () => true);
        $this->insert('leases', 'water-overlap', ['tenant_id' => $this->ids['water-tenant'],
            'leasable_type' => Room::class, 'leasable_id' => $this->ids['ra'], 'status' => 'Check Out',
            'start_date' => '2026-10-01', 'end_date' => '2026-11-05', 'checked_out_at' => '2026-11-05']);
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]));
        $response->assertOk()->assertSee('Tenant change or overlapping leases require allocation');
        $this->assertNull($response->viewData('invoicePreviewToken'));
        $this->assertSame(0, \App\Models\Invoice::count());
    }

    public function test_water_invoice_preview_skips_incomplete_and_foreign_readings_and_expired_plans(): void
    {
        $this->invoiceableWaterReading(); $this->initialize('b');
        $this->saveReading($this->readingInput($this->readingRow('2026-11', 'a', 'pa'), [
            'previous' => '0', 'previousDate' => null, 'current' => null,
        ]));
        $foreignRow = $this->readingRow('2026-11', 'b', 'rb');
        $this->actingAs(User::findOrFail($this->ids['b']));
        Model::withoutEvents(fn () => app(UtilityReadingService::class)->save(User::findOrFail($this->ids['b']),
            $this->readingInput($foreignRow, ['previous' => '0', 'previousDate' => '2026-11-01', 'current' => '90'])));
        $this->actingAs(User::findOrFail($this->ids['a']));
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]));
        $response->assertOk()->assertSee('Save complete readings, dates and calculation first');
        $this->assertSame(1, $response->viewData('invoicePreview')['meter_count']);
        $this->assertCount(1, $response->viewData('invoicePreview')['skipped']);
        $token = $response->viewData('invoicePreviewToken');
        $this->session(['utility_invoice_previews.'.$token.'.expires' => now()->subMinute()->timestamp]);
        $this->post(route('admin.utilities.readings.invoices.generate'), ['preview_token' => $token])->assertSessionHasErrors('invoices');
        $this->assertSame(0, \App\Models\Invoice::count());
    }

    public function test_water_invoice_checkout_settlement_change_blocks_generation(): void
    {
        $this->invoiceableWaterReading();
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]));
        $this->insert('refunds', 'pending-water-refund', ['lease_id' => $this->ids['water-lease'], 'status' => 'pending']);
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.invoices.generate'),
            ['preview_token' => $response->viewData('invoicePreviewToken')]))->assertSessionHasErrors('invoices');
        $this->assertSame(0, \App\Models\Invoice::count());
        $this->assertNull($this->service('ra')->records()->firstOrFail()->invoice_item_id);
    }

    public function test_water_invoice_batch_rolls_back_all_invoices_if_a_later_invoice_fails(): void
    {
        $this->invoiceableWaterReading();
        $this->insert('rooms', 'second-water-room', ['room_no' => 'Second water room', 'unit_id' => $this->ids['ua'],
            'owner_id' => $this->ids['a'], 'created_by' => $this->ids['a']]);
        $this->initialize();
        $this->insert('leases', 'second-water-lease', ['tenant_id' => $this->ids['water-tenant'],
            'leasable_type' => Room::class, 'leasable_id' => $this->ids['second-water-room'], 'status' => 'New', 'start_date' => '2026-11-01']);
        $this->saveReading($this->readingInput($this->readingRow('2026-11', 'a', 'second-water-room'), [
            'previous' => '0', 'previousDate' => '2026-11-01', 'current' => '10',
        ]));
        $generator = app(\App\Services\UtilityInvoiceService::class);
        $manager = User::findOrFail($this->ids['a']);
        $preview = $generator->preview($manager, '2026-11');
        $this->assertSame(2, $preview['lease_count']);
        $this->app->instance(\App\Services\DocumentSequenceService::class, new class extends \App\Services\DocumentSequenceService {
            private int $calls = 0;
            public function generateInvoiceNumber(User $user): string {
                if (++$this->calls === 2) throw new \RuntimeException('Simulated sequence failure');
                return 'TEST-WATER-001';
            }
        });
        try {
            Model::withoutEvents(fn () => $generator->generate($manager, '2026-11', $preview['digest']));
            $this->fail('A failed batch was committed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated sequence failure', $exception->getMessage());
        }
        $this->assertSame(0, \App\Models\Invoice::count());
        $this->assertSame(0, DB::table('invoice_items')->count());
        $this->assertSame(0, Utility::whereNotNull('invoice_item_id')->count());
    }

    public function test_water_invoice_sequence_skips_numbers_already_used_by_another_account(): void
    {
        $this->invoiceableWaterReading();
        $this->insert('invoices', 'other-account-invoice', ['invoice_no' => 'INV00001', 'user_id' => $this->ids['b'],
            'period' => '2026-11-01', 'due_date' => '2026-12-31', 'status' => 'unpaid', 'total_amount' => '123']);
        $generator = app(\App\Services\UtilityInvoiceService::class);
        $manager = User::findOrFail($this->ids['a']);
        $preview = $generator->preview($manager, '2026-11');
        Model::withoutEvents(fn () => $generator->generate($manager, '2026-11', $preview['digest']));
        $this->assertSame('INV00002', \App\Models\Invoice::where('user_id', $this->ids['a'])->sole()->invoice_no);
        $this->assertSame('123', DB::table('invoices')->where('id', $this->ids['other-account-invoice'])->value('total_amount'));
    }

    public function test_reading_entry_includes_vacant_meters_without_leases_for_any_month(): void
    {
        $this->initialize(); $this->initialize('b');
        DB::table('leases')->delete();
        $expected = [$this->service('pa')->id, $this->service('ua')->id, $this->service('ra')->id];
        foreach (['2026-10', '2026-11', '2026-12'] as $month) {
            $this->assertEqualsCanonicalizing($expected, array_column($this->readingRows($month), 'id'));
        }
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk();
    }

    public function test_saving_current_reading_activates_unconfirmed_meter(): void
    {
        Carbon::setTestNow('2026-12-31');
        $this->initialize();
        $row = $this->readingRow();
        $this->assertFalse($row['previousLocked']);
        $this->assertSame('unconfirmed', $row['setupStatus']);
        $this->saveReading($this->readingInput($row, ['previous' => '2.500', 'previousDate' => '2026-11-01', 'current' => '3.500']));
        $this->assertSame('active', $this->service('ra')->status);
        $record = $this->service('ra')->records()->firstOrFail();
        $this->assertSame('confirmed', $record->status);
        $this->assertSame(250, $record->amount);
        $this->saveReading($this->readingInput($this->readingRow(), ['previous' => '2.500', 'previousDate' => '2026-11-01', 'current' => '3.500'], 'confirm'));
    }

    public function test_first_zero_reading_can_confirm_and_activate_without_manual_setup(): void
    {
        Carbon::setTestNow('2026-12-31');
        $this->initialize();
        $this->saveReading($this->readingInput($this->readingRow(), [
            'previous' => '0', 'previousDate' => '2026-11-01', 'current' => '0',
        ], 'confirm'));
        $this->assertSame('active', $this->service('ra')->status);
        $this->assertSame('confirmed', $this->service('ra')->records()->firstOrFail()->status);
    }

    public function test_partial_save_skips_blank_current_and_creates_opening_reading_for_first_entry(): void
    {
        Carbon::setTestNow('2026-12-31');
        $this->initialize();
        $row = $this->readingRow();
        $input = $this->readingInput($row, ['previous' => '2', 'previousDate' => '2026-11-01', 'current' => '3', 'rate' => '']);
        unset($input['mode']);
        $blank = $this->readingInput($this->readingRow('2026-11', 'a', 'pa'), ['current' => '', 'previous' => '', 'previousDate' => '']);
        $input['readings'] = json_encode([...json_decode($input['readings'], true), ...json_decode($blank['readings'], true)]);
        $result = $this->saveReading($input);
        $this->assertSame(1, $result['count']);
        $this->assertSame('2.000', $this->service('ra')->opening_reading);
        $this->assertSame('2026-11-01', $this->service('ra')->opening_reading_date->toDateString());
        $this->assertSame('active', $this->service('ra')->status);
        $this->assertSame(750, $this->service('ra')->records()->firstOrFail()->amount);
        $this->assertSame(0, $this->service('pa')->records()->count());
        $this->assertSame('unconfirmed', $this->service('pa')->status);
        $this->assertSame('3.000', $this->readingRow('2026-12')['previous']);
        $this->saveReading($this->readingInput($this->readingRow(), ['current' => '1']));
        $record = $this->service('ra')->records()->firstOrFail();
        $this->assertSame('1.000', $record->curr_reading);
        $this->assertNull($record->amount);
        $this->assertSame('2.000', $this->service('ra')->opening_reading);
    }

    public function test_reading_entry_meter_delete_returns_to_same_month_and_retains_other_unsaved_readings(): void
    {
        $row = $this->enableWaterEntry();
        $pending = $this->readingInput($this->readingRow('2026-11', 'a', 'pa'), [
            'previous' => '1', 'previousDate' => '2026-11-01', 'current' => '2',
        ])['readings'];
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11']));
        $response->assertOk()->assertSee('Edit water meter setup')->assertSee('Delete water meter setup');
        $visible = collect($response->viewData('readingRows'))->firstWhere('id', $row['id']);
        $this->assertStringContainsString('return_to=readings', $visible['editUrl']);
        $this->assertSame(route('admin.utilities.destroy', $row['id']), $visible['deleteUrl']);
        $target = route('admin.utilities.readings', ['management_user_id' => $this->ids['a'], 'month' => '2026-11']);
        Model::withoutEvents(fn () => $this->delete($visible['deleteUrl'], [
            'return_to' => 'readings', 'month' => '2026-11', 'readings' => $pending, 'reading_date' => '2026-11-13',
        ]))->assertRedirect($target)->assertSessionHas('success')->assertSessionHasInput('readings', $pending)
            ->assertSessionHasInput('reading_date', '2026-11-13');
        $this->assertNull(UtilityService::find($row['id']));
        $this->assertSame(0, $this->service('pa')->records()->count(), 'Retaining input must not save it as history');
        $response = $this->get($target);
        $response->assertOk();
        $this->assertNotContains('room-'.$this->ids['ra'], array_column($response->viewData('readingHierarchy'), 'key'));
    }

    public function test_edit_from_reading_entry_returns_to_reading_month_after_save(): void
    {
        $row = $this->enableWaterEntry();
        $context = ['return_to' => 'readings', 'month' => '2026-11'];
        $this->get(route('admin.utilities.edit', ['utilityService' => $row['id'], ...$context]))
            ->assertOk()->assertSee('Back to Water Readings');
        Model::withoutEvents(fn () => $this->patch(route('admin.utilities.update', $row['id']), [
            ...$context, 'label' => 'Updated room meter', 'billing_method' => 'metered', 'status' => 'active',
            'opening_reading' => '10.125', 'opening_reading_date' => '2026-11-01',
        ]))->assertRedirect(route('admin.utilities.readings', ['management_user_id' => $this->ids['a'], 'month' => '2026-11']))
            ->assertSessionHas('success');
        $this->assertSame('Updated room meter', $this->service('ra')->label);
        $this->from(route('admin.utilities.readings'))->delete(route('admin.utilities.destroy', $row['id']), ['return_to' => 'https://example.com'])
            ->assertSessionHasErrors('return_to');
        $this->assertNotNull(UtilityService::find($row['id']));
    }
    public function test_meter_replacement_event_is_scoped_consumed_once_and_carries_forward(): void
    {
        $this->enableWaterEntry();
        $service = $this->service('ra');
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.events.store', $service), [
            'type' => 'replacement', 'event_date' => '2026-11-10', 'old_closing' => '11', 'new_opening' => '0',
            'new_meter_number' => 'NEW-SERIAL', 'reason' => 'Physical meter replacement evidence',
        ]))->assertRedirect()->assertSessionHas('success');
        $event = $service->meterEvents()->sole();
        try { $this->saveReading($this->readingInput($this->readingRow(), ['current' => '20'])); $this->fail('Pending event ignored'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('meter_event', $e->errors()); }
        $this->saveReading($this->readingInput($this->readingRow(), ['current' => '2', 'eventId' => $event->id]));
        $record = $service->records()->sole();
        $this->assertSame('2.875', $record->consumption);
        $this->assertSame(719, $record->amount);
        $this->assertSame($record->id, $event->fresh()->consumed_by_utility_id);
        $this->assertSame('2.000', $this->readingRow('2026-12')['previous']);
        $this->get(route('admin.utilities.charges.show', $record))->assertOk()->assertSee('2.875');
        $this->initialize('b');
        $this->post(route('admin.utilities.events.store', $this->service('rb')), [])->assertNotFound();
    }

    public function test_shared_charge_allocation_conserves_source_minimum_and_prevents_duplicate_billing(): void
    {
        $this->invoiceableWaterReading();
        $source = $this->service('pa');
        $this->update($source, ['billing_scope' => 'shared']);
        $this->saveReading($this->readingInput($this->readingRow('2026-11', 'a', 'pa'),
            ['previous' => '0', 'previousDate' => '2026-11-01', 'current' => '1', 'rate' => '']));
        $record = $source->records()->sole();
        $data = ['method' => 'equal', 'source_hash' => UtilityAllocationService::sourceHash($record),
            'reason' => 'Approved equal shares including landlord vacancy', 'vacancy_reviewed' => 1,
            'allocations' => json_encode([
                ['lease_id' => $this->ids['water-lease'], 'from_date' => '2026-11-01', 'to_date' => '2026-11-13'],
                ['lease_id' => null, 'from_date' => '2026-11-01', 'to_date' => '2026-11-13'],
            ])];
        $this->get(route('admin.utilities.charges.show', $record))->assertOk()->assertSee('Allocate Shared Charge');
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.allocations.store', $record), $data))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(750, $record->allocations()->sum('amount'));
        $this->assertSame(375, $record->allocations()->whereNotNull('lease_id')->sole()->amount);
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.allocations.store', $record), $data))->assertSessionHasNoErrors();
        $this->assertSame(2, $record->allocations()->count(), 'Identical requests do not duplicate shares.');
        $preview = app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11');
        $this->assertSame(961, $preview['total']);
        $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]))->assertOk()
            ->assertSee('Shared meter total: RM 7.50')->assertSee('RM 7.50 × 1 / 2 = RM 3.75')
            ->assertSee('Minimum charge: RM 7.5 (applied)');
        Model::withoutEvents(fn () => app(UtilityInvoiceService::class)->generate(auth()->user(), '2026-11', $preview['digest']));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(2, Invoice::sole()->items()->count());
        $this->assertNotNull($record->allocations()->whereNotNull('lease_id')->sole()->invoice_item_id);
        $this->assertSame(0, app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11')['total']);
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.allocations.store', $record), $data))->assertSessionHasErrors('allocations');
        $this->actingAs(User::findOrFail($this->ids['water-user']));
        $response = $this->get(route('admin.utilities.charges.show', $record));
        $response->assertOk()->assertSee('RM 3.75')->assertDontSee('Landlord / vacancy')->assertDontSee('Allocate Shared Charge');
        $this->assertCount(1, $response->viewData('allocations'));
        $this->post(route('admin.utilities.allocations.store', $record), $data)->assertRedirect()->assertSessionHas('error', 'You have no permission.');
    }

    public function test_paid_correction_requires_preview_consent_and_preserves_original_charge_and_invoice(): void
    {
        $this->invoiceableWaterReading();
        Gate::define('utilities.correct invoices', fn () => true);
        (require database_path('migrations/2026_01_03_145021_create_wallets_table.php'))->up();
        (require database_path('migrations/2026_01_03_145022_create_wallet_transactions_table.php'))->up();
        $preview = app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11');
        Model::withoutEvents(fn () => app(UtilityInvoiceService::class)->generate(auth()->user(), '2026-11', $preview['digest']));
        $original = Invoice::sole(); $reading = $this->service('ra')->records()->sole();
        $snapshot = $reading->calculation_details;
        DB::table('invoices')->where('id', $original->id)->update(['status' => 'paid', 'amount_paid' => 586, 'amount_balance' => 0]);
        $response = Model::withoutEvents(fn () => $this->post(route('admin.utilities.corrections.store', $reading), [
            'amount' => '4.00', 'reason' => 'Approved tenant billing correction evidence',
        ]));
        $response->assertRedirect()->assertSessionHasNoErrors();
        $proposal = $reading->corrections()->sole();
        $this->assertSame(1, Invoice::count(), 'Preview cannot mutate finances.');
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Correction Preview');
        $url = route('admin.utilities.corrections.confirm', [$reading, $proposal]);
        Model::withoutEvents(fn () => $this->post($url, ['acknowledge_void' => 1]))->assertSessionHasErrors('acknowledge_wallet_credit');
        $this->assertSame('paid', $original->fresh()->status);
        Model::withoutEvents(fn () => $this->post($url, ['acknowledge_void' => 1, 'acknowledge_wallet_credit' => 1]))->assertSessionHas('success');
        $this->assertSame('void', $original->fresh()->status);
        $this->assertSame(586, $original->fresh()->total_amount);
        $this->assertSame(586, $original->fresh()->amount_paid);
        $this->assertSame(586, (int) DB::table('wallets')->sole()->balance);
        $this->assertSame(400, $proposal->fresh()->replacementItem->invoice->total_amount);
        $this->assertSame($snapshot, $reading->fresh()->calculation_details);
        $this->assertSame(586, $reading->fresh()->amount);
        Model::withoutEvents(fn () => $this->post($url, ['acknowledge_void' => 1, 'acknowledge_wallet_credit' => 1]))->assertSessionHasErrors('correction');
        $this->assertSame(2, Invoice::count());
        $this->actingAs(User::findOrFail($this->ids['b']));
        $this->get(route('admin.utilities.charges.show', $reading))->assertNotFound();
        $this->actingAs(User::findOrFail($this->ids['water-user']));
        $this->get(route('admin.utilities.charges.show', $reading))->assertOk()->assertSee('Correction History')->assertDontSee('Preview Correction');
    }

    private function sharedSource(): Utility
    {
        $this->invoiceableWaterReading();
        $service = $this->service('pa'); $this->update($service, ['billing_scope' => 'shared']);
        $this->saveReading($this->readingInput($this->readingRow('2026-11', 'a', 'pa'),
            ['previous' => '0', 'previousDate' => '2026-11-01', 'current' => '1', 'rate' => '']));
        return $service->records()->sole();
    }

    public function test_shared_methods_preserve_revisions_and_reject_wrong_totals_or_foreign_leases(): void
    {
        $record = $this->sharedSource(); $service = app(UtilityAllocationService::class);
        $base = ['source_hash' => UtilityAllocationService::sourceHash($record), 'reason' => 'Approved contractual shared water policy', 'vacancy_reviewed' => 1,
            'rows' => [['lease_id' => $this->ids['water-lease'], 'from_date' => '2026-11-01', 'to_date' => '2026-11-13', 'weight' => '3', 'amount' => '5.00'],
                ['lease_id' => null, 'from_date' => '2026-11-01', 'to_date' => '2026-11-13', 'weight' => '1', 'amount' => '2.50']]];
        $save = fn ($data) => Model::withoutEvents(fn () => $service->allocate(auth()->user(), $record->fresh(), $data));
        $occupants = $save($base + ['method' => 'occupants']);
        $this->assertSame(563, $occupants[1]->lease_id ? $occupants[1]->amount : $occupants[0]->amount);
        $percent = $base; $percent['rows'][0]['weight'] = '60'; $percent['rows'][1]['weight'] = '40';
        $rows = $save($percent + ['method' => 'percentage']);
        $this->assertSame(750, array_sum(array_map(fn ($a) => $a->amount, $rows)));
        $this->assertSame(2, $record->fresh()->allocation_revision);
        $this->assertSame(2, DB::table('utility_allocations')->whereNotNull('superseded_at')->count());
        $manual = $save($base + ['method' => 'manual']);
        $this->assertSame(750, array_sum(array_map(fn ($a) => $a->amount, $manual)));
        foreach (['percentage', 'manual', 'foreign'] as $invalid) {
            $data = $base; $data['method'] = $invalid === 'foreign' ? 'equal' : $invalid;
            if ($invalid === 'manual') $data['rows'][0]['amount'] = '9.00';
            if ($invalid === 'foreign') $data['rows'][0]['lease_id'] = $this->ids['occupied-b'];
            try { $save($data); $this->fail('Ambiguous or invalid allocation accepted'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        Model::withoutEvents(fn () => $service->revoke(auth()->user(), $record, 'Revoke before correcting the unbilled source'));
        $this->assertSame(0, $record->allocations()->count());
        $this->assertSame(6, DB::table('utility_allocations')->where('utility_id', $record->id)->count());
        $this->assertFalse($this->readingRow('2026-11', 'a', 'pa')['locked']);
    }

    public function test_submeter_allocations_lock_the_basis_and_exclude_separate_submeter_invoices(): void
    {
        $record = $this->sharedSource();
        $this->update($this->service('ra'), ['parent_service_id' => $record->utility_service_id, 'opening_reading' => '10.125', 'opening_reading_date' => '2026-11-01']);
        $submeter = $this->service('ra')->records()->sole();
        Model::withoutEvents(fn () => app(UtilityAllocationService::class)->allocate(auth()->user(), $record,
            ['method' => 'submeter', 'source_hash' => UtilityAllocationService::sourceHash($record),
                'reason' => 'Allocate source by connected submeter use', 'vacancy_reviewed' => 1,
                'rows' => [['lease_id' => $this->ids['water-lease'], 'from_date' => '2026-11-01', 'to_date' => '2026-11-13', 'submeter_reading_id' => $submeter->id]] ]));
        $preview = app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11');
        $this->assertSame(750, $preview['total']);
        $this->assertSame(1, $preview['meter_count']);
        $this->assertTrue($this->readingRow()['locked']);
    }

    public function test_correction_preview_detects_payment_changes_and_backend_permissions(): void
    {
        $this->invoiceableWaterReading(); Gate::define('utilities.correct invoices', fn () => true);
        $preview = app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11');
        Model::withoutEvents(fn () => app(UtilityInvoiceService::class)->generate(auth()->user(), '2026-11', $preview['digest']));
        $reading = $this->service('ra')->records()->sole();
        $service = app(UtilityCorrectionService::class);
        $proposal = Model::withoutEvents(fn () => $service->propose(auth()->user(), $reading, ['amount' => '3.00', 'reason' => 'Approved correction evidence']));
        DB::table('invoices')->where('id', $proposal->originalItem->invoice_id)->update(['amount_paid' => 1, 'amount_balance' => 585, 'status' => 'partial']);
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.corrections.confirm', [$reading, $proposal]),
            ['acknowledge_void' => 1, 'acknowledge_wallet_credit' => 1]))->assertSessionHasErrors('correction');
        $this->assertSame(1, Invoice::count());
        Gate::define('utilities.correct invoices', fn () => false);
        $this->post(route('admin.utilities.corrections.store', $reading), ['amount' => '3', 'reason' => 'Attempt correction'])->assertRedirect()->assertSessionHas('error', 'You have no permission.');
    }

    public function test_dated_tariff_assignments_validate_ownership_and_use_stored_invoice_snapshots(): void
    {
        $this->invoiceableWaterReading(); $this->initialize('b');
        $tariffs = app(UtilityTariffManagementService::class);
        $version = Model::withoutEvents(fn () => $tariffs->save(auth()->user(), [
            'name' => 'Contract flat water', 'effective_from' => '2026-11-01', 'contract_confirmed' => true,
            'reason' => 'Approved property billing policy', 'definition' => ['calculation' => 'flat',
                'minimum_policy' => 'none', 'minimum_charge' => '0', 'tiers' => [['limit' => null, 'rate' => '3']]],
        ]));
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.tariffs.assign', $version->tariff), [
            'service_ids' => json_encode([$this->service('rb')->id]), 'effective_from' => '2026-11-01', 'reason' => 'Foreign account assignment attempt',
        ]))->assertForbidden();
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.tariffs.assign', $version->tariff), [
            'service_ids' => json_encode([$this->service('ra')->id]), 'effective_from' => '2026-11-01', 'reason' => 'Authorized meter tariff assignment',
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->saveReading($this->readingInput($this->readingRow(), ['rate' => '', 'reprice' => true]));
        $record = $this->service('ra')->records()->sole(); $saved = $record->calculation_details;
        $this->assertSame(704, $record->amount);
        $this->assertSame($version->id, $record->utility_tariff_version_id);
        Model::withoutEvents(fn () => $tariffs->toggle(auth()->user(), $version->tariff, false));
        $preview = app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11');
        $this->assertSame(704, $preview['total'], 'Saved billable snapshots are unaffected by later tariff availability.');
        Model::withoutEvents(fn () => app(UtilityInvoiceService::class)->generate(auth()->user(), '2026-11', $preview['digest']));
        $this->assertSame($saved, $record->fresh()->calculation_details);
        $this->assertSame(704, Invoice::sole()->total_amount);
    }

    public function test_fixed_tariffs_invoice_without_inventing_physical_readings(): void
    {
        $this->invoiceableWaterReading();
        $tariffs = app(UtilityTariffManagementService::class);
        $v = Model::withoutEvents(fn () => $tariffs->save(auth()->user(), ['name' => 'Fixed contract water',
            'effective_from' => '2026-11-01', 'reason' => 'Agreed monthly contract water charge', 'contract_confirmed' => 1,
            'definition' => ['calculation' => 'fixed', 'minimum_policy' => 'none', 'minimum_charge' => 0, 'fixed_amount' => '12.34', 'tiers' => []]]));
        Model::withoutEvents(fn () => $tariffs->assign(auth()->user(), $v->tariff, [$this->service('ra')->id], '2026-11-01', 'Assign fixed contractual water charge'));
        $this->saveReading($this->readingInput($this->readingRow(), ['rate' => '', 'current' => null, 'previous' => null, 'reprice' => true]));
        $reading = $this->service('ra')->records()->sole();
        $this->assertNull($reading->curr_reading); $this->assertNull($reading->consumption);
        $this->assertSame(1234, $reading->amount);
        $preview = app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11');
        $this->assertSame(1234, $preview['total']);
        $this->assertSame('2026-11-30', $preview['entries'][0]['to']);
        $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]))->assertOk()
            ->assertSee('Fixed monthly charge: RM 12.34')->assertSee('Calculated charge: RM 12.34');
    }

    public function test_actual_provider_bill_requires_reference_and_allocates_the_exact_bill_once(): void
    {
        $reading = $this->sharedSource(); $tariffs = app(UtilityTariffManagementService::class);
        $v = Model::withoutEvents(fn () => $tariffs->save(auth()->user(), ['name' => 'Actual source bill',
            'effective_from' => '2026-11-01', 'reason' => 'Allocate actual documented provider bill', 'contract_confirmed' => 1,
            'definition' => ['calculation' => 'actual_bill', 'minimum_policy' => 'monthly', 'minimum_charge' => 50, 'tiers' => []]]));
        Model::withoutEvents(fn () => $tariffs->assign(auth()->user(), $v->tariff, [$reading->utility_service_id], '2026-11-01', 'Assign actual provider bill policy'));
        $row = $this->readingRow('2026-11', 'a', 'pa');
        $input = $this->readingInput($row, ['previous' => null, 'current' => null, 'rate' => '', 'reprice' => true, 'billAmount' => '10.01']);
        try { $this->saveReading($input); $this->fail('Missing actual bill reference accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('billReference', $e->errors()); }
        $this->saveReading($this->readingInput($row, ['previous' => null, 'current' => null, 'rate' => '', 'reprice' => true, 'billAmount' => '10.01', 'billReference' => 'PROVIDER-BILL-001']));
        $this->assertSame(1001, $reading->fresh()->amount);
        Model::withoutEvents(fn () => app(UtilityAllocationService::class)->allocate(auth()->user(), $reading->fresh(),
            ['method' => 'equal', 'source_hash' => UtilityAllocationService::sourceHash($reading->fresh()), 'reason' => 'Documented bill allocated equally', 'vacancy_reviewed' => 1,
                'rows' => [['lease_id' => $this->ids['water-lease'], 'from_date' => '2026-11-01', 'to_date' => '2026-11-13'],
                    ['lease_id' => null, 'from_date' => '2026-11-01', 'to_date' => '2026-11-13']]]));
        $this->assertSame(1001, $reading->allocations()->sum('amount'));
        $this->assertSame('none', data_get($reading->fresh()->calculation_details, 'tariff.minimum_policy'));
        $this->get(route('admin.utilities.readings', ['month' => '2026-11', 'preview_invoices' => 1]))->assertOk()
            ->assertSee('Provider bill: RM 10.01')->assertSee('Shared meter total: RM 10.01');
    }

    public function test_invoice_replacement_carries_other_water_items_and_restricts_unrelated_tenants(): void
    {
        $source = $this->sharedSource(); Gate::define('utilities.correct invoices', fn () => true);
        Model::withoutEvents(fn () => app(UtilityAllocationService::class)->allocate(auth()->user(), $source,
            ['method' => 'equal', 'source_hash' => UtilityAllocationService::sourceHash($source), 'reason' => 'Approved source allocation to tenant', 'vacancy_reviewed' => 1,
                'rows' => [['lease_id' => $this->ids['water-lease'], 'from_date' => '2026-11-01', 'to_date' => '2026-11-13']]]));
        $preview = app(UtilityInvoiceService::class)->preview(auth()->user(), '2026-11');
        Model::withoutEvents(fn () => app(UtilityInvoiceService::class)->generate(auth()->user(), '2026-11', $preview['digest']));
        $room = $this->service('ra')->records()->sole(); $original = Invoice::sole();
        $corrections = app(UtilityCorrectionService::class);
        $proposal = Model::withoutEvents(fn () => $corrections->propose(auth()->user(), $room, ['amount' => '4.00', 'reason' => 'Approved correction of room charge']));
        $replacement = Model::withoutEvents(fn () => $corrections->confirm(auth()->user(), $room, $proposal, ['acknowledge_void' => 1]));
        $this->assertSame(1150, $replacement->total_amount);
        $share = $source->allocations()->sole();
        $this->assertSame($replacement->id, $corrections->currentItem($source, $share)->invoice_id);
        $this->assertSame(750, (int) $corrections->currentItem($source, $share)->amount);
        $this->assertSame('void', $original->fresh()->status);
        $this->insert('users', 'unrelated-tenant', ['name' => 'Unrelated tenant', 'role' => User::ROLE_TENANT, 'email_verified_at' => now()]);
        $this->actingAs(User::findOrFail($this->ids['unrelated-tenant']));
        $this->get(route('admin.utilities.charges.show', $room))->assertNotFound();
        $this->get(route('admin.utilities.charges.show', $source))->assertNotFound();
        $this->post(route('admin.utilities.corrections.store', $room), ['amount' => '3', 'reason' => 'Unauthorized correction'])->assertRedirect()->assertSessionHas('error', 'You have no permission.');
    }

    public function test_field_entry_has_numeric_phone_inputs_and_retains_building_after_save(): void
    {
        $row = $this->enableWaterEntry();
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk()
            ->assertViewHas('readingDate', today(config('app.timezone'))->toDateString())
            ->assertSee('Property')->assertSee('Manage meters')->assertDontSee('Next meter')
            ->assertSee('inputmode="decimal"', false)->assertSee('enterkeyhint="next"', false)
            ->assertSee('name="reading_date"', false)
            ->assertDontSee('selectedValues[0] !== selectedProperty', false);
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.save'),
            $this->readingInput($row) + ['building' => $this->ids['pa']]))
            ->assertRedirect(route('admin.utilities.readings', ['management_user_id' => $this->ids['a'], 'month' => '2026-11', 'building' => $this->ids['pa']]))
            ->assertSessionHas('success');
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk()
            ->assertViewHas('readingDate', today(config('app.timezone'))->toDateString());
        $this->assertSame('2026-11-13', $this->service('ra')->records()->sole()->curr_reading_date->toDateString());
    }

    public function test_partial_readings_can_be_saved_on_different_days_without_rewriting_earlier_entries(): void
    {
        $row = $this->enableWaterEntry();
        Carbon::setTestNow('2026-11-13 12:00:00');
        $this->saveReading($this->readingInput($row));
        $first = $this->service('ra')->records()->sole()->getAttributes();
        $this->assertSame(0, $this->service('pa')->records()->count());
        Carbon::setTestNow('2026-11-15 12:00:00');
        $unit = $this->readingRow('2026-11', 'a', 'ua');
        $input = $this->readingInput($unit, ['current' => '115', 'previousDate' => '2026-11-01']);
        $input['reading_date'] = '2026-11-15';
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.save'), $input))
            ->assertRedirect()->assertSessionHas('success')->assertSessionHasNoErrors();
        $this->assertSame($first, $this->service('ra')->records()->sole()->getAttributes());
        $this->assertSame('2026-11-15', $this->service('ua')->records()->whereDate('period', '2026-11-01')->sole()->curr_reading_date->toDateString());
        $this->assertSame(0, $this->service('pa')->records()->count());
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk()
            ->assertSee('All meters:')->assertSee('Selected property:')->assertSee('Save Readings')
            ->assertDontSee('Record meters')->assertDontSee('Save &amp; next', false);
    }

    public function test_two_previous_readings_carry_across_year_boundary_and_are_read_only(): void
    {
        $row = $this->enableWaterEntry();
        $this->assertSame('', $row['earlierPrevious']);
        $this->saveReading($this->readingInput($row));
        $december = $this->readingInput($this->readingRow('2026-12'), ['current' => '15']);
        $december['month'] = '2026-12'; $december['reading_date'] = '2026-12-13';
        $this->saveReading($december);
        Carbon::setTestNow('2027-01-31 12:00:00');
        $january = $this->readingRow('2027-01');
        $this->assertSame('12.470', $january['earlierPrevious']);
        $this->assertSame('2026-11-13', $january['earlierPreviousDate']);
        $this->assertSame('15.000', $january['previous']);
        $this->assertSame('2026-12-13', $january['previousDate']);
        $this->assertSame('11/2026', $january['earlierPreviousMonth']);
        $this->assertSame('12/2026', $january['previousMonth']);
        $this->get(route('admin.utilities.readings', ['month' => '2027-01']))->assertOk()
            ->assertSee('11/2026')->assertSee('12/2026')->assertSee('01/2027')
            ->assertDontSee('2026/2027')
            ->assertSee('1st previous reading')->assertSee('2nd previous reading')
            ->assertDontSee('x-model="row.previous"', false)
            ->assertSee('formatReadingDate(row.previousDate)', false)
            ->assertSee('formatReadingDate(row.earlierPreviousDate)', false);
        $this->get(route('admin.utilities.readings', ['month' => '2027-02']))->assertOk()
            ->assertSee('2026/2027')->assertSee('02/2027');
        $input = $this->readingInput($january, ['current' => '18']);
        $input['month'] = '2027-01'; $input['reading_date'] = '2027-01-13';
        $this->saveReading($input);
        $this->assertSame('3.000', $this->service('ra')->records()->whereDate('period', '2027-01-01')->sole()->consumption);
    }

    public function test_default_reading_date_is_inside_the_save_form_and_submits_without_editing_the_calendar(): void
    {
        $row = $this->enableWaterEntry();
        Carbon::setTestNow('2026-11-13 12:00:00');
        $response = $this->get(route('admin.utilities.readings', ['month' => '2026-11']));
        $response->assertOk();
        $document = new \DOMDocument;
        $prior = libxml_use_internal_errors(true);
        try { $document->loadHTML($response->getContent()); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($prior); }
        $xpath = new \DOMXPath($document);
        $fields = $xpath->query('//form[@id="water-readings-form"]//input[@name="reading_date"]');
        $this->assertSame(1, $fields->length);
        $field = $fields->item(0);
        $this->assertSame('hidden', $field->getAttribute('type'));
        $this->assertSame('2026-11-13', $field->getAttribute('value'));
        $this->assertSame('readingDate', $field->getAttribute('x-bind:value'));
        $this->assertSame('readingDate', $xpath->query('//input[@id="bulk-reading-date"]')->item(0)->getAttribute('x-model'));
        $input = $this->readingInput($row);
        $input['reading_date'] = $field->getAttribute('value');
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.save'), $input))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('2026-11-13', $this->service('ra')->records()->sole()->curr_reading_date->toDateString());
    }

    public function test_actual_reading_date_can_differ_from_billing_month_and_carries_forward_its_real_date(): void
    {
        $row = $this->enableWaterEntry();
        $input = $this->readingInput($row);
        $input['reading_date'] = '2026-12-01';
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.save'), $input))
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $reading = $this->service('ra')->records()->sole();
        $this->assertSame('2026-11-01', $reading->period->toDateString());
        $this->assertSame('2026-12-01', $reading->curr_reading_date->toDateString());
        $this->assertSame('2026-11-01', $reading->calculation_details['billing_period']);
        $this->assertSame('2026-12-01', $reading->calculation_details['current_date']);
        $next = $this->readingRow('2026-12');
        $this->assertSame('12.470', $next['previous']);
        $this->assertSame('2026-12-01', $next['previousDate']);
        $nextInput = $this->readingInput($next, ['current' => '15']);
        $nextInput['month'] = '2026-12';
        $nextInput['reading_date'] = '2026-12-13';
        $this->saveReading($nextInput);
        $this->assertSame('2.530', $this->service('ra')->records()->whereDate('period', '2026-12-01')->sole()->consumption);
    }

    public function test_cross_month_readings_still_reject_future_and_reversed_measurement_dates(): void
    {
        $row = $this->enableWaterEntry();
        $input = $this->readingInput($row);
        $input['reading_date'] = '2027-01-01';
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.save'), $input))
            ->assertSessionHasErrors('reading_date');
        $input['reading_date'] = '2026-12-01';
        $this->saveReading($input);
        $next = $this->readingRow('2026-12');
        $nextInput = $this->readingInput($next, ['current' => '15']);
        $nextInput['month'] = '2026-12';
        $nextInput['reading_date'] = '2026-11-30';
        Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.save'), $nextInput))
            ->assertSessionHasErrors();
        $this->assertSame(1, $this->service('ra')->records()->count());
    }

    public function test_reading_page_has_account_scoped_session_draft_and_acknowledges_only_successful_saves(): void
    {
        $row = $this->enableWaterEntry();
        $this->get(route('admin.utilities.readings', ['month' => '2026-11']))->assertOk()
            ->assertSee('utility-readings:'.$this->ids['a'].':'.$this->ids['a'].':2026-11', false)
            ->assertSee('persistDraft()', false)->assertSee('Discard draft');
        $this->assertSame(0, $this->service('ra')->records()->count());
        $input = $this->readingInput($row);
        $response = Model::withoutEvents(fn () => $this->post(route('admin.utilities.readings.save'), $input));
        $response->assertRedirect()->assertSessionHas('utility_reading_draft_saved', fn ($saved) =>
            $saved['scope'] === $this->ids['a'].':'.$this->ids['a'].':2026-11'
            && $saved['rows'][0]['id'] === $row['id'] && $saved['rows'][0]['revision'] === $row['revision']);
        $this->assertSame('12.470', $this->service('ra')->records()->sole()->curr_reading);
    }

}
