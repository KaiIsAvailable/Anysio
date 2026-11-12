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
        Schema::table('leases', function (Blueprint $t) {
            $t->boolean('is_pending_renewal')->default(false);
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
        // These existing scenarios isolate late penalties from processing fees.
        DB::table('settings')->insert([
            'user_id' => 'admin', 'key' => 'processing_fee_config',
            'value' => json_encode(config('settings.processing_fee_config')), 'is_active' => false,
        ]);
        $this->actingAs(User::find('admin'));
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    public function test_lease_invoice_rows_include_related_charge_documents_across_pages_and_respect_permissions(): void
    {
        Schema::table('document_templates', fn (Blueprint $table) => $table->string('title')->nullable());
        \Illuminate\Support\Facades\Gate::before(fn () => true);
        foreach (['penalty', 'processing_fee'] as $type) {
            DB::table('invoices')->insert([
                'id' => $type, 'parent_id' => 'original', 'lease_id' => 'lease', 'type' => $type,
                'invoice_no' => 'INV-'.$type, 'document_template_id' => 'invoice-template',
                'total_amount' => 1000, 'amount_paid' => 1000, 'status' => 'paid',
                'created_at' => '2026-11-01 00:00:00',
            ]);
            DB::table('transactions')->insert([
                'id' => $type, 'invoice_id' => $type, 'receipt_no' => 'REC-'.$type,
                'document_template_id' => 'receipt-template',
            ]);
        }
        // Put the original on a different page from its generated charges.
        foreach (range(1, 3) as $index) {
            DB::table('invoices')->insert([
                'id' => 'other-'.$index, 'lease_id' => 'lease', 'type' => 'manual',
                'status' => 'unpaid', 'created_at' => '2026-10-15 00:00:00',
            ]);
        }
        $service = app(\App\Services\LeaseInvoiceViewService::class);
        $lease = \App\Models\Lease::findOrFail('lease');
        $row = $service->page($lease, 2)['invoices'][0];
        $this->assertSame('original', $row['id']);
        $this->assertCount(2, $row['related_charges']);
        foreach ($row['related_charges'] as $charge) {
            $this->assertSame('paid', $charge['status']);
            $this->assertSame('10.00', $charge['amount']);
            $this->assertNotNull($charge['document_url']);
            $this->assertNotNull($charge['receipts'][0]['document_url']);
        }
        foreach (collect($service->page($lease, 1)['invoices'])->whereIn('id', ['penalty', 'processing_fee']) as $chargeRow) {
            $this->assertSame('original', $chargeRow['original_invoice']['id']);
            $this->assertNotNull($chargeRow['original_invoice']['document_url']);
        }
        \Illuminate\Support\Facades\Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
        $row = $service->page($lease, 2)['invoices'][0];
        $this->assertNull($row['related_charges'][0]['document_url']);
        $this->assertSame([], $row['related_charges'][0]['receipts']);
        foreach (collect($service->page($lease, 1)['invoices'])->whereIn('id', ['penalty', 'processing_fee']) as $chargeRow) {
            $this->assertNull($chargeRow['original_invoice']['document_url']);
            $this->assertSame([], $chargeRow['original_invoice']['receipts']);
        }
    }

    private function setting(array $changes = [], bool $active = true): void
    {
        DB::table('settings')->updateOrInsert(['user_id' => 'admin', 'key' => 'late_penalty_config'], [
            'value' => json_encode(array_merge(['amount' => 1000, 'grace_period_days' => 0, 'frequency' => 3,
                'calculation_type' => 'fixed', 'maximum_amount' => 0, 'applicable_categories' => ['rent']], $changes)),
            'is_active' => $active,
        ]);
    }

    private function enableProcessingFee(): void
    {
        DB::table('settings')->where('key', 'processing_fee_config')->update(['is_active' => true]);
        auth()->user()->unsetRelation('settings');
    }

    public function test_processing_fee_is_collected_separately_without_creating_wallet_excess(): void
    {
        $this->enableProcessingFee();
        $staleInvoice = Invoice::find('original');
        $this->pay($staleInvoice, 120); // RM100 invoice + RM10 penalty + RM10 processing.
        $fee = Invoice::where('type', 'processing_fee')->sole();
        $this->assertSame(1000, $fee->total_amount);
        $this->assertSame('paid', $fee->status);
        $this->assertSame('original', $fee->parent_id);
        $this->assertSame(1000, (int) $fee->items()->sum('amount'));
        $this->assertSame(10000, Invoice::find('original')->amount_paid);
        $this->assertSame(12000, (int) DB::table('transactions')->sum('amount_paid'));
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        try {
            $this->pay($staleInvoice, 120);
            $this->fail('A retry must not collect another processing fee.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice', $exception->errors());
        }
        $this->assertSame(1, Invoice::where('type', 'processing_fee')->count());
    }

    public function test_processing_fee_edits_require_permission_and_use_the_edited_amount(): void
    {
        $this->enableProcessingFee();
        $gate = new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user());
        \Illuminate\Support\Facades\Gate::swap($gate);
        $gate->define('invoice.edit processing fee', fn () => false);
        try {
            $this->pay(Invoice::find('original'), 117.5, extra: ['processing_fee_amount' => '7.50']);
            $this->fail('An unauthorized edit must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('processing_fee', $exception->errors());
        }
        $this->assertSame(0, DB::table('transactions')->count());
        $gate->define('invoice.edit processing fee', fn () => true);
        $this->pay(Invoice::find('original'), 117.5, extra: ['processing_fee_amount' => '7.50']);
        $this->assertSame(750, Invoice::where('type', 'processing_fee')->sole()->total_amount);
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    public function test_processing_fee_removal_requires_its_own_permission(): void
    {
        $this->enableProcessingFee();
        $gate = new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user());
        \Illuminate\Support\Facades\Gate::swap($gate);
        $gate->define('invoice.remove processing fee', fn () => false);
        $gate->define('invoice.edit processing fee', fn () => true);
        try {
            $this->pay(Invoice::find('original'), 110, extra: ['processing_fee_cancelled' => true]);
            $this->fail('Editing permission must not authorize the remove action.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('processing_fee', $exception->errors());
        }
        $gate->define('invoice.remove processing fee', fn () => true);
        $this->pay(Invoice::find('original'), 110, extra: ['processing_fee_cancelled' => true]);
        $this->assertSame(0, Invoice::where('type', 'processing_fee')->count());
        $this->assertSame('paid', Invoice::find('original')->status);
    }

    public function test_processing_fee_does_not_apply_to_nonmatching_methods(): void
    {
        $this->enableProcessingFee();
        $this->pay(Invoice::find('original'), 110, extra: ['payment_method' => 'Bank Transfer']);
        $this->assertSame(0, Invoice::where('type', 'processing_fee')->count());
    }

    public function test_wallet_payment_skips_processing_fee_even_when_the_setting_is_enabled(): void
    {
        $this->enableProcessingFee();
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 12000]);
        $this->pay(Invoice::find('original'), 0, extra: [
            'payment_method' => 'Wallet', 'use_wallet' => true, 'transaction_ref' => null,
        ]);
        $this->assertSame(0, Invoice::where('type', 'processing_fee')->count());
        $this->assertSame(1000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame('paid', Invoice::find('original')->status);
    }

    public function test_processing_fee_cannot_consume_the_entire_payment_or_leave_partial_writes_on_failure(): void
    {
        $this->enableProcessingFee();
        try {
            $this->pay(Invoice::find('original'), 10);
            $this->fail('A payment must contribute toward the original invoice.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('processing_fee', $exception->errors());
        }
        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame(0, DB::table('document_sequences')->count());
        $this->assertSame(10000, Invoice::find('original')->amount_balance);
    }

    public function test_processing_fee_allows_partial_invoice_payment_and_only_true_excess_is_credited(): void
    {
        $this->enableProcessingFee();
        $this->setting(active: false);
        $this->pay(Invoice::find('original'), 30);
        $this->assertSame(8000, Invoice::find('original')->amount_balance);
        $this->assertSame(1000, Invoice::where('type', 'processing_fee')->sole()->amount_paid);
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        // A second cash payment has its own fee: RM80 balance + RM10 fee + RM5 excess.
        $this->pay(Invoice::find('original'), 95);
        $this->assertSame(2, Invoice::where('type', 'processing_fee')->count());
        $this->assertSame(500, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(12000, (int) DB::table('transactions')->sum('amount_paid'));
    }

    private function pay(Invoice $invoice, float $amount, string $date = '2026-10-20', array $extra = []): void
    {
        // Exercise real invoice/payment/wallet writes; suppress only audit/notification observers.
        Model::withoutEvents(fn () => app(InvoiceService::class)->recordPayment($invoice, array_merge([
            'amount_paid' => $amount, 'payment_date' => $date, 'payment_method' => 'Cash', 'transaction_ref' => 'test',
        ], $extra)));
    }

    private function allowPenaltySeparation(): void
    {
        DB::table('settings')->updateOrInsert(['user_id' => 'admin', 'key' => 'payment_config'], [
            'value' => json_encode(['allow_unpaid_penalty' => true, 'auto_pay_from_wallet' => false]), 'is_active' => true,
        ]);
        auth()->user()->unsetRelation('settings');
    }

    public function test_separated_penalty_does_not_block_finishing_partial_rent_or_create_another_penalty(): void
    {
        $this->allowPenaltySeparation();
        $this->pay(Invoice::find('original'), 40);
        $penalty = Invoice::where('parent_id', 'original')->where('type', 'penalty')->sole();
        $this->assertSame(6000, Invoice::find('original')->amount_balance);
        $this->assertNull(app(InvoicePenaltyService::class)->quote(Invoice::find('original'), '2026-10-30'));
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 6000]);
        $this->pay(Invoice::find('original'), 0, '2026-10-30', ['payment_method' => 'Wallet', 'use_wallet' => true, 'transaction_ref' => null]);
        $this->assertSame('paid', Invoice::find('original')->status);
        $this->assertSame(0, Invoice::find('original')->amount_balance);
        $this->assertSame('unpaid', $penalty->fresh()->status);
        $this->assertSame(1000, $penalty->fresh()->amount_balance);
        $this->assertSame(1, Invoice::where('parent_id', 'original')->where('type', 'penalty')->count());
        $this->assertSame(0, (int) DB::table('wallets')->value('balance'));
    }

    public function test_linked_penalty_can_be_paid_while_its_rental_is_still_partially_unpaid(): void
    {
        $this->allowPenaltySeparation();
        $this->pay(Invoice::find('original'), 40);
        $penalty = Invoice::where('parent_id', 'original')->where('type', 'penalty')->sole();
        $this->pay($penalty, 5);
        $this->assertSame('partial', $penalty->fresh()->status);
        $this->assertSame(500, $penalty->fresh()->amount_balance);
        $this->pay($penalty->fresh(), 5);
        $this->assertSame('paid', $penalty->fresh()->status);
        $this->assertSame('partial', Invoice::find('original')->status);
        $this->assertSame(6000, Invoice::find('original')->amount_balance);
        $this->assertSame(1, Invoice::where('type', 'penalty')->count());
    }

    public function test_unpaid_separated_penalty_does_not_block_the_next_rental_invoice(): void
    {
        $this->allowPenaltySeparation();
        $this->pay(Invoice::find('original'), 100);
        $penalty = Invoice::where('type', 'penalty')->sole();
        DB::table('invoices')->insert([
            'id' => 'next-rent', 'lease_id' => 'lease', 'type' => 'rent', 'period' => '2026-11-01', 'due_date' => '2026-11-20',
            'document_template_id' => 'invoice-template', 'invoice_no' => 'NEXT-RENT', 'total_amount' => 10000,
            'amount_balance' => 10000, 'status' => 'unpaid', 'created_at' => '2026-10-25 00:00:00',
        ]);
        $this->pay(Invoice::find('next-rent'), 100);
        $this->assertSame('paid', Invoice::find('next-rent')->status);
        $this->assertSame('unpaid', $penalty->fresh()->status);
        $this->assertSame(1000, $penalty->fresh()->amount_balance);
    }

    public function test_penalty_separation_still_requires_clearing_unrelated_older_rental_debt(): void
    {
        $this->allowPenaltySeparation();
        $this->pay(Invoice::find('original'), 40);
        $penalty = Invoice::where('type', 'penalty')->sole();
        DB::table('invoices')->insert([
            'id' => 'older-rent', 'lease_id' => 'lease', 'type' => 'rent', 'period' => '2026-09-01',
            'total_amount' => 10000, 'amount_balance' => 10000, 'status' => 'unpaid', 'created_at' => '2026-09-01 00:00:00',
        ]);
        try { $this->pay($penalty, 10); $this->fail('Unrelated older rental debt was skipped'); }
        catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
            $this->assertStringContainsString('Earlier outstanding invoices', session('error'));
        }
        $this->assertSame('unpaid', $penalty->fresh()->status);
        $this->assertSame(1000, $penalty->fresh()->amount_balance);
        $this->assertSame(1, DB::table('transactions')->count());
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
                    if ($action === 'preview') {
                        $response = app(SettingsController::class)->calculatePenaltyPreview($request, $invoice, app(InvoicePenaltyService::class));
                        $this->assertSame(422, $response->getStatusCode());
                        $this->assertArrayHasKey('penalty', $response->getData(true)['errors']);
                        $this->assertSame(session('error'), $response->getData(true)['session_status']['message']);
                        $this->assertStringContainsString('Ask the system administrator', $response->getContent());
                        continue;
                    }
                    $this->pay($invoice, 110);
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
        \Illuminate\Support\Facades\Gate::shouldReceive('denies')->once()->with('super-admin')->andReturn(false);
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

    #[\PHPUnit\Framework\Attributes\DataProvider('checkoutRenewalStates')]
    public function test_wallet_is_added_to_draft_without_spending_it(string $initialStatus, string $action): void
    {
        $this->refundFixture('draft');
        Schema::table('leases', function (Blueprint $t) { $t->string('status')->nullable(); });
        DB::table('leases')->where('id', 'lease')->update(['status' => $initialStatus, 'is_pending_renewal' => true]);
        Schema::table('refunds', function (Blueprint $t) {
            $t->string('type')->nullable(); $t->string('reason')->nullable(); $t->string('internal_notes')->nullable();
        });
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 20000]);
        $refund = Model::withoutEvents(fn () => app(\App\Services\RefundService::class)->$action(
            \App\Models\Lease::findOrFail('lease'), [], [
                ['type' => 'cleaning', 'amount_cents' => 5000, 'qty' => 1, 'description' => 'Cleaning'],
            ]
        ));
        $this->assertSame(20000, $refund->wallet_refund_cents);
        $this->assertSame(120000, $refund->gross_amount_cents);
        $this->assertSame(105000, $refund->net_amount_cents);
        $this->assertSame(20000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $lease = \App\Models\Lease::findOrFail('lease');
        $this->assertSame('Check Out', $lease->status);
        $this->assertFalse($lease->is_pending_renewal);
    }

    public static function checkoutRenewalStates(): array
    {
        return [
            'draft from renewal' => ['Renew', 'saveAsDraft'],
            'draft clears stale flag' => ['Check Out', 'saveAsDraft'],
            'pending from renewal' => ['Renew', 'submitForPending'],
            'pending clears stale flag' => ['Check Out', 'submitForPending'],
        ];
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
        \Illuminate\Support\Facades\Gate::shouldReceive('denies')->with('super-admin')->andReturn(false);
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

    #[\PHPUnit\Framework\Attributes\DataProvider('blockedInvoiceChangeStages')]
    public function test_financial_changes_are_blocked_before_any_writes(string $stage, string $action): void
    {
        $this->refundFixture($stage);
        $before = DB::table('refunds')->where('id', 'refund')->first();
        try {
            Model::withoutEvents(function () use ($action) {
                if ($action === 'create') {
                    app(InvoiceService::class)->createManualInvoice(\App\Models\Lease::findOrFail('lease'), [
                        'period' => '2026-11-01', 'due_date' => '2026-11-10',
                        'items' => [['fee_type_id' => 'rent', 'amount' => 20]],
                    ]);
                } else {
                    app(InvoiceService::class)->voidInvoice(Invoice::findOrFail('deposit-invoice'), 'Correction');
                }
            });
            $this->fail('Financial changes must be blocked in this refund stage.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $this->assertSame(2, Invoice::count());
        $this->assertSame('paid', Invoice::findOrFail('deposit-invoice')->status);
        $this->assertSame(0, DB::table('wallet_transactions')->count());
        $this->assertEquals($before, DB::table('refunds')->where('id', 'refund')->first());
    }

    public static function blockedInvoiceChangeStages(): array
    {
        $cases = [];
        foreach (['pending', 'approved', 'processing', 'paid'] as $stage) {
            foreach (['create', 'void'] as $action) $cases[$stage.'-'.$action] = [$stage, $action];
        }
        return $cases;
    }

    public function test_draft_invoice_creation_and_void_refresh_settlement_and_void_is_idempotent(): void
    {
        $this->refundFixture('draft');
        $new = Model::withoutEvents(fn () => app(InvoiceService::class)->createManualInvoice(\App\Models\Lease::findOrFail('lease'), [
            'period' => '2026-11-01', 'due_date' => '2026-11-10', 'items' => [['fee_type_id' => 'rent', 'amount' => 20]],
        ]));
        $this->assertSame(12000, \App\Models\Refund::findOrFail('refund')->auto_deduction_cents);
        Model::withoutEvents(fn () => app(InvoiceService::class)->voidInvoice($new, 'Duplicate charge'));
        $this->assertSame(10000, \App\Models\Refund::findOrFail('refund')->auto_deduction_cents);
        DB::table('transaction_allocations')->insert(['invoice_item_id' => 'deposit-item', 'amount_cents' => 100000]);
        Model::withoutEvents(fn () => app(InvoiceService::class)->voidInvoice(Invoice::findOrFail('deposit-invoice'), 'Deposit invoice correction'));
        $refund = \App\Models\Refund::findOrFail('refund');
        $this->assertSame(0, $refund->deposit_collected_cents);
        $this->assertSame(100000, $refund->wallet_refund_cents);
        $this->assertSame(85000, $refund->net_amount_cents);
        try {
            Model::withoutEvents(fn () => app(InvoiceService::class)->voidInvoice(Invoice::findOrFail('deposit-invoice'), 'Repeated request'));
            $this->fail('Repeated void must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice', $exception->errors());
        }
        $this->assertSame(1, DB::table('wallet_transactions')->count());
        $this->assertSame(100000, (int) DB::table('wallets')->value('balance'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('returnableStages')]
    public function test_return_and_revoke_preserve_figures_and_record_reason(string $stage): void
    {
        $this->refundFixture($stage);
        Schema::table('refunds', fn (Blueprint $table) => $table->text('internal_notes')->nullable());
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 20000, 'reserved_refund_id' => $stage === 'approved' ? 'refund' : null]);
        Model::withoutEvents(function () use ($stage) {
            $service = app(\App\Services\RefundService::class);
            if ($stage === 'approved') $service->revokeApproval(\App\Models\Refund::findOrFail('refund'), 'Correct final utility charge');
            else $service->rejectRefund(\App\Models\Lease::findOrFail('lease'), ['transition_reason' => 'Correct final utility charge']);
        });
        $refund = \App\Models\Refund::findOrFail('refund');
        $this->assertSame('draft', $refund->status->value);
        $this->assertSame(85000, $refund->net_amount_cents);
        $this->assertSame(2, $refund->items()->count());
        $this->assertStringContainsString('Correct final utility charge', $refund->internal_notes);
        $this->assertStringContainsString('admin', $refund->internal_notes);
        $this->assertNull($refund->approved_by);
        $this->assertNull($refund->approved_at);
        $this->assertNull(DB::table('wallets')->value('reserved_refund_id'));
        $this->assertSame(20000, (int) DB::table('wallets')->value('balance'));
    }

    public static function returnableStages(): array
    {
        return [['pending'], ['approved']];
    }

    public function test_completed_refund_cannot_be_reopened_and_residual_debt_can_be_collected(): void
    {
        $this->refundFixture('paid');
        $service = app(\App\Services\RefundService::class);
        try {
            Model::withoutEvents(fn () => $service->saveAsDraft(\App\Models\Lease::findOrFail('lease'), [], []));
            $this->fail('Completed settlements must not create another draft against the same deposit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        try {
            Model::withoutEvents(fn () => $service->revokeApproval(\App\Models\Refund::findOrFail('refund'), 'Correction'));
            $this->fail('Paid approval cannot be revoked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $before = DB::table('refunds')->where('id', 'refund')->first();
        $this->pay(Invoice::findOrFail('original'), 100);
        $this->assertSame('paid', Invoice::findOrFail('original')->status);
        $this->assertEquals($before, DB::table('refunds')->where('id', 'refund')->first());
        $this->assertSame(1, DB::table('refunds')->count());
    }

    public function test_completed_refund_accepts_only_explicit_linked_adjustment_invoice(): void
    {
        $this->refundFixture('paid');
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('settlement_refund_id')->nullable(); $table->text('adjustment_reason')->nullable();
        });
        $before = DB::table('refunds')->where('id', 'refund')->first();
        $new = Model::withoutEvents(fn () => app(InvoiceService::class)->createManualInvoice(\App\Models\Lease::findOrFail('lease'), [
            'period' => '2026-11-01', 'due_date' => '2026-11-10', 'post_refund_adjustment' => true,
            'adjustment_reason' => 'Final utility charge received after settlement',
            'items' => [['fee_type_id' => 'rent', 'amount' => 20]],
        ]));
        $this->assertSame('refund', $new->settlement_refund_id);
        $this->assertSame('adjustment', $new->type);
        $this->assertSame(2000, $new->total_amount);
        $this->assertEquals($before, DB::table('refunds')->where('id', 'refund')->first());
    }

    public function test_old_review_screen_cannot_approve_figures_changed_by_payment(): void
    {
        $this->refundFixture('pending');
        $service = app(\App\Services\RefundService::class);
        $old = $service->settlementFingerprint(\App\Models\Refund::findOrFail('refund'));
        $this->pay(Invoice::findOrFail('original'), 100);
        try {
            Model::withoutEvents(fn () => $service->approveRefund(\App\Models\Refund::findOrFail('refund'), User::findOrFail('admin'), ['settlement_fingerprint' => $old]));
            $this->fail('An old review screen must not approve updated figures.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $refund = \App\Models\Refund::findOrFail('refund');
        $this->assertSame('pending', $refund->status->value);
        $approved = Model::withoutEvents(fn () => $service->approveRefund($refund, User::findOrFail('admin'), ['settlement_fingerprint' => $service->settlementFingerprint($refund)]));
        $this->assertSame('approved', $approved->status->value);
    }

    public function test_ajax_renewal_business_errors_return_json_and_leave_the_lease_unchanged(): void
    {
        \Illuminate\Support\Facades\Gate::shouldReceive('denies')->with('super-admin')->andReturn(false);
        $request = \Mockery::mock(\App\Http\Requests\Lease\StoreLeaseRequest::class)->makePartial();
        $request->initialize();
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('validated')->once()->andReturn(['status' => 'Renew', 'lease_id' => 'lease']);
        $service = \Mockery::mock(\App\Services\LeaseService::class);
        $service->shouldReceive('process')->once()->andThrow(ValidationException::withMessages(['error' => 'Clear unpaid invoices before renewal.']));
        $response = app(\App\Http\Controllers\LeaseController::class)->store($request, $service);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($response->getData(true)['success']);
        $this->assertSame('Clear unpaid invoices before renewal.', session('error'));
        $this->assertSame(1, \App\Models\Lease::count());
        $this->assertSame(0, DB::table('transactions')->count());
    }

    public function test_void_controller_returns_stage_error_in_session(): void
    {
        $this->refundFixture('approved');
        \Illuminate\Support\Facades\Gate::shouldReceive('denies')->with('super-admin')->andReturn(false);
        $request = \Mockery::mock(\App\Http\Requests\Invoice\VoidInvoiceRequest::class)->makePartial();
        $request->initialize();
        $request->shouldReceive('validated')->with('reason')->andReturn('Correction');
        $response = app(\App\Http\Controllers\InvoiceController::class)->void($request, Invoice::findOrFail('original'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('Revoke refund approval', session('error'));
        $this->assertSame('unpaid', Invoice::findOrFail('original')->status);
    }

    public function test_updated_refund_and_invoice_blades_compile(): void
    {
        foreach (['components.modals.lease-create-modal', 'components.modals.manual-invoice-modal', 'adminSide.leases.show', 'adminSide.leases.invoices.index', 'dashboard'] as $view) {
            $php = app('blade.compiler')->compileString(file_get_contents(view($view)->getPath()));
            $this->assertNotEmpty(token_get_all($php, TOKEN_PARSE));
        }
    }

    public function test_post_refund_adjustment_migration_is_reversible(): void
    {
        $migration = require base_path('database/migrations/2026_11_09_000001_link_post_refund_invoice_adjustments.php');
        $migration->up();
        $this->assertTrue(Schema::hasColumn('invoices', 'settlement_refund_id'));
        $this->assertTrue(Schema::hasColumn('invoices', 'adjustment_reason'));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('invoices', 'settlement_refund_id'));
        $this->assertFalse(Schema::hasColumn('invoices', 'adjustment_reason'));
    }

    public function test_unsuccessful_payout_does_not_enter_processing_or_move_funds(): void
    {
        $this->refundFixture('approved');
        $before = DB::table('refunds')->where('id', 'refund')->first();
        try {
            Model::withoutEvents(fn () => app(\App\Services\RefundService::class)->processDisbursement(
                \App\Models\Refund::findOrFail('refund'), ['status' => 'failed'], User::findOrFail('admin')
            ));
            $this->fail('A failed payout must not move settlement funds.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $this->assertEquals($before, DB::table('refunds')->where('id', 'refund')->first());
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    public function test_checked_out_lease_recurring_invoice_action_reports_session_error(): void
    {
        \Illuminate\Support\Facades\Gate::shouldReceive('denies')->with('super-admin')->andReturn(false);
        $lease = new \App\Models\Lease(['status' => 'Check Out']);
        $response = app(\App\Http\Controllers\InvoiceController::class)->generateAutoInvoice(Request::create('/generate', 'POST'), $lease);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('Recurring invoices stop after checkout', session('error'));
        $this->assertSame(1, Invoice::count());
    }

    public static function regenerationContexts(): array
    {
        return ['draft-manual-wallet' => [false, false], 'draft-auto-wallet' => [false, true],
            'before-refund-manual-wallet' => [true, false], 'before-refund-auto-wallet' => [true, true]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('regenerationContexts')]
    public function test_regenerate_charge_invoice_after_void_preserves_wallet_and_counts_deposit(bool $beforeRefund, bool $autoWallet): void
    {
        $this->refundFixture('draft');
        if ($beforeRefund) { DB::table('refund_items')->delete(); DB::table('refunds')->delete(); }
        DB::table('settings')->updateOrInsert(['user_id' => 'admin', 'key' => 'payment_config'], [
            'value' => json_encode(['allow_unpaid_penalty' => true, 'auto_pay_from_wallet' => $autoWallet]), 'is_active' => true,
        ]);
        auth()->user()->unsetRelation('settings');
        DB::table('invoices')->where('id', 'original')->update(['status' => 'void']);
        Schema::table('leases', function (Blueprint $t) { $t->date('start_date')->nullable(); });
        DB::table('leases')->update(['start_date' => '2026-10-01']);
        $lease = \App\Models\Lease::findOrFail('lease');
        $issuer = User::findOrFail('admin');
        $service = app(InvoiceService::class);
        Model::withoutEvents(fn () => $service->voidInvoice(Invoice::findOrFail('deposit-invoice'), 'Replace incorrect invoice'));
        $this->assertSame(100000, (int) DB::table('wallets')->value('balance'));
        $invoice = Model::withoutEvents(fn () => $service->regenerateChargeInvoice($lease, 'deposit-charge', $issuer));
        $this->assertSame(100000, $invoice->total_amount);
        $this->assertSame('deposit-charge', $invoice->items->first()->lease_charge_id);
        $this->assertSame($autoWallet ? 'paid' : 'unpaid', $invoice->status);
        if (!$autoWallet) {
            $this->assertSame(0, app(\App\Services\RefundService::class)->getDepositCollectedCents($lease));
            $this->pay($invoice, 0, '2026-10-20', ['payment_method' => 'Wallet', 'use_wallet' => true, 'transaction_ref' => null]);
        }
        $this->assertSame(0, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(100000, app(\App\Services\RefundService::class)->getDepositCollectedCents($lease));
        try {
            Model::withoutEvents(fn () => $service->regenerateChargeInvoice($lease, 'deposit-charge', $issuer));
            $this->fail('A repeated generation must not duplicate the invoice.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('invoice', $e->errors());
        }
        $this->assertSame(3, Invoice::count());
    }

    public function test_regenerate_charge_invoice_rejects_existing_legacy_and_wrong_lease_charges(): void
    {
        $this->refundFixture('draft');
        $service = app(InvoiceService::class);
        $lease = \App\Models\Lease::findOrFail('lease');
        foreach (['unpaid', 'partial', 'paid', 'overdue', 'legacy', 'wrong-lease'] as $case) {
            if (in_array($case, ['unpaid', 'partial', 'paid', 'overdue'])) DB::table('invoices')->where('id', 'deposit-invoice')->update(['status' => $case]);
            if ($case === 'legacy') DB::table('invoice_items')->where('id', 'deposit-item')->update(['lease_charge_id' => null]);
            if ($case === 'wrong-lease') DB::table('lease_charges')->update(['lease_id' => 'another-lease']);
            try {
                $service->regenerateChargeInvoice($lease, 'deposit-charge', User::findOrFail('admin'));
                $this->fail('Unsafe regeneration must be rejected: '.$case);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('invoice', $e->errors());
            }
        }
        $this->assertSame(2, Invoice::count());
    }

    public function test_regenerate_charge_invoice_controller_redirects_permission_and_stage_errors(): void
    {
        $this->refundFixture('pending');
        $controller = app(\App\Http\Controllers\InvoiceController::class);
        $lease = \App\Models\Lease::findOrFail('lease');
        \Illuminate\Support\Facades\Gate::before(fn () => false);
        $response = $controller->regenerateChargeInvoice(Request::create('/regenerate', 'POST'), $lease, 'deposit-charge');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('You have no permission.', session('error'));
        // Replace the earlier denial callback so this action reaches the stage guard.
        \Illuminate\Support\Facades\Gate::swap(new \Illuminate\Auth\Access\Gate(app(), fn () => auth()->user()));
        \Illuminate\Support\Facades\Gate::before(fn () => true);
        $response = $controller->regenerateChargeInvoice(Request::create('/regenerate', 'POST'), $lease, 'deposit-charge');
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('Return the refund to Draft', session('error'));
        $this->assertSame(2, Invoice::count());
    }

    public function test_regenerate_charge_invoice_is_blocked_in_locked_refund_stages(): void
    {
        $this->refundFixture('pending');
        foreach (['pending', 'approved', 'processing', 'paid'] as $stage) {
            DB::table('refunds')->update(['status' => $stage]);
            try {
                app(InvoiceService::class)->regenerateChargeInvoice(\App\Models\Lease::findOrFail('lease'), 'deposit-charge', User::findOrFail('admin'));
                $this->fail('Refund stage must block regeneration: '.$stage);
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('refund', $e->errors());
            }
        }
        $this->assertSame(2, Invoice::count());
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
