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

class TenantCustomerServiceTest extends TestCase
{
    private User $tenantUser;
    private string $tenantId;
    private string $ownerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach (['users', 'tenants', 'properties', 'units', 'rooms', 'leases', 'tickets', 'ticket_msgs', 'audit_logs'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->string('id')->primary();
                $t->timestamps();
                $columns = match ($table) {
                    'users' => ['name', 'email', 'password', 'role', 'email_verified_at'],
                    'tenants' => ['user_id'],
                    'properties' => ['name', 'owner_id'],
                    'units' => ['unit_no', 'owner_id', 'property_id'],
                    'rooms' => ['room_no', 'owner_id', 'unit_id'],
                    'leases' => ['tenant_id', 'leasable_type', 'leasable_id', 'status', 'start_date', 'end_date', 'checked_out_at', 'agreement_ended_at'],
                    'tickets' => ['sender_id', 'receive_id', 'category', 'subject', 'status'],
                    'ticket_msgs' => ['ticket_id', 'sender_type', 'message'],
                    'audit_logs' => ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'],
                };
                foreach ($columns as $column) $t->text($column)->nullable();
                if ($table === 'leases') $t->boolean('is_current')->default(true);
            });
        }
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); });
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->string('team_id')->nullable(); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('user_management', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->softDeletes(); });
        Schema::create('invoices', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->string('context'); $t->string('status'); $t->timestamps(); $t->softDeletes(); });
        Storage::fake('local');
        $this->tenantUser = $this->user('tenant');
        $this->ownerId = $this->user('owner')->id;
        $this->tenantId = (string) Str::ulid();
        DB::table('tenants')->insert(['id' => $this->tenantId, 'user_id' => $this->tenantUser->id]);
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
        DB::table('properties')->insert(['id' => $property, 'name' => 'Test Property', 'owner_id' => $this->ownerId]);
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

    private function batch(array $extra = []): array
    {
        return array_merge(['category' => 'QNA', 'subject' => 'Question', 'items' => [['type' => 'text', 'text' => 'Hello']]], $extra);
    }

    public function test_subject_only_creates_empty_chat_and_ignores_client_category_sender_and_receiver(): void
    {
        $this->lease();
        $response = $this->postJson(route('tenants.customerService.store'), ['subject' => 'A new complaint'])->assertCreated()->assertJsonCount(0, 'messages')->assertJsonPath('ticket.status', 'pending');
        $ticket = Ticket::findOrFail($response->json('ticket.id'));
        $this->assertSame($this->tenantUser->id, $ticket->sender_id);
        $this->assertSame($this->ownerId, $ticket->receive_id);
        $this->assertSame('complain', $ticket->category);
        $this->assertSame(0, TicketMsg::count());
        $this->postJson(route('tenants.customerService.store'), ['subject' => 'Another', 'category' => 'QNA', 'sender_id' => $this->ownerId, 'receive_id' => $this->tenantUser->id, 'items' => [['type' => 'text', 'text' => 'Ignored']]])->assertCreated();
        $this->assertSame(0, TicketMsg::count());
        $this->assertSame(2, Ticket::where('category', 'complain')->where('receive_id', $this->ownerId)->where('sender_id', $this->tenantUser->id)->count());
    }

    public function test_no_lease_blocks_creation_but_allows_reply_without_resetting_status(): void
    {
        $this->postJson(route('tenants.customerService.store'), $this->batch())->assertUnprocessable()->assertJsonValidationErrors('subject');
        $ticket = $this->ticket('in_progress');
        $this->postJson(route('tenants.customerService.reply', $ticket), $this->batch())->assertOk();
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->assertCount(1, $ticket->messages);
    }

    public function test_multiple_leases_allow_only_a_unique_owner_and_do_not_trust_a_client_lease_choice(): void
    {
        $first = $this->lease();
        $second = $this->lease();
        $this->postJson(route('tenants.customerService.store'), ['subject' => 'Same owner'])->assertCreated();
        DB::table('properties')->where('id', $second->leasable_id)->update(['owner_id' => $this->user('owner')->id]);
        $this->postJson(route('tenants.customerService.store'), ['subject' => 'Conflict', 'lease_id' => $first->id, 'receive_id' => $this->ownerId])->assertUnprocessable()->assertJsonValidationErrors('subject');
        $this->getJson(route('tenants.customerService.index'))->assertOk()->assertJsonPath('creation_reason', 'Your valid leases have different owners. The complaint recipient cannot be determined. Please contact your property manager.');
        DB::table('properties')->where('id', $second->leasable_id)->update(['owner_id' => null]);
        $this->postJson(route('tenants.customerService.store'), ['subject' => 'Missing owner'])->assertUnprocessable();
        $this->assertSame(1, Ticket::count());
    }

    public function test_valid_lease_criteria_use_project_timezone_and_inclusive_dates(): void
    {
        config(['app.timezone' => 'Asia/Kuala_Lumpur']);
        Carbon::setTestNow(Carbon::parse('2026-10-08 17:00:00', 'UTC'));
        foreach (['New', 'Renew', 'active'] as $status) $this->lease(['status' => $status, 'start_date' => '2026-10-09', 'end_date' => '2026-10-09']);
        foreach ([['is_current' => false], ['status' => 'End'], ['checked_out_at' => '2026-10-09'], ['agreement_ended_at' => '2026-10-09'], ['start_date' => '2026-10-10'], ['end_date' => '2026-10-08']] as $invalid) {
            $this->lease(array_merge(['start_date' => '2026-10-09', 'end_date' => '2026-10-09'], $invalid));
        }
        $this->assertSame(3, app(TenantCustomerService::class)->validLeases($this->tenantUser)->count());
    }

    public function test_owner_resolution_uses_unit_owner_for_units_and_rooms(): void
    {
        $lease = $this->lease();
        $unitOwner = $this->user('owner')->id;
        $unitId = (string) Str::ulid();
        $roomId = (string) Str::ulid();
        DB::table('units')->insert(['id' => $unitId, 'property_id' => $lease->leasable_id, 'owner_id' => $unitOwner]);
        DB::table('rooms')->insert(['id' => $roomId, 'unit_id' => $unitId, 'owner_id' => $this->ownerId]);
        $service = app(TenantCustomerService::class);
        $this->assertSame($this->ownerId, $service->ownerId($lease));
        $this->assertSame($unitOwner, $service->ownerId($this->lease(['leasable_type' => Unit::class, 'leasable_id' => $unitId])));
        $this->assertSame($unitOwner, $service->ownerId($this->lease(['leasable_type' => Room::class, 'leasable_id' => $roomId])));
    }

    public function test_all_closed_statuses_block_replies(): void
    {
        foreach (['done', 'complete', 'archive', 'closed'] as $status) {
            $ticket = $this->ticket($status);
            $this->postJson(route('tenants.customerService.reply', $ticket), $this->batch())->assertForbidden();
            $this->assertCount(0, $ticket->messages);
            $this->assertSame($status, $ticket->fresh()->status);
        }
    }

    public function test_ticket_and_attachment_authorization_and_legacy_routes(): void
    {
        $ticket = $this->ticket('pending', $this->user('tenant')->id);
        $this->getJson(route('tenants.customerService.show', $ticket))->assertForbidden();
        $this->postJson(route('tenants.customerService.reply', $ticket), $this->batch())->assertForbidden();
        $message = new TicketMsg(['ticket_id' => $ticket->id, 'message' => 'ticket-attachments/'.$ticket->id.'/'.Str::uuid().'.txt', 'sender_type' => 'tenant']);
        $message->saveQuietly();
        Storage::disk('local')->put($message->message, 'secret');
        $this->getJson(route('tenants.customerService.attachment', [$ticket, $message]))->assertForbidden();
        $own = $this->ticket();
        $this->getJson(route('tenants.customerService.attachment', [$own, $message]))->assertNotFound();
        $this->getJson(route('admin.customerService.index'))->assertForbidden();
        $this->getJson(route('admin.customerService.show', $ticket))->assertForbidden();
        $this->postJson(route('admin.customerService.store'), ['category' => 'QNA', 'subject' => 'X', 'message' => 'X'])->assertForbidden();
        $this->patchJson(route('admin.customerService.update', $ticket), ['message' => 'X'])->assertForbidden();
        $this->getJson(route('admin.customerService.newMessages', $ticket))->assertForbidden();
        $this->postJson(route('admin.customerService.grab', $ticket))->assertForbidden();
        $this->postJson(route('admin.customerService.close', $ticket))->assertForbidden();
    }

    public function test_invalid_files_limits_and_empty_messages_leave_no_partial_reply(): void
    {
        $ticket = $this->ticket();
        $url = route('tenants.customerService.reply', $ticket);
        $bad = UploadedFile::fake()->createWithContent('fake.jpg', 'This is text, not an image');
        $this->postJson($url, $this->batch(['items' => [['type' => 'text', 'text' => 'Hello'], ['type' => 'file', 'file' => $bad]]]))->assertUnprocessable();
        $this->assertSame(0, TicketMsg::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->postJson($url, $this->batch(['items' => [['type' => 'text', 'text' => '  ']]]))->assertUnprocessable();
        $this->postJson($url, $this->batch(['items' => array_map(fn ($i) => ['type' => 'file', 'file' => UploadedFile::fake()->createWithContent("file$i.txt", 'text')], range(1, 11))]))->assertUnprocessable();
        config(['customer_service.types.txt.max_kb' => 1]);
        $this->postJson($url, $this->batch(['items' => [['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('big.txt', str_repeat('a', 1025))]]]))->assertUnprocessable();
        config(['customer_service.types.txt.max_kb' => 10240, 'customer_service.max_total_kb' => 1]);
        $this->postJson($url, $this->batch(['items' => [['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('one.txt', str_repeat('a', 700))], ['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('two.txt', str_repeat('b', 700))]]]))->assertUnprocessable();
        $this->assertSame(0, TicketMsg::count());
    }

    public function test_show_page_and_json_history_preserve_order_legacy_text_and_closed_state(): void
    {
        $ticket = $this->ticket('closed');
        $later = new TicketMsg(['ticket_id' => $ticket->id, 'sender_type' => 'tenant', 'message' => 'Later tenant message']);
        $later->created_at = now();
        $later->saveQuietly();
        $earlier = new TicketMsg(['ticket_id' => $ticket->id, 'sender_type' => 'owner', 'message' => '<script>Legacy plain text</script>']);
        $earlier->created_at = now()->subMinute();
        $earlier->saveQuietly();
        $this->get(route('tenants.customerService.show', $ticket))->assertOk()->assertSee('Back to Customer Service')->assertSee('data-ticket-chat', false)->assertSee('data-can-send="false"', false)->assertSee('<script>Legacy plain text</script>')->assertDontSee('data-chat-modal', false);
        $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()->assertJsonPath('ticket.closed', true)->assertJsonPath('messages.0.text', '<script>Legacy plain text</script>')->assertJsonPath('messages.0.own', false)->assertJsonPath('messages.1.own', true);
    }

    public function test_index_uses_create_component_and_show_links_and_searches_own_subjects(): void
    {
        $this->get(route('tenants.customerService.index'))->assertOk()->assertSee('no valid lease')->assertSee('data-complaint-modal', false)->assertDontSee('data-chat-modal', false)->assertDontSee('name="lease_id"', false)->assertDontSee('name="category"', false)->assertDontSee('Add Text to Batch')->assertDontSee('data-ticket-chat', false);
        $ticket = $this->ticket();
        DB::table('tickets')->where('id', $ticket->id)->update(['subject' => 'Leaking tap']);
        $other = $this->ticket();
        DB::table('tickets')->where('id', $other->id)->update(['subject' => 'Noise complaint']);
        $foreign = $this->ticket('pending', $this->user('tenant')->id);
        DB::table('tickets')->where('id', $foreign->id)->update(['subject' => 'Leaking private ticket']);
        $response = $this->getJson(route('tenants.customerService.index', ['search' => 'Leaking']))->assertOk();
        $this->assertStringContainsString('Leaking tap', $response->json('html'));
        $this->assertStringContainsString('data-ticket-url=', $response->json('html'));
        $this->assertStringContainsString('href="'.route('tenants.customerService.show', $ticket).'"', $response->json('html'));
        $this->assertStringNotContainsString('Noise complaint', $response->json('html'));
        $this->assertStringNotContainsString('Leaking private ticket', $response->json('html'));
        $this->lease();
        $this->get(route('tenants.customerService.index'))->assertOk()->assertSee('Complaint');
    }

    public function test_send_adds_text_and_multiple_files_to_current_chat_and_returns_updated_history(): void
    {
        $ticket = $this->ticket('in_progress');
        $other = $this->ticket();
        $this->postJson(route('tenants.customerService.reply', $ticket), ['items' => [
            ['type' => 'text', 'text' => 'Hello'],
            ['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('one.txt', 'First document')],
            ['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('two.txt', 'Second document')],
        ]])->assertOk()->assertJsonCount(3, 'messages')->assertJsonPath('messages.0.text', 'Hello')->assertJsonPath('messages.1.extension', 'txt')->assertJsonPath('ticket.status', 'in_progress');
        $this->assertCount(3, $ticket->messages);
        $this->assertCount(0, $other->messages);
        $attachment = $ticket->messages->first(fn ($message) => str_starts_with($message->message, 'ticket-attachments/'));
        Storage::disk('local')->assertExists($attachment->message);
        $this->getJson(route('tenants.customerService.attachment', [$ticket, $attachment]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_storage_is_cleaned_and_reply_is_rolled_back_when_message_save_fails(): void
    {
        $ticket = $this->ticket();
        TicketMsg::creating(function ($message) {
            if (str_starts_with($message->message, 'ticket-attachments/')) throw new \RuntimeException('Simulated message failure');
        });
        $this->postJson(route('tenants.customerService.reply', $ticket), $this->batch(['items' => [
            ['type' => 'text', 'text' => 'First'], ['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('note.txt', 'Real text')],
        ]]))->assertStatus(500);
        $this->assertSame(1, Ticket::count());
        $this->assertSame(0, TicketMsg::count());
        $this->assertSame('pending', $ticket->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_ooxml_package_is_verified_and_generic_zip_is_rejected(): void
    {
        $ticket = $this->ticket();
        $file = tempnam(sys_get_temp_dir(), 'ticket-test-');
        try {
            $zip = new \ZipArchive();
            $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('word/document.xml', '<document/>');
            $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
            $zip->close();
            $this->postJson(route('tenants.customerService.reply', $ticket), $this->batch(['items' => [['type' => 'file', 'file' => new UploadedFile($file, 'document.docx', null, null, true)]]]))->assertOk();
            $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            $zip->addFromString('random.txt', 'Just a zip');
            $zip->close();
            $this->postJson(route('tenants.customerService.reply', $ticket), $this->batch(['items' => [['type' => 'file', 'file' => new UploadedFile($file, 'fake.docx', null, null, true)]]]))->assertUnprocessable();
        } finally {
            unlink($file);
        }
    }

    public function test_chat_component_accepts_other_roles_and_custom_endpoints_without_tenant_routes(): void
    {
        $ticket = $this->ticket();
        $html = \Illuminate\Support\Facades\Blade::render('<x-customer-service.chat :ticket="$ticket" current-role="agentAdmin" send-url="/agent/ticket/reply" read-url="/agent/ticket/messages" :can-send="false" :messages="$messages" />', [
            'ticket' => $ticket,
            'messages' => [['sender_type' => 'agentAdmin', 'text' => 'Agent message', 'created_at' => '09 Oct 2026 09:00', 'attachment_url' => null, 'extension' => null]],
        ]);
        $this->assertStringContainsString('data-current-role="agentAdmin"', $html);
        $this->assertStringContainsString('data-send-url="/agent/ticket/reply"', $html);
        $this->assertStringContainsString('data-read-url="/agent/ticket/messages"', $html);
        $this->assertStringContainsString('justify-end', $html);
        $this->assertStringContainsString('Agent message', $html);
        $this->assertStringContainsString('data-chat-form  hidden', $html);
        $this->assertStringNotContainsString('/admin/tenant/', $html);
    }

    public function test_non_tenant_cannot_access_tenant_endpoints(): void
    {
        $this->actingAs($this->user('owner'));
        $this->getJson(route('tenants.customerService.index'))->assertForbidden();
        $this->postJson(route('tenants.customerService.store'), $this->batch())->assertForbidden();
    }
}
