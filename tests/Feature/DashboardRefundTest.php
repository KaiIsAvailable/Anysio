<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use App\Models\{Maintenance, User};
use App\Services\{InvoiceService, LeaseService, MaintenanceService, SetupCheckerService};
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
        Gate::shouldReceive('denies')->with('super-admin')->andReturn(true);
        Gate::shouldReceive('denies')->with('dashboard.tab')->andReturn(false);
        Gate::shouldReceive('allows')->with('super-admin')->andReturn(false);
        Gate::shouldReceive('allows')->with('dashboard.ongoing maintenance list')->andReturn(true)->byDefault();
        Gate::shouldReceive('allows')->with('maintenance.tab')->andReturn(false)->byDefault();
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

    private function dashboardData(array $params = []): array
    {
        $request = Request::create('/dashboard', 'GET', $params);
        $this->app->instance('request', $request);
        $checker = $this->mock(SetupCheckerService::class);
        $checker->shouldReceive('check')->andReturn([]);
        $leases = $this->mock(LeaseService::class);
        $leases->shouldReceive('getLeaseFormPayload')->andReturn([]);
        return (new DashboardController($this->mock(InvoiceService::class)))->index($checker, $request, $leases)->getData();
    }

    public function test_pending_refund_month_filter_uses_lease_end_date_and_preserves_other_sections(): void
    {
        $this->lease('january', 'draft', ['end_date' => '2026-01-15']);
        $this->lease('february', 'pending', ['end_date' => '2026-02-15']);
        $this->lease('last-year', 'approved', ['end_date' => '2025-01-15']);
        $this->lease('foreign', 'draft', ['tenant_id' => 'other', 'end_date' => '2026-01-15']);
        $data = $this->dashboardData(['refund_month' => '01', 'refund_year' => '2026', 'maintenance_month' => '02',
            'maintenance_year' => '2026', 'lease_month' => '12', 'lease_page' => 2]);
        $refunds = $data['incompleteRefundLeases'];
        $this->assertSame(['january'], $refunds->pluck('id')->all());
        $this->assertStringContainsString('maintenance_month=02', $refunds->url(2));
        $this->assertStringContainsString('lease_page=2', $refunds->url(2));
        $this->assertSame(['last-year'], $this->dashboardData(['refund_month' => '01', 'refund_year' => 2025])['incompleteRefundLeases']->pluck('id')->all());
        $this->assertSame(3, $this->dashboardData()['incompleteRefundLeases']->total());
        $this->assertSame(3, $this->dashboardData(['refund_year' => 2026])['incompleteRefundLeases']->total());
    }

    public function test_ongoing_maintenance_uses_existing_scope_created_month_and_independent_pagination(): void
    {
        Schema::create('maintenance', function (Blueprint $table) {
            $table->string('id')->primary();
            foreach (['management_user_id', 'status', 'lease_id', 'leasable_type', 'leasable_id'] as $column) $table->string($column)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        for ($i = 1; $i <= 6; $i++) DB::table('maintenance')->insert(['id' => 'job-'.$i,
            'management_user_id' => 'manager', 'status' => $i % 2 ? 'pending' : 'maintaining', 'created_at' => '2026-01-0'.$i.' 09:00:00']);
        foreach ([['completed', 'manager', 'completed', '2026-01-07'], ['other-month', 'manager', 'pending', '2026-02-01'],
            ['other-year', 'manager', 'pending', '2025-01-01'], ['foreign', 'someone-else', 'pending', '2026-01-01']] as [$id, $manager, $status, $date]) {
            DB::table('maintenance')->insert(['id' => $id, 'management_user_id' => $manager, 'status' => $status, 'created_at' => $date.' 09:00:00']);
        }
        Gate::shouldReceive('allows')->with('maintenance.tab')->andReturn(true);
        Gate::shouldReceive('allows')->with('maintenance.view')->andReturn(true);
        $service = $this->mock(MaintenanceService::class);
        $service->shouldReceive('ready')->once()->andReturn(true);
        $service->shouldReceive('visibleOrders')->once()->andReturn(Maintenance::where('management_user_id', 'manager'));
        $data = $this->dashboardData(['maintenance_month' => '01', 'maintenance_year' => 2026, 'maintenance_page' => 2,
            'refund_month' => '02', 'refund_year' => 2026, 'refund_page' => 3]);
        $jobs = $data['ongoingMaintenance'];
        $this->assertSame(6, $jobs->total());
        $this->assertSame(2, $jobs->currentPage());
        $this->assertSame(['job-6'], $jobs->pluck('id')->all());
        $this->assertStringContainsString('maintenance_page=1', $jobs->url(1));
        $this->assertStringContainsString('refund_page=3', $jobs->url(1));
        $this->assertStringEndsWith('#maintenance-section', $jobs->url(1));
        $this->assertSame('refund_page', $data['incompleteRefundLeases']->getPageName());
    }

    public function test_maintenance_widget_requires_both_module_and_view_permission(): void
    {
        Gate::shouldReceive('allows')->with('maintenance.tab')->andReturn(true);
        Gate::shouldReceive('allows')->with('maintenance.view')->andReturn(false);
        $service = $this->mock(MaintenanceService::class);
        $service->shouldNotReceive('ready');
        $service->shouldNotReceive('visibleOrders');
        $this->assertNull($this->dashboardData()['ongoingMaintenance']);
    }

    public function test_maintenance_widget_does_not_query_records_without_its_dashboard_permission(): void
    {
        Gate::shouldReceive('allows')->with('dashboard.ongoing maintenance list')->andReturn(false);
        $service = $this->mock(MaintenanceService::class);
        $service->shouldNotReceive('ready');
        $service->shouldNotReceive('visibleOrders');
        $this->assertNull($this->dashboardData()['ongoingMaintenance']);
    }

    public function test_dashboard_rejects_invalid_month_year_and_pagination_filters(): void
    {
        foreach (['maintenance_month' => '13', 'refund_month' => 'no', 'maintenance_year' => '2026x', 'refund_year' => 0, 'maintenance_page' => -1] as $field => $value) {
            try {
                $this->dashboardData([$field => $value]);
                $this->fail('Expected validation failure for '.$field);
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
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
