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

class OverpaymentWalletTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'users' => ['name', 'role', 'email', 'created_by', 'owner_id'], 'tenants' => ['user_id', 'created_by'],
            'units' => ['owner_id'], 'rooms' => ['unit_id'],
            'owners' => ['user_id', 'agent_id'], 'properties' => ['owner_id', 'created_by'],
            'leases' => ['tenant_id', 'leasable_type', 'leasable_id', 'parent_lease_id'],
            'invoices' => ['parent_id', 'lease_id', 'user_id', 'context', 'type', 'billable_type', 'billable_id', 'invoice_no', 'document_template_id', 'period', 'due_date', 'status', 'remarks'],
            'invoice_items' => ['invoice_id', 'fee_type_id', 'lease_charge_id', 'description'],
            'fee_types' => ['user_id', 'name', 'category'],
            'document_templates' => ['user_id', 'category', 'status', 'title', 'html_template'],
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
        Schema::create('refund_items', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('refund_id'); $t->string('invoice_id')->nullable();
        });
        Schema::table('leases', function (Blueprint $t) {
            $t->boolean('is_pending_renewal')->default(false);
        });
        Carbon::setTestNow(Carbon::parse('2026-10-30 12:00:00', 'Asia/Kuala_Lumpur'));
        DB::table('users')->insert([
            ['id' => 'admin', 'role' => 'ownerAdmin', 'name' => 'Manager', 'email' => 'manager@test.test'],
            ['id' => 'tenant', 'role' => 'tenant', 'name' => 'Tenant', 'email' => 'tenant@test.test'],
        ]);
        DB::table('tenants')->insert(['id' => 'tenant-record', 'user_id' => 'tenant', 'created_by' => 'admin']);
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
        DB::table('settings')->insert([
            'user_id' => 'admin', 'key' => 'processing_fee_config',
            'value' => json_encode(config('settings.processing_fee_config')), 'is_active' => false,
        ]);
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

    private function enable(bool $enabled = true, bool $partialPenalty = false): void
    {
        DB::table('settings')->updateOrInsert(['user_id' => 'admin', 'key' => 'payment_config'], [
            'value' => json_encode(['auto_pay_from_wallet' => $enabled, 'allow_unpaid_penalty' => $partialPenalty]), 'is_active' => true,
        ]);
        auth()->user()->unsetRelation('settings');
    }

    private function earlier(string $id, int $amount, string $name, string $created = '2026-09-01 00:00:00', array $extra = []): Invoice
    {
        DB::table('fee_types')->insert(['id' => 'fee-'.$id, 'name' => $name, 'category' => 'rent']);
        DB::table('invoices')->insert(array_replace([
            'id' => $id, 'lease_id' => 'lease', 'context' => 'tenant', 'type' => 'manual', 'invoice_no' => 'INV-'.$id,
            'document_template_id' => 'invoice-template', 'period' => '2026-09-01', 'due_date' => '2026-12-01',
            'total_amount' => $amount, 'amount_paid' => 0, 'amount_balance' => $amount, 'status' => 'unpaid', 'created_at' => $created,
        ], $extra));
        DB::table('invoice_items')->insert(['id' => 'item-'.$id, 'invoice_id' => $id, 'fee_type_id' => 'fee-'.$id, 'amount' => $amount]);

        return Invoice::findOrFail($id);
    }

    private function record(float $external = 200): ?array
    {
        return Model::withoutEvents(fn () => app(InvoiceService::class)->recordPayment(Invoice::find('original'), [
            'amount_paid' => $external, 'payment_method' => 'Cash', 'payment_date' => '2026-10-19', 'transaction_ref' => 'CASH',
        ]));
    }

    public function test_overpayment_pays_earlier_same_lease_invoices_and_returns_actual_fee_names_and_amounts(): void
    {
        $this->enable();
        $water = $this->earlier('water', 9000, 'Water Bill');
        $keys = $this->earlier('keys', 1000, 'Late Key Charges', '2026-09-02 00:00:00');
        $summary = $this->record();
        $this->assertSame('paid', Invoice::find('original')->status);
        $this->assertSame('paid', $water->fresh()->status);
        $this->assertSame('paid', $keys->fresh()->status);
        $this->assertSame(10000, $summary['wallet_used_cents']);
        $this->assertSame(0, $summary['wallet_remaining_cents']);
        $this->assertSame(['INV-water', 'INV-keys'], array_column($summary['payments'], 'invoice_no'));
        $this->assertSame(['Water Bill', 'Late Key Charges'], array_column($summary['payments'], 'items'));
        $this->assertSame([9000, 1000], array_column($summary['payments'], 'invoice_amount_cents'));
        $this->assertSame([9000, 1000], array_column($summary['payments'], 'paid_cents'));
        $this->assertSame(0, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(3, DB::table('transactions')->count());
        $this->assertSame(2, DB::table('transactions')->where('payment_method', 'Wallet')->count());
    }

    public function test_insufficient_wallet_partially_pays_oldest_invoice_and_reports_remaining_debt(): void
    {
        $this->enable();
        $first = $this->earlier('first', 9000, 'Water');
        $second = $this->earlier('second', 1000, 'Keys', '2026-09-02 00:00:00');
        $summary = $this->record(150);
        $this->assertSame(4000, $first->fresh()->amount_balance);
        $this->assertSame(1000, $second->fresh()->amount_balance);
        $this->assertSame(5000, $summary['wallet_used_cents']);
        $this->assertSame(9000, $summary['payments'][0]['invoice_amount_cents']);
        $this->assertSame(5000, $summary['payments'][0]['paid_cents']);
        $this->assertSame(4000, $summary['payments'][0]['remaining_cents']);
    }

    public function test_setting_off_keeps_excess_in_wallet_and_does_not_return_an_auto_payment_summary(): void
    {
        $this->enable(false);
        $this->assertNull($this->record());
        $this->assertSame(10000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(1, DB::table('transactions')->count());
    }

    public function test_exact_payment_does_not_trigger_even_if_wallet_has_funds(): void
    {
        $this->enable();
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 20000]);
        $this->assertNull($this->record(100));
        $this->assertSame(20000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    public function test_an_exact_payment_cannot_bypass_existing_older_debt(): void
    {
        $this->enable();
        $this->earlier('old', 5000, 'Water');
        try { $this->record(100); $this->fail('Exact payment bypassed earlier debt'); }
        catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) { $this->assertSame(302, $exception->getResponse()->getStatusCode()); }
        $this->assertSame('unpaid', Invoice::find('original')->status);
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    public function test_overpayment_uses_available_wallet_but_never_pays_other_leases_or_later_invoices(): void
    {
        $this->enable();
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 2000]);
        $old = $this->earlier('old', 5000, 'Water');
        $later = $this->earlier('later', 10000, 'Future Bill', '2026-11-01 00:00:00', ['period' => '2026-11-01']);
        DB::table('leases')->insert(['id' => 'other', 'tenant_id' => 'tenant-record', 'leasable_type' => Property::class, 'leasable_id' => 'property']);
        $other = $this->earlier('other', 10000, 'Other Lease', extra: ['lease_id' => 'other']);
        $summary = $this->record(150);
        $this->assertSame('paid', $old->fresh()->status);
        $this->assertSame(10000, $later->fresh()->amount_balance);
        $this->assertSame(10000, $other->fresh()->amount_balance);
        $this->assertSame(5000, $summary['wallet_used_cents']);
        $this->assertSame(2000, $summary['wallet_remaining_cents']);
    }

    public function test_no_previous_debt_leaves_money_in_wallet_and_explains_it_in_summary(): void
    {
        $this->enable();
        $summary = $this->record();
        $this->assertSame(0, $summary['wallet_used_cents']);
        $this->assertSame(10000, $summary['wallet_remaining_cents']);
        $this->assertSame([], $summary['payments']);
        $this->assertStringContainsString('no earlier unpaid invoices', $summary['notes'][0]);
    }

    public function test_full_penalty_policy_skips_old_debt_without_undoing_external_payment_or_spending_wallet(): void
    {
        $this->enable();
        $old = $this->earlier('old', 9000, 'Rent', extra: ['due_date' => '2026-09-01']);
        $summary = $this->record();
        $this->assertSame('paid', Invoice::find('original')->status);
        $this->assertSame('unpaid', $old->fresh()->status);
        $this->assertSame(10000, $summary['wallet_remaining_cents']);
        $this->assertSame(0, $summary['wallet_used_cents']);
        $this->assertStringContainsString('Full payment', $summary['notes'][0]);
        $this->assertNull(session('error'));
        $this->assertSame(0, Invoice::where('parent_id', 'old')->count());
        $this->assertSame(1, DB::table('transactions')->count());
    }

    public function test_penalty_payments_are_reported_with_their_own_invoice_number_and_fee_name(): void
    {
        $this->enable(partialPenalty: true);
        $old = $this->earlier('old', 9000, 'Rent', extra: ['due_date' => '2026-09-01']);
        $summary = $this->record();
        $this->assertSame('paid', $old->fresh()->status);
        $this->assertSame(10000, $summary['wallet_used_cents']);
        $this->assertSame(2, count($summary['payments']));
        $this->assertSame('Late Payment Penalty', $summary['payments'][1]['items']);
        $this->assertSame(1000, $summary['payments'][1]['paid_cents']);
    }

    public function test_reserved_wallet_is_not_spent_and_successful_payment_is_reported_separately(): void
    {
        $this->enable();
        $this->earlier('old', 5000, 'Water');
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 0, 'reserved_refund_id' => 'held']);
        $summary = $this->record();
        $this->assertSame('paid', Invoice::find('original')->status);
        $this->assertSame(0, $summary['wallet_used_cents']);
        $this->assertSame(10000, $summary['wallet_remaining_cents']);
        $this->assertStringContainsString('reserved', $summary['notes'][0]);
    }

    public function test_repeated_original_payment_is_rejected_before_another_credit_or_deduction(): void
    {
        $this->enable();
        $this->earlier('old', 9000, 'Water');
        $this->record();
        try { $this->record(); $this->fail('Duplicate original payment accepted'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('invoice', $exception->errors()); }
        $this->assertSame(1, DB::table('wallet_transactions')->where('amount', '>', 0)->count());
        $this->assertSame(1, DB::table('wallet_transactions')->where('amount', '<', 0)->count());
        $this->assertSame(1000, (int) DB::table('wallets')->value('balance'));
    }

    public function test_an_older_invoices_missing_receipt_template_keeps_the_external_payment_and_wallet_credit(): void
    {
        $this->enable();
        DB::table('document_templates')->insert(['id' => 'old-template', 'user_id' => 'another-account', 'category' => 'invoice', 'status' => 'active']);
        $older = $this->earlier('old', 5000, 'Water', extra: ['document_template_id' => 'old-template']);
        $summary = $this->record();
        $this->assertSame('paid', Invoice::find('original')->status);
        $this->assertSame('unpaid', $older->fresh()->status);
        $this->assertSame(10000, $summary['wallet_remaining_cents']);
        $this->assertSame(0, $summary['wallet_used_cents']);
        $this->assertStringContainsString('receipt template', $summary['notes'][0]);
        $this->assertSame(0, DB::table('wallet_transactions')->where('amount', '<', 0)->count());
    }

    public function test_pays_one_invoice_in_full_then_uses_the_last_100_on_a_200_invoice(): void
    {
        $this->enable();
        $first = $this->earlier('first', 10000, 'Water');
        $last = $this->earlier('last', 20000, 'Keys', '2026-09-02 00:00:00');
        $summary = $this->record(300);
        $this->assertSame('paid', $first->fresh()->status);
        $this->assertSame('partial', $last->fresh()->status);
        $this->assertSame(10000, $last->fresh()->amount_balance);
        $this->assertSame(0, $summary['wallet_remaining_cents']);
        $this->assertCount(2, $summary['payments']);
        $this->assertSame(10000, $summary['payments'][1]['paid_cents']);
    }

    public function test_automatic_debit_cannot_spend_the_reserved_refund_amount(): void
    {
        DB::table('refunds')->insert(['id' => 'held', 'lease_id' => 'other-lease', 'status' => 'approved', 'wallet_refund_cents' => 109000]);
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 119000, 'reserved_refund_id' => 'held']);
        try {
            Model::withoutEvents(fn () => app(\App\Services\WalletService::class)->debit('tenant', 10001, 'invoice_payment', 'original', automatic: true));
            $this->fail('Spent funds reserved for a refund');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('use_wallet', $exception->errors());
        }
        $this->assertSame(119000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(0, DB::table('wallet_transactions')->count());
    }

    public function test_skips_a_missing_receipt_template_and_partially_pays_the_next_invoice(): void
    {
        $this->enable();
        DB::table('document_templates')->insert(['id' => 'old-template', 'user_id' => 'another-account', 'category' => 'invoice', 'status' => 'active']);
        $blocked = $this->earlier('blocked', 5000, 'Water', extra: ['document_template_id' => 'old-template']);
        $next = $this->earlier('next', 20000, 'Keys', '2026-09-02 00:00:00');
        $summary = $this->record();
        $this->assertSame('unpaid', $blocked->fresh()->status);
        $this->assertSame('partial', $next->fresh()->status);
        $this->assertSame(10000, $next->fresh()->amount_balance);
        $this->assertSame(0, $summary['wallet_remaining_cents']);
        $this->assertSame(10000, $summary['wallet_used_cents']);
        $this->assertSame('INV-next', $summary['payments'][0]['invoice_no']);
        $this->assertStringContainsString('Skipped INV-blocked', $summary['notes'][0]);
    }

    public function test_skips_a_penalty_blocked_invoice_and_uses_wallet_on_the_next_one(): void
    {
        $this->enable();
        $blocked = $this->earlier('blocked', 9000, 'Rent', extra: ['due_date' => '2026-09-01']);
        $next = $this->earlier('next', 20000, 'Keys', '2026-09-02 00:00:00');
        $summary = $this->record();
        $this->assertSame('unpaid', $blocked->fresh()->status);
        $this->assertSame('partial', $next->fresh()->status);
        $this->assertSame(10000, $next->fresh()->amount_paid);
        $this->assertSame(0, $summary['wallet_remaining_cents']);
        $this->assertSame(0, Invoice::where('parent_id', 'blocked')->count());
        $this->assertNull(session('error'));
    }

    public function test_automatic_payment_spends_only_the_surplus_above_another_leases_approved_refund(): void
    {
        $this->enable();
        $next = $this->earlier('next', 20000, 'Water');
        DB::table('refunds')->insert(['id' => 'held', 'lease_id' => 'other-lease', 'status' => 'approved', 'wallet_refund_cents' => 109000]);
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 109000, 'reserved_refund_id' => 'held']);
        $summary = $this->record();
        $this->assertSame('partial', $next->fresh()->status);
        $this->assertSame(10000, $next->fresh()->amount_paid);
        $this->assertSame(10000, $summary['wallet_used_cents']);
        $this->assertSame(109000, $summary['wallet_remaining_cents']);
        $this->assertSame('held', DB::table('wallets')->value('reserved_refund_id'));
        $this->assertSame('approved', DB::table('refunds')->where('id', 'held')->value('status'));
        $this->assertStringContainsString('reserved', $summary['notes'][0]);
    }

    public function test_summary_identifies_the_refund_locked_invoice_and_the_next_invoice_actually_paid(): void
    {
        $this->enable();
        $locked = $this->earlier('locked', 9000, 'Water');
        $next = $this->earlier('next', 20000, 'Keys', '2026-09-02 00:00:00');
        DB::table('refunds')->insert(['id' => 'checkout', 'lease_id' => 'lease', 'status' => 'approved']);
        DB::table('refund_items')->insert(['id' => 'deduction', 'refund_id' => 'checkout', 'invoice_id' => 'locked']);
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 10000]);
        DB::table('invoices')->where('id', 'original')->update(['amount_paid' => 10000, 'amount_balance' => 0, 'status' => 'paid']);
        $summary = Model::withoutEvents(fn () => DB::transaction(fn () => app(\App\Services\OverpaymentWalletService::class)
            ->settle(Invoice::find('original'), '2026-10-19', app(InvoiceService::class))));
        $this->assertSame('unpaid', $locked->fresh()->status);
        $this->assertSame('partial', $next->fresh()->status);
        $this->assertSame(0, $summary['wallet_remaining_cents']);
        $this->assertSame('INV-locked', $summary['skipped'][0]['invoice_no']);
        $this->assertTrue($summary['skipped'][0]['refund_locked']);
        $this->assertSame('INV-next', $summary['skipped'][0]['next_paid_invoice_no']);
        session()->flash('wallet_auto_payment_summary', $summary);
        $html = \Illuminate\Support\Facades\Blade::render('<x-modals.wallet-payment-summary />');
        foreach (['Skipped Invoices', 'INV-locked', 'Locked to approved checkout refund', 'Next Invoice Paid', 'INV-next', 'RM100.00'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_refund_locked_invoice_is_listed_even_when_no_wallet_funds_are_spendable(): void
    {
        $this->enable();
        $this->earlier('locked', 9000, 'Water');
        DB::table('refunds')->insert(['id' => 'checkout', 'lease_id' => 'lease', 'status' => 'approved', 'wallet_refund_cents' => 10000]);
        DB::table('refund_items')->insert(['id' => 'deduction', 'refund_id' => 'checkout', 'invoice_id' => 'locked']);
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 10000, 'reserved_refund_id' => 'checkout']);
        $summary = Model::withoutEvents(fn () => DB::transaction(fn () => app(\App\Services\OverpaymentWalletService::class)
            ->settle(Invoice::find('original'), '2026-10-19', app(InvoiceService::class))));
        $this->assertSame(0, $summary['wallet_used_cents']);
        $this->assertSame(10000, $summary['wallet_reserved_cents']);
        $this->assertSame('INV-locked', $summary['skipped'][0]['invoice_no']);
        $this->assertTrue($summary['skipped'][0]['refund_locked']);
        $this->assertNull($summary['skipped'][0]['next_paid_invoice_no']);
    }

    public function test_skipped_invoice_reports_when_no_later_invoice_was_paid(): void
    {
        $this->enable();
        DB::table('document_templates')->insert(['id' => 'old-template', 'user_id' => 'another-account', 'category' => 'invoice', 'status' => 'active']);
        $this->earlier('blocked', 5000, 'Water', extra: ['document_template_id' => 'old-template']);
        $summary = $this->record();
        $this->assertNull($summary['skipped'][0]['next_paid_invoice_no']);
        session()->flash('wallet_auto_payment_summary', $summary);
        $html = \Illuminate\Support\Facades\Blade::render('<x-modals.wallet-payment-summary />');
        $this->assertStringContainsString('No later invoice was paid', $html);
        $this->assertStringContainsString('No automatic wallet payment was recorded', $html);
    }

    private function createNewInvoice(float $amount = 100): Invoice
    {
        return Model::withoutEvents(fn () => app(InvoiceService::class)->createManualInvoice(\App\Models\Lease::find('lease'), [
            'period' => '2026-11-01', 'due_date' => '2026-12-01',
            'items' => [['fee_type_id' => 'rent', 'amount' => $amount, 'description' => 'New rent']],
        ]));
    }

    public function test_checkout_with_approved_refund_rejects_manual_invoice_before_any_financial_changes(): void
    {
        $this->enable();
        Schema::table('leases', fn (Blueprint $t) => $t->string('status')->nullable());
        DB::table('leases')->where('id', 'lease')->update(['status' => 'Check Out']);
        DB::table('refunds')->insert(['id' => 'approved', 'lease_id' => 'lease', 'status' => 'approved']);
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 10000]);
        try {
            $this->createNewInvoice();
            $this->fail('Created a manual invoice after checkout refund approval');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
            $this->assertStringContainsString('checkout refund has been approved', $exception->errors()['refund'][0]);
        }
        $this->assertSame(1, Invoice::count());
        $this->assertSame(10000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame(0, DB::table('document_sequences')->count());
    }

    public function test_checkout_with_pending_refund_requires_return_to_draft_before_adding_invoice(): void
    {
        $this->enable(false);
        Schema::table('leases', fn (Blueprint $t) => $t->string('status')->nullable());
        DB::table('leases')->where('id', 'lease')->update(['status' => 'Check Out']);
        DB::table('refunds')->insert(['id' => 'pending', 'lease_id' => 'lease', 'status' => 'pending']);
        try {
            $this->createNewInvoice();
            $this->fail('Pending settlements must return to Draft before charges change.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $this->assertSame(1, Invoice::count());
    }

    public function test_new_invoice_is_paid_from_wallet_without_paying_existing_debt(): void
    {
        $this->enable();
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 15000]);
        request()->setLaravelSession(app('session.store'));
        $new = $this->createNewInvoice();
        $this->assertSame('paid', $new->status);
        $this->assertSame(10000, $new->amount_paid);
        $this->assertSame(0, $new->amount_balance);
        $this->assertSame('unpaid', Invoice::find('original')->status);
        $this->assertSame(5000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame('Wallet', DB::table('transactions')->value('payment_method'));
        $summary = session('wallet_auto_payment_summary');
        $this->assertSame('invoice_created', $summary['trigger']);
        $this->assertSame($new->invoice_no, $summary['payments'][0]['invoice_no']);
        $html = \Illuminate\Support\Facades\Blade::render('<x-modals.wallet-payment-summary />');
        $this->assertStringContainsString('newly generated invoice(s)', $html);
        $this->assertStringNotContainsString('after the overpayment', $html);
    }

    public function test_manual_invoice_json_includes_the_summary_for_the_browser_to_display(): void
    {
        $this->enable();
        request()->setLaravelSession(app('session.store'));
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 10000]);
        \Illuminate\Support\Facades\Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
        \Illuminate\Support\Facades\Gate::define('leases.add manual invoice', fn () => true);
        $request = \Mockery::mock(\App\Http\Requests\Invoice\StoreInvoiceRequest::class)->makePartial();
        $request->initialize();
        $request->shouldReceive('expectsJson')->once()->andReturn(true);
        $request->shouldReceive('validated')->once()->andReturn([
            'period' => '2026-11-01', 'due_date' => '2026-12-01',
            'items' => [['fee_type_id' => 'rent', 'amount' => 100]],
        ]);
        $response = Model::withoutEvents(fn () => app(\App\Http\Controllers\InvoiceController::class)
            ->storeManualInvoice($request, \App\Models\Lease::find('lease')));
        $json = $response->getData(true);
        $this->assertTrue($json['success']);
        $this->assertSame('paid', $json['invoice']['status']);
        $this->assertSame('100.00', $json['invoice']['amount_paid']);
        $this->assertSame('invoice_created', $json['wallet_auto_payment_summary']['trigger']);
        $this->assertSame(10000, $json['wallet_auto_payment_summary']['wallet_used_cents']);
    }

    public function test_new_invoice_uses_last_100_to_partially_pay_a_200_invoice(): void
    {
        $this->enable();
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 10000]);
        $new = $this->createNewInvoice(200);
        $this->assertSame('partial', $new->status);
        $this->assertSame(10000, $new->amount_paid);
        $this->assertSame(10000, $new->amount_balance);
        $this->assertSame(0, (int) DB::table('wallets')->value('balance'));
    }

    public function test_creating_invoice_with_setting_off_never_checks_wallets(): void
    {
        $this->enable(false);
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 10000]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $new = $this->createNewInvoice();
        foreach ($queries as $sql) $this->assertStringNotContainsString('wallet', strtolower($sql));
        $this->assertSame('unpaid', $new->status);
        $this->assertSame(0, DB::table('transactions')->count());
    }

    public function test_creating_invoice_without_wallet_balance_leaves_it_unpaid(): void
    {
        $this->enable();
        $new = $this->createNewInvoice();
        $this->assertSame('unpaid', $new->status);
        $this->assertSame(0, DB::table('wallets')->count());
        $this->assertSame(0, DB::table('transactions')->count());
    }

    public function test_new_invoice_spends_surplus_but_preserves_reserved_refund_funds(): void
    {
        $this->enable();
        DB::table('refunds')->insert(['id' => 'held', 'lease_id' => 'other-lease', 'status' => 'approved', 'wallet_refund_cents' => 109000]);
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 119000, 'reserved_refund_id' => 'held']);
        $new = $this->createNewInvoice(200);
        $this->assertSame('partial', $new->status);
        $this->assertSame(10000, $new->amount_paid);
        $this->assertSame(109000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame('held', DB::table('wallets')->value('reserved_refund_id'));
    }

    public function test_rechecking_created_invoice_does_not_charge_wallet_twice(): void
    {
        $this->enable();
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 20000]);
        $new = $this->createNewInvoice();
        $result = Model::withoutEvents(fn () => DB::transaction(fn () => app(InvoiceService::class)->applyWalletToNewInvoice($new, User::find('admin'))));
        $this->assertNull($result);
        $this->assertSame(10000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(1, DB::table('transactions')->count());
    }

    public function test_generated_invoice_summaries_are_combined_after_commit(): void
    {
        $this->enable();
        request()->setLaravelSession(app('session.store'));
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 15000]);
        DB::transaction(function () {
            $this->createNewInvoice();
            $this->createNewInvoice();
            $this->assertNull(session('wallet_auto_payment_summary'));
        });
        $summary = session('wallet_auto_payment_summary');
        $this->assertCount(2, $summary['payments']);
        $this->assertSame(15000, $summary['wallet_used_cents']);
        $this->assertSame(0, $summary['wallet_remaining_cents']);
    }

    public function test_scheduled_recurring_generation_uses_the_issuer_without_a_logged_in_user(): void
    {
        $this->enable();
        Schema::table('leases', function (Blueprint $t) {
            $t->boolean('is_current')->default(true); $t->string('status')->default('New');
            foreach (['start_date', 'end_date', 'cancelled_at', 'checked_out_at', 'agreement_ended_at'] as $field) $t->date($field)->nullable();
        });
        Schema::create('lease_charges', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('lease_id'); $t->string('fee_type_id');
            $t->string('description')->default('Monthly rent'); $t->integer('amount')->default(12000);
            $t->string('charge_type')->default('recurring'); $t->string('frequency')->default('monthly');
            $t->date('next_billing_date')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); $t->softDeletes();
        });
        DB::table('leases')->where('id', 'lease')->update(['start_date' => '2026-09-30', 'end_date' => '2027-09-30']);
        DB::table('fee_types')->insert(['id' => 'monthly', 'name' => 'Monthly Rent', 'category' => 'rent', 'is_system' => true, 'is_active' => true]);
        DB::table('lease_charges')->insert(['id' => 'charge', 'lease_id' => 'lease', 'fee_type_id' => 'monthly']);
        DB::table('settings')->insert(['user_id' => 'admin', 'key' => 'due_date_config', 'is_active' => true,
            'value' => json_encode(['recurring_type' => 'both', 'days' => 7, 'recurring_feq_days' => 0])]);
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 10000]);
        auth()->logout();
        $generate = fn (bool $dryRun = false) => Model::withoutEvents(fn () => app(\App\Services\RecurringInvoiceService::class)
            ->generate(\App\Models\Lease::find('lease'), User::find('admin'), true, $dryRun));
        $this->assertSame(1, $generate(true));
        $this->assertSame(10000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertSame(1, $generate());
        $new = Invoice::where('id', '!=', 'original')->firstOrFail();
        $this->assertSame('partial', $new->status);
        $this->assertSame(10000, $new->amount_paid);
        $this->assertSame(2000, $new->amount_balance);
        $this->assertSame('admin', DB::table('transactions')->value('approved_by'));
        $this->assertSame('Wallet', DB::table('transactions')->value('payment_method'));
        $this->assertSame(0, $generate());
        $this->assertSame(1, DB::table('transactions')->count());
    }

    public function test_rolled_back_invoice_creation_does_not_publish_a_payment_summary(): void
    {
        $this->enable();
        request()->setLaravelSession(app('session.store'));
        DB::table('wallets')->insert(['id' => 'wallet', 'user_id' => 'tenant', 'balance' => 10000]);
        try {
            DB::transaction(function () {
                $this->createNewInvoice();
                throw new \RuntimeException('Rollback invoice creation');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('Rollback invoice creation', $exception->getMessage());
        }
        $this->assertSame(10000, (int) DB::table('wallets')->value('balance'));
        $this->assertSame(1, Invoice::count());
        $this->assertSame(0, DB::table('transactions')->count());
        $this->assertNull(session('wallet_auto_payment_summary'));
    }

    private function invoiceViewPermissions(array $permissions): void
    {
        \Illuminate\Support\Facades\Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
        foreach ($permissions as $permission) \Illuminate\Support\Facades\Gate::define($permission, fn () => true);
    }

    public function test_lease_rows_paginate_on_the_server_and_do_not_send_document_html_or_variables(): void
    {
        $this->invoiceViewPermissions(['leases.show', 'invoice.view invoice', 'invoice.view receipt']);
        for ($number = 0; $number < 7; $number++) $this->earlier('row-'.$number, 1000, 'Water');
        DB::table('document_templates')->where('id', 'invoice-template')->update(['title' => 'Invoice', 'html_template' => 'PRIVATE TEMPLATE CONTENT']);
        $controller = app(\App\Http\Controllers\InvoiceController::class);
        $first = $controller->leaseRows(Request::create('/rows', 'GET', ['page' => 1]), \App\Models\Lease::find('lease'))->getData(true);
        $second = $controller->leaseRows(Request::create('/rows', 'GET', ['page' => 2]), \App\Models\Lease::find('lease'))->getData(true);
        $this->assertCount(5, $first['invoices']);
        $this->assertCount(3, $second['invoices']);
        $this->assertSame(8, $first['pagination']['total']);
        $this->assertSame([], array_intersect(array_column($first['invoices'], 'id'), array_column($second['invoices'], 'id')));
        $this->assertStringNotContainsString('PRIVATE TEMPLATE CONTENT', json_encode($first));
        $this->assertArrayNotHasKey('variables', $first['invoices'][0]);
        $this->assertNotEmpty($first['invoices'][0]['document_url']);
    }

    public function test_all_invoice_table_returns_multiple_authorized_leases_with_the_correct_wallet_for_each(): void
    {
        $this->invoiceViewPermissions(['invoice.tab', 'invoice.view invoice']);
        DB::table('users')->insert(['id' => 'tenant-two', 'role' => 'tenant', 'name' => 'Second Tenant', 'email' => 'two@test.test']);
        DB::table('tenants')->insert(['id' => 'tenant-record-two', 'user_id' => 'tenant-two', 'created_by' => 'admin']);
        DB::table('leases')->insert(['id' => 'lease-two', 'tenant_id' => 'tenant-record-two', 'leasable_type' => Property::class, 'leasable_id' => 'property']);
        $second = $this->earlier('second', 10000, 'Water', extra: ['lease_id' => 'lease-two']);
        DB::table('tenants')->insert(['id' => 'foreign-tenant', 'user_id' => 'tenant', 'created_by' => 'another-account']);
        DB::table('leases')->insert(['id' => 'foreign-lease', 'tenant_id' => 'foreign-tenant', 'leasable_type' => Property::class, 'leasable_id' => 'property']);
        $this->earlier('foreign', 10000, 'Water', extra: ['lease_id' => 'foreign-lease']);
        DB::table('wallets')->insert([
            ['id' => 'wallet-one', 'user_id' => 'tenant', 'balance' => 700],
            ['id' => 'wallet-two', 'user_id' => 'tenant-two', 'balance' => 2300],
        ]);
        $data = app(\App\Http\Controllers\InvoiceController::class)->indexRows(Request::create('/invoices/rows'))->getData(true);
        $rows = collect($data['invoices'])->keyBy('id');
        $this->assertCount(2, $rows);
        $this->assertSame('7.00', $rows['original']['wallet_balance']);
        $this->assertSame('23.00', $rows[$second->id]['wallet_balance']);
        $this->assertSame('Second Tenant', $rows[$second->id]['tenant']['name']);
        $this->assertArrayNotHasKey('template_html', $rows['original']);
        $leaseData = app(\App\Services\InvoiceTableService::class)->page(\App\Models\Lease::find('lease'));
        $this->assertCount(1, $leaseData['invoices']);
        $this->assertSame('original', $leaseData['invoices'][0]['id']);
    }

    public function test_all_invoice_table_paginates_ten_rows_and_does_not_allow_lease_permission_to_read_all(): void
    {
        $this->invoiceViewPermissions(['invoice.tab']);
        for ($number = 0; $number < 11; $number++) $this->earlier('row-'.$number, 1000, 'Water');
        $controller = app(\App\Http\Controllers\InvoiceController::class);
        $first = $controller->indexRows(Request::create('/invoices/rows'))->getData(true);
        $second = $controller->indexRows(Request::create('/invoices/rows', 'GET', ['page' => 2]))->getData(true);
        $this->assertCount(10, $first['invoices']);
        $this->assertCount(2, $second['invoices']);
        $this->assertSame(12, $first['pagination']['total']);
        $this->invoiceViewPermissions(['leases.show']);
        try { $controller->indexRows(Request::create('/invoices/rows')); $this->fail('All-invoice permission bypassed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
    }

    public function test_invoice_index_receives_controller_paginator_and_renders_native_page_links(): void
    {
        $this->invoiceViewPermissions(['invoice.tab']);
        for ($number = 0; $number < 11; $number++) $this->earlier('row-'.$number, 1000, 'Water');
        $view = app(\App\Http\Controllers\InvoiceController::class)->index(Request::create('/invoices', 'GET', ['page' => 2]));
        $invoices = $view->getData()['invoices'];
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $invoices);
        $this->assertSame(2, $invoices->currentPage());
        $this->assertSame(12, $invoices->total());
        $this->assertCount(2, $invoices->items());
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $route = app('router')->getRoutes()->getByName('admin.invoices.index');
        $route->bind(request());
        request()->setRouteResolver(fn () => $route);
        $html = \Illuminate\Support\Facades\Blade::render('<x-table.invoice-table :invoices="$invoices" :ajax="false" :show-tenant="true" />', compact('invoices'));
        $this->assertStringContainsString('page=1', $html);
        $this->assertStringNotContainsString('x-for="page in paginationLinks"', $html);
        $this->assertStringNotContainsString('overflow-x-auto border', $html);
    }

    public function test_invoice_table_only_exposes_payment_proof_urls_with_permission_and_never_the_storage_path(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('invoice_id'); $t->string('receipt_path')->nullable(); $t->timestamps();
        });
        DB::table('payments')->insert(['id' => 'proof', 'invoice_id' => 'original', 'receipt_path' => 'private/proof.pdf']);
        $this->invoiceViewPermissions(['invoice.tab', 'invoice.view payment proof']);
        $controller = app(\App\Http\Controllers\InvoiceController::class);
        $data = $controller->indexRows(Request::create('/invoices/rows'))->getData(true);
        $proof = $data['invoices'][0]['payment_proofs'][0];
        $this->assertTrue($proof['is_pdf']);
        $this->assertStringContainsString('/payments/proof/receipt', $proof['url']);
        $this->assertStringNotContainsString('private/proof.pdf', json_encode($data));
        $this->invoiceViewPermissions(['invoice.tab']);
        $data = $controller->indexRows(Request::create('/invoices/rows'))->getData(true);
        $this->assertSame([], $data['invoices'][0]['payment_proofs']);
        $this->assertStringNotContainsString('/payments/proof/receipt', json_encode($data));
    }

    public function test_shared_invoice_table_renders_optional_tenant_column_and_only_one_set_of_modals(): void
    {
        $this->invoiceViewPermissions(['invoice.tab', 'leases.show']);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $route = app('router')->getRoutes()->getByName('admin.invoices.index');
        $route->bind(request());
        request()->setRouteResolver(fn () => $route);
        $html = \Illuminate\Support\Facades\Blade::render('<x-table.invoice-table endpoint="/lease-rows" lease-id="lease" /><x-table.invoice-table endpoint="/all-rows" :show-tenant="true" />');
        $this->assertSame(1, substr_count($html, 'Tenant Details'));
        $this->assertSame(1, substr_count($html, 'name="amount_paid"'));
        $this->assertSame(1, substr_count($html, 'name="reason"'));
        $this->assertStringContainsString('invoiceTable(', $html);
    }

    public function test_guessing_another_accounts_lease_or_invoice_cannot_read_rows_or_documents(): void
    {
        $this->invoiceViewPermissions(['leases.show', 'invoice.view invoice']);
        DB::table('tenants')->insert(['id' => 'other-tenant', 'user_id' => 'tenant', 'created_by' => 'someone-else']);
        DB::table('leases')->insert(['id' => 'other-lease', 'tenant_id' => 'other-tenant', 'leasable_type' => Property::class, 'leasable_id' => 'property']);
        $foreign = $this->earlier('foreign', 1000, 'Water', extra: ['lease_id' => 'other-lease']);
        $controller = app(\App\Http\Controllers\InvoiceController::class);
        foreach ([fn () => $controller->leaseRows(Request::create('/rows'), \App\Models\Lease::find('other-lease')),
            fn () => $controller->documentData($foreign)] as $action) {
            try { $action(); $this->fail('Cross-account data was exposed'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        }
    }

    public function test_receipt_preview_checks_its_permission_and_invoice_relationship(): void
    {
        $this->record(100);
        $transaction = \App\Models\Transaction::firstOrFail();
        $controller = app(\App\Http\Controllers\InvoiceController::class);
        $this->invoiceViewPermissions(['leases.show', 'invoice.view invoice']);
        try { $controller->receiptData(Invoice::find('original'), $transaction); $this->fail('Receipt permission bypassed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        $this->invoiceViewPermissions(['invoice.view receipt']);
        $another = $this->earlier('another', 1000, 'Water');
        try { $controller->receiptData($another, $transaction); $this->fail('Receipt invoice relationship bypassed'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(404, $error->getStatusCode()); }
    }

    public function test_authorized_document_requests_use_the_invoice_and_receipts_saved_template_ids(): void
    {
        $this->record(100);
        $this->invoiceViewPermissions(['invoice.view invoice', 'invoice.view receipt']);
        DB::table('document_templates')->where('id', 'invoice-template')->update(['html_template' => '<p>Saved invoice template</p>']);
        DB::table('document_templates')->where('id', 'receipt-template')->update(['html_template' => '<p>Saved receipt template</p>']);
        $controller = app(\App\Http\Controllers\InvoiceController::class);
        $invoice = $controller->documentData(Invoice::find('original'))->getData(true);
        $receipt = $controller->receiptData(Invoice::find('original'), \App\Models\Transaction::firstOrFail())->getData(true);
        $this->assertSame('<p>Saved invoice template</p>', $invoice['content']);
        $this->assertSame('<p>Saved receipt template</p>', $receipt['content']);
        $this->assertSame('100.00', $receipt['extra']['paidAmount']);
        $this->assertSame('100.00', $receipt['extra']['invoiceTotal']);
        $this->assertSame('Cash', $receipt['extra']['receiptVariables']['payment_method']);
        $this->assertSame('Rental', $invoice['items'][0]['name']);
    }

    public function test_ajax_payment_returns_committed_wallet_summary_without_flashing_it_for_a_later_page(): void
    {
        $this->enable();
        $this->earlier('water', 10000, 'Water');
        $this->invoiceViewPermissions(['invoice.record payment']);
        $request = \Mockery::mock(\App\Http\Requests\Invoice\RecordPaymentRequest::class)->makePartial();
        $request->initialize();
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('validated')->once()->andReturn(['amount_paid' => 200, 'payment_method' => 'Cash', 'payment_date' => '2026-10-19']);
        $response = Model::withoutEvents(fn () => app(\App\Http\Controllers\InvoiceController::class)->recordPayment($request, Invoice::find('original')));
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame('success', $data['session_status']['type']);
        $this->assertSame(session('success'), $data['session_status']['message']);
        $this->assertSame('lease', $data['lease_id']);
        $this->assertSame('0.00', $data['wallet_balance']);
        $this->assertStringContainsString('INV-water', $data['wallet_summary_html']);
        $this->assertSame('paid', Invoice::find('original')->status);
        $this->assertNull(session('wallet_auto_payment_summary'));
    }

    public function test_earlier_outstanding_error_returns_session_message_as_json_instead_of_redirecting(): void
    {
        $this->enable(false);
        $this->earlier('older', 5000, 'Water');
        $this->invoiceViewPermissions(['invoice.record payment']);
        $request = \Mockery::mock(\App\Http\Requests\Invoice\RecordPaymentRequest::class)->makePartial();
        $request->initialize();
        $request->shouldReceive('expectsJson')->andReturn(true);
        $request->shouldReceive('validated')->once()->andReturn(['amount_paid' => 100, 'payment_method' => 'Cash', 'payment_date' => '2026-10-19']);
        $response = Model::withoutEvents(fn () => app(\App\Http\Controllers\InvoiceController::class)->recordPayment($request, Invoice::find('original')));
        $this->assertSame(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertSame('error', $data['session_status']['type']);
        $this->assertSame(session('error'), $data['session_status']['message']);
        $this->assertStringContainsString('Earlier outstanding invoices', session('error'));
        $this->assertSame(0, DB::table('transactions')->count());
    }

    public function test_request_validation_errors_are_flashed_to_session_and_returned_in_json(): void
    {
        $request = new class extends \App\Http\Requests\Invoice\RecordPaymentRequest {
            public function reject(\Illuminate\Contracts\Validation\Validator $validator) { $this->failedValidation($validator); }
        };
        $request->headers->set('Accept', 'application/json');
        $validator = app('validator')->make(['amount_paid' => -1], ['amount_paid' => 'numeric|min:0']);
        try { $request->reject($validator); $this->fail('Invalid amount was accepted'); }
        catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
            $response = $exception->getResponse();
            $this->assertSame(422, $response->getStatusCode());
            $this->assertSame(session('error'), $response->getData(true)['session_status']['message']);
            $this->assertArrayHasKey('amount_paid', $response->getData(true)['errors']);
        }
        $this->assertSame(0, DB::table('transactions')->count());
    }

    public function test_record_payment_redirect_flashes_the_completed_summary(): void
    {
        $this->enable();
        $this->earlier('water', 10000, 'Water Bill');
        \Illuminate\Support\Facades\Gate::swap(new \Illuminate\Auth\Access\Gate($this->app, fn () => auth()->user()));
        \Illuminate\Support\Facades\Gate::define('invoice.record payment', fn () => true);
        $request = \Mockery::mock(\App\Http\Requests\Invoice\RecordPaymentRequest::class)->makePartial();
        $request->initialize();
        $request->shouldReceive('validated')->once()->andReturn([
            'amount_paid' => 200, 'payment_method' => 'Cash', 'payment_date' => '2026-10-19', 'transaction_ref' => 'CASH',
        ]);
        $response = Model::withoutEvents(fn () => app(\App\Http\Controllers\InvoiceController::class)->recordPayment($request, Invoice::find('original')));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(10000, session('wallet_auto_payment_summary.wallet_used_cents'));
        $this->assertSame('INV-water', session('wallet_auto_payment_summary.payments.0.invoice_no'));
    }

    public function test_summary_renders_the_requested_invoice_fee_names_amounts_and_wallet_remaining(): void
    {
        $this->enable();
        $this->earlier('water', 9000, 'Water Bill');
        $this->earlier('keys', 1000, 'Late Key Charges', '2026-09-02 00:00:00');
        session()->flash('wallet_auto_payment_summary', $this->record());
        $html = \Illuminate\Support\Facades\Blade::render('<x-modals.wallet-payment-summary />');
        foreach (['Auto record payment using wallet', 'Wallet balance used', 'Paid Invoices', 'INV-water',
            'Water Bill', 'INV-keys', 'Late Key Charges', 'RM100.00', 'RM90.00', 'RM10.00', 'RM0.00',
            'Wallet remaining', 'open-wallet-payment-summary'] as $text) $this->assertStringContainsString($text, $html);
    }
}
