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
    private string $managerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach (['users', 'owners', 'tenants', 'properties', 'units', 'rooms', 'leases', 'tickets', 'ticket_msgs', 'audit_logs'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->string('id')->primary();
                $t->timestamps();
                $columns = match ($table) {
                    'users' => ['name', 'email', 'password', 'role', 'email_verified_at'],
                    'tenants' => ['user_id', 'created_by'],
                    'owners' => ['user_id', 'agent_id'],
                    'properties' => ['name', 'owner_id', 'created_by'],
                    'units' => ['unit_no', 'owner_id', 'property_id', 'created_by'],
                    'rooms' => ['room_no', 'owner_id', 'unit_id'],
                    'leases' => ['tenant_id', 'leasable_type', 'leasable_id', 'status', 'start_date', 'end_date', 'checked_out_at', 'agreement_ended_at'],
                    'tickets' => ['sender_id', 'receive_id', 'lease_id', 'category', 'subject', 'status'],
                    'ticket_msgs' => ['ticket_id', 'sender_type', 'message'],
                    'audit_logs' => ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'],
                };
                foreach ($columns as $column) $t->text($column)->nullable();
                if ($table === 'leases') $t->boolean('is_current')->default(true);
            });
        }
        Schema::create('staff', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->string('user_mgnt_id')->nullable(); });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); });
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->string('team_id')->nullable(); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('user_management', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->softDeletes(); });
        Schema::create('invoices', function (Blueprint $t) { $t->string('id'); $t->string('user_id'); $t->string('context'); $t->string('status'); $t->timestamps(); $t->softDeletes(); });
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

    private function batch(array $extra = []): array
    {
        return array_merge(['category' => 'QNA', 'subject' => 'Question', 'details' => 'Hello', 'items' => [['type' => 'text', 'text' => 'Hello']]], $extra);
    }

    public function test_creation_requires_content_and_ignores_client_routing_fields(): void
    {
        $this->lease();
        $url = route('tenants.customerService.store');
        $this->postJson($url, ['subject' => 'Empty'])->assertUnprocessable()->assertJsonValidationErrors('details');
        $this->postJson($url, ['subject' => '  ', 'details' => 'Text'])->assertUnprocessable()->assertJsonValidationErrors('subject');
        $response = $this->postJson($url, ['subject' => 'Complaint', 'details' => 'First text', 'category' => 'QNA', 'sender_id' => $this->ownerId, 'receive_id' => $this->tenantUser->id])->assertCreated()->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.text', 'First text');
        $ticket = Ticket::findOrFail($response->json('ticket.id'));
        $this->assertSame($this->tenantUser->id, $ticket->sender_id);
        $this->assertSame($this->ownerId, $ticket->receive_id);
        $this->assertSame('complain', $ticket->category);
        $this->assertSame('pending', $ticket->status);
    }

    public function test_no_lease_blocks_creation_but_allows_reply_without_resetting_status(): void
    {
        $this->postJson(route('tenants.customerService.store'), $this->batch())->assertUnprocessable()->assertJsonValidationErrors('subject');
        $ticket = $this->ticket('in_progress');
        $this->postJson(route('tenants.customerService.reply', $ticket), $this->batch())->assertOk();
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->assertCount(1, $ticket->messages);
    }

    public function test_multiple_leases_require_choice_and_use_selected_owner(): void
    {
        $first = $this->lease();
        $second = $this->lease();
        $url = route('tenants.customerService.store');
        $this->postJson($url, $this->batch())->assertUnprocessable()->assertJsonValidationErrors('lease_id');
        $this->postJson($url, $this->batch(['lease_id' => $first->id]))->assertCreated();
        $owner = $this->user('owner')->id;
        DB::table('properties')->where('id', $second->leasable_id)->update(['owner_id' => $owner]);
        $response = $this->postJson($url, $this->batch(['lease_id' => $second->id, 'receive_id' => $this->ownerId]))->assertCreated();
        $ticket = Ticket::findOrFail($response->json('ticket.id'));
        $this->assertSame($second->id, $ticket->lease_id);
        $this->assertSame($owner, $ticket->receive_id);
        $this->getJson(route('tenants.customerService.index'))->assertOk()->assertJsonPath('creation_reason', null);
        $this->get(route('tenants.customerService.index'))->assertOk()->assertSee('Select a lease')->assertSee('Test Property');
    }

    public function test_single_lease_is_saved_and_foreign_or_expired_selections_are_rejected(): void
    {
        $lease = $this->lease();
        $url = route('tenants.customerService.store');
        $this->get(route('tenants.customerService.index'))->assertOk()->assertDontSee('name="lease_id"', false);
        $response = $this->postJson($url, $this->batch())->assertCreated();
        $this->assertSame($lease->id, Ticket::findOrFail($response->json('ticket.id'))->lease_id);
        $foreign = $this->lease(['tenant_id' => (string) Str::ulid()]);
        $this->postJson($url, $this->batch(['lease_id' => $foreign->id]))->assertUnprocessable()->assertJsonValidationErrors('lease_id');
        foreach ([['is_current' => false], ['status' => 'End'], ['checked_out_at' => today()], ['agreement_ended_at' => today()], ['start_date' => today()->addDay()], ['end_date' => today()->subDay()]] as $invalid) {
            $expired = $this->lease($invalid);
            $this->postJson($url, $this->batch(['lease_id' => $expired->id]))->assertUnprocessable()->assertJsonValidationErrors('lease_id');
        }
        $this->assertSame(1, Ticket::count());
    }

    public function test_reply_attachments_keep_saved_lease_after_expiry_and_reject_changed_manager(): void
    {
        $lease = $this->lease();
        $other = $this->lease();
        DB::table('properties')->where('id', $other->leasable_id)->update(['created_by' => $this->user('agentAdmin')->id]);
        $response = $this->postJson(route('tenants.customerService.store'), [
            'subject' => 'Attachment', 'lease_id' => $lease->id,
            'attachments' => [UploadedFile::fake()->createWithContent('first.txt', 'First')],
        ])->assertCreated();
        $ticket = Ticket::findOrFail($response->json('ticket.id'));
        DB::table('leases')->where('id', $lease->id)->update(['is_current' => false]);
        $reply = fn () => $this->postJson(route('tenants.customerService.reply', $ticket), [
            'items' => [['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('next.txt', 'Next')]],
        ]);
        $reply()->assertOk();
        foreach ($ticket->messages()->get() as $message) {
            $this->assertStringStartsWith('uploads/user_'.$this->managerId.'/tenants/tickets/', $message->message);
        }
        $changed = $this->user('agentAdmin')->id;
        DB::table('properties')->where('id', $lease->leasable_id)->update(['created_by' => $changed]);
        DB::table('tenants')->where('id', $this->tenantId)->update(['created_by' => $changed]);
        $reply()->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->assertSame(2, $ticket->messages()->count());
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
        $this->get(route('tenants.customerService.show', $ticket))->assertRedirect(route('tenants.customerService.index', ['ticket' => $ticket->id]));
        $this->get(route('tenants.customerService.index', ['ticket' => $ticket->id]))->assertOk()->assertSee('Back to conversations')->assertSee('data-ticket-chat', false)->assertSee('data-can-send="false"', false)->assertSee('<script>Legacy plain text</script>')->assertDontSee('data-chat-modal', false);
        $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()->assertJsonPath('ticket.closed', true)->assertJsonPath('messages.0.text', '<script>Legacy plain text</script>')->assertJsonPath('messages.0.own', false)->assertJsonPath('messages.1.own', true);
    }

    public function test_index_uses_create_component_and_show_links_and_searches_own_subjects(): void
    {
        $this->get(route('tenants.customerService.index'))->assertOk()->assertSee('no valid lease')->assertSee('data-complaint-modal', false)->assertDontSee('data-chat-modal', false)->assertDontSee('name="lease_id"', false)->assertDontSee('name="category"', false)->assertDontSee('Add Text to Batch')->assertSee('data-ticket-chat', false)->assertSee('data-empty="true"', false);
        $ticket = $this->ticket();
        DB::table('tickets')->where('id', $ticket->id)->update(['subject' => 'Leaking tap']);
        $other = $this->ticket();
        DB::table('tickets')->where('id', $other->id)->update(['subject' => 'Noise complaint']);
        $foreign = $this->ticket('pending', $this->user('tenant')->id);
        DB::table('tickets')->where('id', $foreign->id)->update(['subject' => 'Leaking private ticket']);
        $response = $this->getJson(route('tenants.customerService.index', ['search' => 'Leaking']))->assertOk();
        $this->assertStringContainsString('Leaking tap', $response->json('html'));
        $this->assertStringContainsString('data-ticket-id=', $response->json('html'));
        $this->assertStringContainsString('href="'.route('tenants.customerService.index', ['ticket' => $ticket->id]).'"', $response->json('html'));
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
        $attachment = $ticket->messages->first(fn ($message) => str_starts_with($message->message, 'uploads/'));
        Storage::disk('local')->assertExists($attachment->message);
        $this->getJson(route('tenants.customerService.attachment', [$ticket, $attachment]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_storage_is_cleaned_and_reply_is_rolled_back_when_message_save_fails(): void
    {
        $ticket = $this->ticket();
        TicketMsg::creating(function ($message) {
            if (str_starts_with($message->message, 'uploads/')) throw new \RuntimeException('Simulated message failure');
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
            'messages' => [['own' => true, 'sender_type' => 'agentAdmin', 'text' => 'Agent message', 'created_at' => '09 Oct 2026 09:00', 'attachment_url' => null, 'extension' => null]],
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

    public function test_create_text_and_multiple_attachments_use_manager_path_and_are_immediately_readable(): void
    {
        $this->lease();
        $response = $this->postJson(route('tenants.customerService.store'), [
            'subject' => 'Leak', 'details' => 'Please repair', 'manager_id' => $this->tenantUser->id,
            'attachments' => [UploadedFile::fake()->createWithContent('one.TXT', 'First'), UploadedFile::fake()->createWithContent('two.txt', 'Second')],
        ])->assertCreated()->assertJsonCount(3, 'messages')->assertJsonPath('messages.0.text', 'Please repair')->assertJsonPath('messages.1.extension', 'txt');
        $ticket = Ticket::findOrFail($response->json('ticket.id'));
        $this->assertSame($this->ownerId, $ticket->receive_id);
        foreach ($ticket->messages()->get()->slice(1) as $message) {
            $this->assertStringStartsWith('uploads/user_'.$this->managerId.'/tenants/tickets/', $message->message);
            Storage::disk('local')->assertExists($message->message);
            $this->get(route('tenants.customerService.attachment', [$ticket, $message, 'download' => 1]))->assertOk();
            $this->actingAs($this->user('tenant'))->get(route('tenants.customerService.attachment', [$ticket, $message]))->assertForbidden();
            $this->actingAs($this->tenantUser);
        }
        $this->getJson($response->json('ticket.show_url'))->assertOk()->assertJsonCount(3, 'messages');
        $this->postJson(route('tenants.customerService.store'), ['subject' => 'File only', 'attachments' => [UploadedFile::fake()->createWithContent('note.txt', 'Only file')]])->assertCreated()->assertJsonCount(1, 'messages');
    }

    public function test_create_invalid_file_and_failed_second_upload_leave_no_ticket_messages_or_files(): void
    {
        $this->lease();
        $url = route('tenants.customerService.store');
        $this->postJson($url, ['subject' => 'Bad file', 'details' => 'Text', 'attachments' => [UploadedFile::fake()->createWithContent('bad.jpg', 'Text')]])->assertUnprocessable();
        $real = new \App\Services\FileService();
        $mock = \Mockery::mock(\App\Services\FileService::class);
        $calls = 0;
        $mock->shouldReceive('upload')->twice()->andReturnUsing(function ($file, $id, $category) use (&$calls, $real) {
            if (++$calls === 2) throw new \RuntimeException('Upload failed');
            return $real->upload($file, $id, $category);
        });
        $this->app->instance(\App\Services\FileService::class, $mock);
        $this->postJson($url, ['subject' => 'Rollback', 'details' => 'Text', 'attachments' => [UploadedFile::fake()->createWithContent('one.txt', 'First'), UploadedFile::fake()->createWithContent('two.txt', 'Second')]])->assertStatus(500);
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, TicketMsg::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_management_resolution_supports_owner_admin_and_agent_relation_and_rejects_conflicts(): void
    {
        $lease = $this->lease();
        $service = app(TenantCustomerService::class);
        DB::table('properties')->where('id', $lease->leasable_id)->update(['created_by' => null]);
        DB::table('owners')->insert(['id' => (string) Str::ulid(), 'user_id' => $this->ownerId, 'agent_id' => $this->managerId]);
        $this->assertSame($this->managerId, $service->attachmentManagerId($this->tenantUser->fresh(), $lease->fresh()));
        $ownerAdmin = $this->user('ownerAdmin');
        DB::table('properties')->where('id', $lease->leasable_id)->update(['created_by' => $ownerAdmin->id]);
        $this->postJson(route('tenants.customerService.store'), ['subject' => 'Conflict', 'attachments' => [UploadedFile::fake()->createWithContent('one.txt', 'First')]])->assertUnprocessable()->assertJsonValidationErrors('attachments');
        DB::table('properties')->where('id', $lease->leasable_id)->update(['owner_id' => $ownerAdmin->id]);
        DB::table('tenants')->where('id', $this->tenantId)->update(['created_by' => $ownerAdmin->id]);
        $this->assertSame($ownerAdmin->id, $service->attachmentManagerId($this->tenantUser->fresh(), $lease->fresh()));
        DB::table('tenants')->where('id', $this->tenantId)->update(['created_by' => $this->managerId]);
        $linked = $this->ticket();
        $linked->update(['lease_id' => $lease->id]);
        $this->postJson(route('tenants.customerService.reply', $linked), ['items' => [['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('one.txt', 'First')]]])->assertUnprocessable();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_old_attachments_remain_readable_and_text_cannot_impersonate_new_paths(): void
    {
        $ticket = $this->ticket();
        $message = $ticket->messages()->create(['sender_type' => 'tenant', 'message' => 'ticket-attachments/'.$ticket->id.'/'.Str::uuid().'.txt']);
        Storage::disk('local')->put($message->message, 'Old attachment');
        $this->get(route('tenants.customerService.attachment', [$ticket, $message]))->assertOk();
        $this->getJson(route('tenants.customerService.show', $ticket))->assertJsonPath('messages.0.extension', 'txt');
        $this->postJson(route('tenants.customerService.reply', $ticket), ['items' => [['type' => 'text', 'text' => 'uploads/user_'.$this->managerId.'/tenants/tickets/'.Str::uuid().'.txt']]])->assertUnprocessable();
    }

    public function test_legacy_ticket_does_not_choose_a_lease_and_selected_lease_resolves_missing_creator(): void
    {
        DB::table('tenants')->where('id', $this->tenantId)->update(['created_by' => null]);
        $ticket = $this->ticket();
        $this->postJson(route('tenants.customerService.reply', $ticket), ['items' => [['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('one.txt', 'First')]]])->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->postJson(route('tenants.customerService.reply', $ticket), $this->batch())->assertOk();
        $first = $this->lease();
        $this->assertSame($this->managerId, app(TenantCustomerService::class)->attachmentManagerId($this->tenantUser->fresh(), $first));
        $second = $this->lease();
        DB::table('properties')->where('id', $second->leasable_id)->update(['created_by' => $this->user('agentAdmin')->id]);
        $this->postJson(route('tenants.customerService.store'), ['subject' => 'Conflicting leases', 'lease_id' => $first->id, 'attachments' => [UploadedFile::fake()->createWithContent('one.txt', 'First')]])->assertCreated();
        $this->assertSame(2, Ticket::count());
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_owner_admin_upload_uses_users_id_and_keeps_existing_receiver_rule(): void
    {
        $manager = $this->user('ownerAdmin');
        $lease = $this->lease();
        DB::table('tenants')->where('id', $this->tenantId)->update(['created_by' => $manager->id]);
        DB::table('properties')->where('id', $lease->leasable_id)->update(['created_by' => $manager->id]);
        $result = $this->postJson(route('tenants.customerService.store'), ['subject' => 'Owner admin', 'attachments' => [UploadedFile::fake()->createWithContent('one.txt', 'First')]])->assertCreated();
        $ticket = Ticket::findOrFail($result->json('ticket.id'));
        $this->assertSame($this->ownerId, $ticket->receive_id);
        $this->assertStringStartsWith('uploads/user_'.$manager->id.'/tenants/tickets/', $ticket->messages()->first()->message);
    }

    public function test_split_view_fragment_and_direct_links_enforce_ownership_and_use_shared_chat(): void
    {
        $ticket = $this->ticket();
        $ticket->messages()->create(['sender_type' => 'tenant', 'message' => 'First details']);
        $this->get(route('tenants.customerService.index'))->assertOk()->assertSee('Select a conversation')->assertSee('aria-label="New Complaint"', false)->assertDontSee('<table', false);
        $this->get(route('tenants.customerService.index', ['ticket' => $ticket->id]))->assertOk()->assertSee('First details')->assertSee('data-selected-ticket="'.$ticket->id.'"', false)->assertSee('data-ticket-chat', false);
        $response = $this->getJson(route('tenants.customerService.show', [$ticket, 'fragment' => 1]))->assertOk()->assertJsonPath('ticket.id', $ticket->id)->assertJsonPath('messages.0.text', 'First details');
        $this->assertStringContainsString('data-ticket-chat', $response->json('html'));
        $this->assertStringContainsString(route('tenants.customerService.reply', $ticket), $response->json('html'));
        $foreign = $this->ticket('pending', $this->user('tenant')->id);
        $this->get(route('tenants.customerService.index', ['ticket' => $foreign->id]))->assertForbidden();
        $this->getJson(route('tenants.customerService.show', [$foreign, 'fragment' => 1]))->assertForbidden();
        $this->get(route('tenants.customerService.show', $foreign))->assertForbidden();
    }

    public function test_subject_list_search_and_second_page_keep_all_own_tickets_accessible(): void
    {
        for ($i = 0; $i < 17; $i++) {
            $ticket = $this->ticket();
            DB::table('tickets')->where('id', $ticket->id)->update(['subject' => 'Searchable '.$i]);
        }
        $first = $this->getJson(route('tenants.customerService.index', ['search' => 'Searchable']))->assertOk()->json('html');
        $second = $this->getJson(route('tenants.customerService.index', ['search' => 'Searchable', 'page' => 2]))->assertOk()->json('html');
        $this->assertSame(15, substr_count($first, 'data-ticket-id='));
        $this->assertSame(2, substr_count($second, 'data-ticket-id='));
        $this->assertStringContainsString('page=2', $first);
        $this->assertStringContainsString('search=Searchable', $first);
        $this->assertStringNotContainsString('<table', $first);
    }

    public function test_attachment_limits_accept_boundaries_and_report_the_offending_filename(): void
    {
        $service = app(TenantCustomerService::class);
        $mp4 = pack('N', 24).'ftypisom'.pack('N', 0).'isommp42';
        foreach ([['note.txt', 'plain text', 3072], ['clip.mp4', $mp4, 20480]] as [$name, $prefix, $kb]) {
            foreach ([0, 1] as $over) {
                $file = UploadedFile::fake()->createWithContent($name, $prefix.str_repeat(' ', $kb * 1024 - strlen($prefix) + $over));
                $request = new \Illuminate\Http\Request(['items' => [['type' => 'file']]]);
                $request->files->set('items', [['file' => $file]]);
                if (!$over) $this->assertCount(1, $service->validateBatch($request));
                else {
                    try { $service->validateBatch($request); $this->fail('Oversize file accepted'); }
                    catch (\Illuminate\Validation\ValidationException $e) {
                        $this->assertStringContainsString($name.': maximum '.($kb / 1024).' MB', json_encode($e->errors()));
                    }
                }
            }
        }
    }

    public function test_images_are_resized_with_transparency_and_original_size_limit_is_enforced(): void
    {
        $this->lease();
        $image = imagecreatetruecolor(2400, 1200);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        ob_start(); imagepng($image); $bytes = ob_get_clean(); imagedestroy($image);
        $bytes = str_pad($bytes, 2048 * 1024, ' ');
        $result = $this->postJson(route('tenants.customerService.store'), ['subject' => 'Image', 'details' => 'Text', 'attachments' => [UploadedFile::fake()->createWithContent('transparent.png', $bytes)]])->assertCreated();
        $message = Ticket::findOrFail($result->json('ticket.id'))->messages()->where('message', 'like', 'uploads/%')->firstOrFail();
        $path = Storage::disk('local')->path($message->message);
        $this->assertSame([1920, 960], array_slice(getimagesize($path), 0, 2));
        $decoded = imagecreatefrompng($path);
        $this->assertSame(127, (imagecolorat($decoded, 0, 0) >> 24) & 127);
        imagedestroy($decoded);
        $this->assertLessThanOrEqual(2048 * 1024, filesize($path));
        $this->postJson(route('tenants.customerService.store'), ['subject' => 'Too big', 'attachments' => [UploadedFile::fake()->createWithContent('too-big.png', $bytes.' ')]] )->assertUnprocessable()->assertSee('too-big.png');
        $this->assertSame(1, Ticket::count());
    }

    public function test_image_orientation_small_images_and_temporary_cleanup_on_storage_failure(): void
    {
        $image = imagecreatetruecolor(40, 20);
        ob_start(); imagejpeg($image); $jpeg = ob_get_clean(); imagedestroy($image);
        // TIFF orientation tag 6 (90 degrees clockwise) in a JPEG APP1 segment.
        $exif = "Exif\0\0".'II'.pack('vV', 42, 8).pack('v', 1).pack('vvVv', 0x112, 3, 1, 6)."\0\0".pack('V', 0);
        $jpeg = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        $processor = app(\App\Services\TicketImageProcessor::class);
        $file = UploadedFile::fake()->createWithContent('phone.jpeg', $jpeg);
        $path = $processor->upload($file, $this->managerId);
        $this->assertSame([20, 40], array_slice(getimagesize(Storage::disk('local')->path($path)), 0, 2));
        $temporary = null;
        $mock = \Mockery::mock(\App\Services\FileService::class);
        $mock->shouldReceive('upload')->once()->andReturnUsing(function ($file) use (&$temporary) {
            $temporary = $file->getRealPath();
            $this->assertFileExists($temporary);
            throw new \RuntimeException('Storage failed');
        });
        $this->app->instance(\App\Services\FileService::class, $mock);
        try { $processor->upload($file, $this->managerId); $this->fail('Expected storage failure'); }
        catch (\RuntimeException $e) { $this->assertSame('Storage failed', $e->getMessage()); }
        $this->assertFileDoesNotExist($temporary);
    }

    public function test_image_processing_failure_rolls_back_prior_files_and_rejects_unsafe_dimensions(): void
    {
        $this->lease();
        $image = imagecreatetruecolor(2, 2);
        ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
        $oversized = substr_replace($png, pack('NN', 10000, 10000), 16, 8);
        $this->postJson(route('tenants.customerService.store'), ['subject' => 'Rollback', 'details' => 'Text', 'attachments' => [
            UploadedFile::fake()->createWithContent('first.txt', 'Text'), UploadedFile::fake()->createWithContent('huge.png', $oversized),
        ]])->assertUnprocessable()->assertSee('huge.png');
        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, TicketMsg::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_message_dates_use_malaysia_midnight_without_changing_stored_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-13T00:01:00+08:00'));
        $ticket = $this->ticket();
        foreach (['2026-10-11 23:59:00', '2026-10-12 23:59:00', '2026-10-13 00:00:00', '2026-10-13 00:01:00'] as $time) {
            $message = new TicketMsg(['ticket_id' => $ticket->id, 'sender_type' => 'tenant', 'message' => 'Text']);
            $message->created_at = Carbon::parse($time, 'Asia/Kuala_Lumpur')->setTimezone(config('app.timezone'));
            $message->saveQuietly();
        }
        $response = $this->getJson(route('tenants.customerService.show', $ticket))->assertOk()
            ->assertJsonPath('messages.0.date_label', '11 Oct 2026')->assertJsonPath('messages.1.date_label', 'Yesterday')
            ->assertJsonPath('messages.2.date_label', 'Today')->assertJsonPath('messages.2.created_at', '00:00');
        $html = $this->getJson(route('tenants.customerService.show', [$ticket, 'fragment' => 1]))->json('html');
        $this->assertSame(3, substr_count($html, 'data-message-date='));
        Carbon::setTestNow(Carbon::parse('2026-10-14T00:00:00+08:00'));
        $this->getJson(route('tenants.customerService.show', $ticket))->assertJsonPath('messages.2.date_label', 'Yesterday');
    }

    public function test_pdf_preview_and_download_remain_private_and_cross_tenant_access_is_denied(): void
    {
        $ticket = $this->ticket();
        $path = 'ticket-attachments/'.$ticket->id.'/'.Str::uuid().'.pdf';
        $message = $ticket->messages()->create(['sender_type' => 'tenant', 'message' => $path]);
        Storage::disk('local')->put($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $url = route('tenants.customerService.attachment', [$ticket, $message]);
        $preview = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('inline;', $preview->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('attachment;', $this->get($url.'?download=1')->assertOk()->headers->get('Content-Disposition'));
        $html = $this->getJson(route('tenants.customerService.show', [$ticket, 'fragment' => 1]))->assertOk()->json('html');
        $this->assertStringContainsString('data-pdf-url=', $html);
        $this->assertStringContainsString(basename($path), $html);
        $this->actingAs($this->user('tenant'))->get($url)->assertForbidden();
    }

    public function test_file_service_removes_partial_ticket_file_if_storage_returns_false(): void
    {
        $file = \Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalExtension')->andReturn('txt');
        $file->shouldReceive('storeAs')->once()->andReturnUsing(function ($directory, $filename, $disk) {
            Storage::disk($disk)->put($directory.'/'.$filename, 'Partial write');
            return false;
        });
        $this->assertFalse(app(\App\Services\FileService::class)->upload($file, $this->managerId, 'tenant_ticket'));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
    public function test_maintenance_reuses_page_and_manager_reply_is_visible_to_tenant(): void
    {
        $lease = $this->lease();
        $created = $this->postJson(route('tenants.customerService.store'), [
            'subject' => 'Source ticket', 'details' => 'Tenant details',
            'attachments' => [UploadedFile::fake()->createWithContent('proof.txt', 'Proof')],
        ])->assertCreated();
        $ticket = Ticket::findOrFail($created->json('ticket.id'));
        $manager = User::findOrFail($this->managerId);
        $this->actingAs($manager);
        $this->get(route('admin.maintenance.index'))->assertOk()
            ->assertViewIs('tenantSide.customerService.index')->assertSee('Source ticket')
            ->assertSee('Test Property')->assertDontSee('data-open-complaint', false)->assertDontSee('data-complaint-modal', false);
        $this->get(route('admin.maintenance.index', ['ticket' => $ticket->id]))->assertOk()
            ->assertSee('data-can-attach="true"', false)->assertSee('Tenant details');
        $read = $this->getJson(route('admin.maintenance.show', $ticket))->assertOk()
            ->assertJsonPath('messages.0.own', false);
        $this->get($read->json('messages.1.attachment_url'))->assertOk();
        $this->get($read->json('messages.1.attachment_url').'?download=1')->assertOk();
        $reply = $this->postJson(route('admin.maintenance.reply', $ticket), [
            'sender_type' => 'tenant', 'sender_id' => $this->tenantUser->id,
            'items' => [['type' => 'text', 'text' => 'Manager reply']],
        ])->assertOk()->assertJsonPath('messages.2.sender_type', 'agentAdmin')->assertJsonPath('messages.2.own', true);
        $message = $ticket->messages()->latest('id')->first();
        $this->assertSame($manager->id, $message->authorId());
        $this->assertSame($this->ownerId, $ticket->fresh()->receive_id);
        $this->assertSame($lease->id, $ticket->fresh()->lease_id);
        $this->actingAs($this->tenantUser)->getJson(route('tenants.customerService.show', $ticket))
            ->assertOk()->assertJsonPath('messages.0.own', true)->assertJsonPath('messages.2.own', false)
            ->assertJsonPath('messages.2.text', 'Manager reply');
        $this->actingAs(User::findOrFail($this->ownerId))->getJson(route('admin.maintenance.show', $ticket))
            ->assertOk()->assertJsonPath('messages.2.own', false);
        $this->postJson(route('admin.maintenance.reply', $ticket), [
            'items' => [['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('owner.txt', 'Owner attachment')]],
        ])->assertOk();
        $ticket->update(['status' => 'closed']);
        $this->getJson(route('admin.maintenance.show', $ticket))->assertOk()->assertJsonPath('ticket.closed', true);
        $this->postJson(route('admin.maintenance.reply', $ticket), $this->batch())->assertForbidden();
        $this->assertSame(4, $ticket->messages()->count());
    }

    public function test_maintenance_isolates_owners_managers_staff_and_legacy_endpoints(): void
    {
        $first = $this->lease();
        $second = $this->lease();
        $otherOwner = $this->user('owner');
        $otherManager = $this->user('ownerAdmin');
        DB::table('properties')->where('id', $second->leasable_id)->update(['owner_id' => $otherOwner->id, 'created_by' => $otherManager->id]);
        $one = $this->postJson(route('tenants.customerService.store'), $this->batch(['lease_id' => $first->id, 'subject' => 'First private']))->assertCreated();
        $two = $this->postJson(route('tenants.customerService.store'), $this->batch(['lease_id' => $second->id, 'subject' => 'Second private']))->assertCreated();
        $ticket = Ticket::findOrFail($two->json('ticket.id'));
        $path = 'ticket-attachments/'.$ticket->id.'/'.Str::uuid().'.pdf';
        $message = $ticket->messages()->create(['sender_type' => 'tenant', 'message' => $path]);
        Storage::disk('local')->put($path, '%PDF-1.4 proof');
        foreach ([User::findOrFail($this->ownerId), User::findOrFail($this->managerId), $this->user('agentAdmin'), $this->user('ownerAdmin'), $this->user('owner'), $this->user('staff'), $this->user('admin')] as $unrelated) {
            $this->actingAs($unrelated);
            $list = $this->getJson(route('admin.maintenance.index'));
            if (in_array($unrelated->role, ['staff', 'admin'])) $list->assertForbidden();
            else { $list->assertOk(); $this->assertStringNotContainsString('Second private', $list->json('html')); }
            $this->getJson(route('admin.maintenance.show', $ticket))->assertForbidden();
            $this->postJson(route('admin.maintenance.reply', $ticket), $this->batch())->assertForbidden();
            foreach ([false, true] as $download) $this->get(route('admin.maintenance.attachment', [$ticket, $message, 'download' => $download]))->assertForbidden();
            $this->getJson(route('admin.customerService.show', $ticket))->assertForbidden();
            $this->patchJson(route('admin.customerService.update', $ticket), ['message' => 'Bypass'])->assertForbidden();
        }
        $this->actingAs($otherManager)->getJson(route('admin.maintenance.show', $ticket))->assertOk();
        $this->get(route('admin.maintenance.index'))->assertOk()->assertSee('Second private')->assertDontSee('First private');
        $this->postJson(route('admin.maintenance.reply', $ticket), $this->batch())->assertOk();
        $this->actingAs($otherOwner)->getJson(route('admin.maintenance.show', $ticket))->assertOk();
        $foreignMessage = Ticket::findOrFail($one->json('ticket.id'))->messages()->first();
        $this->get(route('admin.maintenance.attachment', [$ticket, $foreignMessage]))->assertNotFound();
        $this->actingAs($this->tenantUser)->getJson(route('admin.maintenance.index'))->assertForbidden();
    }

    public function test_legacy_ticket_requires_recorded_owner_relationship_and_authors_are_not_inferred_from_roles(): void
    {
        $ticket = $this->ticket();
        $ticket->update(['receive_id' => $this->ownerId]);
        $manager = User::findOrFail($this->managerId);
        $this->lease(); // A current lease alone must not authorize a legacy ticket.
        $this->actingAs($manager)->getJson(route('admin.maintenance.show', $ticket))->assertForbidden();
        DB::table('owners')->insert(['id' => (string) Str::ulid(), 'user_id' => $this->ownerId, 'agent_id' => $manager->id]);
        $this->getJson(route('admin.maintenance.show', $ticket))->assertOk();
        $this->postJson(route('admin.maintenance.reply', $ticket), $this->batch())->assertOk()->assertJsonPath('messages.0.own', true);
        $replacement = $this->user('agentAdmin');
        DB::table('owners')->where('user_id', $this->ownerId)->update(['agent_id' => $replacement->id]);
        $this->actingAs($replacement)->getJson(route('admin.maintenance.show', $ticket))->assertOk()->assertJsonPath('messages.0.own', false);
        DB::table('audit_logs')->where('auditable_id', $ticket->messages()->first()->id)->delete();
        $this->getJson(route('admin.maintenance.show', $ticket))->assertOk()
            ->assertJsonPath('messages.0.own', false)->assertJsonPath('messages.0.sender_label', 'Unverified sender');
        $this->assertNull($ticket->fresh()->lease_id);
        $this->actingAs($manager)->getJson(route('admin.maintenance.show', $ticket))->assertForbidden();
    }
    public function test_all_maintenance_roles_upload_to_ticket_manager_and_tenant_can_download(): void
    {
        foreach (['agentAdmin', 'ownerAdmin', 'owner'] as $role) {
            $manager = $role === 'ownerAdmin' ? $this->user('ownerAdmin') : User::findOrFail($this->managerId);
            $lease = $this->lease();
            DB::table('properties')->where('id', $lease->leasable_id)->update(['created_by' => $manager->id]);
            DB::table('tenants')->where('id', $this->tenantId)->update(['created_by' => $manager->id]);
            $this->actingAs($this->tenantUser);
            $created = $this->postJson(route('tenants.customerService.store'), [
                'subject' => 'Attachments '.$role, 'lease_id' => $lease->id,
                'attachments' => [UploadedFile::fake()->createWithContent('tenant.txt', 'Tenant proof')],
            ])->assertCreated();
            $ticket = Ticket::findOrFail($created->json('ticket.id'));
            $actor = $role === 'owner' ? User::findOrFail($this->ownerId) : $manager;
            $this->actingAs($actor);
            $this->get(route('admin.maintenance.attachment', [$ticket, $ticket->messages()->first()]))->assertOk();
            $image = imagecreatetruecolor(2000, 1000);
            ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
            $this->postJson(route('admin.maintenance.reply', $ticket), [
                'sender_type' => 'tenant', 'manager_id' => $actor->id, 'user_id' => $actor->id,
                'items' => [['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('photo.png', $png)]],
            ])->assertOk()->assertJsonPath('messages.1.sender_type', $role)->assertJsonPath('messages.1.own', true);
            $this->postJson(route('admin.maintenance.reply', $ticket), [
                'items' => [['type' => 'text', 'text' => 'With documents'],
                    ['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n%%EOF")],
                    ['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('note.txt', 'Notes')]],
            ])->assertOk()->assertJsonCount(5, 'messages');
            $messages = $ticket->messages()->orderBy('id')->get();
            $this->assertSame($actor->id, $messages[1]->authorId());
            $this->assertSame([1920, 960], array_slice(getimagesize(Storage::disk('local')->path($messages[1]->message)), 0, 2));
            $this->actingAs($this->tenantUser)->getJson(route('tenants.customerService.show', $ticket))
                ->assertOk()->assertJsonPath('messages.1.own', false)->assertJsonPath('messages.3.extension', 'pdf');
            foreach ($messages as $message) {
                if (!app(TenantCustomerService::class)->attachmentPath($message)) continue;
                $this->assertStringStartsWith('uploads/user_'.$manager->id.'/tenants/tickets/', $message->message);
                $this->get(route('tenants.customerService.attachment', [$ticket, $message]))->assertOk();
                $this->get(route('tenants.customerService.attachment', [$ticket, $message, 'download' => 1]))->assertOk();
            }
            $this->assertSame($lease->id, $ticket->fresh()->lease_id);
            $this->assertSame($this->ownerId, $ticket->fresh()->receive_id);
        }
    }

    public function test_maintenance_attachment_limits_authorization_and_closed_tickets_leave_no_files(): void
    {
        $lease = $this->lease();
        $created = $this->postJson(route('tenants.customerService.store'), $this->batch())->assertCreated();
        $ticket = Ticket::findOrFail($created->json('ticket.id'));
        $url = route('admin.maintenance.reply', $ticket);
        $fileItem = fn ($file) => ['type' => 'file', 'file' => $file];
        $mp4 = pack('N', 24).'ftypisom'.pack('N', 0).'isommp42';
        $image = imagecreatetruecolor(1, 1);
        ob_start(); imagepng($image); $png = ob_get_clean(); imagedestroy($image);
        $this->actingAs(User::findOrFail($this->managerId));
        foreach ([
            [$fileItem(UploadedFile::fake()->createWithContent('large.png', $png)->size(2049))],
            [$fileItem(UploadedFile::fake()->createWithContent('large.mp4', $mp4)->size(20481))],
            [$fileItem(UploadedFile::fake()->createWithContent('large.txt', 'Text')->size(3073))],
            [$fileItem(UploadedFile::fake()->createWithContent('fake.png', 'Not an image'))],
            array_map(fn ($i) => $fileItem(UploadedFile::fake()->createWithContent($i.'.txt', 'Text')), range(1, 11)),
            array_map(fn ($i) => $fileItem(UploadedFile::fake()->createWithContent($i.'.mp4', $mp4)->size(20480)), range(1, 6)),
        ] as $items) $this->postJson($url, ['items' => $items])->assertUnprocessable();
        $payload = fn () => ['items' => [$fileItem(UploadedFile::fake()->createWithContent('proof.txt', 'Text'))]];
        $this->actingAs($this->user('agentAdmin'))->postJson($url, $payload())->assertForbidden();
        $this->actingAs($this->user('staff'))->postJson($url, $payload())->assertForbidden();
        $this->actingAs(User::findOrFail($this->managerId));
        foreach (config('customer_service.closed_statuses') as $status) {
            $ticket->update(['status' => $status]);
            $this->postJson($url, $payload())->assertForbidden();
        }
        $this->assertSame(1, $ticket->messages()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_maintenance_upload_and_message_failures_roll_back_entire_batch(): void
    {
        $this->lease();
        $created = $this->postJson(route('tenants.customerService.store'), $this->batch())->assertCreated();
        $ticket = Ticket::findOrFail($created->json('ticket.id'));
        $this->actingAs(User::findOrFail($this->managerId));
        $real = new \App\Services\FileService();
        $mock = \Mockery::mock(\App\Services\FileService::class);
        $calls = 0;
        $mock->shouldReceive('upload')->twice()->andReturnUsing(function ($file, $id, $category) use (&$calls, $real) {
            if (++$calls === 2) throw new \RuntimeException('Upload failed');
            return $real->upload($file, $id, $category);
        });
        $this->app->instance(\App\Services\FileService::class, $mock);
        $payload = fn () => ['items' => [
            ['type' => 'text', 'text' => 'Rolled back text'],
            ['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('first.txt', 'First')],
            ['type' => 'file', 'file' => UploadedFile::fake()->createWithContent('second.txt', 'Second')],
        ]];
        $this->postJson(route('admin.maintenance.reply', $ticket), $payload())->assertStatus(500);
        $this->assertSame(1, $ticket->messages()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->app->instance(\App\Services\FileService::class, $real);
        TicketMsg::creating(function ($message) {
            if (str_starts_with($message->message, 'uploads/')) throw new \RuntimeException('Message save failed');
        });
        $this->postJson(route('admin.maintenance.reply', $ticket), $payload())->assertStatus(500);
        $this->assertSame(1, $ticket->messages()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame('pending', $ticket->fresh()->status);
    }
}