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
            'leases' => ['tenant_id', 'leasable_type', 'leasable_id', 'parent_lease_id'],
            'invoices' => ['parent_id', 'lease_id', 'user_id', 'context', 'type', 'billable_type', 'billable_id', 'invoice_no', 'document_template_id', 'period', 'due_date', 'status', 'remarks'],
            'invoice_items' => ['invoice_id', 'fee_type_id', 'lease_charge_id', 'description'],
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
        Schema::create('refunds', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('lease_id'); $t->string('status');
            foreach (['deposit_collected_cents', 'wallet_refund_cents', 'gross_amount_cents', 'auto_deduction_cents', 'manual_deduction_cents', 'deduction_amount_cents', 'net_amount_cents'] as $column) {
                $t->bigInteger($column)->default(0);
            }
            $t->string('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->timestamps();
        });
        Schema::table('wallets', function (Blueprint $t) {
            $t->string('reserved_refund_id')->nullable();
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
        $this->assertSame('Late penalty for invoice INV-TEST', $penalty->remarks);
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
        try { $this->pay(Invoice::find('original'), 0, '2026-10-20', ['use_wallet' => true, 'payment_method' => 'Wallet', 'transaction_ref' => null]); $this->fail('Receipt should fail.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('test receipt failure', $e->getMessage()); }
        $this->assertSame(1, Invoice::count());
        $this->assertSame(10000, Invoice::find('original')->amount_balance);
        $this->assertSame(11000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame(0, DB::table('document_sequences')->count());
        DB::unprepared('DROP TRIGGER fail_receipt');
        $this->pay(Invoice::find('original'), 0, '2026-10-20', ['use_wallet' => true, 'payment_method' => 'Wallet', 'transaction_ref' => null]);
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

    public function test_payment_methods_and_references_survive_cash_bank_wallet_and_mixed_collection(): void
    {
        // Each case starts with independent persisted balances; no production DB or migrations.
        foreach ([
            ['Cash', 100, false, 'CASH', 5000, 5000],
            ['Bank Transfer', 100, false, 'BANK-123', 5000, 5000],
            ['Wallet', 0, true, null, 10000, 0],
            ['Cash', 50, true, 'CASH', 5000, 0],
            ['Bank Transfer', 50, true, 'MIXED-123', 5000, 0],
        ] as [$method, $external, $useWallet, $reference, $balance, $remaining]) {
            DB::table('transactions')->delete(); DB::table('wallet_transactions')->delete();
            DB::table('wallets')->updateOrInsert(['id' => 'wallet'], ['user_id' => 'tenant', 'balance' => $balance]);
            DB::table('invoices')->where('id', 'original')->update(['amount_paid' => 0, 'amount_balance' => 10000, 'status' => 'unpaid']);
            $data = ['payment_method' => $method, 'amount_paid' => $external, 'use_wallet' => $useWallet, 'transaction_ref' => $reference, 'payment_date' => '2026-10-19'];
            $request = \App\Http\Requests\Invoice\RecordPaymentRequest::create('/payment', 'POST', $data);
            $validator = \Illuminate\Support\Facades\Validator::make($data, $request->rules());
            $this->assertTrue($validator->passes(), $validator->errors()->toJson());
            Model::withoutEvents(fn () => app(InvoiceService::class)->recordPayment(Invoice::find('original'), $validator->validated()));
            $transaction = DB::table('transactions')->sole();
            $this->assertSame($method, $transaction->payment_method);
            $this->assertSame($reference, $transaction->transaction_ref);
            $this->assertSame(10000, (int) $transaction->amount_applied);
            $this->assertNotEmpty($transaction->receipt_no);
            $this->assertSame($remaining, (int) DB::table('wallets')->value('balance'));
            $this->assertSame($useWallet ? -$balance : 0, (int) DB::table('wallet_transactions')->sum('amount'));
            $this->assertSame(0, Invoice::find('original')->amount_balance);
        }
    }

    public function test_conflicting_wallet_requests_are_rejected_without_partial_writes(): void
    {
        $base = ['amount_paid' => 0, 'payment_date' => '2026-10-20', 'payment_method' => 'Wallet', 'use_wallet' => true, 'transaction_ref' => null];
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 11000]);
        foreach ([['use_wallet' => false], ['amount_paid' => 20], ['transaction_ref' => 'CASH'], ['transaction_ref' => 'BANK-OLD'], ['payment_method' => 'Cash']] as $changes) {
            $data = array_merge($base, $changes);
            $request = \App\Http\Requests\Invoice\RecordPaymentRequest::create('/payment', 'POST', $data);
            $this->assertFalse(\Illuminate\Support\Facades\Validator::make($data, $request->rules())->passes());
            try {
                Model::withoutEvents(fn () => app(InvoiceService::class)->recordPayment(Invoice::find('original'), $data));
                $this->fail('Contradictory request must fail even when bypassing FormRequest.');
            } catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
            $this->assertSame(0, DB::table('transactions')->count());
            $this->assertSame(0, DB::table('wallet_transactions')->count());
            $this->assertSame(0, DB::table('document_sequences')->count());
            $this->assertSame(1, Invoice::count());
            $this->assertSame(10000, Invoice::find('original')->amount_balance);
            $this->assertSame(11000, (int) DB::table('wallets')->value('balance'));
        }
        foreach (['empty', 'missing', 'no-tenant'] as $case) {
            DB::table('wallets')->where('id', 'wallet')->update(['balance' => 0]);
            if ($case === 'missing') DB::table('wallets')->delete();
            if ($case === 'no-tenant') DB::table('leases')->where('id', 'lease')->update(['tenant_id' => null]);
            try {
                Model::withoutEvents(fn () => app(InvoiceService::class)->recordPayment(Invoice::find('original'), $base));
                $this->fail('Wallet label requires actual available Wallet funds.');
            } catch (ValidationException $e) { $this->assertArrayHasKey('use_wallet', $e->errors()); }
            $this->assertSame(0, DB::table('transactions')->count());
            $this->assertSame(0, DB::table('wallet_transactions')->count());
            $this->assertSame(0, DB::table('document_sequences')->count());
            $this->assertSame(1, Invoice::count());
            $this->assertSame(10000, Invoice::find('original')->amount_balance);
        }
    }

    public function test_payment_and_settings_blades_compile(): void
    {
        foreach (['components.modals.payment-modal', 'adminSide.setting.index'] as $view) {
            $php = app('blade.compiler')->compileString(file_get_contents(view($view)->getPath()));
            $this->assertNotEmpty(token_get_all($php, TOKEN_PARSE));
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refundPaymentStages')]
    public function test_later_invoice_payment_refreshes_active_refunds_only(string $stage, int $payment): void
    {
        $this->refundFixture($stage);
        $this->pay(Invoice::findOrFail('original'), $payment / 100);

        $refund = \App\Models\Refund::findOrFail('refund');
        $completed = $stage === 'paid';
        $balance = $completed ? 10000 : 10000 - $payment;
        $this->assertSame($balance, $refund->auto_deduction_cents);
        $this->assertSame(5000, $refund->manual_deduction_cents);
        $this->assertSame(100000 - 5000 - $balance, $refund->net_amount_cents);
        $this->assertSame($stage === 'approved' ? 'pending' : $stage, $refund->status->value);
        $this->assertSame(5000, (int) DB::table('refund_items')->where('id', 'manual')->value('amount_cents'));
        $automatic = DB::table('refund_items')->where('refund_id', 'refund')->where('origin', 'automatic_invoice');
        $this->assertSame($balance > 0 ? 1 : 0, $automatic->count());
        $this->assertSame($balance, (int) $automatic->sum('amount_cents'));
        if ($stage === 'approved') {
            $this->assertNull($refund->approved_by);
            $this->assertNull($refund->approved_at);
        }
    }

    public static function refundPaymentStages(): array
    {
        $cases = [];
        foreach (['draft', 'pending', 'paid'] as $stage) {
            foreach ([4000, 10000] as $amount) {
                $cases[$stage.'-'.$amount] = [$stage, $amount];
            }
        }
        return $cases;
    }

    public function test_approved_refund_blocks_external_and_wallet_payments_without_changing_records(): void
    {
        $this->refundFixture('approved');
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 100000]);
        foreach ([
            ['amount_paid' => 100, 'payment_method' => 'Cash', 'use_wallet' => false],
            ['amount_paid' => 0, 'payment_method' => 'Wallet', 'use_wallet' => true],
        ] as $data) {
            try {
                $this->pay(Invoice::findOrFail('original'), $data['amount_paid'], extra: $data);
                $this->fail('Approved refund must block separate invoice payments.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'This invoice is approved for deduction in the refund and cannot be paid separately.',
                    $exception->errors()['refund'][0]
                );
            }
        }
        $this->assertSame(10000, Invoice::findOrFail('original')->amount_balance);
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertSame(100000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame('approved', \App\Models\Refund::findOrFail('refund')->status->value);
        $this->assertSame(10000, (int) DB::table('refund_items')->where('origin', 'automatic_invoice')->sum('amount_cents'));
    }

    public function test_payment_modal_preview_reports_approved_refund_lock_before_collecting_payment(): void
    {
        $this->refundFixture('approved');
        $request = Request::create('/preview', 'POST', ['payment_date' => '2026-10-20']);
        try {
            app(SettingsController::class)->calculatePenaltyPreview($request, Invoice::findOrFail('original'), app(InvoicePenaltyService::class));
            $this->fail('Opening the payment modal must report the approved refund lock.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame('approved', \App\Models\Refund::findOrFail('refund')->status->value);
    }

    public function test_deposit_paid_after_pending_updates_collected_deposit_and_removes_deduction(): void
    {
        $this->refundFixture('pending');
        DB::table('invoices')->where('id', 'original')->update(['status' => 'paid', 'amount_balance' => 0]);
        DB::table('invoices')->where('id', 'deposit-invoice')->update(['status' => 'unpaid', 'amount_balance' => 100000, 'amount_paid' => 0]);
        DB::table('refunds')->where('id', 'refund')->update([
            'deposit_collected_cents' => 0, 'gross_amount_cents' => 0,
            'auto_deduction_cents' => 100000, 'deduction_amount_cents' => 105000, 'net_amount_cents' => 0,
        ]);
        DB::table('refund_items')->where('id', 'automatic')->update([
            'invoice_id' => 'deposit-invoice', 'invoice_item_id' => 'deposit-item',
            'amount_cents' => 100000, 'unit_amount_cents' => 100000,
        ]);

        $this->pay(Invoice::findOrFail('deposit-invoice'), 1000);

        $refund = \App\Models\Refund::findOrFail('refund');
        $this->assertSame(100000, $refund->deposit_collected_cents);
        $this->assertSame(0, $refund->auto_deduction_cents);
        $this->assertSame(95000, $refund->net_amount_cents);
        $this->assertSame(0, DB::table('refund_items')->where('origin', 'automatic_invoice')->count());
        $this->assertSame(1, DB::table('refund_items')->where('id', 'manual')->count());
    }

    public function test_payout_recheck_commits_updated_settlement_and_requires_approval_again(): void
    {
        $this->refundFixture('approved');
        DB::table('invoices')->where('id', 'original')->update(['status' => 'paid', 'amount_balance' => 0]);
        try {
            Model::withoutEvents(fn () => app(\App\Services\RefundService::class)->processDisbursement(
                \App\Models\Refund::findOrFail('refund'), [], User::findOrFail('admin')
            ));
            $this->fail('A changed settlement must not be paid.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $refund = \App\Models\Refund::findOrFail('refund');
        $this->assertSame('pending', $refund->status->value);
        $this->assertSame(95000, $refund->net_amount_cents);
        $this->assertSame(0, $refund->auto_deduction_cents);
        $this->assertSame(0, DB::table('transactions')->count());
    }

    public function test_approval_rechecks_old_pending_figures_before_approving(): void
    {
        $this->refundFixture('pending');
        DB::table('invoices')->where('id', 'original')->update(['status' => 'paid', 'amount_balance' => 0]);
        try {
            Model::withoutEvents(fn () => app(\App\Services\RefundService::class)->approveRefund(
                \App\Models\Refund::findOrFail('refund'), User::findOrFail('admin')
            ));
            $this->fail('Updated figures must be reviewed before approval.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $refund = \App\Models\Refund::findOrFail('refund');
        $this->assertSame('pending', $refund->status->value);
        $this->assertSame(95000, $refund->net_amount_cents);
    }

    public function test_unchanged_settlement_can_be_approved_and_paid_without_rewriting_its_deductions(): void
    {
        $this->refundFixture('pending');
        Schema::table('leases', function (Blueprint $t) {
            $t->string('status')->nullable(); $t->date('checked_out_at')->nullable(); $t->string('refund_document_id')->nullable();
        });
        Schema::table('refunds', function (Blueprint $t) {
            $t->string('paid_by')->nullable(); $t->timestamp('paid_at')->nullable();
        });
        Schema::create('refund_disbursements', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('refund_id'); $t->bigInteger('amount_cents');
            $t->string('bank_detail_id')->nullable(); $t->string('method'); $t->string('reference')->nullable();
            $t->string('status'); $t->timestamp('processed_at'); $t->timestamps();
        });
        DB::table('leases')->where('id', 'lease')->update(['leasable_type' => null, 'leasable_id' => null]);
        DB::table('invoices')->where('id', 'original')->update(['due_date' => null]);
        Model::withoutEvents(function () {
            $service = app(\App\Services\RefundService::class);
            $refund = $service->approveRefund(\App\Models\Refund::findOrFail('refund'), User::findOrFail('admin'));
            $this->assertSame('approved', $refund->status->value);
            $service->processDisbursement($refund, [
                // Payout must use the server's approved net amount.
                'amount_cents' => 1, 'checked_out_at' => '2026-10-30',
                'refund_document_id' => 'template', 'method' => 'bank_transfer',
            ], User::findOrFail('admin'));
        });
        $this->assertSame('paid', \App\Models\Refund::findOrFail('refund')->status->value);
        $this->assertSame('paid', Invoice::findOrFail('original')->status);
        $this->assertSame(85000, (int) DB::table('refund_disbursements')->sum('amount_cents'));
        $this->assertSame(10000, (int) DB::table('refund_items')->where('origin', 'automatic_invoice')->sum('amount_cents'));
    }

    public function test_payment_on_parent_lease_refreshes_deposit_on_renewed_lease_refund(): void
    {
        $this->refundFixture('pending');
        DB::table('leases')->insert(['id' => 'renewed', 'parent_lease_id' => 'lease']);
        DB::table('refunds')->where('id', 'refund')->update(['lease_id' => 'renewed']);
        // The earlier lease's outstanding invoice is not a deduction on the renewal.
        DB::table('refund_items')->where('id', 'automatic')->delete();
        DB::table('refunds')->where('id', 'refund')->update([
            'auto_deduction_cents' => 0, 'deduction_amount_cents' => 5000, 'net_amount_cents' => 95000,
        ]);
        DB::table('invoices')->where('id', 'original')->update(['status' => 'paid', 'amount_balance' => 0]);
        DB::table('invoices')->where('id', 'deposit-invoice')->update(['status' => 'unpaid', 'amount_balance' => 100000, 'amount_paid' => 0]);
        DB::table('refunds')->where('id', 'refund')->update([
            'deposit_collected_cents' => 0, 'gross_amount_cents' => 0, 'net_amount_cents' => 0,
        ]);

        $this->pay(Invoice::findOrFail('deposit-invoice'), 1000);

        $refund = \App\Models\Refund::findOrFail('refund');
        $this->assertSame(100000, $refund->deposit_collected_cents);
        $this->assertSame(95000, $refund->net_amount_cents);
        $this->assertSame('pending', $refund->status->value);
    }

    public function test_wallet_is_added_to_draft_without_spending_it(): void
    {
        $this->refundFixture('draft');
        Schema::table('leases', function (Blueprint $t) { $t->string('status')->nullable(); });
        Schema::table('refunds', function (Blueprint $t) {
            $t->string('type')->nullable(); $t->string('reason')->nullable(); $t->string('internal_notes')->nullable();
        });
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 20000]);
        $refund = Model::withoutEvents(fn () => app(\App\Services\RefundService::class)->saveAsDraft(
            \App\Models\Lease::findOrFail('lease'), [], [
                ['type' => 'cleaning', 'amount_cents' => 5000, 'qty' => 1, 'description' => 'Cleaning'],
            ]
        ));
        $this->assertSame(20000, $refund->wallet_refund_cents);
        $this->assertSame(120000, $refund->gross_amount_cents);
        $this->assertSame(105000, $refund->net_amount_cents);
        $this->assertSame(20000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    public function test_approval_reserves_wallet_and_blocks_spending_on_other_invoices(): void
    {
        $this->refundFixture('pending');
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 20000]);
        Model::withoutEvents(function () {
            $service = app(\App\Services\RefundService::class);
            $service->refreshSettlement(\App\Models\Refund::findOrFail('refund'));
            $service->approveRefund(\App\Models\Refund::findOrFail('refund'), User::findOrFail('admin'));
        });
        $this->assertSame('refund', DB::table('wallets')->value('reserved_refund_id'));
        $this->assertSame(20000, app(\App\Services\WalletService::class)->refundableBalance('tenant', 'refund'));
        $this->assertSame(0, app(\App\Services\WalletService::class)->refundableBalance('tenant', 'another-refund'));
        try {
            Model::withoutEvents(fn () => app(\App\Services\WalletService::class)->debit('tenant', 10000, 'invoice_payment', 'other-invoice'));
            $this->fail('Reserved wallet funds must not be spent.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('use_wallet', $exception->errors());
        }
        $this->assertSame(20000, (int) DB::table('wallets')->value('balance'));
    }

    public function test_wallet_change_before_payout_returns_refund_for_review_and_releases_reservation(): void
    {
        $this->refundFixture('pending');
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 20000]);
        Model::withoutEvents(function () {
            $service = app(\App\Services\RefundService::class);
            $service->refreshSettlement(\App\Models\Refund::findOrFail('refund'));
            $service->approveRefund(\App\Models\Refund::findOrFail('refund'), User::findOrFail('admin'));
            app(\App\Services\WalletService::class)->credit('tenant', 5000, 'overpayment_credit', 'other-invoice');
            try {
                $service->processDisbursement(\App\Models\Refund::findOrFail('refund'), [], User::findOrFail('admin'));
                $this->fail('Changed wallet funds must be reviewed before payout.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
        });
        $refund = \App\Models\Refund::findOrFail('refund');
        $this->assertSame('pending', $refund->status->value);
        $this->assertSame(25000, $refund->wallet_refund_cents);
        $this->assertSame(110000, $refund->net_amount_cents);
        $this->assertNull(DB::table('wallets')->value('reserved_refund_id'));
        $this->assertSame(25000, (int) DB::table('wallets')->value('balance'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('walletSettlementAmounts')]
    public function test_checkout_pays_wallet_once_and_can_use_it_to_settle_invoices(int $deposit, int $wallet, int $expectedRefund): void
    {
        $this->refundFixture('pending');
        DB::table('lease_charges')->where('id', 'deposit-charge')->update(['amount' => $deposit]);
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => $wallet]);
        Schema::table('leases', function (Blueprint $t) {
            $t->string('status')->nullable(); $t->date('checked_out_at')->nullable(); $t->string('refund_document_id')->nullable();
        });
        Schema::table('refunds', function (Blueprint $t) {
            $t->string('paid_by')->nullable(); $t->timestamp('paid_at')->nullable();
        });
        Schema::create('refund_disbursements', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('refund_id'); $t->bigInteger('amount_cents');
            $t->string('bank_detail_id')->nullable(); $t->string('method'); $t->string('reference')->nullable();
            $t->string('status'); $t->timestamp('processed_at'); $t->timestamps();
        });
        DB::table('leases')->where('id', 'lease')->update(['leasable_type' => null, 'leasable_id' => null]);
        DB::table('invoices')->where('id', 'original')->update(['due_date' => null]);
        Model::withoutEvents(function () use ($expectedRefund) {
            $service = app(\App\Services\RefundService::class);
            $service->refreshSettlement(\App\Models\Refund::findOrFail('refund'));
            $refund = $service->approveRefund(\App\Models\Refund::findOrFail('refund'), User::findOrFail('admin'));
            $this->assertSame($expectedRefund, $refund->net_amount_cents);
            $service->processDisbursement($refund, [
                'checked_out_at' => '2026-10-30', 'refund_document_id' => 'template', 'method' => 'bank_transfer',
            ], User::findOrFail('admin'));
            try {
                $service->processDisbursement($refund, [], User::findOrFail('admin'));
                $this->fail('Duplicate payout must fail.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
        });
        $this->assertSame('paid', \App\Models\Refund::findOrFail('refund')->status->value);
        $this->assertSame('paid', Invoice::findOrFail('original')->status);
        $this->assertSame(0, (int) DB::table('wallets')->value('balance'));
        $this->assertNull(DB::table('wallets')->value('reserved_refund_id'));
        $this->assertSame(-$wallet, (int) DB::table('wallet_transactions')->where('type', 'checkout_refund')->sum('amount'));
        $this->assertSame(1, DB::table('wallet_transactions')->where('type', 'checkout_refund')->count());
        $this->assertSame($expectedRefund, (int) DB::table('refund_disbursements')->sum('amount_cents'));
        Model::withoutEvents(fn () => app(\App\Services\WalletService::class)->credit('tenant', 1000, 'manual_credit', 'later'));
        Model::withoutEvents(fn () => app(\App\Services\RefundService::class)->refreshSettlement(\App\Models\Refund::findOrFail('refund')));
        $this->assertSame($wallet, \App\Models\Refund::findOrFail('refund')->wallet_refund_cents);
    }

    public static function walletSettlementAmounts(): array
    {
        return ['deposit-and-wallet' => [100000, 20000, 105000], 'wallet-covers-invoice' => [0, 20000, 5000]];
    }

    public function test_refund_statement_table_includes_wallet_credit_and_checkout_blade_compiles(): void
    {
        $html = app(\App\Services\DocumentTemplateService::class)->replaceRefundTable(
            '<table><tbody id="dynamic-refund-tbody"></tbody></table>', [],
            ['total_deposit_collected' => '1,000.00', 'wallet_refund_amount' => '200.00']
        );
        $this->assertStringContainsString('Wallet Balance to Refund', $html);
        $this->assertStringContainsString('RM 200.00', $html);
        $php = app('blade.compiler')->compileString(file_get_contents(view('components.modals.lease-create-modal')->getPath()));
        $this->assertNotEmpty(token_get_all($php, TOKEN_PARSE));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('walletOffsetExamples')]
    public function test_refund_preview_offsets_wallet_against_deductions_without_showing_amount_owed(int $walletCents, string $net): void
    {
        Schema::table('document_templates', function (Blueprint $t) {
            $t->string('title')->nullable(); $t->text('html_template')->nullable();
        });
        DB::table('document_templates')->insert([
            'id' => 'refund-template', 'user_id' => 'admin', 'category' => 'refund', 'status' => 'active', 'title' => 'Refund',
            'html_template' => '<table><tbody id="dynamic-refund-tbody"></tbody></table><p>Deductions: {{total_deductions}}</p><p>Net: {{nett_refund_amount}}</p>',
        ]);
        \Illuminate\Support\Facades\Gate::shouldReceive('denies')->with('owner-admin')->andReturn(false);
        $request = Request::create('/preview', 'POST', [
            'template_id' => 'refund-template', 'category' => 'refund',
            'refund_context' => [
                'total_deposit_collected_cents' => 0, 'wallet_refund_cents' => $walletCents,
                'auto_deduction_total_cents' => 600000,
                'unpaid_invoices' => [
                    ['description' => 'Monthly rental', 'balance_cents' => 100000],
                    ['description' => 'Deposit', 'balance_cents' => 500000],
                ],
                'active_refund' => ['items' => []],
            ],
        ]);
        $response = app(\App\Http\Controllers\LeaseController::class)->previewTemplate(
            $request, app(\App\Services\DocumentTemplateService::class), app(\App\Services\LeaseService::class)
        );
        $html = $response->getData(true)['content'];
        $this->assertStringContainsString('Deductions: 6,000.00', $html);
        $this->assertStringContainsString('Net: '.$net, $html);
        $this->assertStringNotContainsString('Remaining Amount Owed by Tenant', $html);
        $this->assertStringNotContainsString('data-refund-amount-owed', $html);
    }

    public static function walletOffsetExamples(): array
    {
        return ['insufficient-credit' => [37300, '0.00'], 'sufficient-credit' => [650000, '500.00']];
    }

    private function refundFixture(string $stage): void
    {
        $this->setting([], false);
        Schema::create('lease_charges', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('lease_id'); $t->string('fee_type_id');
            $t->string('invoice_id')->nullable(); $t->string('charge_type'); $t->bigInteger('amount');
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('transaction_allocations', function (Blueprint $t) {
            $t->id(); $t->string('invoice_item_id'); $t->bigInteger('amount_cents');
        });
        Schema::create('refund_items', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('refund_id'); $t->string('origin');
            foreach (['invoice_id', 'invoice_item_id', 'lease_charge_id', 'fee_type_id', 'description'] as $column) $t->string($column)->nullable();
            $t->string('type'); $t->integer('qty')->default(1); $t->bigInteger('unit_amount_cents');
            $t->bigInteger('amount_cents'); $t->timestamps();
        });
        DB::table('fee_types')->insert(['id' => 'deposit-fee', 'name' => 'Deposit', 'category' => 'deposit']);
        DB::table('lease_charges')->insert([
            'id' => 'deposit-charge', 'lease_id' => 'lease', 'fee_type_id' => 'deposit-fee',
            'charge_type' => 'refundable', 'amount' => 100000,
        ]);
        DB::table('invoices')->insert([
            'id' => 'deposit-invoice', 'lease_id' => 'lease', 'type' => 'deposit', 'status' => 'paid',
            'document_template_id' => 'invoice-template', 'invoice_no' => 'DEP-1',
            'total_amount' => 100000, 'amount_paid' => 100000, 'amount_balance' => 0,
            'period' => '2026-09-01', 'due_date' => '2026-10-20', 'created_at' => '2026-09-01',
        ]);
        DB::table('invoice_items')->insert([
            'id' => 'deposit-item', 'invoice_id' => 'deposit-invoice', 'lease_charge_id' => 'deposit-charge',
            'fee_type_id' => 'deposit-fee', 'amount' => 100000,
        ]);
        DB::table('refunds')->insert([
            'id' => 'refund', 'lease_id' => 'lease', 'status' => $stage,
            'deposit_collected_cents' => 100000, 'gross_amount_cents' => 100000,
            'auto_deduction_cents' => 10000, 'manual_deduction_cents' => 5000,
            'deduction_amount_cents' => 15000, 'net_amount_cents' => 85000,
            'approved_by' => $stage === 'approved' ? 'admin' : null,
            'approved_at' => $stage === 'approved' ? '2026-10-19' : null,
        ]);
        DB::table('refund_items')->insert([
            ['id' => 'automatic', 'refund_id' => 'refund', 'origin' => 'automatic_invoice',
                'type' => 'utility', 'description' => 'Unpaid Invoice Auto-Deduction: INV-TEST',
                'invoice_id' => 'original', 'invoice_item_id' => 'item', 'amount_cents' => 10000, 'unit_amount_cents' => 10000],
            ['id' => 'manual', 'refund_id' => 'refund', 'origin' => 'manual',
                'type' => 'cleaning', 'description' => 'Cleaning',
                'invoice_id' => null, 'invoice_item_id' => null, 'amount_cents' => 5000, 'unit_amount_cents' => 5000],
        ]);
    }
}
