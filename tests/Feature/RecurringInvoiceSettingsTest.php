<?php

namespace Tests\Feature;

use App\Http\Controllers\{InvoiceController, SettingsController};
use App\Models\{Lease, Setting, User};
use App\Services\{InvoiceService, SettingService};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Schema, Validator};
use Tests\TestCase;

class RecurringInvoiceSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('role'); $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id(); $t->string('user_id'); $t->string('key'); $t->text('value');
            $t->boolean('is_active')->default(true); $t->timestamps();
            $t->unique(['user_id', 'key']);
        });
        DB::table('users')->insert([['id' => 'a', 'role' => 'ownerAdmin'], ['id' => 'b', 'role' => 'ownerAdmin']]);
        $this->actingAs(User::findOrFail('a'));
        // Isolate permission decisions without loading unrelated Spatie tables.
        Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
    }

    private function save(array $values, string $user = 'a', array $extra = []): void
    {
        Model::withoutEvents(fn () => app(SettingService::class)->saveSettings(
            $user, array_merge($extra, ['due_date_config' => $values]), []
        ));
    }

    private function resolved(string $user = 'a'): array
    {
        return app(SettingService::class)->getEffectiveSettings($user)['due_date_config']['value'];
    }

    public function test_defaults_legacy_values_and_account_overrides(): void
    {
        $this->assertSame(config('settings.due_date_config'), $this->resolved());
        DB::table('settings')->insert(['user_id' => 'a', 'key' => 'due_date_config', 'value' => '{"days":12}']);
        $this->assertSame(array_replace(config('settings.due_date_config'), ['days' => 12]), $this->resolved());
        $this->save(['recurring_type' => 'both', 'recurring_feq' => 'after', 'recurring_feq_days' => '31']);
        $this->assertSame(['days' => 12, 'recurring_type' => 'both', 'recurring_feq' => 'after', 'recurring_feq_days' => 31], $this->resolved());
        $this->assertSame(config('settings.due_date_config'), $this->resolved('b'));
    }

    public function test_independent_save_preserves_hidden_fields_other_forms_and_users(): void
    {
        $this->save(['recurring_type' => 'both', 'recurring_feq' => 'after', 'recurring_feq_days' => 31]);
        $this->save(['days' => 22], 'b');
        DB::table('settings')->insert(['user_id' => 'a', 'key' => 'payment_config', 'value' => '{"allow_unpaid_penalty":true}']);
        $before = DB::table('settings')->where('key', 'payment_config')->first();
        $this->save(['recurring_type' => 'manual', 'days' => 0], 'a', ['payment_config' => ['allow_unpaid_penalty' => false]]);
        $this->assertEquals($before, DB::table('settings')->where('key', 'payment_config')->first());
        $this->assertSame('after', $this->resolved()['recurring_feq']);
        $this->assertSame(31, $this->resolved()['recurring_feq_days']);
        $this->assertSame(22, $this->resolved('b')['days']);
    }

    public function test_validation_rejects_invalid_enums_ranges_and_unknown_keys(): void
    {
        foreach ([['recurring_type' => 'invalid'], ['recurring_feq' => 'invalid'],
            ['recurring_feq_days' => -1], ['recurring_feq_days' => 32], ['recurring_feq_days' => 1.5],
            ['days' => -1], ['days' => 366], ['days' => null], ['auto_generate' => true]] as $values) {
            $this->assertTrue(Validator::make(['due_date_config' => $values], SettingService::recurringInvoiceRules())->fails());
        }
        foreach ([0, 31] as $days) {
            $this->assertTrue(Validator::make(['due_date_config' => ['recurring_feq_days' => $days]], SettingService::recurringInvoiceRules())->passes());
        }
        $this->save(['days' => 32, 'recurring_feq_days' => 31]);
        $this->assertSame(32, $this->resolved()['days']);
    }

    public function test_manual_endpoint_modes_and_existing_permission_are_enforced(): void
    {
        Gate::define('owner-admin', fn () => true);
        $service = \Mockery::mock(InvoiceService::class);
        $service->shouldReceive('generateRecurringInvoices')->twice()->andReturn(0);
        $this->app->instance(InvoiceService::class, $service);
        foreach (['auto' => 403, 'manual' => 200, 'both' => 200] as $mode => $status) {
            $this->save(['recurring_type' => $mode]);
            $this->actingAs(User::findOrFail('a'));
            $response = app(InvoiceController::class)->generateAutoInvoice(Request::create('/test', 'POST'), new Lease);
            $this->assertSame($status, $response->getStatusCode());
        }
        Gate::define('owner-admin', fn () => false);
        Gate::define('leases.auto generate invoice', fn () => false);
        $this->assertSame(302, app(InvoiceController::class)->generateAutoInvoice(Request::create('/test', 'POST'), new Lease)->getStatusCode());
    }

    public function test_service_also_blocks_auto_mode_before_invoice_access(): void
    {
        $this->save(['recurring_type' => 'auto']);
        $this->actingAs(User::findOrFail('a'));
        $this->expectException(\DomainException::class);
        app(InvoiceService::class)->generateRecurringInvoices(new Lease);
    }

    public function test_settings_controller_uses_effective_account_and_isolates_form(): void
    {
        Gate::define('owner-admin', fn () => true);
        $request = Request::create('/settings', 'PATCH', [
            'due_date_config' => ['days' => 9, 'recurring_type' => 'both'],
            'user_id' => 'b', 'payment_config' => ['allow_unpaid_penalty' => true],
        ]);
        Model::withoutEvents(fn () => app(SettingsController::class)->update($request, app(SettingService::class)));
        $this->assertSame(9, $this->resolved()['days']);
        $this->assertSame(7, $this->resolved('b')['days']);
        $this->assertSame(1, Setting::count());
    }

    public function test_setting_save_rolls_back_on_failure(): void
    {
        $this->save(['days' => 9]);
        try {
            DB::transaction(function () {
                $this->save(['days' => 20]);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('rollback', $e->getMessage());
        }
        $this->assertSame(9, $this->resolved()['days']);
    }

    public function test_form_renders_existing_components_and_lease_button_follows_modes(): void
    {
        Gate::define('owner-admin', fn () => true);
        $source = file_get_contents(resource_path('views/adminSide/setting/index.blade.php'));
        $start = strpos($source, '<!-- 1. RECURRING INVOICE & DUE DATE FORM -->');
        $form = substr($source, $start, strpos($source, '<!-- 2. PENDING RENEWAL SETTINGS FORM -->') - $start);
        $html = \Illuminate\Support\Facades\Blade::render($form, [
            'settings' => app(SettingService::class)->getEffectiveSettings('a'),
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ]);
        foreach (['Recurring Type', 'Auto Generate Frequency', 'Due Date Days', 'Enable Edit', 'max="31"', 'editingDueDate: false'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $source = file_get_contents(resource_path('views/adminSide/leases/show.blade.php'));
        $start = strpos($source, "@canany(['owner-admin', 'leases.auto generate invoice'])");
        $button = substr($source, $start, strpos($source, '@endcanany', $start) - $start).'@endcanany';
        foreach (['auto', 'manual', 'both'] as $mode) {
            $this->save(['recurring_type' => $mode]);
            $html = \Illuminate\Support\Facades\Blade::render($button, [
                'allowManualRecurringInvoice' => app(SettingService::class)->allowsManualRecurringInvoice('a'),
            ]);
            $this->assertSame($mode !== 'auto', str_contains($html, 'Auto Generate Invoice'));
        }
    }
}
