<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingsController;
use App\Models\{Setting, User};
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, DB, Gate, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class WalletAutoPaymentSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('role');
            $table->timestamps();
        });
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->string('key');
            $table->text('value');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
        DB::table('users')->insert([
            ['id' => 'a', 'role' => 'ownerAdmin'],
            ['id' => 'b', 'role' => 'ownerAdmin'],
        ]);
        $this->actingAs(User::findOrFail('a'));
        Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
        Gate::define('settings.edit payment collection', fn () => true);
        Gate::define('settings.payment collection setting', fn () => true);
    }

    private function updatePayment(array $values): void
    {
        $request = Request::create('/settings', 'PATCH', [
            'payment_config' => $values,
            'user_id' => 'b', // Submitted account IDs must not control ownership.
            'tab' => 'payment',
        ]);
        Model::withoutEvents(fn () => app(SettingsController::class)->update($request, app(SettingService::class)));
    }

    public function test_auto_payment_defaults_off_and_requires_an_active_account_setting(): void
    {
        $service = app(SettingService::class);
        // No invoice or wallet tables exist: checking the setting must not inspect them.
        $this->assertFalse($service->allowsAutomaticWalletPayment('a'));
        $this->updatePayment(['auto_pay_from_wallet' => '1']);
        $this->assertTrue($service->allowsAutomaticWalletPayment('a'));
        $this->assertFalse($service->allowsAutomaticWalletPayment('b'));
        DB::table('settings')->where('user_id', 'a')->update(['is_active' => false]);
        $this->assertFalse($service->allowsAutomaticWalletPayment('a'));
    }

    public function test_partial_saves_preserve_the_other_payment_settings_and_store_booleans(): void
    {
        $this->updatePayment(['allow_unpaid_penalty' => '1']);
        $this->updatePayment(['auto_pay_from_wallet' => '1']);
        $this->assertSame([
            'allow_unpaid_penalty' => true,
            'auto_pay_from_wallet' => true,
        ], Setting::where('user_id', 'a')->firstOrFail()->value);
        $this->updatePayment(['allow_unpaid_penalty' => '0']);
        $this->assertTrue(app(SettingService::class)->allowsAutomaticWalletPayment('a'));
        $this->updatePayment(['auto_pay_from_wallet' => '0']);
        $this->assertFalse(app(SettingService::class)->allowsAutomaticWalletPayment('a'));
        $this->assertSame(0, Setting::where('user_id', 'b')->count());
    }

    public function test_invalid_auto_payment_values_are_rejected_without_saving(): void
    {
        foreach (['yes', 'false', 2, null, ['enabled' => true]] as $value) {
            try {
                $this->updatePayment(['auto_pay_from_wallet' => $value]);
                $this->fail('Invalid auto-payment setting was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment_config.auto_pay_from_wallet', $exception->errors());
            }
        }
        $this->assertSame(0, Setting::count());
    }

    public function test_edit_permission_is_required_to_enable_auto_payment(): void
    {
        Gate::define('settings.edit payment collection', fn () => false);
        $this->updatePayment(['auto_pay_from_wallet' => '1']);
        $this->assertSame('You have no permission.', session('error'));
        $this->assertSame(0, Setting::count());
    }

    public function test_payment_form_renders_the_saved_switch_with_edit_controls(): void
    {
        $this->updatePayment(['auto_pay_from_wallet' => '1']);
        $source = file_get_contents(resource_path('views/adminSide/setting/index.blade.php'));
        $section = substr($source, strpos($source, '<!-- SEPARATE TAB SECTION: Payment Collection Rules -->'));
        $section = substr($section, 0, strpos($section, '<!-- TAB 2: LEASE SETTINGS -->'));
        $html = Blade::render($section, [
            'settings' => app(SettingService::class)->getEffectiveSettings('a'),
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ]);
        foreach (['Auto-pay Invoices from Tenant Wallet', 'payment_config[auto_pay_from_wallet]',
            'autoPayWallet: true', 'aria-labelledby="auto_pay_from_wallet_label"',
            ':disabled="!editingPayment"', 'Save Payment Settings'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
    }
}
