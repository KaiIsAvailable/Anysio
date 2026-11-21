<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingsController;
use App\Models\{Invoice, Setting, User, UserManagement};
use App\Services\{SettingService, SubscriptionService};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EarlyPaymentSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'users' => ['role', 'email'],
            'settings' => ['user_id', 'key', 'value'],
            'user_management' => ['user_id', 'package_id', 'subscription_status', 'start_date', 'end_date'],
            'ref_code_packages' => ['name', 'price_mode'],
            'invoices' => ['user_id', 'context', 'status', 'billable_type', 'billable_id', 'invoice_no', 'document_template_id', 'type', 'period', 'due_date'],
            'invoice_items' => ['invoice_id', 'fee_type_id', 'description'],
            'document_templates' => ['user_id', 'category', 'status'],
            'document_sequences' => ['user_id', 'category', 'sequence'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($table, $columns) {
                if ($table === 'settings') $t->id();
                else $t->string('id')->primary();
                $t->timestamps();
                foreach ($columns as $column) $t->text($column)->nullable();
                if (in_array($table, ['user_management', 'invoices'])) $t->softDeletes();
                if ($table === 'settings') $t->boolean('is_active')->default(true);
                foreach (match ($table) {
                    'ref_code_packages' => ['price'],
                    'invoices' => ['total_amount', 'amount_paid', 'amount_balance'],
                    'invoice_items' => ['amount'], default => [],
                } as $column) $t->integer($column)->default(0);
            });
        }
        DB::table('users')->insert([
            ['id' => 'system-admin', 'role' => 'admin', 'email' => 'admin@anysio.com'],
            ['id' => 'manager', 'role' => 'ownerAdmin', 'email' => 'manager@example.test'],
        ]);
        DB::table('ref_code_packages')->insert(['id' => 'package', 'name' => 'Monthly', 'price_mode' => 'monthly', 'price' => 10000]);
        DB::table('user_management')->insert([
            'id' => 'management', 'user_id' => 'manager', 'package_id' => 'package',
            'subscription_status' => 'active', 'start_date' => '2026-11-01', 'end_date' => '2026-12-01',
        ]);
        Carbon::setTestNow(Carbon::parse('2026-11-24 12:00:00'));
        Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
        Gate::define('super-admin', fn (User $user) => $user->role === 'admin');
        $this->actingAs(User::findOrFail('system-admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function save(array $data)
    {
        $request = Request::create('/settings', 'PATCH', ['early_payment_config' => $data, 'user_id' => 'manager']);
        return Model::withoutEvents(fn () => app(SettingsController::class)->update($request, app(SettingService::class)));
    }

    public function test_only_super_admin_can_save_global_settings_and_days_are_calculated_on_the_server(): void
    {
        $this->save(['number' => 2, 'mode' => 'months', 'is_active' => '1']);
        $saved = Setting::where('user_id', 'system-admin')->firstOrFail();
        $this->assertSame(['number' => 2, 'mode' => 'months', 'days' => 60], $saved->value);
        $this->assertSame(0, Setting::where('user_id', 'manager')->count());
        $html = view('adminSide.setting.partials.system', ['errors' => new \Illuminate\Support\ViewErrorBag()])->render();
        $this->assertStringContainsString('Early Payment Notification Settings', $html);
        $this->assertStringContainsString('early_payment_config[number]', $html);
        $this->assertStringContainsString('id="early-payment-enabled" role="switch"', $html);
        $this->assertStringContainsString('name="early_payment_config[is_active]" :value="enabled ? \'1\' : \'0\'"', $html);
        $this->assertStringContainsString(':disabled="!editing || loading"', $html);
        $this->actingAs(User::findOrFail('manager'));
        $response = $this->save(['number' => 1, 'mode' => 'days', 'is_active' => '0']);
        $this->assertSame('You have no permission.', $response->getSession()->get('error'));
        $this->assertSame(60, $saved->fresh()->value['days']);
        $this->app->instance('request', Request::create('/settings?tab=system'));
        $this->assertSame('errors.403', app(SettingsController::class)->index(app(SettingService::class))->name());
    }

    public function test_invalid_notice_values_are_rejected(): void
    {
        foreach ([['number' => 0], ['number' => 366], ['mode' => 'weeks'], ['is_active' => 'invalid'], ['days' => 999]] as $invalid) {
            try {
                $this->save(array_replace(['number' => 7, 'mode' => 'days', 'is_active' => '1'], $invalid));
                $this->fail('Invalid early payment setting accepted.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, Setting::count());
    }

    public function test_notice_window_generates_one_invoice_without_blocking_until_expiry(): void
    {
        $this->save(['number' => 7, 'mode' => 'days', 'is_active' => '1']);
        $user = User::findOrFail('manager');
        $service = app(SubscriptionService::class);
        Carbon::setTestNow('2026-11-23 12:00:00');
        $this->assertNull($service->ensureRenewalInvoice($user));
        Carbon::setTestNow('2026-11-24 12:00:00');
        $invoice = Model::withoutEvents(fn () => $service->ensureRenewalInvoice($user));
        $this->assertNotNull($invoice);
        $this->assertSame('2026-12-01', $invoice->due_date->toDateString());
        $this->assertFalse($service->requiresPayment(UserManagement::find('management'), $invoice));
        $this->assertSame($invoice->id, $service->ensureRenewalInvoice($user)->id);
        Carbon::setTestNow('2026-12-01 00:00:00');
        Model::withoutEvents(fn () => $service->ensureRenewalInvoice($user));
        $this->assertSame(1, Invoice::count());
        $this->assertTrue($service->requiresPayment(UserManagement::find('management'), $invoice));
        $this->assertSame('pending', UserManagement::find('management')->subscription_status);
    }

    public function test_disabled_notices_still_generate_the_required_invoice_at_expiry(): void
    {
        $this->save(['number' => 14, 'mode' => 'days', 'is_active' => '0']);
        $service = app(SubscriptionService::class);
        $user = User::findOrFail('manager');
        $this->assertNull($service->ensureRenewalInvoice($user));
        Carbon::setTestNow('2026-12-01 00:00:00');
        $invoice = Model::withoutEvents(fn () => $service->ensureRenewalInvoice($user));
        $this->assertTrue($service->requiresPayment(UserManagement::find('management'), $invoice));
    }

    public function test_expiry_and_repeated_generation_reuse_any_outstanding_subscription_invoice(): void
    {
        $service = app(SubscriptionService::class);
        $user = User::findOrFail('manager');
        $invoice = Model::withoutEvents(fn () => $service->ensureRenewalInvoice($user));
        $this->assertNotNull($invoice);
        Carbon::setTestNow('2026-12-01 00:00:00');

        foreach (['unpaid', 'partial', 'overdue'] as $status) {
            DB::table('invoices')->where('id', $invoice->id)->update(['status' => $status]);
            DB::table('user_management')->where('id', 'management')->update(['subscription_status' => 'active']);
            Model::withoutEvents(function () use ($service, $user, $invoice) {
                $this->assertSame($invoice->id, $service->ensureRenewalInvoice($user)->id);
                $this->assertSame($invoice->id, $service->generateInvoice($user, $user->user_management->package)->id);
                $service->ensureRenewalInvoice($user);
            });
            $this->assertSame(1, Invoice::where('user_id', $user->id)->where('context', 'subscription')->count());
            $this->assertSame($status, $invoice->fresh()->status);
        }
        $this->assertSame(1, DB::table('invoice_items')->where('invoice_id', $invoice->id)->count());
        $this->assertSame(2, \App\Models\DocumentSequence::firstOrFail()->sequence['next_number']);
    }

    public function test_extending_expired_access_uses_the_new_end_date_and_preserves_the_unpaid_invoice(): void
    {
        $service = app(SubscriptionService::class);
        $user = User::findOrFail('manager');
        Carbon::setTestNow('2026-12-02 12:00:00');
        $invoice = Model::withoutEvents(fn () => $service->ensureRenewalInvoice($user));
        $this->assertTrue($service->requiresPayment(UserManagement::find('management'), $invoice));
        $this->assertSame('pending', UserManagement::find('management')->subscription_status);

        // Grant two more days without changing the pending status or invoice.
        DB::table('user_management')->where('id', 'management')->update(['end_date' => '2026-12-04']);
        for ($visit = 0; $visit < 2; $visit++) {
            $service->ensureRenewalInvoice($user);
            $this->assertFalse($service->requiresPayment(UserManagement::find('management'), $invoice->fresh()));
        }
        $this->assertSame('pending', UserManagement::find('management')->subscription_status);
        $this->assertSame(1, Invoice::count());
        $this->assertSame('unpaid', $invoice->fresh()->status);
        $this->assertSame($invoice->amount_balance, $invoice->fresh()->amount_balance);
        $this->assertSame($invoice->due_date->toDateString(), $invoice->fresh()->due_date->toDateString());

        Carbon::setTestNow('2026-12-04 00:00:00');
        $this->assertTrue($service->requiresPayment(UserManagement::find('management'), $invoice));
        Carbon::setTestNow('2026-12-05 12:00:00');
        $this->assertTrue($service->requiresPayment(UserManagement::find('management'), $invoice));
        $this->assertSame(1, Invoice::count());
    }

    public function test_missing_database_setting_uses_the_fourteen_day_config_default(): void
    {
        $settings = app(SettingService::class)->getSystemEarlyPaymentSettings();
        $this->assertSame(['number' => 14, 'mode' => 'days', 'days' => 14], $settings['value']);
        $this->assertTrue($settings['is_active']);
        $this->assertSame(0, Setting::count());

        $user = User::findOrFail('manager');
        $service = app(SubscriptionService::class);
        Carbon::setTestNow('2026-11-16 12:00:00');
        $this->assertNull($service->ensureRenewalInvoice($user));
        Carbon::setTestNow('2026-11-17 00:00:00');
        $this->assertNotNull(Model::withoutEvents(fn () => $service->ensureRenewalInvoice($user)));
    }

    public function test_default_is_available_without_a_system_admin_record(): void
    {
        DB::table('users')->where('id', 'system-admin')->delete();
        $this->assertSame([
            'value' => ['number' => 14, 'mode' => 'days', 'days' => 14], 'is_active' => true,
        ], app(SettingService::class)->getSystemEarlyPaymentSettings());
    }

    public function test_floating_notice_uses_a_stable_login_marker_and_formatted_expiry(): void
    {
        $render = fn () => \Illuminate\Support\Facades\Blade::render(
            '<x-ui.advance-payment-notice :expires-at="$expiry" invoice-id="invoice-one" />',
            ['expiry' => Carbon::parse('2026-12-01')],
        );
        $html = $render();
        $loginKey = session('subscription_notice_login');
        $this->assertNotEmpty($loginKey);
        $this->assertStringContainsString('01/12/2026', $html);
        $this->assertStringContainsString('Do not show again during this login', $html);
        $this->assertStringContainsString('fixed bottom-4', $html);
        $render();
        $this->assertSame($loginKey, session('subscription_notice_login'));
        session()->forget('subscription_notice_login');
        $render();
        $this->assertNotSame($loginKey, session('subscription_notice_login'));
    }

    public function test_payment_modal_has_close_and_cancel_only_before_expiry(): void
    {
        $invoiceService = \Mockery::mock(\App\Services\InvoiceService::class);
        $invoiceService->shouldReceive('getInvoiceVariables')->andReturn([]);
        $this->app->instance(\App\Services\InvoiceService::class, $invoiceService);
        $bankService = \Mockery::mock(\App\Services\BankAccountService::class);
        $bankService->shouldReceive('getDefaultForSystemAdmin')->andReturn(null);
        $this->app->instance(\App\Services\BankAccountService::class, $bankService);
        $invoice = new Invoice(['invoice_no' => 'TEST', 'amount_balance' => 10000]);
        $invoice->id = 'test-invoice';
        $invoice->setRelation('latestPayment', null);
        $invoice->setRelation('documentTemplate', null);
        $invoice->setRelation('items', collect());
        $data = ['invoice' => $invoice, 'userMgmt' => UserManagement::find('management'),
            'errors' => new \Illuminate\Support\ViewErrorBag()];

        $early = view('components.modals.make_payment', $data + ['dismissible' => true])->render();
        $this->assertStringContainsString('aria-label="Close advance payment"', $early);
        $this->assertStringContainsString('Cancel', $early);
        $this->assertStringNotContainsString('subscription-payment-logout', $early);
        $this->assertStringNotContainsString('Pay Later', $early);

        $required = view('components.modals.make_payment', $data + ['dismissible' => false])->render();
        $this->assertStringNotContainsString('Close advance payment', $required);
        $this->assertStringNotContainsString('Cancel', $required);
        $this->assertStringContainsString('form="subscription-payment-logout"', $required);
        $this->assertStringContainsString('dismissible: false', $required);
    }

    public function test_free_and_undated_subscriptions_do_not_generate_renewal_invoices(): void
    {
        $this->save(['number' => 7, 'mode' => 'days', 'is_active' => '1']);
        DB::table('ref_code_packages')->where('id', 'package')->update(['price' => 0]);
        $this->assertNull(app(SubscriptionService::class)->ensureRenewalInvoice(User::find('manager')));
        DB::table('ref_code_packages')->where('id', 'package')->update(['price' => 10000]);
        DB::table('user_management')->where('id', 'management')->update(['end_date' => null]);
        $this->assertNull(app(SubscriptionService::class)->ensureRenewalInvoice(User::find('manager')));
        $this->assertSame(0, Invoice::count());
    }

    public function test_subscription_receipt_cannot_be_submitted_for_another_account(): void
    {
        DB::table('invoices')->insert([
            'id' => 'other-invoice', 'user_id' => 'system-admin', 'context' => 'subscription',
            'status' => 'unpaid', 'total_amount' => 10000, 'amount_balance' => 10000,
        ]);
        $this->actingAs(User::find('manager'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\PaymentService::class)->submitReceipt(Invoice::find('other-invoice'), auth()->user(), []);
    }
}
