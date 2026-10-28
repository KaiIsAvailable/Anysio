<?php

namespace Tests\Feature;

use App\Models\{Invoice, Property, Unit, Room};
use App\Services\InvoiceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Blade, DB, Schema, Validator};
use Tests\TestCase;

class InvoiceStatementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        foreach ([
            'users' => ['name', 'created_by', 'owner_id'],
            'tenants' => ['user_id', 'created_by'],
            'properties' => ['owner_id'], 'units' => ['owner_id', 'property_id'], 'rooms' => ['unit_id'],
            'leases' => ['tenant_id', 'leasable_type', 'leasable_id'],
            'invoices' => ['lease_id', 'user_id', 'invoice_no', 'document_template_id', 'status', 'remarks'],
            'invoice_items' => ['invoice_id', 'fee_type_id', 'lease_charge_id', 'description'], 'fee_types' => ['name'],
            'document_templates' => ['user_id'],
            'transactions' => ['invoice_id', 'receipt_no', 'payment_method', 'transaction_ref', 'payment_date', 'remarks'],
            'wallets' => ['user_id'], 'wallet_transactions' => ['wallet_id', 'reference_id', 'type', 'remarks'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($table, $columns) {
                $t->string('id')->primary(); $t->timestamps();
                foreach ($columns as $column) $t->text($column)->nullable();
                if (in_array($table, ['invoices', 'transactions'])) $t->softDeletes();
                foreach (match ($table) {
                    'invoices' => ['total_amount', 'amount_balance'], 'transactions' => ['amount_paid', 'amount_applied', 'amount_excess'],
                    'wallets' => ['balance'], 'wallet_transactions' => ['amount'], default => [],
                } as $column) $t->bigInteger($column)->default(0);
            });
        }
        foreach (['manager', 'other', 'alice', 'bob', 'owner'] as $id) {
            $this->insert('users', ['id' => $id, 'name' => ucfirst($id)]);
        }
        $this->insert('tenants', ['id' => 'ta', 'user_id' => 'alice', 'created_by' => 'manager']);
        $this->insert('tenants', ['id' => 'tb', 'user_id' => 'bob', 'created_by' => 'other']);
        $this->insert('properties', ['id' => 'p', 'owner_id' => 'owner']);
        foreach (['la' => 'ta', 'la2' => 'ta', 'lb' => 'tb'] as $id => $tenant) {
            $this->insert('leases', ['id' => $id, 'tenant_id' => $tenant, 'leasable_type' => Property::class, 'leasable_id' => 'p']);
        }
        $this->insert('document_templates', ['id' => 'doc', 'user_id' => 'manager']);
        foreach (['ia' => 'la', 'ia2' => 'la2', 'ib' => 'lb'] as $id => $lease) {
            $this->insert('invoices', ['id' => $id, 'lease_id' => $lease, 'invoice_no' => strtoupper($id), 'total_amount' => 100000, 'document_template_id' => 'doc', 'status' => 'paid']);
        }
        $this->insert('fee_types', ['id' => 'rent', 'name' => 'Rental']);
        $this->insert('fee_types', ['id' => 'fee', 'name' => 'Service fee']);
        $this->insert('invoice_items', ['id' => 'item1', 'invoice_id' => 'ia', 'fee_type_id' => 'rent', 'description' => 'October rent']);
        $this->insert('invoice_items', ['id' => 'item2', 'invoice_id' => 'ia', 'fee_type_id' => 'fee', 'description' => null]);
        $this->insert('transactions', ['id' => 'receipt', 'invoice_id' => 'ia', 'amount_paid' => 120000, 'amount_applied' => 100000, 'amount_excess' => 20000, 'payment_date' => '2026-10-20', 'receipt_no' => 'R1', 'payment_method' => 'bank_transfer']);
        $this->insert('wallets', ['id' => 'wa', 'user_id' => 'alice', 'balance' => 99999999]);
        $this->insert('wallets', ['id' => 'wb', 'user_id' => 'bob', 'balance' => 88888888]);
        $this->movement('credit', 'wa', 'ia', 20000, 'overpayment_credit');
        $this->movement('debit', 'wa', 'receipt', -30000, 'invoice_payment');
        $this->movement('other-lease', 'wa', 'ia2', 1500, 'overpayment_credit');
        $this->movement('private', 'wb', 'ib', 500, 'overpayment_credit');
        $this->movement('unlinked', 'wa', null, 700, 'manual_credit');
        $this->movement('private-unlinked', 'wb', null, 800, 'manual_credit');
    }

    private function insert(string $table, array $data): void
    {
        DB::table($table)->insert($data + ['created_at' => '2026-10-20 12:34:00', 'updated_at' => '2026-10-20 12:34:00']);
    }

    public function test_statement_modal_hides_filters_and_opts_into_existing_shake_only(): void
    {
        $html = Blade::render('<x-invoice-statement-modal endpoint="/statement" />');
        $this->assertStringContainsString('aria-label="Close Invoice Statement"', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('Loading statement', $html);
        $this->assertStringContainsString('shake = true; setTimeout', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="show = false"', $html);
        foreach (['<form', 'statement_from', 'statement_to', 'Search History', 'Record Type', 'Clear'] as $filter) {
            $this->assertStringNotContainsString($filter, $html);
        }
        $default = Blade::render('<x-modal name="unchanged">Other modal</x-modal>');
        $this->assertStringContainsString('x-on:click="show = false"', $default);
        $this->assertStringNotContainsString('shake = true; setTimeout', $default);
    }

    public function test_scope_labels_have_no_rendered_text_or_dedicated_container(): void
    {
        foreach (['Records for this lease only', 'All records you can view'] as $label) {
            $html = view('components.invoice-statement', [
                'statement' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
                'scopeLabel' => $label,
            ])->render();
            $this->assertStringNotContainsString($label, $html);
            $this->assertMatchesRegularExpression('/<section[^>]*>\s*<div class="overflow-x-auto">/', $html);
            $this->assertStringContainsString('No statement records', $html);
        }
    }

    public function test_statement_endpoints_enforce_permissions_and_lease_scope(): void
    {
        $user = \App\Models\User::findOrFail('manager');
        $user->email_verified_at = now();
        $this->actingAs($user);
        \Illuminate\Support\Facades\Gate::swap(new \Illuminate\Auth\Access\Gate(app(), fn () => $user));
        \Illuminate\Support\Facades\Gate::before(fn ($user, $ability) => in_array($ability, ['invoice.tab', 'leases.show']));
        // Short fixture IDs use explicit binding instead of the model's ULID validation.
        \Illuminate\Support\Facades\Route::bind('lease', fn ($id) => \App\Models\Lease::whereKey($id)->firstOrFail());
        $leaseUrl = route('admin.leases.invoice-statement', 'la');
        $globalUrl = route('admin.invoices.statement');
        $html = $this->getJson($leaseUrl.'?lease_id=lb')->assertOk()->json('html');
        $this->assertStringContainsString('invoice:ia', $html);
        $this->assertStringNotContainsString('Tenant / Lease', $html);
        $this->assertStringContainsString('Tenant Wallet', $html);
        $this->assertStringNotContainsString('invoice:ia2', $html);
        $this->assertStringNotContainsString('invoice:ib', $html);
        $this->getJson(route('admin.leases.invoice-statement', 'lb'))->assertForbidden();
        $this->getJson(route('admin.leases.invoice-statement', 'missing'))->assertNotFound();
        $html = $this->getJson($globalUrl.'?page=99&search=outside&lease_id=lb')->assertOk()->json('html');
        $this->assertStringContainsString('invoice:ia2', $html);
        $this->assertStringContainsString('Tenant / Lease', $html);
        $this->assertStringNotContainsString('Available across leases', $html);
        $this->assertStringNotContainsString('invoice:ib', $html);
        $this->getJson($globalUrl.'?statement_kind=invalid')->assertUnprocessable();
        $html = $this->getJson($leaseUrl.'?statement_invoice=absent')->assertOk()->json('html');
        $this->assertStringContainsString('No statement records', $html);
        for ($i = 0; $i < 25; $i++) $this->movement('page-'.$i, 'wa', 'ia', 1, 'manual_credit');
        $html = $this->getJson($leaseUrl.'?statement_page=2&statement_kind=wallet_credit&page=99')->assertOk()->json('html');
        $this->assertStringContainsString('statement_page=1', $html);
        $this->assertStringContainsString('statement_kind=wallet_credit', $html);
        $this->assertStringNotContainsString('page=99', $html);
    }

    public function test_statement_endpoints_deny_users_without_existing_permissions(): void
    {
        $user = \App\Models\User::findOrFail('manager');
        $user->email_verified_at = now();
        $this->actingAs($user);
        \Illuminate\Support\Facades\Gate::swap(new \Illuminate\Auth\Access\Gate(app(), fn () => $user));
        \Illuminate\Support\Facades\Gate::before(fn () => false);
        \Illuminate\Support\Facades\Route::bind('lease', fn ($id) => \App\Models\Lease::whereKey($id)->firstOrFail());
        $this->getJson(route('admin.invoices.statement'))->assertForbidden();
        $this->getJson(route('admin.leases.invoice-statement', 'la'))->assertForbidden();
    }

    private function movement(string $id, string $wallet, ?string $ref, int $amount, string $type): void
    {
        $this->insert('wallet_transactions', ['id' => $id, 'wallet_id' => $wallet, 'reference_id' => $ref, 'amount' => $amount, 'type' => $type, 'remarks' => 'History '.$id]);
    }

    public function test_lease_history_groups_explicit_sources_without_join_multiplication_or_balance_fabrication(): void
    {
        $page = app(InvoiceService::class)->getStatement('manager', 'manager', false, [], 'la');
        $rows = $page->getCollection()->keyBy('id');
        $this->assertSame(3, $page->total());
        $this->assertSame(100000, $rows['invoice:ia']['amount_cents']);
        $this->assertSame(100000, $rows['payment:receipt']['amount_cents']);
        $this->assertSame(20000, $rows['wallet_credit:credit']['amount_cents']);
        $this->assertSame(-30000, $rows['payment:receipt']['linked_wallet'][0]->amount);
        $this->assertSame('Alice', $rows['wallet_credit:credit']['party']);
        $this->assertSame('Manager', $rows['invoice:ia']['issuer']);
        $this->assertSame('Owner', $rows['payment:receipt']['payee']);
        $this->assertSame(['Rental', 'Service fee'], array_column($rows['payment:receipt']['fees'], 'name'));
        $this->assertSame('October rent', $rows['invoice:ia']['fees'][0]['description']);
        $this->assertNull($rows['invoice:ia']['fees'][1]['description']);
        $this->assertArrayNotHasKey('note', $rows['wallet_credit:credit']);
        $this->assertSame('History credit', $rows['wallet_credit:credit']['description']);
        $this->assertSame('History debit', $rows['payment:receipt']['linked_wallet'][0]->description);
        $this->assertFalse($rows->contains(fn ($r) => array_key_exists('balance', $r)));
        $this->assertSame(99999999, DB::table('wallets')->where('id', 'wa')->value('balance'));
    }

    public function test_global_scope_is_not_limited_to_invoice_list_page_and_excludes_other_managers(): void
    {
        $rows = app(InvoiceService::class)->getStatement('staff', 'manager', false)->getCollection();
        $this->assertContains('invoice:ia2', $rows->pluck('id'));
        $this->assertContains('wallet_credit:unlinked', $rows->pluck('id'));
        $this->assertNotContains('invoice:ib', $rows->pluck('id'));
        $this->assertNotContains('wallet_credit:private', $rows->pluck('id'));
        $this->assertNotContains('wallet_credit:private-unlinked', $rows->pluck('id'));
        $this->assertSame(0, app(InvoiceService::class)->getStatement('manager', 'manager', false, [], 'lb')->total());
        $this->assertContains('invoice:ib', app(InvoiceService::class)->getStatement('root', 'root', true)->getCollection()->pluck('id'));
        $this->assertSame(0, app(InvoiceService::class)->getStatement(null, null, false)->total());
    }

    public function test_soft_deleted_records_and_mismatched_wallet_owners_do_not_leak(): void
    {
        $this->movement('wrong-owner', 'wb', 'ia', 999, 'manual_credit');
        DB::table('invoices')->where('id', 'ia2')->update(['deleted_at' => '2026-10-21']);
        DB::table('transactions')->where('id', 'receipt')->update(['deleted_at' => '2026-10-21']);
        $ids = app(InvoiceService::class)->getStatement('manager', 'manager', false)->getCollection()->pluck('id');
        foreach (['invoice:ia2', 'wallet_credit:other-lease', 'payment:receipt', 'wallet_payment:debit', 'wallet_credit:wrong-owner'] as $id) $this->assertNotContains($id, $ids);
    }

    public function test_filters_pagination_and_safe_shared_blade(): void
    {
        $service = app(InvoiceService::class);
        $page = $service->getStatement('manager', 'manager', false, ['statement_from' => '2026-10-20', 'statement_to' => '2026-10-20', 'statement_kind' => 'payment', 'statement_invoice' => 'IA']);
        $this->assertSame(1, $page->total());
        $this->assertSame('20/10/2026', $page[0]['date']);
        $this->assertSame('20/10/2026 12:34:00', $page[0]['recorded_at']);
        $this->assertSame(0, $service->getStatement('manager', 'manager', false, ['statement_from' => '2026-10-21'])->total());
        DB::table('invoice_items')->where('id', 'item1')->update(['description' => '<script>bad()</script>']);
        $page = $service->getStatement('manager', 'manager', false, [], 'la');
        $html = Blade::render('<x-invoice-statement :statement="$statement" />', ['statement' => $page]);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>bad()', $html);
        $this->assertStringNotContainsString('Balance', $html);
        $this->assertStringContainsString('History credit', $html);
        $this->assertStringContainsString('History debit', $html);
        foreach (['do not add again', 'not an additional receipt', 'may include wallet funds', 'no invoice payment allocation inferred', 'Wallet movement, not an invoice payment', 'not amounts to add together', 'No description'] as $explanation) {
            $this->assertStringNotContainsString($explanation, $html);
        }
        for ($i = 0; $i < 25; $i++) $this->movement('extra-'.$i, 'wa', 'ia', 1, 'manual_credit');
        $page = $service->getStatement('manager', 'manager', false, ['statement_page' => 2], 'la');
        $this->assertSame(28, $page->total());
        $this->assertSame(8, $page->count());
        $this->assertStringContainsString('statement_page=1', $page->url(1));
    }

    public function test_date_validation_allows_open_ended_range_and_rejects_invalid_input(): void
    {
        foreach ([['statement_to' => '2026-10-20'], ['statement_from' => '2026-10-20']] as $data) {
            $this->assertTrue(Validator::make($data, InvoiceService::statementFilterRules())->passes());
        }
        foreach ([['statement_from' => 'bad'], ['statement_page' => -1], ['statement_kind' => 'refund_action'], ['statement_from' => '2026-10-21', 'statement_to' => '2026-10-20']] as $data) {
            $this->assertTrue(Validator::make($data, InvoiceService::statementFilterRules())->fails());
        }
    }

    public function test_existing_owner_visibility_applies_to_property_unit_and_room(): void
    {
        DB::table('users')->where('id', 'owner')->update(['created_by' => 'manager']);
        $this->insert('units', ['id' => 'u', 'property_id' => 'p', 'owner_id' => 'owner']);
        $this->insert('rooms', ['id' => 'r', 'unit_id' => 'u']);
        foreach ([[Property::class, 'p'], [Unit::class, 'u'], [Room::class, 'r']] as [$type, $id]) {
            DB::table('leases')->where('id', 'lb')->update(['leasable_type' => $type, 'leasable_id' => $id]);
            $rows = app(InvoiceService::class)->getStatement('staff', 'manager', false, [], 'lb');
            $this->assertContains('invoice:ib', $rows->getCollection()->pluck('id'));
            $this->assertContains('wallet_credit:private', $rows->getCollection()->pluck('id'));
        }
    }

    public function test_unresolved_references_and_existing_refund_history_are_read_only(): void
    {
        $this->movement('unknown', 'wa', 'missing-reference', 1234, 'manual_credit');
        $this->movement('refund-history', 'wa', 'ia', 4000, 'refund');
        $global = app(InvoiceService::class)->getStatement('manager', 'manager', false)->getCollection()->keyBy('id');
        $this->assertSame('Wallet credited RM12.34', $global['wallet_credit:unknown']['amount']);
        $this->assertNull($global['wallet_credit:unknown']['invoice_no']);
        $this->assertFalse($global->has('wallet_credit:refund-history'));
        $leaseIds = app(InvoiceService::class)->getStatement('manager', 'manager', false, [], 'la')->getCollection()->pluck('id');
        $this->assertNotContains('wallet_credit:unknown', $leaseIds);
        $this->assertNotContains('wallet_credit:refund-history', $leaseIds);
        $this->assertSame(99999999, DB::table('wallets')->where('id', 'wa')->value('balance'));
    }

    public function test_semantic_amounts_zero_partial_and_wallet_payments(): void
    {
        $this->insert('transactions', ['id' => 'partial', 'invoice_id' => 'ia', 'amount_applied' => 12345]);
        $this->insert('transactions', ['id' => 'wallet-paid', 'invoice_id' => 'ia', 'amount_applied' => 30000, 'payment_method' => 'wallet']);
        $this->insert('transactions', ['id' => 'negative', 'invoice_id' => 'ia', 'amount_applied' => -123]);
        $this->insert('transactions', ['id' => 'zero', 'invoice_id' => 'ia', 'amount_applied' => 0]);
        $this->movement('zero', 'wa', 'ia', 0, 'adjustment');
        $page = app(InvoiceService::class)->getStatement('manager', 'manager', false, [], 'la');
        $rows = $page->getCollection()->keyBy('id');
        foreach ([
            'invoice:ia' => ['Charged RM1,000.00', 'text-slate-800'],
            'payment:receipt' => ['Applied RM1,000.00', 'text-slate-800'],
            'wallet_credit:credit' => ['Wallet credited RM200.00', 'text-slate-800'],
            'payment:partial' => ['Applied RM123.45', 'text-slate-800'],
            'payment:wallet-paid' => ['Applied RM300.00', 'text-slate-800'],
            'payment:negative' => ['Applied RM-1.23', 'text-slate-800'],
            'payment:zero' => ['Applied RM0.00', 'text-slate-800'],
            'wallet_other:zero' => ['Wallet movement RM0.00', 'text-slate-800'],
        ] as $id => [$amount, $class]) {
            $this->assertSame($amount, $rows[$id]['amount']);
            $this->assertSame($class, $rows[$id]['amount_class']);
        }
        $this->assertSame(8, $page->total());
        $html = Blade::render('<x-invoice-statement :statement="$statement" />', ['statement' => $page]);
        $this->assertSame(1, substr_count($html, '>Amount</th>'));
        $this->assertStringNotContainsString('Wallet movement (RM)', $html);
        $this->assertSame(8, substr_count($html, 'data-statement-event='));
        DB::table('invoices')->where('id', 'ia')->update(['total_amount' => 0]);
        $zero = app(InvoiceService::class)->getStatement('manager', 'manager', false, ['statement_kind' => 'invoice'], 'la')[0];
        $this->assertSame('Charged RM0.00', $zero['amount']);
        $this->assertSame('text-slate-800', $zero['amount_class']);
    }

    public function test_nonempty_descriptions_are_preserved_read_only(): void
    {
        DB::table('invoice_items')->where('id', 'item1')->update(['description' => ' Rental ']);
        DB::table('invoice_items')->where('id', 'item2')->update(['description' => " \t "]);
        DB::table('wallet_transactions')->where('id', 'credit')->update(['remarks' => 'Rental']);
        $rows = app(InvoiceService::class)->getStatement('manager', 'manager', false, [], 'la')->getCollection()->keyBy('id');
        $this->assertSame(['Rental', 'Service fee'], array_column($rows['invoice:ia']['fees'], 'name'));
        $this->assertSame([' Rental ', null], array_column($rows['invoice:ia']['fees'], 'description'));
        $this->assertSame('Rental', $rows['wallet_credit:credit']['description']);
        $this->assertSame(' Rental ', DB::table('invoice_items')->where('id', 'item1')->value('description'));
        DB::table('invoice_items')->where('id', 'item1')->update(['description' => 'rental']);
        $row = app(InvoiceService::class)->getStatement('manager', 'manager', false, ['statement_kind' => 'invoice'], 'la')[0];
        $this->assertSame('rental', $row['fees'][0]['description']);
    }

    public function test_explicit_wallet_children_stay_with_their_receipt_before_pagination(): void
    {
        // Same invoice/date/reference/amount does not identify an operation.
        for ($i = 0; $i < 25; $i++) {
            $this->insert('transactions', ['id' => sprintf('tx-%02d', $i), 'invoice_id' => 'ia', 'amount_applied' => 1000, 'transaction_ref' => 'shared']);
            $this->movement('linked-'.$i, 'wa', sprintf('tx-%02d', $i), 5000, 'overpayment_credit');
        }
        $service = app(InvoiceService::class);
        $first = $service->getStatement('manager', 'manager', false, [], 'la');
        $second = $service->getStatement('manager', 'manager', false, ['statement_page' => 2], 'la');
        $this->assertSame(28, $first->total());
        $this->assertSame(8, $second->count());
        $rows = $first->getCollection()->concat($second->getCollection());
        $this->assertSame(28, $rows->pluck('id')->unique()->count());
        $this->assertSame(25, $rows->filter(fn ($row) => str_starts_with($row['id'], 'payment:tx-') && count($row['linked_wallet']) === 1)->count());
        $this->assertSame($first->getCollection()->pluck('id')->all(), $service->getStatement('manager', 'manager', false, [], 'la')->getCollection()->pluck('id')->all());
        $this->assertTrue($rows->contains('id', 'wallet_credit:credit')); // Invoice-only link remains separate.
    }

    public function test_partial_wallet_mixed_and_multi_invoice_allocations_are_not_reported_as_cash(): void
    {
        foreach ([['ordinary', 'ia', 100000, 'cash'], ['partial', 'ia', 10000, 'bank_transfer'], ['wallet', 'ia', 50000, 'wallet'], ['mixed', 'ia', 60000, 'bank_transfer'], ['penalty', 'ia2', 2000, 'bank_transfer']] as [$id, $invoice, $amount, $method]) {
            $this->insert('transactions', ['id' => $id, 'invoice_id' => $invoice, 'amount_applied' => $amount, 'amount_paid' => $amount, 'payment_method' => $method, 'transaction_ref' => 'same-reference', 'remarks' => 'Stored remark']);
        }
        $this->movement('pure-wallet', 'wa', 'wallet', -50000, 'invoice_payment');
        $this->movement('mixed-wallet', 'wa', 'mixed', -10000, 'invoice_payment');
        $this->movement('excess-explicit', 'wa', 'partial', 500000, 'overpayment_credit');
        $this->movement('unresolved-mixed', 'wa', 'ia', -30000, 'invoice_payment');
        $rows = app(InvoiceService::class)->getStatement('manager', 'manager', false)->getCollection()->keyBy('id');
        $this->assertSame('Wallet payment', $rows['payment:wallet']['label']);
        $this->assertSame('Applied RM500.00', $rows['payment:wallet']['amount']);
        $this->assertSame(-10000, $rows['payment:mixed']['linked_wallet'][0]->amount);
        $this->assertSame(500000, $rows['payment:partial']['linked_wallet'][0]->amount);
        $this->assertSame('Stored remark', $rows['payment:partial']['description']);
        $this->assertTrue($rows->has('payment:penalty'));
        $this->assertTrue($rows->has('wallet_payment:unresolved-mixed'));
        $this->assertFalse($rows->contains(fn ($r) => str_contains($r['amount'], 'Received')));
    }

    public function test_explicit_link_must_have_correct_owner_type_and_unambiguous_reference(): void
    {
        $this->movement('wrong-receipt-owner', 'wb', 'receipt', -999, 'invoice_payment');
        $this->movement('unknown-type', 'wa', 'receipt', -200, 'adjustment');
        // IDs can overlap across historical tables; never assume a transaction in that case.
        $this->insert('transactions', ['id' => 'ia', 'invoice_id' => 'ia', 'amount_applied' => 200]);
        $rows = app(InvoiceService::class)->getStatement('manager', 'manager', false, [], 'la')->getCollection()->keyBy('id');
        $this->assertFalse($rows->has('wallet_payment:wrong-receipt-owner'));
        $this->assertCount(1, $rows['payment:receipt']['linked_wallet']);
        $this->assertTrue($rows->has('wallet_payment:unknown-type'));
        $this->assertTrue($rows->has('wallet_credit:credit'));
        $this->assertCount(0, $rows['payment:ia']['linked_wallet']);
    }

    public function test_summary_uses_full_authorized_persisted_balances_without_wallet_duplication(): void
    {
        DB::table('invoices')->where('id', 'ia')->update(['status' => 'partial', 'amount_balance' => 12345]);
        DB::table('invoices')->where('id', 'ia2')->update(['status' => 'unpaid', 'amount_balance' => 2000]);
        DB::table('invoices')->where('id', 'ib')->update(['status' => 'unpaid', 'amount_balance' => 999999]);
        $this->insert('invoices', ['id' => 'void', 'lease_id' => 'la', 'status' => 'void', 'amount_balance' => 90000]);
        $this->insert('invoices', ['id' => 'penalty', 'lease_id' => 'la', 'status' => 'overdue', 'amount_balance' => 500]);
        $this->insert('invoices', ['id' => 'deleted', 'lease_id' => 'la', 'status' => 'unpaid', 'amount_balance' => 1000, 'deleted_at' => '2026-10-20']);
        $summary = Invoice::statementSummary('manager', 'manager', false, 'la');
        $this->assertEquals(12845, $summary['outstanding']);
        $this->assertEquals(99999999, $summary['wallet']);
        $global = Invoice::statementSummary('manager', 'manager', false, null);
        $this->assertEquals(14845, $global['outstanding']);
        $this->assertNull($global['wallet']);
        for ($i = 0; $i < 30; $i++) $this->movement('summary-'.$i, 'wa', 'ia', 1, 'manual_credit');
        app(InvoiceService::class)->getStatement('manager', 'manager', false, ['statement_page' => 2], 'la');
        $this->assertSame($summary, Invoice::statementSummary('manager', 'manager', false, 'la'));
        $this->assertNull(Invoice::statementSummary('manager', 'manager', false, 'lb')['wallet']);
        DB::table('wallets')->where('id', 'wa')->delete();
        $this->assertNull(Invoice::statementSummary('manager', 'manager', false, 'la')['wallet']);
    }

    public function test_recorded_time_orders_same_day_backdated_and_grouped_events_before_pagination(): void
    {
        DB::table('invoices')->where('id', 'ia')->update(['created_at' => '2026-10-20 14:00:00', 'updated_at' => '2027-01-01 00:00:00']);
        for ($i = 0; $i < 25; $i++) {
            $this->insert('transactions', ['id' => sprintf('sort-%02d', $i), 'invoice_id' => 'ia', 'amount_applied' => 100,
                'payment_date' => '2026-10-20', 'created_at' => '2026-10-20 15:00:00']);
        }
        $this->insert('transactions', ['id' => 'backfill', 'invoice_id' => 'ia', 'amount_applied' => 100,
            'payment_date' => '2026-09-01', 'created_at' => '2026-10-20 16:00:00']);
        $this->insert('transactions', ['id' => 'unknown', 'invoice_id' => 'ia', 'amount_applied' => 100,
            'payment_date' => '2026-12-01', 'created_at' => null]);
        $this->movement('later-child', 'wa', 'sort-00', -100, 'invoice_payment');
        DB::table('wallet_transactions')->where('id', 'later-child')->update(['created_at' => '2026-10-21 18:00:00']);
        $service = app(InvoiceService::class);
        $first = $service->getStatement('manager', 'manager', false, [], 'la');
        $second = $service->getStatement('manager', 'manager', false, ['statement_page' => 2], 'la');
        $rows = $first->getCollection()->concat($second->getCollection());
        $this->assertSame(30, $first->total());
        $this->assertSame(30, $rows->pluck('id')->unique()->count());
        $this->assertSame('payment:backfill', $first[0]['id']);
        $this->assertSame('20/10/2026', $first[0]['date']);
        $this->assertSame('01/09/2026', $first[0]['payment_date']);
        $this->assertSame('20/10/2026 16:00:00', $first[0]['recorded_at']);
        $this->assertSame('payment:sort-24', $first[1]['id']);
        $this->assertSame('payment:sort-06', $first[19]['id']);
        $this->assertSame('payment:sort-05', $second[0]['id']);
        $this->assertSame('payment:unknown', $rows->last()['id']);
        $this->assertSame('Not recorded', $rows->last()['date']);
        $this->assertNull($rows->last()['recorded_at']);
        $this->assertCount(1, $rows->firstWhere('id', 'payment:sort-00')['linked_wallet']);
        $this->assertSame($first->getCollection()->pluck('id')->all(), $service->getStatement('manager', 'manager', false, [], 'la')->getCollection()->pluck('id')->all());
        $html = view('components.invoice-statement', ['statement' => $first])->render();
        $this->assertStringContainsString('Payment date: 01/09/2026', $html);
        $this->assertStringContainsString('Recorded: 20/10/2026 16:00:00', $html);
        $this->assertStringNotContainsString('Date / Time', $html);
        $this->assertSame('2026-09-01', DB::table('transactions')->where('id', 'backfill')->value('payment_date'));
    }

    public function test_optional_descriptions_survive_charge_initial_and_recurring_invoice_writes(): void
    {
        Schema::table('fee_types', function (Blueprint $t) {
            $t->string('user_id')->nullable(); $t->string('category')->default('rent');
            $t->boolean('is_system')->default(true); $t->boolean('is_active')->default(true);
        });
        Schema::table('document_templates', function (Blueprint $t) {
            $t->string('category')->default('invoice'); $t->string('status')->default('active');
        });
        Schema::table('invoices', function (Blueprint $t) {
            foreach (['billable_type', 'billable_id', 'type', 'period', 'due_date', 'parent_id'] as $column) $t->string($column)->nullable();
            $t->bigInteger('amount_paid')->default(0);
        });
        Schema::table('invoice_items', fn (Blueprint $t) => $t->bigInteger('amount')->default(0));
        Schema::create('lease_charges', function (Blueprint $t) {
            $t->string('id')->primary(); $t->timestamps(); $t->softDeletes();
            foreach (['lease_id', 'fee_type_id', 'description', 'charge_type', 'frequency'] as $column) $t->string($column);
            $t->bigInteger('amount'); $t->date('next_billing_date')->nullable();
            $t->boolean('is_active'); $t->integer('sort_order');
        });
        $user = \App\Models\User::findOrFail('manager');
        $this->actingAs($user);
        $sequence = \Mockery::mock(\App\Services\DocumentSequenceService::class);
        $sequence->shouldReceive('generateInvoiceNumber')->andReturnUsing(fn () => 'TEST-'.\Illuminate\Support\Str::ulid());
        $settings = \Mockery::mock(\App\Services\SettingService::class);
        $settings->shouldReceive('getEffectiveSettings')->andReturn([]);
        $this->app->instance(\App\Services\DocumentSequenceService::class, $sequence);
        $this->app->instance(\App\Services\SettingService::class, $settings);
        $service = app(InvoiceService::class);
        $lease = \App\Models\Lease::findOrFail('la');
        $lease->start_date = '2026-10-01'; $lease->end_date = '2027-12-31'; $lease->is_current = true;
        $descriptions = [[], ['description' => null], ['description' => ''], ['description' => " \t "], ['description' => ' Meter 123 ']];
        $charges = array_map(fn ($description) => $description + ['fee_type_id' => 'rent', 'amount' => 12.34, 'frequency' => 'monthly'], $descriptions);
        // Disable observers only inside the isolated persistence test; no real database is used.
        \Illuminate\Database\Eloquent\Model::withoutEvents(function () use ($service, $lease, $user, $charges) {
            $method = new \ReflectionMethod(\App\Services\LeaseService::class, 'createLeaseCharges');
            $method->invoke(app(\App\Services\LeaseService::class), $lease, ['charges' => $charges]);
            $this->assertSame(['', '', '', '', ' Meter 123 '], $lease->charges()->orderBy('sort_order')->pluck('description')->all());
            $initial = $service->createInitialInvoiceForLease($lease, $user);
            $this->assertSame(5, $initial->count());
            $this->assertSame(['', '', '', '', ' Meter 123 '], $initial->flatMap->items->pluck('description')->all());
            $this->assertSame(1, $service->generateRecurringInvoices($lease));
            $recurring = Invoice::where('lease_id', 'la')->whereDate('period', '2026-11-01')->firstOrFail();
            $this->assertSame(['', '', '', '', ' Meter 123 '], $recurring->items->pluck('description')->all());
            $this->assertSame(6170, $recurring->total_amount);
            $manual = $service->createManualInvoice($lease, ['items' => $charges, 'period' => '2026-12-01', 'due_date' => '2026-12-08']);
            $this->assertSame(['', '', '', '', ' Meter 123 '], $manual->items->pluck('description')->all());
        });
    }
}
