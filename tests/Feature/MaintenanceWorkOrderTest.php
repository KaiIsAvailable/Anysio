<?php

namespace Tests\Feature;

use App\Models\{DocumentSequence, Lease, Maintenance, Property, Refund, Room, Ticket, TicketMsg, Unit, User};
use App\Services\{DocumentSequenceService, RefundService, TenantCustomerService};
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class MaintenanceWorkOrderTest extends TestCase
{
    private User $tenantUser;
    private string $tenantId;
    private string $ownerId;
    private string $managerId;
    private array $assignmentStaff = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        \Tests\Support\MaintenanceSchema::create();
        Storage::fake('local');
        $this->tenantUser = $this->user('tenant');
        $this->ownerId = $this->user('owner')->id;
        $this->managerId = $this->user('agentAdmin')->id;
        $this->tenantId = (string) Str::ulid();
        DB::table('tenants')->insert(['id' => $this->tenantId, 'user_id' => $this->tenantUser->id, 'created_by' => $this->managerId]);
        $this->actingAs($this->tenantUser);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        $id = (string) Str::ulid();
        DB::table('users')->insert(['id' => $id, 'name' => $role, 'email' => $id.'@example.test', 'role' => $role, 'email_verified_at' => now()]);
        return User::findOrFail($id);
    }

    private function lease(array $overrides = []): Lease
    {
        $property = (string) Str::ulid();
        DB::table('properties')->insert(['id' => $property, 'name' => 'Test Property', 'owner_id' => $this->ownerId, 'created_by' => $this->managerId]);
        $id = (string) Str::ulid();
        DB::table('leases')->insert(array_merge([
            'id' => $id, 'tenant_id' => $this->tenantId, 'leasable_type' => Property::class, 'leasable_id' => $property,
            'status' => 'New', 'is_current' => true, 'start_date' => today()->toDateString(), 'end_date' => today()->toDateString(),
        ], $overrides));
        return Lease::findOrFail($id);
    }

    private function ticket(string $status = 'pending', ?string $sender = null): Ticket
    {
        $id = (string) Str::ulid();
        DB::table('tickets')->insert(['id' => $id, 'sender_id' => $sender ?? $this->tenantUser->id, 'status' => $status, 'category' => 'QNA', 'subject' => 'Test']);
        return Ticket::findOrFail($id);
    }

    private function manager(array $permissions = ['tab','view','create','edit','assign','complete']): User
    {
        $grants = array_map(fn ($p) => ($p === 'reply' ? 'service request.' : 'maintenance.').$p, $permissions);
        if (in_array('tab', $permissions, true)) $grants[] = 'service request.tab';
        DB::table('users')->where('id', $this->managerId)->update(['account_permissions' => json_encode(array_merge($grants, ['fee types.item']))]);
        return User::findOrFail($this->managerId);
    }
    private function source(): Ticket
    {
        $ticket = $this->ticket();
        $ticket->update(['lease_id' => $this->lease()->id, 'receive_id' => $this->ownerId]);
        return $ticket->fresh();
    }
    private function payload(array $extra = []): array
    {
        return array_merge(['request_key' => (string) Str::uuid(), 'title' => 'Broken light', 'desc' => 'Verified with tenant', 'location_detail' => 'Bedroom ceiling'], $extra);
    }
    private function createOrder(Ticket $ticket, array $extra = []): \App\Models\Maintenance
    {
        $response = $this->postJson(route('admin.maintenance.work-orders.store', $ticket), $this->payload($extra))->assertOk();
        return \App\Models\Maintenance::findOrFail($response->json('id'));
    }
    private function staffMember(?string $managerId = null, string $name = 'Maintenance Staff'): string
    {
        $managerId ??= $this->managerId;
        $managementId = DB::table('user_management')->where('user_id', $managerId)->value('id');
        if (!$managementId) {
            $managementId = (string) Str::ulid();
            DB::table('user_management')->insert(['id' => $managementId, 'user_id' => $managerId]);
        }
        $user = $this->user('staff');
        DB::table('users')->where('id', $user->id)->update(['name' => $name]);
        $id = (string) Str::ulid();
        DB::table('staff')->insert(['id' => $id, 'user_id' => $user->id, 'user_mgnt_id' => $managementId]);
        return $id;
    }

    private function assignOrder($order, array $extra = [])
    {
        return $this->postJson(route('admin.maintenance.work-orders.assign', $order), array_merge([
            'request_key' => (string) Str::uuid(), 'version' => $order->fresh()->version,
            'assigned_staff_id' => $this->assignmentStaff[$this->managerId] ??= $this->staffMember(),
        ], $extra));
    }

    private function checkoutRefundService(): RefundService
    {
        $invoices = \Mockery::mock(\App\Services\InvoiceService::class);
        $invoices->shouldReceive('settleOutstandingInvoicesFromDeposit')->andReturn(0);
        $service = \Mockery::mock(RefundService::class, [new \App\Services\RefundCalculationService(), $invoices])->makePartial();
        $service->shouldReceive('getDepositCollectedCents')->andReturn(100000);
        $service->shouldReceive('getWalletRefundCents')->andReturn(0);
        $this->app->instance(RefundService::class, $service);
        return $service;
    }

    private function deduction(string $fee, int $qty = 1): array
    {
        return ['fee_type_id' => $fee, 'type' => 'damage', 'qty' => $qty, 'unit_amount_cents' => 1000, 'amount_cents' => $qty * 1000];
    }

    private function processCheckout(RefundService $service, Refund $refund): void
    {
        $service->processDisbursement($refund, ['checked_out_at' => today()->toDateString(), 'refund_document_id' => 'refund-template',
            'method' => 'bank_transfer', 'status' => 'successful'], auth()->user());
    }

    private function approveCheckout(RefundService $service, Lease $lease, array $items): Refund
    {
        return $service->approveRefund($service->submitForPending($lease, [], $items), auth()->user());
    }

    public function test_refund_maintenance_is_created_only_after_successful_processing(): void
    {
        $this->actingAs($this->manager(['tab', 'view', 'assign', 'complete', 'edit']));
        $lease = $this->lease();
        $service = $this->checkoutRefundService();
        $bulb = $this->item();
        $bed = $this->item(['name' => 'Bed frame']);
        $items = [$this->deduction($bulb, 2), $this->deduction($bed), $this->deduction($bulb)];
        $refund = $service->saveAsDraft($lease, [], $items);
        $this->assertSame(0, Maintenance::count());
        $service->saveAsDraft($lease, [], array_reverse($items));
        $this->assertSame(0, Maintenance::count());
        $refund = $service->submitForPending($lease, [], $items);
        $this->assertSame(0, Maintenance::count());
        $refund = $service->approveRefund($refund, auth()->user());
        $this->assertSame(0, Maintenance::count());
        $this->assertSame(0, DocumentSequence::count());
        $this->processCheckout($service, $refund);
        $this->assertSame('paid', $refund->fresh()->status->value);
        $this->assertSame(3, Maintenance::count());
        $this->assertSame([1, 1, 2], Maintenance::orderBy('qty')->pluck('qty')->all());
        $this->assertSame(4000, $refund->fresh()->manual_deduction_cents);
        $this->assertSame(96000, $refund->fresh()->net_amount_cents);
        $this->assertSame(96000, (int) DB::table('refund_disbursements')->sum('amount_cents'));
        $this->assertSame(4, DocumentSequence::sole()->sequence['next_number']);
        app(\App\Services\MaintenanceService::class)->createFromRefund($refund->fresh());
        $this->assertSame(3, Maintenance::count());
        $this->assertSame(3, DB::table('maintenance_events')->count());
        $this->assertSame(4, DocumentSequence::sole()->sequence['next_number']);
        $order = Maintenance::firstOrFail();
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertSee('Checkout Refund');
        $this->get(route('admin.maintenance.work-orders.show', $order))->assertOk()->assertSee('checkout refund');
        $this->putJson(route('admin.maintenance.work-orders.update', $order), ['request_key' => (string) Str::uuid(), 'version' => $order->version,
            'qty' => 5, 'desc' => $order->desc, 'location_detail' => null])->assertOk();
        $this->assertSame(5, $order->fresh()->qty);
        $other = $this->user('agentAdmin');
        DB::table('users')->where('id', $other->id)->update(['account_permissions' => json_encode(['maintenance.tab', 'maintenance.view'])]);
        $this->actingAs($other->fresh());
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        $this->getJson(route('admin.maintenance.work-orders.show', $order))->assertForbidden();
    }

    public function test_deductions_removed_before_processing_do_not_generate_work_orders(): void
    {
        $this->actingAs($this->manager());
        $lease = $this->lease();
        $service = $this->checkoutRefundService();
        $first = $this->deduction($this->item());
        $removedFee = $this->item(['name' => 'Removed deduction']);
        $service->saveAsDraft($lease, [], [$first, $this->deduction($removedFee), $first]);
        $service->saveAsDraft($lease, [], [$first, $first]);
        $this->assertSame(0, Maintenance::count());
        $refund = $this->approveCheckout($service, $lease, [$first, $first]);
        $this->assertSame(0, Maintenance::count());
        $this->processCheckout($service, $refund);
        $this->assertSame(2, Maintenance::count());
        $this->assertSame(0, Maintenance::where('fee_type_id', $removedFee)->count());
    }

    public function test_three_identical_deduction_rows_generate_three_orders_at_processing(): void
    {
        $this->actingAs($this->manager());
        $service = $this->checkoutRefundService();
        $item = $this->deduction($this->item(), 2);
        $refund = $this->approveCheckout($service, $this->lease(), [$item, $item, $item]);
        $this->processCheckout($service, $refund);
        $this->assertSame(3, Maintenance::count());
        $this->assertSame([2, 2, 2], Maintenance::pluck('qty')->all());
        $this->assertSame(3, Maintenance::distinct()->count('refund_item_id'));
    }

    public function test_automatic_invoice_and_refundable_rows_do_not_create_maintenance_at_processing(): void
    {
        $this->actingAs($this->manager());
        $lease = $this->lease();
        DB::table('invoices')->insert(['id' => (string) Str::ulid(), 'user_id' => $this->managerId, 'lease_id' => $lease->id,
            'context' => 'lease', 'status' => 'unpaid', 'invoice_no' => 'INV-OUTSTANDING', 'amount_balance' => 500]);
        $service = $this->checkoutRefundService();
        $refund = $this->approveCheckout($service, $lease, [['type' => 'refundable_amount', 'qty' => 1, 'amount_cents' => 1000, 'description' => 'Refundable deposit']]);
        $this->processCheckout($service, $refund);
        $this->assertSame(500, $refund->fresh()->auto_deduction_cents);
        $this->assertSame(99500, $refund->fresh()->net_amount_cents);
        $this->assertSame(0, Maintenance::count());
        $this->assertSame(0, DocumentSequence::count());
    }

    public function test_failed_refund_maintenance_creation_rolls_back_processing_and_numbering(): void
    {
        $this->actingAs($this->manager());
        $service = $this->checkoutRefundService();
        $item = $this->deduction($this->item());
        $refund = $this->approveCheckout($service, $this->lease(), [$item, $item]);
        $real = app(DocumentSequenceService::class);
        $calls = 0;
        $mock = \Mockery::mock(DocumentSequenceService::class);
        $mock->shouldReceive('generateMaintenanceNumber')->twice()->andReturnUsing(function ($user) use ($real, &$calls) {
            if (++$calls === 2) throw new \RuntimeException('Sequence failure');
            return $real->generateMaintenanceNumber($user);
        });
        $this->app->instance(DocumentSequenceService::class, $mock);
        try {
            $this->processCheckout($service, $refund);
            $this->fail('Expected processing to roll back.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Sequence failure', $exception->getMessage());
        }
        $this->assertSame('approved', $refund->fresh()->status->value);
        $this->assertSame(2, $refund->items()->count());
        $this->assertSame(0, DB::table('refund_disbursements')->count());
        $this->assertSame(0, Maintenance::count());
        $this->assertSame(0, DocumentSequence::count());
        $this->app->instance(DocumentSequenceService::class, $real);
        $this->processCheckout($service, $refund);
        $this->assertSame(2, Maintenance::count());
        $this->assertSame(1, DB::table('refund_disbursements')->count());
    }

    public function test_unsuccessful_refund_processing_does_not_create_maintenance(): void
    {
        $this->actingAs($this->manager());
        $service = $this->checkoutRefundService();
        $refund = $this->approveCheckout($service, $this->lease(), [$this->deduction($this->item())]);
        try {
            $service->processDisbursement($refund, ['status' => 'pending'], auth()->user());
            $this->fail('Unconfirmed processing must be rejected.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('refund', $exception->errors());
        }
        $this->assertSame(0, Maintenance::count());
        $this->assertSame(0, DB::table('refund_disbursements')->count());
        $this->assertSame('approved', $refund->fresh()->status->value);
    }

    public function test_platform_processed_refund_uses_the_lease_management_sequence_and_saved_item_name(): void
    {
        $this->actingAs($this->manager());
        $service = $this->checkoutRefundService();
        $fee = $this->item();
        $refund = $this->approveCheckout($service, $this->lease(), [$this->deduction($fee)]);
        DB::table('fee_types')->where('id', $fee)->update(['name' => 'Renamed catalog label']);
        DocumentSequence::create(['user_id' => $this->managerId, 'category' => 'maintenance',
            'sequence' => ['prefix' => 'REPAIR-', 'padding' => 3, 'next_number' => 17]]);
        $admin = $this->user('admin');
        $this->actingAs($admin);
        $this->processCheckout($service, $refund);
        $order = Maintenance::sole();
        $this->assertSame('REPAIR-017', $order->maintenance_number);
        $this->assertSame($this->managerId, $order->management_user_id);
        $this->assertSame($admin->id, $order->created_by);
        $this->assertSame('Light bulb', $order->title);
    }

    public function test_processing_rejects_foreign_management_ownership_without_saving_payout_or_work_orders(): void
    {
        $this->actingAs($this->manager());
        $service = $this->checkoutRefundService();
        $lease = $this->lease();
        $refund = $this->approveCheckout($service, $lease, [$this->deduction($this->item())]);
        DB::table('properties')->where('id', $lease->leasable_id)->update(['created_by' => $this->user('agentAdmin')->id]);
        try {
            $this->processCheckout($service, $refund);
            $this->fail('Foreign ownership must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('approved', $refund->fresh()->status->value);
        $this->assertSame(0, Maintenance::count());
        $this->assertSame(0, DB::table('refund_disbursements')->count());
    }

    public function test_zero_net_refund_still_generates_maintenance_when_processed(): void
    {
        $this->actingAs($this->manager());
        $service = $this->checkoutRefundService();
        $refund = $this->approveCheckout($service, $this->lease(), [$this->deduction($this->item(), 100)]);
        $this->assertSame(0, $refund->net_amount_cents);
        $this->processCheckout($service, $refund);
        $this->assertSame('paid', $refund->fresh()->status->value);
        $this->assertSame(1, Maintenance::count());
        $this->assertSame(100, Maintenance::sole()->qty);
    }

    public function test_manual_and_conversation_creation_share_account_sequence_and_retries_do_not_advance_it(): void
    {
        $this->actingAs($this->manager());
        $sequence = DocumentSequence::create(['user_id' => $this->managerId, 'category' => 'maintenance',
            'sequence' => ['prefix' => 'REPAIR-', 'next_number' => 42, 'padding' => 4]]);
        $ticket = $this->source();
        $payload = $this->payload();
        $url = route('admin.maintenance.work-orders.store', $ticket);
        $id = $this->postJson($url, $payload)->assertOk()->json('id');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseHas('maintenance', ['id' => $id, 'maintenance_number' => 'REPAIR-0042']);
        $this->assertSame(43, $sequence->fresh()->sequence['next_number']);

        $payload = $this->payload(['leasable_type' => 'property', 'leasable_id' => $ticket->lease->leasable_id, 'fee_type_id' => $this->item(), 'qty' => 1]);
        $url = route('admin.maintenance.work-orders.store-from-list');
        $id = $this->postJson($url, $payload)->assertOk()->json('id');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseHas('maintenance', ['id' => $id, 'maintenance_number' => 'REPAIR-0043']);
        $this->assertSame(44, $sequence->fresh()->sequence['next_number']);

        // Reusing an earlier next number must skip existing references in this account.
        $sequence->update(['sequence' => ['prefix' => 'REPAIR-', 'next_number' => 42, 'padding' => 4]]);
        $order = $this->createOrder($this->source());
        $this->assertSame('REPAIR-0044', $order->maintenance_number);
        $this->assertSame(45, $sequence->fresh()->sequence['next_number']);
    }

    public function test_maintenance_sequences_are_independent_for_each_management_account(): void
    {
        $this->actingAs($this->manager());
        $first = $this->createOrder($this->source());
        $this->managerId = $this->user('agentAdmin')->id;
        $this->actingAs($this->manager());
        $second = $this->createOrder($this->source());

        $this->assertSame('MNT00001', $first->maintenance_number);
        $this->assertSame('MNT00001', $second->maintenance_number);
        $this->assertNotSame($first->management_user_id, $second->management_user_id);
        $this->assertSame(2, DocumentSequence::where('category', 'maintenance')->count());
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->modelKeys() === [$second->id]);
    }

    public function test_failed_maintenance_creation_rolls_back_number_allocation(): void
    {
        $this->actingAs($this->manager());
        $ticket = $this->source();
        $this->mock(\App\Services\FileService::class, function ($mock) {
            $mock->shouldReceive('upload')->once()->andThrow(new \RuntimeException('Disk unavailable'));
        });
        $url = route('admin.maintenance.work-orders.store', $ticket);
        $payload = $this->payload();
        $this->postJson($url, array_merge($payload, ['attachments' => [UploadedFile::fake()->createWithContent('before.txt', 'Evidence')]]))->assertStatus(500);
        $this->assertSame(0, DocumentSequence::count());
        $this->assertDatabaseCount('maintenance', 0);
        $this->assertDatabaseCount('maintenance_events', 0);
        $id = $this->postJson($url, $payload)->assertOk()->json('id');
        $this->assertDatabaseHas('maintenance', ['id' => $id, 'maintenance_number' => 'MNT00001']);
        $this->assertSame(2, DocumentSequence::sole()->sequence['next_number']);
    }

    public function test_full_flow_single_work_order_retry_reassignment_and_tenant_evidence(): void
    {
        $ticket = $this->source();
        $this->actingAs($this->manager());
        $payload = $this->payload();
        $url = route('admin.maintenance.work-orders.store', $ticket);
        $id = $this->postJson($url, $payload)->assertOk()->json('id');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('id', $id);
        $this->postJson($url, array_merge($payload, ['title'=>'Changed payload']))->assertConflict();
        $order = \App\Models\Maintenance::findOrFail($id);
        $this->postJson($url, $this->payload())->assertUnprocessable();
        $this->assertSame(1, $ticket->messages()->where('sender_type','system')->count());
        $this->assertSame('maintenance', $ticket->fresh()->category);
        $this->assertSame('pending', $ticket->fresh()->status);
        $this->assertSame(1, \App\Models\Maintenance::count());
        $completeUrl = route('admin.maintenance.work-orders.complete', $order);
        $this->postJson($completeUrl, ['request_key'=>(string) Str::uuid(), 'version'=>1])->assertConflict();
        $assignment = ['request_key'=>(string) Str::uuid(), 'version'=>1];
        $this->assignOrder($order, $assignment)->assertOk();
        $this->assignOrder($order, $assignment)->assertOk();
        $this->assignOrder($order, ['assigned_staff_id' => $this->staffMember(name: 'Replacement')])->assertOk();
        $this->assertSame(2, $ticket->messages()->where('sender_type','system')->count());
        $this->assertSame(3, $order->fresh()->version);
        $this->assignOrder($order, ['version'=>2])->assertConflict();
        $completion = ['request_key'=>(string) Str::uuid(), 'version'=>3, 'attachments'=>[UploadedFile::fake()->createWithContent('finished.txt','Completed repair')]];
        $this->postJson($completeUrl, $completion)->assertOk();
        $this->postJson($completeUrl, $completion)->assertOk();
        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame($this->managerId, $order->completed_by);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(3, $ticket->messages()->where('sender_type','system')->count());
        $this->assertSame(4, $order->events()->count());
        $this->assignOrder($order)->assertConflict();
        $this->postJson($completeUrl, ['request_key'=>(string) Str::uuid(),'version'=>4])->assertConflict();
        $file = $order->attachments()->sole();
        $this->actingAs($this->tenantUser);
        $data = $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()->assertDontSee('private-phone')->assertDontSee('Replacement');
        $this->assertStringContainsString('Description: Verified with tenant', $data->json('messages.0.text'));
        $this->assertStringContainsString('Status: Pending', $data->json('messages.0.text'));
        $this->assertStringContainsString('Status: Maintaining', $data->json('messages.1.text'));
        $this->assertStringContainsString('Status: Completed', $data->json('messages.2.text'));
        $this->assertSame('pending', $data->json('messages.0.maintenance_status'));
        $this->assertSame('maintaining', $data->json('messages.1.maintenance_status'));
        $this->assertSame('completed', $data->json('messages.2.maintenance_status'));
        foreach (['pending', 'maintaining', 'completed'] as $index => $status) {
            $this->assertSame(get_status_badge($status), $data->json('messages.'.$index.'.maintenance_status_class'));
        }
        $this->assertSame(get_status_badge('maintenance'), get_status_badge('maintaining'));
        $this->assertSame(get_status_badge('completed'), $data->json('ticket.status_badge_class'));
        $this->assertStringContainsString('This conversation is now read-only.', $data->json('messages.2.text'));
        $this->assertCount(1, $data->json('messages.2.maintenance_evidence'));
        $this->assertSame(route('maintenance.evidence', $file), $data->json('messages.2.maintenance_evidence.0.url'));
        $this->get(route('tenants.customerService.index', ['ticket' => $ticket->id]))->assertOk()
            ->assertDontSee('data-maintenance-cards', false)->assertSee('Completion evidence (1)')
            ->assertDontSee('<strong>Status:</strong>', false);
        $this->get(route('maintenance.evidence', $file))->assertOk();
        $this->get(route('maintenance.evidence', [$file, 'download'=>1]))->assertOk();
        $this->assertSame(3, app(TenantCustomerService::class)->unreadCount($ticket, $this->tenantUser));
        $ids = $ticket->messages()->pluck('id')->all();
        $this->postJson(route('tenants.customerService.markRead',$ticket),['message_ids'=>$ids])->assertOk();
        $this->assertSame(0, app(TenantCustomerService::class)->unreadCount($ticket, $this->tenantUser));
    }

    public function test_conversation_follows_maintenance_and_completion_blocks_all_chat_submissions(): void
    {
        $ticket = $this->source();
        $manager = $this->manager();
        $this->actingAs($manager);
        $order = $this->createOrder($ticket);
        $this->assertSame('pending', $ticket->fresh()->status);
        $this->assignOrder($order)->assertOk();
        $this->assertSame('maintaining', $ticket->fresh()->status);
        $this->postJson(route('admin.maintenance.reply', $ticket), ['items' => [['type' => 'text', 'text' => 'Repair underway']]])->assertOk();
        $this->actingAs($this->tenantUser);
        $cursor = $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()->json('cursor');
        $this->actingAs($manager);
        $completion = ['request_key' => (string) Str::uuid(), 'version' => 2];
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), $completion)->assertOk();
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), $completion)->assertOk();
        $this->assertSame('completed', $ticket->fresh()->status);
        $count = $ticket->messages()->count();
        foreach ([$this->tenantUser, $manager, User::findOrFail($this->ownerId)] as $actor) {
            $this->actingAs($actor);
            $prefix = $actor->isTenant() ? 'tenants.customerService' : 'admin.maintenance';
            $this->getJson(route($prefix.'.show', $ticket))->assertOk()
                ->assertJsonPath('ticket.status', 'completed')->assertJsonPath('ticket.closed', true)
                ->assertJsonPath('ticket.can_send', false)->assertJsonPath('ticket.maintenance', []);
            $this->postJson(route($prefix.'.reply', $ticket), ['items' => [['type' => 'text', 'text' => 'Too late']]])->assertForbidden();
            $this->post(route($prefix.'.reply', $ticket), ['items' => [['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('late.txt', 'Too late')]]], ['Accept' => 'application/json'])->assertForbidden();
        }
        $this->actingAs($manager);
        $this->postJson(route('admin.maintenance.work-orders.store', $ticket), $this->payload())->assertUnprocessable();
        $this->assertSame($count, $ticket->messages()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->actingAs($this->tenantUser);
        $this->getJson(route('tenants.customerService.index', ['sync' => 1, 'ticket' => $ticket->id, 'cursor' => $cursor]))->assertOk()
            ->assertJsonPath('ticket.status', 'completed')->assertJsonPath('ticket.can_send', false);
        $this->get(route('tenants.customerService.index', ['ticket' => $ticket->id]))->assertOk()->assertSee('This conversation is read-only.');
    }

    public function test_existing_work_order_hides_creation_and_database_prevents_duplicates(): void
    {
        $ticket = $this->source();
        $this->actingAs($this->manager());
        $order = $this->createOrder($ticket);
        $this->getJson(route('admin.maintenance.show', $ticket))->assertOk()->assertJsonPath('ticket.maintenance', []);
        $this->postJson(route('admin.maintenance.work-orders.store', $ticket), $this->payload())->assertUnprocessable();
        (require database_path('migrations/2026_11_17_000002_sync_maintenance_conversation_statuses.php'))->up();
        $duplicate = $order->replicate()->getAttributes();
        $duplicate['id'] = (string) Str::ulid();
        $duplicate['maintenance_number'] = 'MNT-'.$duplicate['id'];
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('maintenance')->insert($duplicate);
    }

    public function test_existing_conversation_statuses_are_backfilled_without_reopening_closed_history(): void
    {
        $this->actingAs($this->manager());
        $tickets = [];
        foreach (['pending', 'maintaining', 'completed', 'closed'] as $status) {
            $ticket = $this->source();
            $order = $this->createOrder($ticket);
            $order->update(['status' => $status === 'closed' ? 'pending' : $status]);
            $ticket->update(['status' => $status === 'closed' ? 'closed' : 'pending']);
            $tickets[$status] = $ticket;
        }
        $unrelated = $this->ticket();
        $migration = require database_path('migrations/2026_11_17_000002_sync_maintenance_conversation_statuses.php');
        $migration->up();
        $migration->up();
        foreach ($tickets as $status => $ticket) $this->assertSame($status, $ticket->fresh()->status);
        $this->assertSame('pending', $unrelated->fresh()->status);
        $migration->down();
        $this->assertSame('completed', $tickets['completed']->fresh()->status);
    }

    public function test_optional_completion_and_failed_upload_roll_back_until_explicit_retry(): void
    {
        $ticket = $this->source(); $this->actingAs($this->manager()); $order = $this->createOrder($ticket);
        $this->assignOrder($order)->assertOk();
        $key = (string) Str::uuid(); $url = route('admin.maintenance.work-orders.complete',$order);
        $this->mock(\App\Services\FileService::class, function ($mock) { $mock->shouldReceive('upload')->twice()->andReturnUsing(function () {
            static $n = 0; $n++; if ($n === 2) throw new \RuntimeException('Disk unavailable');
            Storage::disk('local')->put('maintenance-test/first.txt','first'); return 'maintenance-test/first.txt';
        }); });
        $this->postJson($url,['request_key'=>$key,'version'=>2,'attachments'=>[
            UploadedFile::fake()->createWithContent('one.txt','first'), UploadedFile::fake()->createWithContent('two.txt','second')]])->assertStatus(500);
        $this->assertSame('maintaining',$order->fresh()->status);
        $this->assertSame('maintaining', $ticket->fresh()->status);
        $this->assertNull($order->fresh()->completed_by);
        $this->assertSame(0,$order->attachments()->count());
        Storage::disk('local')->assertMissing('maintenance-test/first.txt');
        $this->assertSame(2,$ticket->messages()->count());
        $this->postJson($url,['request_key'=>$key,'version'=>2])->assertOk();
        $this->assertSame('completed',$order->fresh()->status);
        $this->assertSame('completed', $ticket->fresh()->status);
        $this->assertSame(0,$order->attachments()->count());
        $this->actingAs($this->tenantUser);
        $this->getJson(route('tenants.customerService.show',$ticket))->assertOk()->assertJsonPath('messages.2.maintenance_evidence',[]);
    }

    public function test_scope_permissions_legacy_source_and_private_before_evidence(): void
    {
        $ticket = $this->source(); $this->actingAs($this->manager());
        DB::table('owners')->insert(['id'=>(string) Str::ulid(),'user_id'=>$this->ownerId,'agent_id'=>$this->managerId]);
        $legacy = $this->ticket(); $legacy->update(['receive_id'=>$this->ownerId]);
        $this->postJson(route('admin.maintenance.work-orders.store',$legacy),$this->payload())->assertUnprocessable();
        $order = $this->createOrder($ticket,['attachments'=>[UploadedFile::fake()->createWithContent('before.txt','Private evidence')]]);
        $file = $order->attachments()->sole();
        $this->actingAs($this->manager(['tab','view']));
        $this->postJson(route('admin.maintenance.work-orders.store',$ticket),$this->payload())->assertForbidden();
        $this->assignOrder($order)->assertForbidden();
        $this->get(route('admin.maintenance.work-orders.show',$order))->assertOk();
        $this->get(route('maintenance.evidence',$file))->assertOk();
        $other = $this->user('agentAdmin');
        DB::table('users')->where('id',$other->id)->update(['account_permissions'=>json_encode(['maintenance.tab','maintenance.view','maintenance.create','maintenance.assign','maintenance.complete'])]);
        $this->actingAs($other->fresh());
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertDontSee('Broken light');
        $this->getJson(route('admin.maintenance.work-orders.show',$order))->assertForbidden();
        $this->assignOrder($order)->assertForbidden();
        $this->getJson(route('maintenance.evidence',$file))->assertForbidden();
        $this->actingAs($this->user('tenant'));
        $this->getJson(route('maintenance.evidence',$file))->assertForbidden();
        $this->actingAs($this->tenantUser);
        $this->getJson(route('maintenance.evidence',$file))->assertForbidden();
    }

    public function test_existing_chat_evidence_cannot_be_taken_from_another_ticket_and_is_copied(): void
    {
        $ticket=$this->source(); $other=$this->source(); $this->actingAs($this->manager());
        $path='ticket-attachments/'.$other->id.'/'.Str::uuid().'.txt'; Storage::disk('local')->put($path,'chat proof');
        $message=$other->messages()->create(['sender_type'=>'tenant','message'=>$path]);
        $this->postJson(route('admin.maintenance.work-orders.store',$ticket),$this->payload(['source_message_ids'=>[$message->id]]))->assertNotFound();
        $this->assertSame(0,\App\Models\Maintenance::count());
        $order=$this->createOrder($other,['source_message_ids'=>[$message->id]]);
        $file=$order->attachments()->sole();
        $this->assertNotSame($path,$file->path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame($message->id,$file->source_ticket_msg_id);
    }

    public function test_staff_work_order_permissions_include_chat_reply_and_record_real_actor(): void
    {
        $this->manager(['tab', 'reply', 'view', 'create', 'assign', 'complete']);
        $ticket=$this->source(); $staff=$this->user('staff'); $mgmt=(string) Str::ulid();
        DB::table('user_management')->insert(['id'=>$mgmt,'user_id'=>$this->managerId]);
        DB::table('staff')->insert(['id'=>(string) Str::ulid(),'user_id'=>$staff->id,'user_mgnt_id'=>$mgmt]);
        $role=DB::table('roles')->insertGetId(['name'=>'Maintenance staff','guard_name'=>'web','team_id'=>$this->managerId]);
        DB::table('model_has_roles')->insert(['role_id'=>$role,'model_type'=>User::class,'model_id'=>$staff->id,'team_id'=>$this->managerId]);
        foreach(['service request.tab','service request.reply','maintenance.tab','maintenance.view','maintenance.create','maintenance.assign','maintenance.complete'] as $ability) {
            $pid=DB::table('permissions')->insertGetId(['name'=>$ability,'guard_name'=>'web']);
            DB::table('role_has_permissions')->insert(['role_id'=>$role,'permission_id'=>$pid]);
        }
        $this->actingAs($staff);
        DocumentSequence::create(['user_id' => $this->managerId, 'category' => 'maintenance',
            'sequence' => ['prefix' => 'TEAM-', 'next_number' => 7, 'padding' => 3]]);
        $personalSequence = DocumentSequence::create(['user_id' => $staff->id, 'category' => 'maintenance',
            'sequence' => ['prefix' => 'PERSONAL-', 'next_number' => 99, 'padding' => 5]]);
        $this->assertTrue(app(TenantCustomerService::class)->canReply($ticket,$staff));
        $this->manager(['tab', 'view', 'create', 'assign', 'complete']);
        $this->assertFalse(app(TenantCustomerService::class)->canReply($ticket,$staff));
        $this->manager(['tab', 'reply', 'view', 'create', 'assign', 'complete']);
        $order=$this->createOrder($ticket); $this->assignOrder($order)->assertOk();
        $this->assertSame('TEAM-007', $order->maintenance_number);
        $this->assertSame(99, $personalSequence->fresh()->sequence['next_number']);
        $this->postJson(route('admin.maintenance.work-orders.complete',$order),['request_key'=>(string) Str::uuid(),'version'=>2])->assertOk();
        $this->assertSame($staff->id,$order->fresh()->created_by);
        $this->assertSame($staff->id,$order->fresh()->assigned_by);
        $this->assertSame($staff->id,$order->fresh()->completed_by);
        $this->assertFalse(app(TenantCustomerService::class)->canReply($ticket->fresh(), $staff));
        $this->postJson(route('admin.maintenance.reply', $ticket), ['items' => [['type' => 'text', 'text' => 'After completion']]])->assertForbidden();
        $this->assertSame([$staff->id],$order->events()->pluck('actor_id')->unique()->values()->all());
        $this->assertDatabaseHas('audit_logs',['auditable_id'=>$order->id,'user_id'=>$staff->id]);
    }
    public function test_legacy_notices_are_enriched_but_saved_detailed_notices_remain_unchanged(): void
    {
        $ticket = $this->source();
        $this->actingAs($this->manager());
        $order = $this->createOrder($ticket, ['title' => '<Repair>', 'desc' => 'Original verified issue']);
        $message = $ticket->messages()->where('sender_type', 'system')->sole();
        $saved = $message->message;
        $order->update(['desc' => 'Revised details']);
        $this->actingAs($this->tenantUser);
        $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()->assertJsonPath('messages.0.text', $saved);
        DB::table('ticket_msgs')->where('id', $message->id)->update(['message' => str_replace('Status: Pending', "Lease: Test Property - Property\nStatus: Pending", $saved)]);
        $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()->assertJsonPath('messages.0.text', $saved);
        DB::table('ticket_msgs')->where('id', $message->id)->update(['message' => str_replace('Status: Pending', "Location: Test Property / Bedroom ceiling\nStatus: Pending", $saved)]);
        $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()->assertJsonPath('messages.0.text', $saved);
        DB::table('ticket_msgs')->where('id', $message->id)->update(['message' => 'Maintenance created · '.$order->maintenance_number.' · '.$order->title]);
        $legacy = $this->getJson(route('tenants.customerService.show', $ticket))->assertOk();
        $this->assertStringContainsString('Description: Revised details', $legacy->json('messages.0.text'));
        $this->get(route('tenants.customerService.index', ['ticket' => $ticket->id]))->assertOk()
            ->assertSee('&lt;Repair&gt;', false)->assertDontSee('<Repair>', false);
        $this->actingAs($this->manager());
        $foreign = $this->createOrder($this->source(), ['desc' => 'Private description from another conversation']);
        DB::table('ticket_msgs')->where('id', $message->id)->update(['maintenance_id' => $foreign->id]);
        $this->actingAs($this->tenantUser);
        $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()
            ->assertJsonPath('messages.0.maintenance_evidence', [])->assertDontSee('Private description from another conversation');
    }

    public function test_room_maintenance_notice_omits_duplicate_lease_and_keeps_bold_labels(): void
    {
        $ticket = $this->source();
        $unitId = (string) Str::ulid();
        $roomId = (string) Str::ulid();
        DB::table('units')->insert(['id' => $unitId, 'unit_no' => '80', 'property_id' => $ticket->lease->leasable_id, 'owner_id' => $this->ownerId, 'created_by' => $this->managerId]);
        DB::table('rooms')->insert(['id' => $roomId, 'room_no' => '80-1', 'unit_id' => $unitId]);
        $ticket->lease->update(['leasable_type' => Room::class, 'leasable_id' => $roomId, 'start_date' => '2026-11-11', 'end_date' => '2026-12-11']);
        $this->actingAs($this->manager());
        $this->createOrder($ticket);
        $this->actingAs($this->tenantUser);
        $response = $this->getJson(route('tenants.customerService.show', $ticket))->assertOk();
        $this->assertStringNotContainsString('Lease:', $response->json('messages.0.text'));
        $this->assertStringNotContainsString('Location:', $response->json('messages.0.text'));
        $this->get(route('tenants.customerService.index', ['ticket' => $ticket->id]))->assertOk()
            ->assertSee('<strong>Maintenance created</strong>', false)
            ->assertSee('<strong>Reference:</strong>', false)->assertDontSee('<strong>Lease:</strong>', false);
    }

    private function item(array $overrides = []): string
    {
        $id = (string) Str::ulid();
        DB::table('fee_types')->insert(array_merge(['id' => $id, 'user_id' => $this->managerId, 'name' => 'Light bulb', 'category' => 'item', 'is_active' => true], $overrides));
        return $id;
    }

    private function vacantRoom(): Room
    {
        $property = (string) Str::ulid();
        $unit = (string) Str::ulid();
        $room = (string) Str::ulid();
        DB::table('properties')->insert(['id' => $property, 'name' => 'Test building', 'created_by' => $this->managerId, 'owner_id' => $this->ownerId, 'status' => 'Vacant']);
        DB::table('units')->insert(['id' => $unit, 'property_id' => $property, 'unit_no' => '70-1', 'created_by' => $this->managerId, 'owner_id' => $this->ownerId, 'status' => 'Vacant']);
        DB::table('rooms')->insert(['id' => $room, 'unit_id' => $unit, 'room_no' => 'O41', 'status' => 'Vacant']);
        return Room::findOrFail($room);
    }

    private function manualOrder($asset): Maintenance
    {
        $response = $this->postJson(route('admin.maintenance.work-orders.store-from-list'), $this->payload([
            'leasable_type' => strtolower(class_basename($asset)), 'leasable_id' => $asset->id,
            'fee_type_id' => $this->item(), 'qty' => 1,
        ]))->assertOk();
        return Maintenance::findOrFail($response->json('id'));
    }

    private function completeOrder(Maintenance $order): void
    {
        $this->assignOrder($order)->assertOk();
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), [
            'request_key' => (string) Str::uuid(), 'version' => $order->fresh()->version,
        ])->assertOk();
    }

    public function test_vacant_room_without_a_lease_becomes_vacant_only_after_both_jobs_are_completed(): void
    {
        $this->actingAs($this->manager());
        $room = $this->vacantRoom();
        $other = $this->vacantRoom();
        $first = $this->manualOrder($room);
        $second = $this->manualOrder($room);
        $this->manualOrder($other);
        $this->assertNull($first->lease_id);
        $this->assertSame('Maintenance', $room->fresh()->status);
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()
            ->assertSee(route('admin.rooms.show', $room), false)->assertSee('O41')->assertDontSee('Select a lease');
        $this->getJson(route('admin.maintenance.work-orders.edit', $first))->assertOk()
            ->assertJson(fn ($json) => $json->where('html', fn ($html) => str_contains($html, 'O41 - Room'))->etc());
        $this->completeOrder($first);
        $this->assertSame('Maintenance', $room->fresh()->status);
        $this->completeOrder($second);
        $this->assertSame('Vacant', $room->fresh()->status);
        $this->assertSame('Vacant', $room->unit->fresh()->status);
        $this->assertSame('Vacant', $room->unit->property->fresh()->status);
        $this->assertSame('Maintenance', $other->fresh()->status);
    }

    public function test_compact_dashboard_table_keeps_source_links_and_direct_maintenance_actions(): void
    {
        $manager = $this->manager();
        $manager->forceFill(['account_permissions' => array_merge($manager->account_permissions, ['tenant.show', 'leases.show', 'property.show unit'])])->save();
        $this->actingAs($manager->fresh());
        $ticket = $this->source();
        $order = $this->createOrder($ticket);
        $orders = Maintenance::with(['leasable', 'lease.tenant.user'])->paginate(5, ['*'], 'maintenance_page');
        $render = fn () => \Illuminate\Support\Facades\Blade::render('<x-dashboard.maintenance-table :orders="$orders" />', ['orders' => $orders]);
        $html = $render();
        foreach ([route('admin.maintenance.work-orders.show', $order), route('admin.tenants.show', $this->tenantId),
            route('admin.leases.show', $ticket->lease_id), route('admin.maintenance.index', ['ticket' => $ticket->id]),
            'data-assign-maintenance', 'Assign Contractor'] as $value) $this->assertStringContainsString($value, $html);
        $this->assertStringNotContainsString('data-complete-maintenance', $html);
        $this->assertStringNotContainsString('Search by item', $html);
        $this->assignOrder($order)->assertOk();
        $orders->setCollection($orders->getCollection()->map(fn ($order) => $order->fresh(['leasable', 'lease.tenant.user'])));
        $html = $render();
        $this->assertStringContainsString('Change Contractor', $html);
        $this->assertStringContainsString('data-complete-maintenance', $html);
        $manager->forceFill(['account_permissions' => ['maintenance.tab', 'maintenance.view']])->save();
        $this->actingAs($manager->fresh());
        $html = $render();
        foreach (['data-assign-maintenance', 'data-complete-maintenance', route('admin.tenants.show', $this->tenantId),
            route('admin.leases.show', $ticket->lease_id), route('admin.maintenance.index', ['ticket' => $ticket->id]),
            route('admin.properties.show', $ticket->lease->leasable_id)] as $value) $this->assertStringNotContainsString($value, $html);
        $this->assertStringContainsString($order->title, $html);
    }

    public function test_history_is_scoped_to_the_exact_property_unit_or_room_and_includes_all_activity(): void
    {
        $this->actingAs($this->manager());
        $room = $this->vacantRoom();
        $other = $this->vacantRoom();
        $orders = [];
        foreach ([$room->unit->property, $room->unit, $room, $other] as $asset) $orders[$asset->id] = $this->manualOrder($asset);
        $order = $orders[$room->id];
        Carbon::setTestNow('2026-01-11 09:00:00');
        $this->assignOrder($order, ['assigned_staff_id' => $this->staffMember(name: 'First Contractor')])->assertOk();
        Carbon::setTestNow('2026-01-12 09:00:00');
        $this->assignOrder($order, ['assigned_staff_id' => $this->staffMember(name: 'Replacement Contractor')])->assertOk();
        $this->putJson(route('admin.maintenance.work-orders.update', $order), $this->payload(['title' => $order->title, 'qty' => 3, 'version' => $order->fresh()->version,
            'desc' => 'Replaced the broken frame']))->assertOk();
        Carbon::setTestNow('2026-01-13 09:00:00');
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), ['request_key' => (string) Str::uuid(),
            'version' => $order->fresh()->version, 'attachments' => [UploadedFile::fake()->createWithContent('completed.txt', 'done')]])->assertOk();
        foreach ([$room->unit->property, $room->unit, $room] as $asset) {
            $html = $this->getJson(route('admin.maintenance.history', ['type' => strtolower(class_basename($asset)), 'id' => $asset->id]))
                ->assertOk()->json('html');
            $this->assertStringContainsString($orders[$asset->id]->maintenance_number, $html);
            foreach ($orders as $assetId => $otherOrder) {
                if ($assetId !== $asset->id) $this->assertStringNotContainsString($otherOrder->maintenance_number, $html);
            }
            if ($asset instanceof Room) {
                foreach (['Started', 'Completed', '11/01/2026', '13/01/2026', 'First Contractor', 'Replacement Contractor',
                    'Maintenance updated', 'Replaced the broken frame', 'QTY:3', 'completed.txt'] as $text) $this->assertStringContainsString($text, $html);
            }
        }
        $foreign = $order->replicate();
        $foreign->fill(['management_user_id' => $this->user('agentAdmin')->id, 'maintenance_number' => 'FOREIGN-HISTORY'])->save();
        $html = $this->getJson(route('admin.maintenance.history.index'))->assertOk()->json('html');
        foreach ($orders as $ownOrder) $this->assertStringContainsString($ownOrder->maintenance_number, $html);
        $this->assertStringNotContainsString('FOREIGN-HISTORY', $html);
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertSee('data-maintenance-history', false);
    }

    public function test_history_pagination_and_empty_state_preserve_the_selected_space(): void
    {
        $this->actingAs($this->manager());
        $room = $this->vacantRoom();
        $url = route('admin.maintenance.history', ['type' => 'room', 'id' => $room->id]);
        $this->assertStringContainsString('No maintenance history for this room.', $this->getJson($url)->assertOk()->json('html'));
        $orders = [];
        for ($i = 0; $i < 11; $i++) $orders[] = $this->manualOrder($room);
        $first = $this->getJson($url)->assertOk()->json('html');
        $second = $this->getJson($url.'?page=2')->assertOk()->json('html');
        $this->assertStringContainsString('11 work orders', $first);
        $this->assertStringContainsString($url.'?page=2', $first);
        $this->assertStringNotContainsString($orders[0]->maintenance_number, $first);
        $this->assertStringContainsString($orders[0]->maintenance_number, $second);
        $this->assertStringNotContainsString($orders[10]->maintenance_number, $second);
        $this->getJson($url.'?page=-1')->assertUnprocessable();
    }

    public function test_history_rejects_foreign_spaces_invalid_types_and_missing_permissions(): void
    {
        $this->actingAs($this->manager());
        $room = $this->vacantRoom();
        $url = route('admin.maintenance.history', ['type' => 'room', 'id' => $room->id]);
        $foreign = $this->vacantRoom();
        DB::table('properties')->where('id', $foreign->unit->property_id)->update(['created_by' => $this->user('agentAdmin')->id]);
        DB::table('units')->where('id', $foreign->unit_id)->update(['created_by' => null]);
        $this->getJson(route('admin.maintenance.history', ['type' => 'room', 'id' => $foreign->id]))->assertForbidden();
        $this->getJson(route('admin.maintenance.history', ['type' => 'user', 'id' => $room->id]))->assertNotFound();
        $this->getJson(route('admin.maintenance.history', ['type' => 'room', 'id' => (string) Str::ulid()]))->assertNotFound();
        $this->actingAs($this->manager(['tab', 'view']));
        $this->getJson($url)->assertOk();
        $this->actingAs($this->manager(['tab']));
        $this->getJson($url)->assertForbidden();
        $this->actingAs($this->manager(['view']));
        $this->getJson($url)->assertForbidden();
        $this->actingAs($this->tenantUser);
        $this->getJson($url)->assertForbidden();
    }

    public function test_creating_maintenance_for_an_occupied_property_unit_or_room_preserves_occupancy(): void
    {
        $this->actingAs($this->manager());
        foreach (['property', 'unit', 'room'] as $type) {
            $room = $this->vacantRoom();
            $asset = match ($type) { 'property' => $room->unit->property, 'unit' => $room->unit, 'room' => $room };
            $this->lease(['leasable_type' => $asset->getMorphClass(), 'leasable_id' => $asset->id]);
            $asset->update(['status' => 'Occupied']);
            $order = $this->manualOrder($asset);
            $this->assertSame('Occupied', $asset->fresh()->status, $type.' must remain occupied after maintenance creation');
            $this->assertSame('pending', $order->status);
            $this->completeOrder($order);
            $this->assertSame('Occupied', $asset->fresh()->status, $type.' must remain occupied after maintenance completion');
        }
    }

    public function test_completion_preserves_occupancy_of_a_new_lease_and_its_parent(): void
    {
        $this->actingAs($this->manager());
        $room = $this->vacantRoom();
        $order = $this->manualOrder($room);
        $this->lease(['leasable_type' => Room::class, 'leasable_id' => $room->id]);
        $this->completeOrder($order);
        $this->assertSame('Occupied', $room->fresh()->status);
        $this->assertSame('Occupied', $room->unit->fresh()->status);
        $this->assertSame('Occupied', $room->unit->property->fresh()->status);
    }

    public function test_parent_and_room_work_orders_preserve_each_other_and_occupied_siblings(): void
    {
        $this->actingAs($this->manager());
        $room = $this->vacantRoom();
        $sibling = (string) Str::ulid();
        DB::table('rooms')->insert(['id' => $sibling, 'unit_id' => $room->unit_id, 'room_no' => 'O42', 'status' => 'Occupied']);
        $this->lease(['leasable_type' => Room::class, 'leasable_id' => $sibling]);
        $unitOrder = $this->manualOrder($room->unit);
        $roomOrder = $this->manualOrder($room);
        $this->assertSame('Occupied', Room::findOrFail($sibling)->status);
        $this->completeOrder($roomOrder);
        $this->assertSame('Maintenance', $room->fresh()->status);
        $this->completeOrder($unitOrder);
        $this->assertSame('Vacant', $room->fresh()->status);
        $this->assertSame('Occupied', Room::findOrFail($sibling)->status);
        $this->assertSame('Vacant', $room->unit->fresh()->status);
    }

    public function test_refund_and_manual_jobs_for_the_same_room_must_all_complete_before_vacancy(): void
    {
        $this->actingAs($this->manager());
        $room = $this->vacantRoom();
        $lease = $this->lease(['leasable_type' => Room::class, 'leasable_id' => $room->id]);
        $service = $this->checkoutRefundService();
        $refund = $this->approveCheckout($service, $lease, [$this->deduction($this->item()), $this->deduction($this->item())]);
        $this->processCheckout($service, $refund);
        $orders = Maintenance::where('refund_id', $refund->id)->get();
        $this->assertCount(2, $orders);
        $manual = $this->manualOrder($room);
        foreach ($orders as $order) {
            $this->assertSame($room->id, $order->leasable_id);
            $this->completeOrder($order);
            $this->assertSame('Maintenance', $room->fresh()->status);
        }
        $this->completeOrder($manual);
        $this->assertSame('Vacant', $room->fresh()->status);
        $html = $this->getJson(route('admin.maintenance.history', ['type' => 'room', 'id' => $room->id]))->assertOk()->json('html');
        $this->assertStringContainsString('3 work orders', $html);
        $this->assertStringContainsString('Checkout Refund', $html);
        $this->assertStringContainsString('Manual', $html);
    }

    public function test_direct_location_selection_enforces_account_type_and_availability(): void
    {
        $this->actingAs($this->manager());
        $room = $this->vacantRoom();
        $foreign = $this->vacantRoom();
        DB::table('properties')->where('id', $foreign->unit->property_id)->update(['created_by' => $this->user('agentAdmin')->id]);
        DB::table('units')->where('id', $foreign->unit_id)->update(['created_by' => null]);
        $url = route('admin.maintenance.work-orders.store-from-list');
        $payload = $this->payload(['leasable_type' => 'room', 'leasable_id' => $foreign->id, 'fee_type_id' => $this->item(), 'qty' => 1]);
        $this->postJson($url, $payload)->assertForbidden();
        $payload['leasable_id'] = $room->id;
        $this->postJson($url, array_merge($payload, ['leasable_type' => 'unit']))->assertNotFound();
        $this->postJson($url, array_merge($payload, ['leasable_id' => '']))->assertUnprocessable()->assertJsonValidationErrors('leasable_id');
        DB::table('units')->where('id', $room->unit_id)->update(['status' => 'Removed']);
        $this->postJson($url, $payload)->assertConflict();
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()
            ->assertViewHas('leasableOptions', fn ($options) => !array_intersect([$room->id, $room->unit_id, $foreign->id], array_column($options, 'value')));
        $this->assertSame(0, Maintenance::count());
    }

    public function test_leasable_migration_backfills_existing_work_orders_and_preserves_their_lease(): void
    {
        $this->actingAs($this->manager());
        $ticket = $this->source();
        $order = $this->createOrder($ticket);
        $migration = require database_path('migrations/2026_11_19_000003_link_maintenance_to_leasables.php');
        $migration->down();
        $migration->up();
        $this->assertDatabaseHas('maintenance', ['id' => $order->id, 'lease_id' => $ticket->lease_id,
            'leasable_type' => Property::class, 'leasable_id' => $ticket->lease->leasable_id]);
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()
            ->assertSee(route('admin.properties.show', $ticket->lease->leasable_id), false);
    }

    public function test_manual_creation_uses_leasable_item_quantity_without_lease_and_supports_full_lifecycle(): void
    {
        $this->actingAs($this->manager());
        $lease = $this->lease();
        $item = $this->item();
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()
            ->assertSee('Add New Maintenance')->assertDontSee('Tenant conversation')->assertSee('Light bulb')
            ->assertSee('aria-label="Close"', false)
            ->assertViewHas('leasableOptions', fn ($options) => array_column($options, 'value') === [$lease->leasable_id]);
        $url = route('admin.maintenance.work-orders.store-from-list');
        $payload = $this->payload(['leasable_type' => 'property', 'leasable_id' => $lease->leasable_id, 'fee_type_id' => $item, 'qty' => 4]);
        unset($payload['title']);
        $created = $this->postJson($url, $payload)->assertOk()->assertSessionHas('success');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('id', $created->json('id'));
        $this->postJson($url, array_merge($payload, ['qty' => 5]))->assertConflict();
        $order = \App\Models\Maintenance::findOrFail($created->json('id'));
        $this->assertSame('manual', $order->source_type);
        $this->assertNull($order->ticket_id);
        $this->assertNull($order->lease_id);
        $this->assertSame($lease->leasable_id, $order->leasable_id);
        $this->assertSame('Light bulb', $order->title);
        $this->assertSame(4, $order->qty);
        $this->assertSame($item, $order->fee_type_id);
        $this->assertSame($item, $order->feeType?->id);
        $this->assertSame('Light bulb', $order->feeType?->name);
        $this->assertSame(1, \App\Models\Maintenance::count());
        $this->get(route('admin.maintenance.work-orders.show', $order))->assertOk()->assertViewIs('adminSide.maintenance.show')->assertDontSee('Open source conversation')
            ->assertSee($order->maintenance_number.' Light bulb(QTY:4) - manual');
        $this->getJson(route('admin.maintenance.work-orders.edit', $order))->assertOk()->assertJsonStructure(['html']);
        $this->put(route('admin.maintenance.work-orders.update', $order), $this->payload(['title' => 'Light bulb', 'qty' => 5, 'version' => 1]))->assertRedirect();
        $this->assertSame(5, $order->fresh()->qty);
        $this->assignOrder($order)->assertOk();
        $completion = ['request_key' => (string) Str::uuid(), 'version' => $order->fresh()->version,
            'attachments' => [UploadedFile::fake()->createWithContent('done.txt', 'Repair complete')]];
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), $completion)->assertOk();
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), $completion)->assertOk();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(4, $order->events()->count());
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, TicketMsg::count());
        $file = $order->attachments()->sole();
        $this->get(route('maintenance.evidence', $file))->assertOk();
        $this->actingAs($this->tenantUser);
        $this->get(route('maintenance.evidence', $file))->assertForbidden();
        $this->actingAs($this->manager(['tab', 'view']));
        $this->postJson($url, $payload)->assertForbidden();
    }

    public function test_manual_creation_rejects_foreign_leasable_and_invalid_catalog_or_quantity(): void
    {
        $lease = $this->lease();
        $item = $this->item();
        $foreignManager = $this->user('agentAdmin');
        $foreignLease = $this->lease();
        DB::table('properties')->where('id', $foreignLease->leasable_id)->update(['created_by' => $foreignManager->id]);
        $this->actingAs($this->manager());
        $payload = $this->payload(['leasable_type' => 'property', 'leasable_id' => $lease->leasable_id, 'fee_type_id' => $item, 'qty' => 4]);
        $url = route('admin.maintenance.work-orders.store-from-list');
        foreach ([0, -1, 1.5, 'invalid'] as $qty) {
            $this->postJson($url, array_merge($payload, ['qty' => $qty]))->assertUnprocessable()->assertJsonValidationErrors('qty');
        }
        foreach ([['user_id' => $foreignManager->id], ['category' => 'invoice'], ['is_active' => false]] as $overrides) {
            $this->postJson($url, array_merge($payload, ['fee_type_id' => $this->item($overrides)]))->assertUnprocessable()->assertJsonValidationErrors('fee_type_id');
        }
        $this->postJson($url, array_merge($payload, ['leasable_id' => $foreignLease->leasable_id]))->assertForbidden();
        $this->postJson($url, array_merge($payload, ['leasable_type' => User::class]))->assertUnprocessable()->assertJsonValidationErrors('leasable_type');
        $this->postJson($url, array_merge($payload, ['lease_id' => $lease->id]))->assertUnprocessable()->assertJsonValidationErrors('lease_id');
        $this->postJson($url, array_merge($payload, ['ticket_id' => (string) Str::ulid()]))->assertUnprocessable();
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()
            ->assertViewHas('leasableOptions', fn ($options) => array_column($options, 'value') === [$lease->leasable_id]);
        $id = $this->postJson($url, $payload)->assertOk()->json('id');
        DB::table('fee_types')->where('id', $item)->update(['name' => 'Renamed item']);
        $this->assertSame('Light bulb', \App\Models\Maintenance::findOrFail($id)->title);
        DB::table('users')->where('id', $foreignManager->id)->update(['account_permissions' => json_encode(['maintenance.tab', 'maintenance.view', 'maintenance.assign'])]);
        $this->actingAs($foreignManager->fresh());
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertDontSee('Light bulb');
        $this->get(route('admin.maintenance.work-orders.show', $id))->assertForbidden();
        $this->postJson(route('admin.maintenance.work-orders.assign', $id), ['request_key' => (string) Str::uuid(), 'version' => 1, 'contractor_name' => 'Foreign'])->assertForbidden();
    }

    public function test_conversation_creation_uses_selected_catalog_name_and_quantity(): void
    {
        $ticket = $this->source();
        $this->actingAs($this->manager());
        $order = $this->createOrder($ticket, ['fee_type_id' => $this->item(), 'qty' => 4, 'title' => 'Forged name']);
        $this->assertSame('Light bulb', $order->title);
        $this->assertSame(4, $order->qty);
        $this->assertStringContainsString('Quantity: 4', $ticket->messages()->sole()->message);
    }

    public function test_staff_can_be_created_inline_and_assigned_without_leaving_maintenance(): void
    {
        $manager = $this->manager();
        $manager->account_permissions = array_merge($manager->account_permissions, ['staff.create']);
        $manager->save();
        $this->actingAs($manager);
        $managementId = (string) Str::ulid();
        DB::table('user_management')->insert(['id' => $managementId, 'user_id' => $manager->id]);
        DB::table('roles')->insert(['name' => 'Contractor', 'guard_name' => 'web', 'team_id' => $manager->id]);
        $foreignManager = $this->user('agentAdmin');
        $foreignManagementId = (string) Str::ulid();
        DB::table('user_management')->insert(['id' => $foreignManagementId, 'user_id' => $foreignManager->id]);
        DB::table('roles')->insert(['name' => 'Foreign Role', 'guard_name' => 'web', 'team_id' => $foreignManager->id]);
        $order = $this->createOrder($this->source());
        $this->get(route('admin.maintenance.work-orders.show', $order))->assertOk()
            ->assertSee('Assign Contractor')->assertSee('aria-label="Close Assign Contractor"', false)
            ->assertSee('min-h-[calc(100dvh-3rem)]', false)->assertSee('overflow-visible', false)
            ->assertSee('quick-staff-name')->assertSee('Create Staff')->assertSee('Contractor')
            ->assertDontSee('Foreign Role')->assertDontSee('href="'.route('admin.staff.create').'"', false);
        $this->get(route('admin.staff.create'))->assertRedirect(route('admin.staff.index', ['create' => 1]));

        $payload = ['name' => 'New Contractor', 'email' => 'contractor@example.test', 'password' => 'test-password',
            'password_confirmation' => 'test-password', 'role' => 'Contractor', 'verify_email_now' => '1',
            'user_mgnt_id' => $foreignManagementId];
        $this->postJson(route('admin.staff.store'), array_merge($payload, ['role' => 'Foreign Role']))
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $created = $this->postJson(route('admin.staff.store'), $payload)->assertCreated()
            ->assertJsonPath('staff.name', 'New Contractor')->assertJsonPath('staff.management_user_id', $manager->id)
            ->assertJsonMissingPath('staff.password')->assertSessionHas('success');
        $staffId = $created->json('staff.id');
        $this->assertDatabaseHas('staff', ['id' => $staffId, 'user_mgnt_id' => $managementId, 'role' => 'Contractor']);
        $this->postJson(route('admin.staff.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assignOrder($order, ['assigned_staff_id' => $staffId])->assertOk();
        $this->assertSame('New Contractor', $order->fresh()->contractor_name);

        $this->actingAs($this->manager());
        $this->postJson(route('admin.staff.store'), array_merge($payload, ['email' => 'blocked@example.test']))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.test']);
    }

    public function test_list_actions_support_assignment_reassignment_and_completion_with_permissions_and_scope(): void
    {
        $this->actingAs($this->manager());
        $order = $this->createOrder($this->source());
        $staff = $this->staffMember(name: 'List Contractor');
        $index = route('admin.maintenance.work-orders.index');
        $url = route('admin.maintenance.work-orders.assignment', $order);
        $completionUrl = route('admin.maintenance.work-orders.completion', $order);
        $this->get($index)->assertOk()->assertSee('data-assign-maintenance', false)->assertSee('aria-label="Assign Contractor"', false)
            ->assertDontSee('data-complete-maintenance', false);
        $this->getJson($completionUrl)->assertConflict();
        $html = $this->getJson($url)->assertOk()->json('html');
        $this->assertStringContainsString('List Contractor', $html);
        $this->assertStringContainsString('data-maintenance-form="assign"', $html);
        $this->assertStringContainsString('name="version" value="1"', $html);
        $this->assertStringNotContainsString('data-maintenance-form="complete"', $html);
        $this->get($url)->assertRedirect(route('admin.maintenance.work-orders.show', $order));
        $this->assignOrder($order, ['assigned_staff_id' => $staff])->assertOk()->assertSessionHas('success', 'Contractor assigned successfully.');
        $this->get($index)->assertOk()->assertSee('aria-label="Change Contractor"', false);
        $html = $this->getJson($url)->assertOk()->json('html');
        $this->assertStringContainsString('name="version" value="2"', $html);
        $this->assertStringContainsString($staff, $html);
        $this->assignOrder($order, ['assigned_staff_id' => $this->staffMember(name: 'Replacement Contractor')])->assertOk();
        $this->get($index)->assertOk()->assertSee('data-complete-maintenance', false);
        $html = $this->getJson($completionUrl)->assertOk()->json('html');
        $this->assertStringContainsString('data-maintenance-form="complete"', $html);
        $this->assertStringContainsString('name="version" value="3"', $html);
        $this->assertStringContainsString('aria-label="Close Confirm Completion"', $html);
        $this->assertStringContainsString('Cancel', $html);
        $this->assertStringContainsString('data-maintenance-files', $html);
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), ['request_key' => (string) Str::uuid(), 'version' => 2])->assertConflict();
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), ['request_key' => (string) Str::uuid(), 'version' => 3,
            'attachments' => [UploadedFile::fake()->createWithContent('completed.txt', 'Maintenance completed')]])->assertOk()->assertSessionHas('success', 'Maintenance completed successfully.');
        $this->assertSame(1, $order->attachments()->count());
        $this->get($index)->assertOk()->assertDontSee('data-assign-maintenance', false)->assertDontSee('data-complete-maintenance', false);
        $this->getJson($url)->assertConflict();
        $this->getJson($completionUrl)->assertConflict();
        $this->actingAs($this->manager(['tab', 'view']));
        $this->getJson($url)->assertForbidden();
        $this->getJson($completionUrl)->assertForbidden();
        $other = $this->user('agentAdmin');
        DB::table('users')->where('id', $other->id)->update(['account_permissions' => json_encode(['maintenance.tab', 'maintenance.view', 'maintenance.assign'])]);
        $this->actingAs($other->fresh());
        $this->getJson($url)->assertForbidden();
    }

    public function test_list_completion_works_with_complete_permission_without_edit_or_assignment_permissions(): void
    {
        $this->actingAs($this->manager());
        $order = $this->createOrder($this->source());
        $this->assignOrder($order)->assertOk();
        $this->actingAs($this->manager(['tab', 'view', 'complete']));
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertSee('data-maintenance-action-host', false)
            ->assertSee('data-complete-maintenance', false)->assertDontSee('data-assign-maintenance', false)->assertDontSee('data-edit-maintenance', false);
        $completionUrl = route('admin.maintenance.work-orders.completion', $order);
        $this->getJson($completionUrl)->assertOk()->assertJsonStructure(['html']);
        $other = $this->user('agentAdmin');
        DB::table('users')->where('id', $other->id)->update(['account_permissions' => json_encode(['maintenance.tab', 'maintenance.view', 'maintenance.complete'])]);
        $this->actingAs($other->fresh());
        $this->getJson($completionUrl)->assertForbidden();
    }

    public function test_assignment_accepts_only_active_staff_from_the_management_account_and_preserves_history(): void
    {
        $this->actingAs($this->manager());
        $order = $this->createOrder($this->source());
        $showUrl = route('admin.maintenance.work-orders.show', $order);
        $url = route('admin.maintenance.work-orders.assign', $order);
        $this->get($showUrl)->assertOk()->assertSee('No active staff are available.')->assertDontSee('Add New Staff');

        $staff = $this->staffMember(name: 'Approved Technician');
        DB::table('staff')->where('id', $staff)->update(['phone' => '0123456789']);
        $legacyActive = $this->staffMember(name: 'Legacy Active');
        DB::table('staff')->where('id', $legacyActive)->update(['is_active' => 'active']);
        $foreign = $this->staffMember($this->user('agentAdmin')->id, 'Foreign Technician');
        $inactive = $this->staffMember(name: 'Inactive Technician');
        DB::table('staff')->where('id', $inactive)->update(['is_active' => '0']);
        $disabled = $this->staffMember(name: 'Disabled Account');
        DB::table('users')->where('id', DB::table('staff')->where('id', $disabled)->value('user_id'))->update(['status' => 'inactive']);
        $removedManagement = $this->user('agentAdmin')->id;
        $removed = $this->staffMember($removedManagement, 'Removed Management');
        DB::table('user_management')->where('user_id', $removedManagement)->update(['deleted_at' => now()]);

        $this->get($showUrl)->assertOk()->assertViewHas('staffOptions', fn ($options) => array_column($options, 'value') === [$staff, $legacyActive])
            ->assertDontSee('Foreign Technician')->assertDontSee('Inactive Technician')->assertDontSee('Disabled Account')
            ->assertDontSee('name="contractor_name"', false)->assertDontSee('name="contractor_phone"', false);
        $this->postJson($url, ['request_key' => (string) Str::uuid(), 'version' => 1, 'contractor_name' => 'Unregistered contractor'])
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_staff_id');
        foreach ([$foreign, $inactive, $disabled, $removed, (string) Str::ulid()] as $invalid) {
            $this->postJson($url, ['request_key' => (string) Str::uuid(), 'version' => 1, 'assigned_staff_id' => $invalid])
                ->assertUnprocessable()->assertJsonValidationErrors('assigned_staff_id');
        }
        $this->assertSame('pending', $order->fresh()->status);
        $payload = ['request_key' => (string) Str::uuid(), 'version' => 1, 'assigned_staff_id' => $staff,
            'contractor_name' => 'Forged name', 'contractor_phone' => 'Forged phone'];
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('message', 'Staff assigned.');
        $this->postJson($url, $payload)->assertOk();
        $order->refresh();
        $this->assertSame($staff, $order->assigned_staff_id);
        $this->assertSame('Approved Technician', $order->contractor_name);
        $this->assertSame('0123456789', $order->contractor_phone);
        $this->get($showUrl)->assertOk()->assertViewHas('staffPhone', '0123456789');
        DB::table('staff')->where('id', $staff)->update(['phone' => '0198765432']);
        $this->get($showUrl)->assertOk()->assertViewHas('staffPhone', '0198765432');
        DB::table('staff')->where('id', $staff)->update(['phone' => null]);
        $this->get($showUrl)->assertOk()->assertViewHas('staffPhone', null);
        $this->assertSame('maintaining', $order->status);
        $this->assertSame(1, $order->events()->where('action', 'assign')->count());
        $this->assertSame($staff, $order->events()->where('action', 'assign')->sole()->changes['assigned_staff_id']['to']);

        DB::table('staff')->where('id', $staff)->update(['is_active' => '0']);
        $this->postJson($url, ['request_key' => (string) Str::uuid(), 'version' => 2, 'assigned_staff_id' => $staff])
            ->assertUnprocessable()->assertJsonValidationErrors('assigned_staff_id');
        DB::table('staff')->where('id', $staff)->delete();
        $this->assertNull($order->fresh()->assigned_staff_id);
        $this->assertSame('Approved Technician', $order->fresh()->contractor_name);
        $this->get($showUrl)->assertOk()->assertSee('Approved Technician')->assertViewHas('staffPhone', '0123456789');
    }

    public function test_show_highlights_status_timing_without_restarting_progress_on_reassignment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-10 12:00:00', 'Asia/Kuala_Lumpur'));
        $ticket = $this->source();
        $this->actingAs($this->manager());
        $order = $this->createOrder($ticket);
        $url = route('admin.maintenance.work-orders.show', $order);
        Carbon::setTestNow(Carbon::parse('2026-11-13 12:00:00', 'Asia/Kuala_Lumpur'));
        $this->get($url)->assertOk()->assertSee('Awaiting staff assignment')->assertSee('Time Waiting')->assertSee('3 days');
        $this->assignOrder($order)->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-11-16 12:00:00', 'Asia/Kuala_Lumpur'));
        $this->assignOrder($order, ['assigned_staff_id' => $this->staffMember(name: 'Replacement Staff')])->assertOk();
        $page = $this->get($url)->assertOk()->assertSee('Time in Progress')->assertSee('3 days')
            ->assertSee('First assigned 13/11/2026, 12:00')->assertSee('Replacement Staff')
            ->assertSee('agentAdmin')->assertDontSee('User '.$this->managerId);
        $completePattern = '/<form\b[^>]*action="'.preg_quote(route('admin.maintenance.work-orders.complete', $order), '/').'"[^>]*>(.*?)<\/form>/s';
        $this->assertSame(1, preg_match($completePattern, $page->getContent(), $completeForm));
        $this->assertStringContainsString('aria-label="Close Confirm Completion"', $completeForm[1]);
        $this->assertStringContainsString('Cancel', $completeForm[1]);
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), [
            'request_key' => (string) Str::uuid(), 'version' => $order->fresh()->version,
        ])->assertOk();
        $this->getJson(route('admin.maintenance.work-orders.edit', $order))->assertConflict()
            ->assertJsonPath('message', 'Completed or closed maintenance cannot be edited.');
        Carbon::setTestNow(Carbon::parse('2026-11-18 12:00:00', 'Asia/Kuala_Lumpur'));
        $this->get($url)->assertOk()->assertSee('Completed On')->assertSee('16/11/2026, 12:00')
            ->assertDontSee('Time in Progress')->assertDontSee('Edit Maintenance')->assertDontSee('Change Contractor');
    }

    public function test_item_selector_matches_refund_shared_and_managed_owner_catalog_and_disabled_settings(): void
    {
        $shared = $this->item(['user_id' => $this->user('admin')->id, 'is_system' => true, 'name' => 'Shared item']);
        DB::table('owners')->insert(['id' => (string) Str::ulid(), 'user_id' => $this->ownerId, 'agent_id' => $this->managerId]);
        $managed = $this->item(['user_id' => $this->ownerId, 'name' => 'Owner item']);
        $disabled = $this->item(['name' => 'Disabled item']);
        DB::table('settings')->insert(['user_id' => $this->managerId, 'key' => 'fee_types_config', 'value' => json_encode(['item_disabled_item' => ['is_active' => false]]), 'is_active' => true]);
        $this->actingAs($this->manager());
        $lease = $this->lease();
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()
            ->assertViewHas('itemOptions', fn ($options) => array_column($options, 'value') === [$managed, $shared]);
        $payload = $this->payload(['leasable_type' => 'property', 'leasable_id' => $lease->leasable_id, 'qty' => 4, 'fee_type_id' => $disabled]);
        $url = route('admin.maintenance.work-orders.store-from-list');
        $this->postJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('fee_type_id');
        $this->postJson($url, array_merge($payload, ['fee_type_id' => $shared]))->assertOk();
    }

    public function test_edit_loads_existing_evidence_and_can_keep_remove_and_replace_files_without_duplicate_retries(): void
    {
        $this->actingAs($this->manager());
        $ticket = $this->source();
        $order = $this->createOrder($ticket, ['attachments' => [
            UploadedFile::fake()->createWithContent('remove.txt', 'old evidence'),
            UploadedFile::fake()->createWithContent('keep.txt', 'retained evidence'),
        ]]);
        $removed = $order->attachments()->where('filename', 'remove.txt')->sole();
        $kept = $order->attachments()->where('filename', 'keep.txt')->sole();
        $html = $this->getJson(route('admin.maintenance.work-orders.edit', $order))->assertOk()->json('html');
        foreach (['remove.txt', 'keep.txt', 'remove_attachment_ids[]', 'data-maintenance-files', route('maintenance.evidence', $removed)] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $payload = $this->payload(['version' => 1, 'remove_attachment_ids' => [$removed->id],
            'attachments' => [UploadedFile::fake()->createWithContent('new.txt', 'new evidence')]]);
        $url = route('admin.maintenance.work-orders.update', $order);
        $this->putJson($url, $payload)->assertOk();
        $this->putJson($url, $payload)->assertOk();
        $this->assertSame(['keep.txt', 'new.txt'], $order->attachments()->orderBy('filename')->pluck('filename')->all());
        Storage::disk('local')->assertMissing($removed->path);
        Storage::disk('local')->assertExists($kept->path);
        $this->assertSame(2, $order->fresh()->version);
        $this->assertSame(1, $order->events()->where('action', 'edit')->count());
        $changes = $order->events()->where('action', 'edit')->sole()->changes;
        $this->assertSame('remove.txt', $changes['evidence_removed']['from']);
        $this->assertSame('new.txt', $changes['evidence_added']['to']);
        $this->get(route('maintenance.evidence', $removed->id))->assertNotFound();
        $this->assertSame(1, $ticket->messages()->count());
    }

    public function test_edit_evidence_enforces_ownership_and_combined_saved_and_new_file_limits(): void
    {
        $this->actingAs($this->manager());
        $order = $this->createOrder($this->source(), ['attachments' => [
            UploadedFile::fake()->createWithContent('one.txt', str_repeat('x', 600)),
            UploadedFile::fake()->createWithContent('two.txt', str_repeat('x', 600)),
        ]]);
        $foreign = $this->createOrder($this->source(), ['attachments' => [UploadedFile::fake()->createWithContent('foreign.txt', 'foreign')]])->attachments()->sole();
        $url = route('admin.maintenance.work-orders.update', $order);
        $this->putJson($url, $this->payload(['version' => 1, 'remove_attachment_ids' => [$foreign->id]]))
            ->assertUnprocessable()->assertJsonValidationErrors('remove_attachment_ids');
        config(['customer_service.max_files' => 2]);
        $payload = $this->payload(['version' => 1, 'attachments' => [UploadedFile::fake()->createWithContent('extra.txt', str_repeat('x', 800))]]);
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('attachments');
        config(['customer_service.max_total_kb' => 1]);
        $payload['remove_attachment_ids'] = [$order->attachments()->first()->id];
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->assertSame(2, $order->attachments()->count());
        $this->assertSame(1, $order->fresh()->version);
        Storage::disk('local')->assertExists($foreign->path);
    }

    public function test_failed_edit_upload_retains_original_evidence_and_rolls_back_all_changes(): void
    {
        $this->actingAs($this->manager());
        $order = $this->createOrder($this->source(), ['attachments' => [UploadedFile::fake()->createWithContent('original.txt', 'original')]]);
        $original = $order->attachments()->sole();
        $this->mock(\App\Services\FileService::class, function ($mock) {
            $mock->shouldReceive('upload')->twice()->andReturnUsing(function () {
                static $attempt = 0;
                if (++$attempt === 2) throw new \RuntimeException('Disk unavailable');
                Storage::disk('local')->put('maintenance-test/edit-first.txt', 'new');
                return 'maintenance-test/edit-first.txt';
            });
        });
        $payload = $this->payload(['version' => 1, 'desc' => 'Updated with replacement files', 'remove_attachment_ids' => [$original->id],
            'attachments' => [UploadedFile::fake()->createWithContent('first.txt', 'first'), UploadedFile::fake()->createWithContent('second.txt', 'second')]]);
        $url = route('admin.maintenance.work-orders.update', $order);
        $this->putJson($url, $payload)->assertStatus(500);
        $this->assertSame($original->id, $order->attachments()->sole()->id);
        $this->assertSame('Verified with tenant', $order->fresh()->desc);
        $this->assertSame(1, $order->fresh()->version);
        $this->assertSame(1, $order->events()->count());
        Storage::disk('local')->assertExists($original->path);
        Storage::disk('local')->assertMissing('maintenance-test/edit-first.txt');
        unset($payload['attachments']);
        $this->putJson($url, $payload)->assertOk();
        $this->assertSame(0, $order->attachments()->count());
        Storage::disk('local')->assertMissing($original->path);
    }

    public function test_edit_records_changes_preserves_chat_history_and_rejects_stale_or_completed_updates(): void
    {
        $this->actingAs($this->manager());
        $ticket = $this->source();
        $order = $this->createOrder($ticket);
        $originalNotice = $ticket->messages()->sole()->message;
        $url = route('admin.maintenance.work-orders.update', $order);
        $this->getJson(route('admin.maintenance.work-orders.edit', $order))->assertOk()->assertJsonStructure(['html']);
        $payload = $this->payload(['version' => 1, 'desc' => 'Corrected problem description', 'lease_id' => 'forged', 'status' => 'completed']);
        $this->put($url, $payload)->assertRedirect(route('admin.maintenance.work-orders.show', $order))->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('Broken light', $order->title);
        $this->assertSame('Corrected problem description', $order->desc);
        $this->assertSame($ticket->lease_id, $order->lease_id);
        $this->assertSame('pending', $order->status);
        $this->assertSame(2, $order->version);
        $this->assertSame($originalNotice, $ticket->messages()->sole()->message);
        $this->assertDatabaseHas('maintenance_events', ['maintenance_id' => $order->id, 'action' => 'edit', 'actor_id' => $this->managerId]);
        $this->from('/previous')->put($url, $this->payload(['version' => 1]))->assertRedirect('/previous')->assertSessionHas('error');
        $this->actingAs($this->manager(['tab', 'view']));
        $this->putJson($url, $this->payload(['version' => 2]))->assertForbidden();
        $this->actingAs($this->manager());
        $this->assignOrder($order)->assertOk();
        $this->postJson(route('admin.maintenance.work-orders.complete', $order), ['request_key' => (string) Str::uuid(), 'version' => 3])->assertOk();
        $this->from('/previous')->put($url, $this->payload(['version' => 4]))->assertRedirect('/previous')->assertSessionHas('error');
        $this->assertSame('Broken light', $order->fresh()->title);
    }

    public function test_edit_modal_has_close_and_cancel_controls_and_keeps_the_lease_readonly(): void
    {
        $this->actingAs($this->manager());
        $order = $this->createOrder($this->source());
        $editUrl = route('admin.maintenance.work-orders.edit', $order);
        $this->get($editUrl)->assertRedirect(route('admin.maintenance.work-orders.show', $order));
        $html = $this->getJson($editUrl)->assertOk()->json('html');
        $this->assertStringContainsString('aria-label="Close Edit Maintenance"', $html);
        $this->assertStringContainsString('min-h-[calc(100dvh-3rem)]', $html);
        $this->assertStringContainsString('id="maintenance-edit-lease"', $html);
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringNotContainsString('Recorded location', $html);
        $this->assertStringContainsString('name="_method" value="PUT"', $html);
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertSee('data-edit-maintenance', false)->assertSee('data-maintenance-edit-host', false);
        $this->get(route('admin.maintenance.work-orders.show', $order))->assertOk()->assertSee('data-edit-maintenance', false)->assertSee('data-maintenance-edit-host', false);

        $formPattern = '/<form\b[^>]*action="'.preg_quote(route('admin.maintenance.work-orders.update', $order), '/').'"[^>]*>(.*?)<\/form>/s';
        $this->assertSame(1, preg_match($formPattern, $html, $form));
        $this->assertSame(1, preg_match('/<button\b([^>]*)>\s*<span>\s*Cancel\s*<\/span>\s*<\/button>/', $form[1], $matches));
        $attributes = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('$dispatch(\'close-modal\', \'edit-maintenance\')', $attributes);
        $this->assertStringNotContainsString('window.location', $attributes);
        $this->assertStringNotContainsString('@js(', $attributes);
    }

    public function test_edit_preserves_the_recorded_item_and_allows_quantity_changes_even_if_catalog_item_changes(): void
    {
        $this->actingAs($this->manager());
        $item = $this->item();
        $order = $this->createOrder($this->source(), ['fee_type_id' => $item, 'qty' => 2]);
        $url = route('admin.maintenance.work-orders.update', $order);
        $editUrl = route('admin.maintenance.work-orders.edit', $order);
        $html = $this->getJson($editUrl)->assertOk()->json('html');
        $this->assertStringContainsString('id="maintenance-edit-item"', $html);
        $this->assertStringContainsString('Light bulb', $html);
        $this->assertStringContainsString('readonly', $html);
        $this->assertStringNotContainsString('name="fee_type_id"', $html);
        $this->assertStringNotContainsString('name="title"', $html);
        $this->assertStringContainsString('Update Maintenance', $html);
        $this->assertStringNotContainsString('Add Item', $html);

        $manager = User::findOrFail($this->managerId);
        $manager->account_permissions = array_merge($manager->account_permissions, ['fee types.create']);
        $manager->save();
        $this->actingAs($manager);
        $html = $this->getJson($editUrl)->assertOk()->json('html');
        $this->assertStringNotContainsString('Add Item', $html);
        $this->assertStringNotContainsString('open-fee-type-modal', $html);
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertSee('Add Item');

        $payload = $this->payload(['version' => 1, 'qty' => 3]);
        unset($payload['title']);
        $replacement = $this->item(['name' => 'Air conditioner']);
        $this->putJson($url, array_merge($payload, ['fee_type_id' => $replacement]))->assertUnprocessable()->assertJsonValidationErrors('fee_type_id');
        $this->putJson($url, array_merge($payload, ['title' => 'Air conditioner']))->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->putJson($url, array_merge($payload, ['fee_type_id' => null]))->assertUnprocessable()->assertJsonValidationErrors('fee_type_id');
        $this->assertSame(2, $order->fresh()->qty);
        DB::table('fee_types')->where('id', $item)->update(['name' => 'Renamed catalog item', 'is_active' => false]);
        $this->putJson($url, $payload)->assertOk()->assertJsonPath('message', 'Maintenance updated successfully.')->assertSessionHas('success');
        $this->putJson($url, array_merge($payload, ['request_key' => (string) Str::uuid()]))->assertConflict()
            ->assertJsonPath('message', 'This work order changed. Refresh its details before confirming.');
        $this->assertSame($item, $order->fresh()->fee_type_id);
        $this->assertSame('Light bulb', $order->fresh()->title);
        $this->assertSame(3, $order->fresh()->qty);
        $this->assertSame(['from' => 2, 'to' => 3], $order->events()->where('action', 'edit')->sole()->changes['qty']);
        $this->get(route('admin.maintenance.work-orders.show', $order))->assertOk()->assertSee('Light bulb(QTY:3)')->assertDontSee('Renamed catalog item');

        $foreign = $this->item(['user_id' => $this->user('agentAdmin')->id]);
        $this->putJson($url, array_merge($payload, ['version' => 2, 'fee_type_id' => $foreign]))
            ->assertUnprocessable()->assertJsonValidationErrors('fee_type_id');
        $this->assertSame($item, $order->fresh()->fee_type_id);
    }

    public function test_maintenance_list_combines_search_and_status_filters(): void
    {
        $this->actingAs($this->manager());
        $first = $this->createOrder($this->source(), ['title' => 'Broken kitchen light']);
        $second = $this->createOrder($this->source(), ['title' => 'Bathroom light']);
        $this->assignOrder($second)->assertOk();
        $url = route('admin.maintenance.work-orders.index');
        $this->get($url.'?search=light&status=maintaining')->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->modelKeys() === [$second->id]);
        $this->get($url.'?search=light&status=pending')->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->modelKeys() === [$first->id]);
        $this->get($url.'?search=unmatched&status=pending')->assertOk()
            ->assertSee('No records found');
        $this->getJson($url.'?status=invalid')->assertUnprocessable();
    }

    public function test_maintenance_has_separate_navigation_and_requires_work_order_view_permission(): void
    {
        $this->actingAs($this->manager());
        $ordersUrl = route('admin.maintenance.work-orders.index');
        $chatUrl = route('admin.maintenance.index');
        $page = $this->get($ordersUrl)->assertOk()->assertViewIs('adminSide.maintenance.index');
        $page->assertSee('Track manual maintenance and work orders raised from service requests.')
            ->assertSee('Search by item, reference or property...');
        foreach ([$ordersUrl => true, $chatUrl => false] as $url => $active) {
            preg_match_all('/<a\b[^>]*href="'.preg_quote($url, '/').'"[^>]*>/', $page->getContent(), $links);
            $navigationLinks = array_values(array_filter($links[0], fn ($link) => str_contains($link, 'border-b-2') || str_contains($link, 'border-l-4')));
            $this->assertCount(2, $navigationLinks, 'Desktop and mobile navigation links must both be present.');
            foreach ($navigationLinks as $link) {
                $this->assertSame($active, str_contains($link, 'border-indigo-400'));
            }
        }
        $this->get($chatUrl)->assertOk()->assertSee('data-customer-service', false)
            ->assertDontSee('maintenanceTab')->assertDontSee('title="Maintenance work orders"', false);

        $this->actingAs($this->manager(['tab']));
        $this->get($chatUrl)->assertOk()->assertDontSee('href="'.$ordersUrl.'"', false);
        $this->get($ordersUrl)->assertForbidden();
        $this->actingAs($this->tenantUser);
        $this->get(route('tenants.customerService.index'))->assertOk()->assertDontSee('href="'.$ordersUrl.'"', false);
        $this->get($ordersUrl)->assertForbidden();
    }

    public function test_maintenance_permissions_are_independent_from_service_request_permissions(): void
    {
        $this->actingAs($this->manager());
        $order = $this->createOrder($this->source());
        $indexUrl = route('admin.maintenance.work-orders.index');
        $editUrl = route('admin.maintenance.work-orders.edit', $order);
        $updateUrl = route('admin.maintenance.work-orders.update', $order);

        DB::table('users')->where('id', $this->managerId)->update(['account_permissions' => json_encode([
            'service request.tab', 'service request.reply', 'service request.edit',
        ])]);
        $this->actingAs(User::findOrFail($this->managerId));
        $this->get(route('admin.maintenance.index'))->assertOk()->assertDontSee('href="'.$indexUrl.'"', false);
        $this->get($indexUrl)->assertForbidden();
        $this->get($editUrl)->assertForbidden();
        $this->putJson($updateUrl, $this->payload(['version' => 1]))->assertForbidden();

        DB::table('users')->where('id', $this->managerId)->update(['account_permissions' => json_encode([
            'maintenance.tab', 'maintenance.view',
        ])]);
        $this->actingAs(User::findOrFail($this->managerId));
        $this->get($indexUrl)->assertOk()->assertDontSee('aria-label="Edit maintenance"', false);
        $this->get($editUrl)->assertForbidden();
        $this->putJson($updateUrl, $this->payload(['version' => 1]))->assertForbidden();

        DB::table('users')->where('id', $this->managerId)->update(['account_permissions' => json_encode([
            'maintenance.tab', 'maintenance.view', 'maintenance.edit',
        ])]);
        $this->actingAs(User::findOrFail($this->managerId));
        $this->get($indexUrl)->assertOk()->assertSee('aria-label="Edit maintenance"', false);
        $this->getJson($editUrl)->assertOk();
        $this->put($updateUrl, $this->payload(['version' => 1, 'desc' => 'Maintenance-only edit']))->assertRedirect();
        $this->assertSame('Broken light', $order->fresh()->title);
        $this->assertSame('Maintenance-only edit', $order->fresh()->desc);
        $this->get(route('admin.maintenance.index'))->assertForbidden();
        $this->assignOrder($order)->assertForbidden();
        $this->postJson(route('admin.maintenance.work-orders.store-from-list'), [])->assertForbidden();
    }

    public function test_pages_and_incremental_maintenance_notices_preserve_read_rules(): void
    {
        $ticket=$this->source(); $this->actingAs($this->manager());
        $this->get(route('admin.maintenance.index',['ticket'=>$ticket->id]))->assertOk()->assertSee('Create Maintenance')
            ->assertDontSee('title="Maintenance work orders"', false)->assertDontSee('Customer Service Conversations');
        $order=$this->createOrder($ticket);
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertSee($order->title);
        $this->get(route('admin.maintenance.work-orders.show',$order))->assertOk()->assertSee('Assign Contractor');
        $this->actingAs($this->tenantUser);
        $initial=$this->getJson(route('tenants.customerService.show',$ticket))->assertOk();
        $cursor=$initial->json('cursor');
        $this->actingAs($this->manager()); $this->assignOrder($order)->assertOk();
        $this->actingAs($this->tenantUser);
        $sync=$this->getJson(route('tenants.customerService.index',['sync'=>1,'ticket'=>$ticket->id,'cursor'=>$cursor]))->assertOk()
            ->assertJsonPath('ticket.status','maintaining')->assertJsonCount(1, 'messages');
        $this->assertStringContainsString('Maintenance assigned', $sync->json('messages.0.text'));
        $this->assertStringContainsString('Reference: '.$order->maintenance_number, $sync->json('messages.0.text'));
        $this->assertStringNotContainsString('Lease:', $sync->json('messages.0.text'));
        $this->assertSame(2,app(TenantCustomerService::class)->unreadCount($ticket,$this->tenantUser));
        $this->getJson(route('tenants.customerService.index',['sync'=>1,'ticket'=>$ticket->id,'cursor'=>$sync->json('cursor')]))->assertOk()->assertJsonCount(0, 'messages');
        // Optional local browser QA fixture: exports only this isolated in-memory test DB.
        if ($path = getenv('MAINTENANCE_PREVIEW_DB')) {
            DB::statement('VACUUM INTO '.DB::getPdo()->quote($path));
        }
    }

}
