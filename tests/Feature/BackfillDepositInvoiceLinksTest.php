<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class BackfillDepositInvoiceLinksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('leases', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('parent_lease_id')->nullable();
        });
        Schema::create('fee_types', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('category');
        });
        Schema::create('lease_charges', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('lease_id');
            $t->string('fee_type_id'); $t->integer('amount'); $t->softDeletes();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('lease_id');
            $t->string('invoice_no'); $t->string('status'); $t->softDeletes();
        });
        Schema::create('invoice_items', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('invoice_id');
            $t->string('fee_type_id'); $t->integer('amount');
            $t->string('lease_charge_id')->nullable();
        });
        Schema::create('transaction_allocations', function (Blueprint $t) {
            $t->id(); $t->string('invoice_item_id'); $t->integer('amount_cents');
        });
        DB::table('leases')->insert([['id' => 'lease'], ['id' => 'other']]);
        DB::table('fee_types')->insert(['id' => 'deposit', 'category' => 'deposit']);
        DB::table('lease_charges')->insert([
            'id' => 'charge', 'lease_id' => 'lease', 'fee_type_id' => 'deposit', 'amount' => 100000,
        ]);
        DB::table('invoices')->insert([
            'id' => 'invoice', 'lease_id' => 'lease', 'invoice_no' => 'INV-OLD', 'status' => 'paid',
        ]);
        DB::table('invoice_items')->insert([
            'id' => 'item', 'invoice_id' => 'invoice', 'fee_type_id' => 'deposit', 'amount' => 100000,
        ]);
    }

    public function test_preview_apply_and_rerun_are_safe(): void
    {
        $this->artisan('invoices:backfill-deposit-links')->expectsOutput('Would link: 1; skipped: 0.')->assertSuccessful();
        $this->assertNull(DB::table('invoice_items')->value('lease_charge_id'));
        $this->artisan('invoices:backfill-deposit-links', ['--apply' => true, '--lease' => 'other'])
            ->expectsOutput('Linked: 0; skipped: 0.')->assertSuccessful();
        $this->artisan('invoices:backfill-deposit-links', ['--apply' => true])
            ->expectsOutput('Linked: 1; skipped: 0.')->assertSuccessful();
        $this->assertSame('charge', DB::table('invoice_items')->value('lease_charge_id'));
        $this->artisan('invoices:backfill-deposit-links', ['--apply' => true])
            ->expectsOutput('Linked: 0; skipped: 0.')->assertSuccessful();
        $this->assertSame('paid', DB::table('invoices')->value('status'));
    }

    public function test_backfilled_legacy_paid_deposit_is_counted_by_checkout_including_renewal(): void
    {
        $service = app(\App\Services\RefundService::class);
        $lease = \App\Models\Lease::findOrFail('lease');
        $this->assertSame(0, $service->getDepositCollectedCents($lease));

        $this->artisan('invoices:backfill-deposit-links', ['--apply' => true])->assertSuccessful();
        $this->assertSame(100000, $service->getDepositCollectedCents($lease));

        DB::table('leases')->where('id', 'other')->update(['parent_lease_id' => 'lease']);
        $this->assertSame(100000, $service->getDepositCollectedCents(\App\Models\Lease::findOrFail('other')));

        DB::table('invoices')->update(['status' => 'unpaid']);
        $this->assertSame(0, $service->getDepositCollectedCents($lease));
        DB::table('transaction_allocations')->insert(['invoice_item_id' => 'item', 'amount_cents' => 25000]);
        DB::table('invoices')->update(['status' => 'partial']);
        $this->assertSame(25000, $service->getDepositCollectedCents($lease));
        DB::table('invoices')->update(['status' => 'void']);
        $this->assertSame(0, $service->getDepositCollectedCents($lease));
    }

    public function test_current_calculation_omits_legacy_deposit_when_another_deposit_has_allocations(): void
    {
        DB::table('lease_charges')->insert([
            'id' => 'second-charge', 'lease_id' => 'lease', 'fee_type_id' => 'deposit', 'amount' => 20000,
        ]);
        DB::table('invoice_items')->insert([
            'id' => 'second-item', 'invoice_id' => 'invoice', 'fee_type_id' => 'deposit', 'amount' => 20000,
        ]);
        DB::table('transaction_allocations')->insert(['invoice_item_id' => 'second-item', 'amount_cents' => 20000]);
        $this->artisan('invoices:backfill-deposit-links', ['--apply' => true])
            ->expectsOutput('Linked: 2; skipped: 0.')->assertSuccessful();

        // Documents the existing calculation limitation, not the desired financial result (120000).
        $this->assertSame(20000, app(\App\Services\RefundService::class)
            ->getDepositCollectedCents(\App\Models\Lease::findOrFail('lease')));
    }

    public function test_ambiguous_charges_are_skipped(): void
    {
        DB::table('lease_charges')->insert([
            'id' => 'duplicate', 'lease_id' => 'lease', 'fee_type_id' => 'deposit', 'amount' => 100000,
        ]);
        $this->artisan('invoices:backfill-deposit-links', ['--apply' => true])
            ->expectsOutput('Linked: 0; skipped: 1.')->assertSuccessful();
        $this->assertNull(DB::table('invoice_items')->value('lease_charge_id'));
    }

    public function test_competing_items_and_already_used_charges_are_skipped(): void
    {
        DB::table('invoice_items')->insert([
            'id' => 'competitor', 'invoice_id' => 'invoice', 'fee_type_id' => 'deposit', 'amount' => 100000,
        ]);
        $this->artisan('invoices:backfill-deposit-links', ['--apply' => true])
            ->expectsOutput('Linked: 0; skipped: 2.')->assertSuccessful();
        DB::table('invoice_items')->where('id', 'competitor')->update(['lease_charge_id' => 'charge']);
        $this->artisan('invoices:backfill-deposit-links', ['--apply' => true])
            ->expectsOutput('Linked: 0; skipped: 1.')->assertSuccessful();
        $this->assertNull(DB::table('invoice_items')->where('id', 'item')->value('lease_charge_id'));
    }

    public function test_invalid_matches_are_not_linked(): void
    {
        foreach (['wrong-lease', 'wrong-amount', 'deleted-charge', 'void-invoice', 'deleted-invoice', 'not-deposit'] as $case) {
            DB::table('lease_charges')->where('id', 'charge')->update([
                'lease_id' => $case === 'wrong-lease' ? 'other' : 'lease',
                'amount' => $case === 'wrong-amount' ? 200000 : 100000,
                'deleted_at' => $case === 'deleted-charge' ? now() : null,
            ]);
            DB::table('invoices')->update([
                'status' => $case === 'void-invoice' ? 'void' : 'paid',
                'deleted_at' => $case === 'deleted-invoice' ? now() : null,
            ]);
            DB::table('fee_types')->update(['category' => $case === 'not-deposit' ? 'rent' : 'deposit']);
            $this->artisan('invoices:backfill-deposit-links', ['--apply' => true])->assertSuccessful();
            $this->assertNull(DB::table('invoice_items')->value('lease_charge_id'), $case);
        }
    }
}
