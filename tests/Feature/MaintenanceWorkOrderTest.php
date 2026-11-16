<?php

namespace Tests\Feature;

use App\Models\{Lease, Property, Room, Ticket, TicketMsg, Unit, User};
use App\Services\TenantCustomerService;
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

    private function manager(array $permissions = ['tab','view','create','assign','complete']): User
    {
        DB::table('users')->where('id', $this->managerId)->update(['account_permissions' => json_encode(array_map(fn ($p) => 'maintenance.'.$p, $permissions))]);
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
    private function assignOrder($order, array $extra = [])
    {
        return $this->postJson(route('admin.maintenance.work-orders.assign', $order), array_merge([
            'request_key' => (string) Str::uuid(), 'version' => $order->fresh()->version,
            'contractor_name' => 'External contractor', 'contractor_phone' => 'private-phone',
        ], $extra));
    }

    public function test_full_flow_multiple_items_retry_reassignment_and_tenant_evidence(): void
    {
        $ticket = $this->source();
        $this->actingAs($this->manager());
        $payload = $this->payload();
        $url = route('admin.maintenance.work-orders.store', $ticket);
        $id = $this->postJson($url, $payload)->assertOk()->json('id');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('id', $id);
        $this->postJson($url, array_merge($payload, ['title'=>'Changed payload']))->assertConflict();
        $order = \App\Models\Maintenance::findOrFail($id);
        $this->createOrder($ticket);
        $this->assertSame(2, $ticket->messages()->where('sender_type','system')->count());
        $this->assertSame('maintenance', $ticket->fresh()->category);
        $this->assertSame('pending', $ticket->fresh()->status);
        $this->assertSame(2, \App\Models\Maintenance::count());
        $completeUrl = route('admin.maintenance.work-orders.complete', $order);
        $this->postJson($completeUrl, ['request_key'=>(string) Str::uuid(), 'version'=>1])->assertConflict();
        $assignment = ['request_key'=>(string) Str::uuid(), 'version'=>1];
        $this->assignOrder($order, $assignment)->assertOk();
        $this->assignOrder($order, $assignment)->assertOk();
        $this->assignOrder($order, ['contractor_name'=>'Replacement'])->assertOk();
        $this->assertSame(3, $ticket->messages()->where('sender_type','system')->count());
        $this->assertSame(3, $order->fresh()->version);
        $this->assignOrder($order, ['version'=>2])->assertConflict();
        $completion = ['request_key'=>(string) Str::uuid(), 'version'=>3, 'attachments'=>[UploadedFile::fake()->createWithContent('finished.txt','Completed repair')]];
        $this->postJson($completeUrl, $completion)->assertOk();
        $this->postJson($completeUrl, $completion)->assertOk();
        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame($this->managerId, $order->completed_by);
        $this->assertNotNull($order->completed_at);
        $this->assertSame(4, $ticket->messages()->where('sender_type','system')->count());
        $this->assertSame(4, $order->events()->count());
        $this->assignOrder($order)->assertConflict();
        $this->postJson($completeUrl, ['request_key'=>(string) Str::uuid(),'version'=>4])->assertConflict();
        $file = $order->attachments()->sole();
        $this->actingAs($this->tenantUser);
        $data = $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()->assertDontSee('private-phone')->assertDontSee('Replacement');
        $this->assertSame('completed', $data->json('maintenance_cards.0.status'));
        $this->assertCount(1, $data->json('maintenance_cards.0.attachments'));
        $this->get(route('maintenance.evidence', $file))->assertOk();
        $this->get(route('maintenance.evidence', [$file, 'download'=>1]))->assertOk();
        $this->assertSame(4, app(TenantCustomerService::class)->unreadCount($ticket, $this->tenantUser));
        $ids = $ticket->messages()->pluck('id')->all();
        $this->postJson(route('tenants.customerService.markRead',$ticket),['message_ids'=>$ids])->assertOk();
        $this->assertSame(0, app(TenantCustomerService::class)->unreadCount($ticket, $this->tenantUser));
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
        $this->assertNull($order->fresh()->completed_by);
        $this->assertSame(0,$order->attachments()->count());
        Storage::disk('local')->assertMissing('maintenance-test/first.txt');
        $this->assertSame(2,$ticket->messages()->count());
        $this->postJson($url,['request_key'=>$key,'version'=>2])->assertOk();
        $this->assertSame('completed',$order->fresh()->status);
        $this->assertSame(0,$order->attachments()->count());
        $this->actingAs($this->tenantUser);
        $this->getJson(route('tenants.customerService.show',$ticket))->assertOk()->assertJsonPath('maintenance_cards.0.attachments',[]);
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

    public function test_staff_work_order_permissions_are_independent_of_chat_reply_and_record_real_actor(): void
    {
        $ticket=$this->source(); $staff=$this->user('staff'); $mgmt=(string) Str::ulid();
        DB::table('user_management')->insert(['id'=>$mgmt,'user_id'=>$this->managerId]);
        DB::table('staff')->insert(['id'=>(string) Str::ulid(),'user_id'=>$staff->id,'user_mgnt_id'=>$mgmt]);
        $role=DB::table('roles')->insertGetId(['name'=>'Maintenance staff','guard_name'=>'web','team_id'=>$this->managerId]);
        DB::table('model_has_roles')->insert(['role_id'=>$role,'model_type'=>User::class,'model_id'=>$staff->id,'team_id'=>$this->managerId]);
        foreach(['tab','view','create','assign','complete'] as $ability) {
            $pid=DB::table('permissions')->insertGetId(['name'=>'maintenance.'.$ability,'guard_name'=>'web']);
            DB::table('role_has_permissions')->insert(['role_id'=>$role,'permission_id'=>$pid]);
        }
        $this->actingAs($staff);
        $this->assertFalse(app(TenantCustomerService::class)->canReply($ticket,$staff));
        $order=$this->createOrder($ticket); $this->assignOrder($order)->assertOk();
        $this->postJson(route('admin.maintenance.work-orders.complete',$order),['request_key'=>(string) Str::uuid(),'version'=>2])->assertOk();
        $this->assertSame($staff->id,$order->fresh()->created_by);
        $this->assertSame($staff->id,$order->fresh()->assigned_by);
        $this->assertSame($staff->id,$order->fresh()->completed_by);
        $this->assertSame([$staff->id],$order->events()->pluck('actor_id')->unique()->values()->all());
        $this->assertDatabaseHas('audit_logs',['auditable_id'=>$order->id,'user_id'=>$staff->id]);
    }
    public function test_pages_and_incremental_cards_preserve_read_rules(): void
    {
        $ticket=$this->source(); $this->actingAs($this->manager());
        $this->get(route('admin.maintenance.index',['ticket'=>$ticket->id]))->assertOk()->assertSee('Create Maintenance')->assertSee('Maintenance work orders');
        $order=$this->createOrder($ticket);
        $this->get(route('admin.maintenance.work-orders.index'))->assertOk()->assertSee($order->title);
        $this->get(route('admin.maintenance.work-orders.show',$order))->assertOk()->assertSee('Assign external Contractor');
        $this->actingAs($this->tenantUser);
        $initial=$this->getJson(route('tenants.customerService.show',$ticket))->assertOk();
        $cursor=$initial->json('cursor');
        $this->actingAs($this->manager()); $this->assignOrder($order)->assertOk();
        $this->actingAs($this->tenantUser);
        $sync=$this->getJson(route('tenants.customerService.index',['sync'=>1,'ticket'=>$ticket->id,'cursor'=>$cursor]))->assertOk()
            ->assertJsonPath('maintenance_cards.0.status','maintaining');
        $this->assertSame(2,app(TenantCustomerService::class)->unreadCount($ticket,$this->tenantUser));
        $this->getJson(route('tenants.customerService.index',['sync'=>1,'ticket'=>$ticket->id,'cursor'=>$sync->json('cursor')]))->assertOk()->assertJsonPath('maintenance_cards',null);
        // Optional local browser QA fixture: exports only this isolated in-memory test DB.
        if ($path = getenv('MAINTENANCE_PREVIEW_DB')) {
            DB::statement('VACUUM INTO '.DB::getPdo()->quote($path));
        }
    }

}
