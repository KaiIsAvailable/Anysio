<?php

namespace Tests\Feature;

use App\Enums\RefundStatus;
use App\Models\{Lease, Refund, User};
use App\Services\{InvoiceService, RefundCalculationService, RefundService};
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Blade, DB, Gate, Schema};
use Tests\TestCase;

class LeaseRefundStatusTest extends TestCase
{
    public function test_table_shows_refund_stages_under_checkout_and_handles_legacy_refunded_leases(): void
    {
        Gate::before(fn () => false);
        $route = app('router')->getRoutes()->getByName('admin.leases.index');
        $route->bind(request());
        request()->setRouteResolver(fn () => $route);

        foreach ([
            ['Check Out', 'draft', 'REFUND: DRAFT'],
            ['Check Out', 'pending', 'REFUND: PENDING APPROVE'],
            ['Check Out', 'approved', 'REFUND: APPROVED, AWAITING PAYMENT'],
            ['Check Out', 'paid', 'REFUNDED'],
            ['Check Out', null, 'REFUND: NOT STARTED'],
            ['refunded', 'paid', 'REFUNDED'],
        ] as [$status, $refundStatus, $label]) {
            $lease = new Lease(['status' => $status, 'leasable_type' => 'App\\Models\\Property']);
            $lease->setRelation('charges', new Collection());
            $lease->setRelation('latestRefund', $refundStatus ? new Refund(['status' => $refundStatus]) : null);

            $html = Blade::render(
                '<x-table.lease-table :leases="$leases" :showLeaseable="false" :showOwner="false" :showTenant="false" :showAction="false" />',
                ['leases' => new Collection([$lease])]
            );

            $this->assertStringContainsString('CHECK OUT', $html);
            $this->assertStringContainsString($label, $html);
        }

        $lease->status = 'New';
        $html = Blade::render(
            '<x-table.lease-table :leases="$leases" :showLeaseable="false" :showOwner="false" :showTenant="false" :showAction="false" />',
            ['leases' => new Collection([$lease])]
        );
        $this->assertStringContainsString('CURRENT', $html);
        $this->assertStringNotContainsString('REFUNDED', $html);
    }

    public function test_latest_refund_uses_creation_date_and_loads_for_multiple_leases_together(): void
    {
        $this->createRefundTables();
        DB::table('refunds')->insert([
            ['id' => 'z-old', 'lease_id' => 'a', 'status' => 'draft', 'created_at' => '2026-11-01'],
            ['id' => 'a-new', 'lease_id' => 'a', 'status' => 'paid', 'created_at' => '2026-11-02'],
            ['id' => 'b-new', 'lease_id' => 'b', 'status' => 'pending', 'created_at' => '2026-11-02'],
        ]);
        $a = new Lease();
        $a->id = 'a';
        $b = new Lease();
        $b->id = 'b';
        $leases = new Collection([$a, $b]);

        DB::enableQueryLog();
        $leases->loadMissing('latestRefund');
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame(RefundStatus::PAID, $a->latestRefund->status);
        $this->assertSame(RefundStatus::PENDING, $b->latestRefund->status);
        DB::disableQueryLog();
    }

    public function test_successful_payout_keeps_lease_checked_out_and_marks_refund_paid(): void
    {
        $this->createRefundTables();
        Schema::create('leases', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('status');
            $table->boolean('is_pending_renewal')->default(false);
            $table->date('checked_out_at')->nullable();
            $table->string('refund_document_id')->nullable();
            $table->string('leasable_type')->nullable();
            $table->string('leasable_id')->nullable();
            $table->timestamps();
        });
        Schema::create('refund_disbursements', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('refund_id');
            $table->integer('amount_cents');
            $table->string('bank_detail_id')->nullable();
            $table->string('method');
            $table->string('reference')->nullable();
            $table->string('status');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        DB::table('leases')->insert(['id' => 'lease', 'status' => 'Check Out']);
        DB::table('refunds')->insert([
            'id' => 'refund', 'lease_id' => 'lease', 'status' => 'approved',
            'deposit_collected_cents' => 100000, 'net_amount_cents' => 80000,
        ]);
        $invoices = $this->mock(InvoiceService::class);
        $invoices->shouldReceive('settleOutstandingInvoicesFromDeposit')
            ->once()->withArgs(fn ($lease, $deposit, $date, $reference) => $lease->id === 'lease' && $deposit === 100000)
            ->andReturn(20000);
        $service = \Mockery::mock(RefundService::class, [new RefundCalculationService(), $invoices])->makePartial();
        $service->shouldReceive('refreshSettlement')->once()->andReturn(false);
        $user = new User();
        $user->id = 'payer';

        Model::withoutEvents(fn () => $service->processDisbursement(Refund::findOrFail('refund'), [
            'amount_cents' => 80000,
            'checked_out_at' => '2026-11-06',
            'refund_document_id' => 'template',
            'bank_detail_id' => 'bank',
            'method' => 'bank_transfer',
        ], $user));

        $this->assertSame('Check Out', Lease::findOrFail('lease')->status);
        $this->assertSame(RefundStatus::PAID, Refund::findOrFail('refund')->status);
        $this->assertSame(80000, DB::table('refund_disbursements')->sum('amount_cents'));
    }

    private function createRefundTables(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('refunds', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('lease_id');
            $table->string('status');
            $table->integer('deposit_collected_cents')->default(0);
            $table->integer('wallet_refund_cents')->default(0);
            $table->integer('net_amount_cents')->default(0);
            $table->string('paid_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }
}
