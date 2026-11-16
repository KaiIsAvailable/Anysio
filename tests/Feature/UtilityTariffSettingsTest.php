<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingsController;
use App\Models\{Setting, User};
use App\Services\{SettingService, UtilityTariffService, UtilityTariffManagementService};
use App\Models\UtilityTariffVersion;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, DB, Gate, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UtilityTariffSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('role'); $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->id(); $table->string('user_id'); $table->string('key');
            $table->text('value'); $table->boolean('is_active')->default(true); $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
        DB::table('users')->insert([['id' => 'a', 'role' => 'ownerAdmin'], ['id' => 'b', 'role' => 'ownerAdmin']]);
        foreach (['utility_services', 'utilities', 'invoice_items', 'leases'] as $table) {
            Schema::create($table, fn (Blueprint $t) => $t->ulid('id')->primary());
        }
        (require database_path('migrations/2026_11_14_000001_version_utility_billing.php'))->up();
        (require database_path('migrations/2026_11_15_000001_publish_default_perak_water_tariff.php'))->up();
        Schema::table('utility_services', function (Blueprint $t) {
            $t->string('management_user_id')->nullable(); $t->string('type')->nullable(); $t->softDeletes();
        });
        Schema::create('properties', function (Blueprint $t) { $t->ulid('id')->primary(); $t->string('owner_id')->nullable(); $t->string('created_by')->nullable(); });
        Schema::create('owners', function (Blueprint $t) { $t->ulid('id')->primary(); $t->string('user_id')->nullable(); $t->string('agent_id')->nullable(); });
        $this->actingAs(User::findOrFail('a'));
        Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
        Gate::define('settings.edit utility meter prices', fn () => true);
    }

    public function test_default_settings_include_reference_profiles_without_marking_them_customized(): void
    {
        $settings = app(SettingService::class)->getEffectiveSettings('a');
        $this->assertSame(config('utility_tariffs.profiles'), data_get($settings, 'utility_tariff_config.value.profiles'));
        $profiles = app(UtilityTariffService::class)->profiles($settings);
        $this->assertFalse($profiles['water_selangor_domestic']['customized']);
        $this->assertSame('water_perak_domestic', data_get($settings, 'utility_tariff_config.value.default_profile'));
        $this->assertSame('water_perak_domestic', app(UtilityTariffService::class)->defaultWaterProfile(User::findOrFail('a'))['profile_id']);
        $this->assertSame(config('utility_tariffs.profiles.water_pahang_domestic.tiers'), $profiles['water_pahang_domestic']['tiers']);
        $this->assertSame(0, Setting::count());
    }

    private function payload(string $id = 'water_sabah_domestic'): array
    {
        return array_merge(['profile_id' => $id], array_intersect_key(config('utility_tariffs.profiles.'.$id), array_flip([
            'calculation', 'effective_date', 'tiers', 'capacity_rate', 'network_rate', 'minimum_charge', 'fixed_charge', 'fixed_waiver_up_to',
        ])));
    }

    public function test_new_accounts_use_published_official_lap_rates_without_creating_settings_or_custom_tariffs(): void
    {
        $service = app(UtilityTariffManagementService::class);
        foreach (['a', 'b'] as $id) {
            $profile = $service->resolve(User::findOrFail($id), null, '2026-11-01', '2026-11-15');
            $this->assertSame('water_perak_domestic', $profile['profile_id']);
            $this->assertSame('official', $profile['kind']);
            $this->assertTrue($profile['billable']);
            $this->assertSame('verified', $profile['verification_status']);
            $this->assertSame('2024-05-01', $profile['effective_date']);
            $this->assertSame(750, app(UtilityTariffService::class)->waterAmount($profile, '0'));
            $this->assertSame(2580, app(UtilityTariffService::class)->waterAmount($profile, '30'));
        }
        $this->assertSame(0, Setting::count());
        $this->assertSame(0, DB::table('utility_tariffs')->where('kind', 'custom')->count());
        $count = UtilityTariffVersion::count();
        (require database_path('migrations/2026_11_15_000001_publish_default_perak_water_tariff.php'))->up();
        $this->assertSame($count, UtilityTariffVersion::count());
    }

    public function test_personal_rate_versions_do_not_change_official_rates_or_another_account(): void
    {
        $service = app(UtilityTariffManagementService::class);
        $official = $service->resolve(User::findOrFail('a'), null, '2026-11-01', '2026-11-15');
        $input = $this->versionInput() + ['make_default' => true, 'reference_key' => 'water_perak_domestic'];
        $input['definition']['tiers'][0]['rate'] = '0.90';
        $v1 = Model::withoutEvents(fn () => $service->save(User::findOrFail('a'), $input));
        $input['id'] = $v1->utility_tariff_id;
        $input['definition']['tiers'][0]['rate'] = '0.95';
        $v2 = Model::withoutEvents(fn () => $service->save(User::findOrFail('a'), $input));
        $this->assertSame(2, $v2->version);
        $this->assertSame('0.900000', $v1->fresh()->definition['tiers'][0]['rate']);
        $this->assertSame($official, $service->resolve(User::findOrFail('b'), null, '2026-11-01', '2026-11-15'));
        $this->assertSame('custom', $service->resolve(User::findOrFail('a'), null, '2026-11-01', '2026-11-15')['kind']);
        $this->assertSame($official, $service->resolve(User::findOrFail('a'), null, '2026-11-01', '2026-11-15', $official));
    }

    private function save(array $data, array $extra = [])
    {
        $request = Request::create('/settings', 'PATCH', array_merge($extra, ['utility_tariff_config' => $data, 'user_id' => 'b']));
        return Model::withoutEvents(fn () => app(SettingsController::class)->update($request, app(SettingService::class)));
    }

    public function test_saves_one_profile_for_current_account_with_a_session_message(): void
    {
        $data = $this->payload();
        $data['tiers'][0]['rate'] = '0.35';
        $response = $this->save($data, ['payment_config' => ['auto_pay_from_wallet' => true]]);
        $this->assertSame(route('admin.settings.index', ['tab' => 'utility', 'tariff' => 'water_sabah_domestic']), $response->getTargetUrl());
        $this->assertSame('Utility meter prices updated successfully.', session('success'));
        $stored = Setting::where('user_id', 'a')->sole();
        $this->assertSame('utility_tariff_config', $stored->key);
        $this->assertEquals(0.35, $stored->value['profiles']['water_sabah_domestic']['tiers'][0]['rate']);
        $this->assertSame(0, Setting::where('user_id', 'b')->count());
    }

    public function test_profile_saves_do_not_replace_other_schedules_and_shorter_tiers_do_not_merge_defaults(): void
    {
        $this->save($this->payload());
        $water = $this->payload('water_selangor_domestic');
        $water['calculation'] = 'flat';
        $water['tiers'] = [['limit' => null, 'rate' => '2.000000']];
        $this->save($water);
        $profiles = app(UtilityTariffService::class)->profiles(app(SettingService::class)->getEffectiveSettings('a'));
        $this->assertCount(5, $profiles['water_sabah_domestic']['tiers']);
        $this->assertSame([['limit' => null, 'rate' => '2.000000']], $profiles['water_selangor_domestic']['tiers']);
        $this->assertTrue($profiles['water_selangor_domestic']['customized']);
        $this->assertSame(1, Setting::count());
    }

    public function test_rejects_invalid_rates_limits_modes_and_unknown_profiles_without_saving(): void
    {
        foreach ([
            ['profile_id' => 'another_account'], ['calculation' => 'invalid'], ['minimum_charge' => -1],
            ['tiers' => [['limit' => 20, 'rate' => 1], ['limit' => 10, 'rate' => 2], ['limit' => null, 'rate' => 3]]],
            ['tiers' => [['limit' => 20, 'rate' => 1]]], ['tiers' => [['limit' => null, 'rate' => null]]],
            ['tiers' => [['limit' => null, 'rate' => -1]]], ['tiers' => [['limit' => null, 'rate' => 'invalid']]],
            ['calculation' => 'flat'], ['effective_date' => 'not-a-date'],
        ] as $invalid) {
            try {
                $this->save(array_replace($this->payload(), $invalid));
                $this->fail('Invalid tariff must not be saved.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(0, Setting::count());
    }

    public function test_edit_permission_is_required(): void
    {
        Gate::define('settings.edit utility meter prices', fn () => false);
        $this->save($this->payload());
        $this->assertSame('You have no permission.', session('error'));
        $this->assertSame(0, Setting::count());
    }

    public function test_price_settings_separate_reference_custom_and_preview_with_shared_components(): void
    {
        $html = view('adminSide.setting.partials.utility-meter-prices', ['settings' => []])->render();
        foreach (['Official Provider Tariffs', 'Custom Tariffs', 'Price Calculation Preview', 'Verified',
            'Save Tariff Version', 'Current default:', 'No setup is needed.', 'Edit Rates', 'Lembaga Air Perak',
            'tariff[contract_confirmed]', 'loading', 'Assign Tariff to Properties / Meters'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Publish New Reference Version', $html);
        $this->assertStringNotContainsString('Use as Custom Starting Point', $html);
        foreach (['Sabah', 'Sarawak', 'Air Selangor'] as $hidden) $this->assertStringNotContainsString($hidden, $html);
        Gate::define('settings.edit utility meter prices', fn () => false);
        $html = view('adminSide.setting.partials.utility-meter-prices', ['settings' => []])->render();
        $this->assertStringNotContainsString('Save Tariff Version', $html);
        $this->assertStringNotContainsString('Create Custom Tariff', $html);
    }
    private function versionInput(): array
    {
        return ['name' => 'Contract water', 'effective_from' => '2026-01-01', 'reason' => 'Approved tenancy charging policy',
            'contract_confirmed' => true, 'definition' => ['calculation' => 'blocks', 'minimum_policy' => 'monthly',
            'minimum_charge' => '7.50', 'tiers' => [['limit' => '20', 'rate' => '0.75'], ['limit' => '35', 'rate' => '1.08'], ['limit' => null, 'rate' => '1.35']]]];
    }

    public function test_versions_are_immutable_scoped_and_official_publication_requires_super_admin(): void
    {
        $service = app(UtilityTariffManagementService::class); $manager = User::findOrFail('a');
        $v1 = Model::withoutEvents(fn () => $service->save($manager, $this->versionInput()));
        $input = $this->versionInput(); $input['id'] = $v1->utility_tariff_id; $input['effective_from'] = '2026-12-01';
        $input['definition']['tiers'][0]['rate'] = '0.80';
        $v2 = Model::withoutEvents(fn () => $service->save($manager, $input));
        $this->assertSame(2, $v2->version); $this->assertSame('0.750000', $v1->fresh()->definition['tiers'][0]['rate']);
        try { $v1->update(['definition' => []]); $this->fail('Immutable version was changed'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->getMessage()); }
        $this->actingAs(User::findOrFail('b'));
        try { Model::withoutEvents(fn () => $service->save(User::findOrFail('b'), $input)); $this->fail('Foreign version edited'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $official = $this->versionInput() + ['provider' => 'LAP', 'state' => 'Perak', 'customer_category' => 'Domestic individual',
            'source_url' => 'https://example.test/document.pdf', 'verified' => true];
        try { Model::withoutEvents(fn () => $service->save(User::findOrFail('b'), $official, true)); $this->fail('Owner published official reference'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        Gate::define('super-admin', fn () => true);
        $verified = Model::withoutEvents(fn () => $service->save(User::findOrFail('b'), $official, true));
        $this->assertNotNull($verified->verified_at); $this->assertNull($verified->tariff->management_user_id);
        $this->assertTrue($service->snapshot($verified)['billable']);
        $this->assertFalse($service->snapshot(UtilityTariffVersion::where('utility_tariff_id', '!=', $verified->utility_tariff_id)->whereHas('tariff', fn ($q) => $q->where('kind', 'official'))->firstOrFail())['billable']);
    }

    public function test_effective_versions_require_review_across_boundaries_and_saved_snapshot_is_retained(): void
    {
        $service = app(UtilityTariffManagementService::class); $manager = User::findOrFail('a');
        $v1 = Model::withoutEvents(fn () => $service->save($manager, $this->versionInput() + ['make_default' => true]));
        $input = $this->versionInput(); $input['id'] = $v1->utility_tariff_id; $input['effective_from'] = '2026-12-01';
        $input['definition']['tiers'][0]['rate'] = '0.80';
        $v2 = Model::withoutEvents(fn () => $service->save($manager, $input));
        $plan = $service->timeline($manager->fresh());
        $this->assertSame(1, $service->resolveTimeline($plan, 'meter', '2026-11-01', '2026-11-30')['version']);
        $this->assertSame(2, $service->resolveTimeline($plan, 'meter', '2026-12-01', '2026-12-31')['version']);
        $this->assertFalse($service->resolveTimeline($plan, 'meter', '2026-11-13', '2026-12-13')['billable']);
        $saved = $service->snapshot($v1);
        $this->assertSame($saved, $service->resolveTimeline($plan, 'meter', '2026-11-13', '2026-12-13', $saved));
        $actual = $service->validateDefinition(['calculation' => 'actual_bill', 'minimum_policy' => 'monthly', 'minimum_charge' => 50, 'tiers' => []]);
        $this->assertSame('none', $actual['minimum_policy']);
        $this->assertSame('0.000000', $actual['minimum_charge']);
    }

    public function test_creating_custom_tariff_without_default_selection_keeps_official_default(): void
    {
        $service = app(UtilityTariffManagementService::class); $manager = User::findOrFail('a');
        $original = $service->timeline($manager)['default'];
        $input = $this->versionInput() + ['reference_key' => 'water_perak_domestic'];
        $custom = Model::withoutEvents(fn () => $service->save($manager, $input));
        $this->assertSame($original, $service->timeline($manager)['default']);
        $this->assertNotSame($custom->utility_tariff_id, $original);
        $this->assertSame('official', $service->resolveTimeline($service->timeline($manager), 'meter', '2026-01-01', '2026-01-31')['kind']);
        $this->assertSame(0, Setting::count());
    }

}
