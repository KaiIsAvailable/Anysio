<?php

namespace Tests\Feature;

use App\Http\Controllers\{FeeTypeController, RefundController};
use App\Models\Refund;
use App\Services\{FeeTypeService, RefundService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RefundPermissionTest extends TestCase
{
    public static function protectedActions(): array
    {
        return [
            'draft' => [RefundController::class, 'storeDraft', 'leases.save draft'],
            'pending' => [RefundController::class, 'pending', 'leases.set as pending'],
            'reject' => [RefundController::class, 'reject', 'leases.reject refund'],
            'approve' => [RefundController::class, 'approve', 'leases.approve refund'],
            'payout' => [RefundController::class, 'disburse', 'leases.proceed refund'],
            'fee type' => [FeeTypeController::class, 'store', 'leases.add fee type'],
        ];
    }

    #[DataProvider('protectedActions')]
    public function test_direct_requests_without_permission_redirect_with_error_before_processing(string $controller, string $method, string $permission): void
    {
        Gate::shouldReceive('denies')->once()->with('super-admin')->andReturn(true);
        Gate::shouldReceive('denies')->once()->with($permission)->andReturn(true);
        $response = $this->controller($controller)->$method(Request::create('/action', 'POST'));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('You have no permission.', session('error'));
    }

    #[DataProvider('protectedActions')]
    public function test_action_permission_allows_request_to_reach_normal_validation(string $controller, string $method, string $permission): void
    {
        Gate::shouldReceive('denies')->once()->with('super-admin')->andReturn(true);
        Gate::shouldReceive('denies')->once()->with($permission)->andReturn(false);
        $this->expectException(ValidationException::class);
        $this->controller($controller)->$method(Request::create('/action', 'POST'));
    }

    #[DataProvider('protectedActions')]
    public function test_system_admin_can_bypass_individual_action_permission(string $controller, string $method, string $permission): void
    {
        Gate::shouldReceive('denies')->once()->with('super-admin')->andReturn(false);
        $this->expectException(ValidationException::class);
        $this->controller($controller)->$method(Request::create('/action', 'POST'));
    }

    #[DataProvider('deductionChanges')]
    public function test_saving_or_submitting_changed_deductions_requires_deduction_permission(string $method, int $rowCount): void
    {
        $this->deductionFixture();
        Gate::shouldReceive('denies')->with('super-admin')->andReturn(true);
        Gate::shouldReceive('denies')->with($method === 'storeDraft' ? 'leases.save draft' : 'leases.set as pending')->andReturn(false);
        Gate::shouldReceive('allows')->with('super-admin')->andReturn(false);
        Gate::shouldReceive('allows')->with('leases.add deduction item')->andReturn(false);
        $items = array_fill(0, $rowCount, ['fee_type_id' => 'fee', 'qty' => 1, 'sell_price' => 20]);
        $response = $this->controller(RefundController::class)->$method(Request::create('/action', 'POST', ['lease_id' => 'lease', 'items' => $items]));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('You have no permission.', session('error'));
        $this->assertSame(1, DB::table('refund_items')->count());
    }

    public static function deductionChanges(): array
    {
        return [
            'draft add' => ['storeDraft', 2], 'draft remove' => ['storeDraft', 0],
            'pending add' => ['pending', 2], 'pending remove' => ['pending', 0],
        ];
    }

    public function test_save_permission_can_save_existing_unchanged_deductions(): void
    {
        $this->deductionFixture();
        Gate::shouldReceive('denies')->with('super-admin')->andReturn(true);
        Gate::shouldReceive('denies')->with('leases.save draft')->andReturn(false);
        Gate::shouldReceive('allows')->with('super-admin')->andReturn(false);
        Gate::shouldReceive('allows')->with('leases.add deduction item')->andReturn(false);
        $service = $this->mock(RefundService::class);
        $service->shouldReceive('saveAsDraft')->once()->andReturn(new Refund());
        $response = (new RefundController($service))->storeDraft(Request::create('/action', 'POST', [
            'lease_id' => 'lease', 'items' => [['fee_type_id' => 'fee', 'qty' => 1, 'sell_price' => 20]],
        ]));
        $this->assertSame(302, $response->getStatusCode());
    }

    private function controller(string $controller): object
    {
        return $controller === RefundController::class
            ? new RefundController($this->mock(RefundService::class))
            : new FeeTypeController($this->mock(FeeTypeService::class));
    }

    private function deductionFixture(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('leases', function (Blueprint $table) { $table->string('id')->primary(); });
        Schema::create('fee_types', function (Blueprint $table) { $table->string('id')->primary(); });
        Schema::create('refunds', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('lease_id'); $table->string('status');
        });
        Schema::create('refund_items', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('refund_id'); $table->string('fee_type_id');
            $table->string('origin'); $table->string('type'); $table->integer('qty'); $table->integer('amount_cents');
        });
        DB::table('leases')->insert(['id' => 'lease']);
        DB::table('fee_types')->insert(['id' => 'fee']);
        DB::table('refunds')->insert(['id' => 'refund', 'lease_id' => 'lease', 'status' => 'draft']);
        DB::table('refund_items')->insert([
            'id' => 'item', 'refund_id' => 'refund', 'fee_type_id' => 'fee',
            'origin' => 'manual', 'type' => 'adjustment', 'qty' => 1, 'amount_cents' => 2000,
        ]);
    }
}
