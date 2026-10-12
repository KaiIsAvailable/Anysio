<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingsController;
use App\Models\{Invoice, Property, User};
use App\Services\{InvoicePenaltyService, InvoiceService, SettingService};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LatePenaltyPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'users' => ['name', 'role', 'email'], 'tenants' => ['user_id'],
            'owners' => ['user_id', 'agent_id'], 'properties' => ['owner_id', 'created_by'],
            'leases' => ['tenant_id', 'leasable_type', 'leasable_id'],
            'invoices' => ['parent_id', 'lease_id', 'user_id', 'context', 'type', 'billable_type', 'billable_id', 'invoice_no', 'document_template_id', 'period', 'due_date', 'status', 'remarks'],
            'invoice_items' => ['invoice_id', 'fee_type_id', 'description'],
            'fee_types' => ['user_id', 'name', 'category'],
            'document_templates' => ['user_id', 'category', 'status'],
            'document_sequences' => ['user_id', 'category', 'sequence'],
            'transactions' => ['invoice_id', 'payment_method', 'transaction_ref', 'receipt_no', 'document_template_id', 'payment_date', 'approved_by'],
            'wallets' => ['user_id'], 'wallet_transactions' => ['wallet_id', 'type', 'reference_id', 'remarks'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($table, $columns) {
                $t->string('id')->primary(); $t->timestamps();
                foreach ($columns as $column) $t->text($column)->nullable();
                if (in_array($table, ['invoices', 'transactions'])) $t->softDeletes();
                foreach (match ($table) {
                    'invoices' => ['total_amount', 'amount_paid', 'amount_balance'],
                    'transactions' => ['amount_paid', 'amount_applied', 'amount_excess'],
                    'invoice_items', 'wallet_transactions' => ['amount'], 'wallets' => ['balance'], default => [],
                } as $column) $t->bigInteger($column)->default(0);
                if ($table === 'fee_types') { $t->boolean('is_system')->default(true); $t->boolean('is_active')->default(true); }
            });
        }
        Schema::create('settings', function (Blueprint $t) {
            $t->id(); $t->string('user_id'); $t->string('key'); $t->text('value'); $t->boolean('is_active'); $t->timestamps();
        });
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00', 'Asia/Kuala_Lumpur'));
        DB::table('users')->insert([
            ['id' => 'admin', 'role' => 'ownerAdmin', 'name' => 'Manager', 'email' => 'manager@test.test'],
            ['id' => 'tenant', 'role' => 'tenant', 'name' => 'Tenant', 'email' => 'tenant@test.test'],
        ]);
        DB::table('tenants')->insert(['id' => 'tenant-record', 'user_id' => 'tenant']);
        DB::table('properties')->insert(['id' => 'property', 'owner_id' => 'admin', 'created_by' => 'admin']);
        DB::table('leases')->insert(['id' => 'lease', 'tenant_id' => 'tenant-record', 'leasable_type' => Property::class, 'leasable_id' => 'property']);
        DB::table('document_templates')->insert([
            ['id' => 'invoice-template', 'user_id' => 'admin', 'category' => 'invoice', 'status' => 'active'],
            ['id' => 'receipt-template', 'user_id' => 'admin', 'category' => 'receipt', 'status' => 'active'],
        ]);
        DB::table('fee_types')->insert([
            ['id' => 'rent', 'name' => 'Rental', 'category' => 'rent'],
            ['id' => 'penalty', 'name' => 'Late Payment Penalty', 'category' => 'service'],
        ]);
        DB::table('invoices')->insert([
            'id' => 'original', 'lease_id' => 'lease', 'context' => 'tenant', 'type' => 'rent', 'invoice_no' => 'INV-TEST',
            'document_template_id' => 'invoice-template', 'period' => '2026-10-01', 'due_date' => '2026-10-20',
            'total_amount' => 10000, 'amount_paid' => 0, 'amount_balance' => 10000, 'status' => 'unpaid', 'created_at' => '2026-10-01 00:00:00',
        ]);
        DB::table('invoice_items')->insert(['id' => 'item', 'invoice_id' => 'original', 'fee_type_id' => 'rent', 'amount' => 10000]);
        $this->setting();
        $this->actingAs(User::find('admin'));
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function setting(array $changes = [], bool $active = true): void
    {
        DB::table('settings')->updateOrInsert(['user_id' => 'admin', 'key' => 'late_penalty_config'], [
            'value' => json_encode(array_merge(['amount' => 1000, 'grace_period_days' => 0, 'frequency' => 3,
                'calculation_type' => 'fixed', 'maximum_amount' => 0, 'applicable_categories' => ['rent']], $changes)),
            'is_active' => $active,
        ]);
    }

    private function pay(Invoice $invoice, float $amount, string $date = '2026-10-20', array $extra = []): void
    {
        // Exercise real invoice/payment/wallet writes; suppress only audit/notification observers.
        Model::withoutEvents(fn () => app(InvoiceService::class)->recordPayment($invoice, array_merge([
            'amount_paid' => $amount, 'payment_date' => $date, 'payment_method' => 'Cash', 'transaction_ref' => 'test',
        ], $extra)));
    }

    public function test_preview_and_collection_share_dates_and_ignore_client_penalty_amount(): void
    {
        $invoice = Invoice::find('original');
        $request = Request::create('/preview', 'POST', ['payment_date' => '2026-10-23', 'due_date' => '2099-01-01', 'total_amount' => 0]);
        $response = app(SettingsController::class)->calculatePenaltyPreview($request, $invoice, app(InvoicePenaltyService::class));
        $this->assertEquals(20, $response->getData(true)['amount']);
        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, DB::table('transactions')->count());
        $this->pay($invoice, 120, '2026-10-23', ['penalty_details' => ['amount' => 9999]]);
        $penalty = Invoice::where('parent_id', 'original')->sole();
        $this->assertSame(2000, $penalty->total_amount);
        $this->assertSame(0, $penalty->amount_balance);
        $this->assertSame('paid', Invoice::find('original')->status);
        $this->assertStringContainsString('start=2026-10-20; through=2026-10-23; frequency=3; charges=2', $penalty->remarks);
        $this->assertSame(12000, (int) DB::table('transactions')->sum('amount_paid'));
    }

    public function test_repeated_preview_is_read_only_and_stale_payment_retry_cannot_charge_again(): void
    {
        $staleInvoice = Invoice::find('original');
        $quotes = app(InvoicePenaltyService::class);
        $this->assertEquals($quotes->quote($staleInvoice, '2026-10-20'), $quotes->quote($staleInvoice, '2026-10-20'));
        $this->assertSame(1, Invoice::count());
        $this->pay($staleInvoice, 110);
        for ($i = 0; $i < 2; $i++) {
            try { $this->pay($staleInvoice, 110); $this->fail('Retry must be rejected before any ledger write.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('invoice', $e->errors()); }
        }
        $this->assertSame(1, Invoice::where('parent_id', 'original')->count());
        $this->assertSame(2, DB::table('transactions')->count());
        $this->assertSame(11000, (int) DB::table('transactions')->sum('amount_paid'));
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertNull($quotes->quote(Invoice::find('original'), '2026-10-30'));
    }

    public function test_partial_balance_percentage_cap_and_full_payment_requirement(): void
    {
        DB::table('invoices')->where('id', 'original')->update(['amount_paid' => 5000, 'amount_balance' => 5000, 'status' => 'partial']);
        $this->setting(['calculation_type' => 'percentage', 'rate' => 10, 'maximum_amount' => 800]);
        $this->assertEquals(5, app(InvoicePenaltyService::class)->quote(Invoice::find('original'), '2026-10-20')['amount']);
        $this->assertEquals(8, app(InvoicePenaltyService::class)->quote(Invoice::find('original'), '2026-10-23')['amount']);
        try { $this->pay(Invoice::find('original'), 50, '2026-10-23'); $this->fail('Penalty requires full payment.'); }
        catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { $this->assertSame(302, $e->getResponse()->getStatusCode()); }
        $this->assertSame(5000, Invoice::find('original')->amount_balance);
        $this->assertSame(1, Invoice::count());
        $this->pay(Invoice::find('original'), 58, '2026-10-23');
        $this->assertSame(800, Invoice::where('parent_id', 'original')->sole()->total_amount);
        $this->assertSame(10000, Invoice::find('original')->amount_paid);
    }

    public function test_disabled_empty_and_nonmatching_categories_do_not_apply_penalties(): void
    {
        foreach ([[[], false], [['applicable_categories' => []], true], [['applicable_categories' => ['deposit']], true]] as [$settings, $active]) {
            $this->setting($settings, $active);
            $this->assertNull(app(InvoicePenaltyService::class)->quote(Invoice::find('original'), '2026-10-30'));
        }
        // A forged penalty cannot override the selected fee categories; ordinary partial payment remains allowed.
        $this->pay(Invoice::find('original'), 20, '2026-10-23', ['penalty_details' => ['amount' => 10]]);
        $this->assertSame(8000, Invoice::find('original')->amount_balance);
        $this->assertSame('partial', Invoice::find('original')->status);
        $this->assertSame(0, Invoice::where('type', 'penalty')->count());
    }

    public function test_legacy_penalties_are_not_recalculated_even_when_voided_or_deleted(): void
    {
        DB::table('invoices')->insert(['id' => 'old-penalty', 'parent_id' => 'original', 'type' => 'penalty',
            'total_amount' => 500, 'amount_paid' => 500, 'amount_balance' => 0, 'status' => 'paid', 'remarks' => 'Generated via late penalty policy']);
        foreach ([['status' => 'paid'], ['status' => 'void'], ['deleted_at' => now()]] as $change) {
            DB::table('invoices')->where('id', 'old-penalty')->update($change);
            try { $this->pay(Invoice::find('original'), 120, '2026-10-23'); $this->fail('Existing coverage must be reviewed.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('penalty', $e->errors()); }
            $this->assertSame(2, Invoice::withTrashed()->count());
            $this->assertSame(0, DB::table('transactions')->count());
            $this->assertSame(500, Invoice::withTrashed()->find('old-penalty')->total_amount);
            $this->assertSame(10000, Invoice::find('original')->amount_balance);
        }
    }

    public function test_wallet_and_overpayment_allocation_and_retry_are_preserved(): void
    {
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 5000]);
        $invoice = Invoice::find('original');
        $this->pay($invoice, 70, '2026-10-20', ['use_wallet' => true]);
        $this->assertSame(1000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(2, DB::table('wallet_transactions')->count());
        try { $this->pay($invoice, 70, '2026-10-20', ['use_wallet' => true]); $this->fail('Do not debit wallet on retry.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('invoice', $e->errors()); }
        $this->assertSame(1000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(2, DB::table('wallet_transactions')->count());
    }

    public function test_receipt_failure_rolls_back_penalty_parent_and_wallet_then_retry_succeeds(): void
    {
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 11000]);
        DB::unprepared("CREATE TRIGGER fail_receipt BEFORE INSERT ON transactions WHEN NEW.invoice_id = 'original' BEGIN SELECT RAISE(ABORT, 'test receipt failure'); END");
        try { $this->pay(Invoice::find('original'), 0, '2026-10-20', ['use_wallet' => true]); $this->fail('Receipt should fail.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('test receipt failure', $e->getMessage()); }
        $this->assertSame(1, Invoice::count());
        $this->assertSame(10000, Invoice::find('original')->amount_balance);
        $this->assertSame(11000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame(0, DB::table('document_sequences')->count());
        DB::unprepared('DROP TRIGGER fail_receipt');
        $this->pay(Invoice::find('original'), 0, '2026-10-20', ['use_wallet' => true]);
        $this->assertSame(1, Invoice::where('parent_id', 'original')->count());
        $this->assertSame(0, (int) DB::table('wallets')->value('balance'));
    }

    public function test_paid_void_and_penalty_invoices_do_not_accrue_another_penalty(): void
    {
        foreach ([['amount_balance' => 0], ['status' => 'void'], ['type' => 'penalty']] as $attributes) {
            $invoice = Invoice::find('original'); $invoice->fill($attributes);
            $this->assertNull(app(InvoicePenaltyService::class)->quote($invoice, '2026-10-30'));
        }
    }

    public function test_invoice_manager_settings_are_used_instead_of_the_current_operators_settings(): void
    {
        DB::table('users')->insert(['id' => 'operator', 'role' => 'agentAdmin', 'name' => 'Operator', 'email' => 'operator@test.test']);
        DB::table('settings')->insert(['user_id' => 'operator', 'key' => 'late_penalty_config', 'value' => json_encode(['amount' => 99900]), 'is_active' => true]);
        $this->actingAs(User::find('operator'));
        $this->assertEquals(10, app(InvoicePenaltyService::class)->quote(Invoice::find('original'), '2026-10-20')['amount']);
        DB::table('properties')->where('id', 'property')->update(['created_by' => 'operator']);
        $this->expectException(ValidationException::class);
        app(InvoicePenaltyService::class)->quote(Invoice::find('original'), '2026-10-20');
    }

    public function test_unavailable_system_penalty_fee_blocks_preview_and_payment_without_writes(): void
    {
        foreach (['missing', 'inactive', 'private', 'wrong-category', 'duplicate', 'manager-disabled'] as $case) {
            DB::table('fee_types')->where('name', 'Late Payment Penalty')->delete();
            DB::table('settings')->where('key', 'fee_types_config')->delete();
            if ($case !== 'missing') {
                DB::table('fee_types')->insert(['id' => 'penalty', 'name' => 'Late Payment Penalty',
                    'category' => $case === 'wrong-category' ? 'penalty' : 'service',
                    'user_id' => 'other-manager', 'is_system' => $case !== 'private', 'is_active' => $case !== 'inactive']);
            }
            if ($case === 'duplicate') DB::table('fee_types')->insert(['id' => 'duplicate', 'name' => 'Late Payment Penalty', 'category' => 'service', 'is_system' => true, 'is_active' => true]);
            if ($case === 'manager-disabled') DB::table('settings')->insert(['user_id' => 'admin', 'key' => 'fee_types_config', 'is_active' => true, 'value' => json_encode(['service_late_payment_penalty' => ['is_active' => false]])]);
            $invoice = Invoice::find('original');
            $before = $invoice->getAttributes();
            $request = Request::create('/preview', 'POST', ['payment_date' => '2026-10-20']);
            $request->headers->set('Accept', 'application/json');
            foreach (['preview', 'payment'] as $action) {
                try {
                    if ($action === 'preview') app(SettingsController::class)->calculatePenaltyPreview($request, $invoice, app(InvoicePenaltyService::class));
                    else $this->pay($invoice, 110);
                    $this->fail("$case must block $action.");
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey('penalty', $e->errors());
                    $response = app(\Illuminate\Contracts\Debug\ExceptionHandler::class)->render($request, $e);
                    $this->assertSame(422, $response->getStatusCode());
                    $this->assertStringContainsString('Ask the system administrator', $response->getContent());
                }
            }
            $this->assertSame($before, $invoice->fresh()->getAttributes());
            $this->assertSame(1, Invoice::count());
            $this->assertSame(0, DB::table('transactions')->count());
            $this->assertSame(0, DB::table('wallet_transactions')->count());
            $this->assertSame(0, DB::table('document_sequences')->count());
        }
    }

    public function test_system_fee_is_used_instead_of_another_managers_private_fee_and_is_rechecked(): void
    {
        DB::table('fee_types')->insert(['id' => 'private', 'user_id' => 'other-manager', 'name' => 'Late Payment Penalty', 'category' => 'service', 'is_system' => false, 'is_active' => true]);
        $invoice = Invoice::find('original');
        $this->assertSame('penalty', app(InvoicePenaltyService::class)->quote($invoice, '2026-10-20')['fee_type_id']);
        DB::table('fee_types')->where('id', 'penalty')->update(['is_active' => false]);
        try { $this->pay($invoice, 110); $this->fail('A stale preview must not bypass the fee check.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('penalty', $e->errors()); }
        $this->assertSame(0, DB::table('transactions')->count());
        DB::table('fee_types')->where('id', 'penalty')->update(['is_active' => true]);
        $this->pay($invoice, 110);
        $child = Invoice::where('parent_id', 'original')->sole();
        $this->assertSame('penalty', $child->items->sole()->fee_type_id);
    }

    public function test_old_five_hundred_ringgit_invoice_collects_capped_penalty_once(): void
    {
        $this->setting(['frequency' => 1, 'maximum_amount' => 10000]);
        DB::table('invoices')->where('id', 'original')->update(['total_amount' => 50000, 'amount_paid' => 10000, 'amount_balance' => 40000, 'status' => 'partial', 'due_date' => '2026-09-21']);
        $invoice = Invoice::find('original');
        $quote = app(InvoicePenaltyService::class)->quote($invoice, '2026-10-12');
        $this->assertEquals(100, $quote['amount']);
        $this->assertSame(22, $quote['periods']);
        $this->pay($invoice, 500, '2026-10-12');
        $this->assertSame(50000, $invoice->fresh()->amount_paid);
        $this->assertSame(0, $invoice->fresh()->amount_balance);
        $this->assertSame(10000, Invoice::where('parent_id', 'original')->sole()->total_amount);
        try { $this->pay($invoice, 500, '2026-10-12'); $this->fail('Duplicate payment must fail.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('invoice', $e->errors()); }
        $this->assertSame(2, DB::table('transactions')->count());
        $this->assertSame(1, Invoice::where('parent_id', 'original')->count());
    }

    public function test_missing_fee_form_submission_returns_actionable_page_error(): void
    {
        DB::table('fee_types')->where('id', 'penalty')->delete();
        \Illuminate\Support\Facades\Gate::shouldReceive('denies')->once()->with('owner-admin')->andReturn(false);
        $request = \Mockery::mock(\App\Http\Requests\Invoice\RecordPaymentRequest::class)->makePartial();
        $request->initialize();
        $request->shouldReceive('validated')->once()->andReturn(['payment_date' => '2026-10-20', 'amount_paid' => 110, 'payment_method' => 'Cash']);
        $response = app(\App\Http\Controllers\InvoiceController::class)->recordPayment($request, Invoice::find('original'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('Ask the system administrator', session('error'));
        $this->assertTrue(session('errors')->has('penalty'));
        $this->assertSame(0, DB::table('transactions')->count());
    }

    public function test_payment_and_settings_blades_compile(): void
    {
        foreach (['components.modals.payment-modal', 'adminSide.setting.index'] as $view) {
            $php = app('blade.compiler')->compileString(file_get_contents(view($view)->getPath()));
            $this->assertNotEmpty(token_get_all($php, TOKEN_PARSE));
        }
    }
}
