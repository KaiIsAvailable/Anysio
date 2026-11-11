<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Http\Controllers\SettingsController;
use App\Models\{Setting, User};
use App\Services\SettingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProcessingFeeSettingsTest extends TestCase
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
    }

    private function saveProcessingFee(array $values, string $active = '1'): void
    {
        $request = Request::create('/settings', 'PATCH', [
            'settings' => ['processing_fee_config' => $values],
            'is_active' => ['processing_fee_config' => $active],
            'user_id' => 'b',
            'tab' => 'payment',
        ]);
        Model::withoutEvents(fn () => app(SettingsController::class)->update($request, app(SettingService::class)));
    }

    public function test_processing_fee_saves_amount_multiple_methods_and_toggle_for_current_account(): void
    {
        $values = [
            'processing amount' => '12.50',
            'applicable_payment_method' => [PaymentMethod::CASH->value, PaymentMethod::CHEQUE->value],
        ];
        $this->saveProcessingFee($values);
        $saved = Setting::where('user_id', 'a')->where('key', 'processing_fee_config')->firstOrFail();
        $this->assertEquals(12.5, $saved->value['processing amount']);
        $this->assertSame($values['applicable_payment_method'], $saved->value['applicable_payment_method']);
        $this->assertTrue($saved->is_active);
        $this->assertSame(0, Setting::where('user_id', 'b')->count());

        $this->saveProcessingFee($values, '0');
        $this->assertFalse($saved->fresh()->is_active);
    }

    public function test_reloading_a_shorter_selection_does_not_restore_previous_or_default_methods(): void
    {
        $this->saveProcessingFee([
            'processing amount' => '10',
            'applicable_payment_method' => [PaymentMethod::CASH->value, PaymentMethod::CHEQUE->value],
        ]);
        $this->saveProcessingFee([
            'processing amount' => '10',
            'applicable_payment_method' => [PaymentMethod::BANK_TRANSFER->value],
        ]);
        $resolved = app(SettingService::class)->getEffectiveSettings('a');
        $this->assertSame([PaymentMethod::BANK_TRANSFER->value], $resolved['processing_fee_config']['value']['applicable_payment_method']);
    }

    public function test_invalid_amounts_and_methods_are_rejected_without_saving(): void
    {
        $valid = ['processing amount' => '10', 'applicable_payment_method' => ['Cash']];
        foreach ([
            ['processing amount' => '-1'],
            ['processing amount' => '1.234'],
            ['processing amount' => 'invalid'],
            ['applicable_payment_method' => ['renew']],
            ['applicable_payment_method' => []],
            ['applicable_payment_method' => ['']],
        ] as $invalid) {
            try {
                $this->saveProcessingFee(array_replace($valid, $invalid));
                $this->fail('Invalid processing fee was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(0, Setting::count());
    }

    public function test_edit_permission_is_required(): void
    {
        Gate::define('settings.edit payment collection', fn () => false);
        $this->saveProcessingFee(['processing amount' => '10', 'applicable_payment_method' => ['Cash']]);
        $this->assertSame('You have no permission.', session('error'));
        $this->assertSame(0, Setting::count());
    }

    public function test_payment_modal_edit_and_remove_controls_follow_separate_permissions(): void
    {
        $source = file_get_contents(resource_path('views/components/modals/payment-modal.blade.php'));
        $section = substr($source, strpos($source, '{{-- Processing fee follows'));
        $section = substr($section, 0, strpos($section, '{{-- Cancelled Penalty State'));

        Gate::define('invoice.edit processing fee', fn () => false);
        Gate::define('invoice.remove processing fee', fn () => false);
        $html = \Illuminate\Support\Facades\Blade::render($section);
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringNotContainsString('Cancel/Remove Processing Fee', $html);

        Gate::define('invoice.edit processing fee', fn () => true);
        $html = \Illuminate\Support\Facades\Blade::render($section);
        $this->assertStringNotContainsString('readonly', $html);
        $this->assertStringNotContainsString('Cancel/Remove Processing Fee', $html);

        Gate::define('invoice.remove processing fee', fn () => true);
        $html = \Illuminate\Support\Facades\Blade::render($section);
        $this->assertStringContainsString('Cancel/Remove Processing Fee', $html);
        $this->assertStringContainsString('Restore', $html);
        $this->assertContains('edit processing fee', config('permissions.modules.invoice.record payment'));
        $this->assertContains('remove processing fee', config('permissions.modules.invoice.record payment'));
    }
}
