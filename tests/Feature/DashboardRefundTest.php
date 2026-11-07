<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Services\{InvoiceService, LeaseService, SetupCheckerService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Tests\TestCase;

class DashboardRefundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'users' => ['role', 'created_by', 'owner_id'],
            'tenants' => ['user_id', 'created_by'],
            'properties' => ['owner_id'],
            'units' => ['property_id', 'owner_id', 'status'],
            'rooms' => ['unit_id', 'status'],
            'leases' => ['tenant_id', 'leasable_id', 'leasable_type', 'status', 'end_date'],
            'refunds' => ['lease_id', 'status'],
            'invoices' => ['lease_id', 'status', 'due_date', 'deleted_at'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $schema) use ($table, $columns) {
                $schema->string('id')->primary();
                $schema->timestamps();
                foreach ($columns as $column) $schema->text($column)->nullable();
                if ($table === 'leases') {
                    $schema->boolean('is_current')->default(true);
                    $schema->boolean('is_pending_renewal')->default(false);
                }
            });
        }
        DB::table('users')->insert(['id' => 'manager', 'role' => 'ownerAdmin']);
        $this->actingAs(User::find('manager'));
        Gate::shouldReceive('denies')->with('owner-admin')->andReturn(false);
        Gate::shouldReceive('allows')->with('super-admin')->andReturn(false);
        DB::table('tenants')->insert([
            ['id' => 'mine', 'created_by' => 'manager'],
            ['id' => 'other', 'created_by' => 'someone-else'],
        ]);
    }

    private function lease(string $id, ?string $refundStatus, array $changes = []): void
    {
        DB::table('leases')->insert(array_merge([
            'id' => $id, 'tenant_id' => 'mine', 'status' => 'Check Out',
            'end_date' => '2026-01-01', 'is_current' => true,
        ], $changes));
        if ($refundStatus !== null) {
            DB::table('refunds')->insert([
                'id' => $id, 'lease_id' => $id, 'status' => $refundStatus,
                'created_at' => '2026-01-01 00:00:00',
            ]);
        }
    }

    public function test_dashboard_lists_only_latest_unfinished_refunds_with_ownership_and_separate_pagination(): void
    {
        foreach (['draft', 'pending', 'approved', 'rejected', 'paid', 'cancelled'] as $status) {
            $this->lease($status, $status);
        }
        $this->lease('historical', 'draft', ['is_current' => false]);
        $this->lease('not-started', null);
        $this->lease('foreign', 'draft', ['tenant_id' => 'other']);
        $this->lease('completed', 'draft');
        DB::table('refunds')->insert([
            'id' => 'newer-paid', 'lease_id' => 'completed', 'status' => 'paid',
            'created_at' => '2026-02-01 00:00:00',
        ]);

        $checker = $this->mock(SetupCheckerService::class);
        $checker->shouldReceive('check')->once()->andReturn([]);
        $leaseService = $this->mock(LeaseService::class);
        $leaseService->shouldReceive('getLeaseFormPayload')->once()
            ->withArgs(fn ($user, $request, $ids) => $ids === ['approved', 'draft', 'historical', 'pending', 'rejected'])
            ->andReturn([]);
        $request = Request::create('/dashboard', 'GET', ['lease_month' => '12', 'lease_page' => 2]);
        $view = (new DashboardController($this->mock(InvoiceService::class)))
            ->index($checker, $request, $leaseService);
        $refunds = $view->getData()['incompleteRefundLeases'];

        $this->assertSame(5, $refunds->total());
        $this->assertSame(1, $refunds->currentPage());
        $this->assertSame(['approved', 'draft', 'historical', 'pending', 'rejected'], $refunds->pluck('id')->all());
        $this->assertTrue($refunds->first()->relationLoaded('latestRefund'));
        $this->assertStringContainsString('refund_page=2', $refunds->url(2));
        $this->assertStringEndsWith('#refund-section', $refunds->url(2));
    }
}
