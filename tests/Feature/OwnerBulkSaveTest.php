<?php

namespace Tests\Feature;

use App\Http\Controllers\OwnerBulkController;
use App\Http\Requests\BulkOwnerRequest;
use App\Jobs\SaveBulkOperation;
use App\Models\BulkOperation;
use App\Models\Owners;
use App\Models\User;
use App\Services\BankAccountService;
use App\Services\OwnerBulkOperationService;
use App\Services\OwnerBulkService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerBulkSaveTest extends TestCase
{
    private User $manager;

    private int $identitySequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'users' => ['name', 'email', 'password', 'role', 'email_verified_at', 'account_permissions', 'status'],
            'owners' => ['user_id', 'agent_id', 'company_name', 'ic_number', 'phone', 'gender', 'address', 'postcode', 'city', 'state'],
            'companies' => ['name', 'ssm_new_number', 'ssm_old_number', 'address', 'phone', 'email', 'logo_path', 'ssm_cert_path'],
            'company_user' => ['company_id', 'user_id'],
            'bank_details' => ['bank_name', 'bank_code', 'account_holder_name', 'account_number'],
            'bank_user' => ['bank_detail_id', 'user_id'],
            'audit_logs' => ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'],
        ] as $table => $fields) {
            Schema::create($table, function (Blueprint $schema) use ($fields, $table) {
                $schema->string('id')->primary();
                foreach ($fields as $field) {
                    $schema->text($field)->nullable();
                }
                if ($table === 'bank_user') {
                    $schema->boolean('is_default')->default(true);
                }
                if ($table === 'users') {
                    $schema->unique('email');
                }
                $schema->timestamps();
            });
        }
        (require database_path('migrations/2026_11_16_000001_create_bulk_operations_table.php'))->up();
        Storage::fake('local');
        Storage::fake('public');
        $this->manager = User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'secret', 'role' => 'ownerAdmin', 'email_verified_at' => now()]);
        $this->grant(['owner.tab', 'owner.create', 'owner.edit', 'owner.delete']);
        $this->actingAs($this->manager);
    }

    private function grant(array $grants): void
    {
        $this->manager->forceFill(['account_permissions' => $grants])->save();
    }

    private function row(array $extra = []): array
    {
        $number = ++$this->identitySequence;

        return array_replace(['action' => 'create', 'id' => null, 'version' => null, 'rowNumber' => 1,
            'name' => 'Owner', 'email' => '', 'phone' => '01'.str_pad((string) $number, 9, '0', STR_PAD_LEFT), 'ic' => '900101'.str_pad((string) $number, 6, '0', STR_PAD_LEFT), 'gender' => 'Male',
            'address' => 'Owner Street', 'postcode' => '01234', 'city' => 'Ipoh', 'state' => 'Perak'], $extra);
    }

    private function validated(array $rows, array $uploads = []): array
    {
        $request = BulkOwnerRequest::create('/', 'POST', ['rows_json' => json_encode($rows), 'page' => 1]);
        $request->validateRowDetails = true;
        $request->files->set('uploads', $uploads);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return $request->validated('rows');
    }

    private function create(array $extra = []): Owners
    {
        app(OwnerBulkService::class)->save($this->validated([$this->row($extra)]), $this->manager);

        return Owners::latest('id')->firstOrFail();
    }

    private function editRow(Owners $owner, array $extra = []): array
    {
        $owner->load(['user.companies', 'user.bankDetails' => fn ($q) => $q->wherePivot('is_default', true)]);

        return $this->row(array_replace(['action' => 'update', 'id' => $owner->id, 'phone' => $owner->phone, 'ic' => $owner->ic_number, 'version' => app(OwnerBulkService::class)->revision($owner)], $extra));
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('ssm.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    public function test_optional_company_bank_and_separate_addresses_are_saved_with_encryption(): void
    {
        $owner = $this->create(['companyName' => 'Owner Company', 'companyAddress' => 'Company Street',
            'bankName' => 'Bank', 'accountHolder' => 'Owner', 'accountNumber' => '0012345678']);
        $this->assertSame('Owner Street', $owner->address);
        $this->assertSame('Company Street', $owner->user->companies->first()->address);
        $this->assertNull($owner->company_name);
        $this->assertSame($this->manager->id, $owner->agent_id);
        $this->assertSame('0012345678', app(BankAccountService::class)->getDefaultForUser($owner->user)->account_number);
        $this->assertNotSame('0012345678', DB::table('bank_details')->value('account_number'));
        $this->create();
        $this->assertDatabaseCount('owners', 2);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('bank_details', 1);
    }

    public function test_grid_loads_fifty_at_a_time_and_filters_recovered_ids_by_account(): void
    {
        $rows = [];
        for ($i = 0; $i < 53; $i++) {
            $rows[] = $this->row(['name' => 'Owner '.$i, 'rowNumber' => $i + 1]);
        }
        app(OwnerBulkService::class)->save($this->validated($rows), $this->manager);
        $foreign = $this->create();
        $foreign->update(['agent_id' => 'another-manager']);
        $inactive = $this->create();
        $inactive->user->update(['status' => 'inactive']);
        $first = $this->getJson(route('admin.owners.bulk-edit'))->assertOk()->assertJsonCount(50, 'rows');
        $cursor = $first->json('nextCursor');
        $this->assertNotNull($cursor);
        $next = $this->getJson(route('admin.owners.bulk-edit', ['after' => $cursor]))->assertOk()->assertJsonCount(3, 'rows')->assertJsonPath('nextCursor', null);
        $id = $next->json('rows.0.key');
        $this->getJson(route('admin.owners.bulk-edit', ['ids' => [$id, $foreign->id, $inactive->id]]))->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.key', $id);
    }

    public function test_queue_mixed_batch_is_idempotent_and_completion_uses_session_message(): void
    {
        Queue::fake();
        $owner = $this->create(['bankName' => 'Bank', 'accountHolder' => 'Owner', 'accountNumber' => '001234']);
        $remove = $this->create();
        $rows = [$this->editRow($owner, ['name' => 'Edited', 'bankName' => 'New Bank', 'accountHolder' => 'Owner', 'accountNumber' => '']),
            $this->editRow($remove, ['action' => 'remove']), $this->row(['rowNumber' => 3])];
        $key = (string) Str::uuid();
        $payload = ['rows_json' => json_encode($rows), 'page' => 1, 'request_key' => $key];
        $url = route('admin.owners.bulk-save');
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect(route('admin.owners.bulk-edit', ['page' => 1, 'operation' => $key]));
        $this->post($url, $payload)->assertSessionHasNoErrors();
        Queue::assertPushed(SaveBulkOperation::class, 1);
        $this->assertDatabaseCount('bulk_operations', 1);
        $operation = BulkOperation::firstOrFail();
        app(OwnerBulkOperationService::class)->process($operation->id);
        $this->assertSame('succeeded', $operation->fresh()->status, json_encode($operation->fresh()->errors));
        $this->assertSame(['created' => 1, 'updated' => 1, 'removed' => 1], $operation->fresh()->counts);
        $this->assertSame('Edited', $owner->fresh()->user->name);
        $this->assertSame('inactive', $remove->fresh()->user->status);
        $this->assertSame('001234', app(BankAccountService::class)->getDefaultForUser($owner->fresh()->user)->account_number);
        $this->assertNull($operation->fresh()->payload);
        $this->get(route('admin.owners.bulk-complete', $key))->assertRedirect(route('admin.owners.index'))->assertSessionHas('success')->assertSessionHas('owner_bulk_saved_draft_key');
        app(OwnerBulkOperationService::class)->process($operation->id);
        $this->assertDatabaseCount('owners', 3);
    }

    public function test_partial_company_bank_and_case_insensitive_duplicates_are_rejected_before_queuing(): void
    {
        Queue::fake();
        foreach ([['companyPhone' => '0123456'], ['bankName' => 'Bank']] as $extra) {
            $this->post(route('admin.owners.bulk-save'), ['rows_json' => json_encode([$this->row($extra)]), 'page' => 1])->assertSessionHasErrors();
        }
        $this->create(['email' => 'EXISTING@example.test']);
        $this->post(route('admin.owners.bulk-save'), ['rows_json' => json_encode([$this->row(['email' => 'existing@example.test'])]), 'page' => 1])
            ->assertSessionHasErrors('rows.0.email');
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('bulk_operations', 0);
    }

    public function test_foreign_records_and_action_permissions_are_rechecked(): void
    {
        $owner = $this->create();
        $row = $this->editRow($owner);
        $owner->update(['agent_id' => 'other-manager']);
        $this->post(route('admin.owners.bulk-save'), ['rows_json' => json_encode([$row]), 'page' => 1])->assertSessionHas('error', 'You have no permission.');
        $this->grant(['owner.tab', 'owner.create']);
        $owner->update(['agent_id' => $this->manager->id]);
        $this->post(route('admin.owners.bulk-save'), ['rows_json' => json_encode([$this->editRow($owner)]), 'page' => 1])->assertSessionHas('error', 'You have no permission.');
        $this->assertDatabaseCount('bulk_operations', 0);
    }

    public function test_worker_rejects_stale_records_and_revoked_permissions_without_partial_saves(): void
    {
        Queue::fake();
        $owner = $this->create();
        $operations = app(OwnerBulkOperationService::class);
        $operation = $operations->start($this->validated([$this->editRow($owner, ['name' => 'Changed']), $this->row()]), $this->manager, [], (string) Str::uuid(), 1, 'draft');
        $owner->user->update(['name' => 'Changed elsewhere']);
        $operations->process($operation->id);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertDatabaseCount('owners', 1);
        $this->assertSame('Changed elsewhere', $owner->fresh()->user->name);
        $operation = $operations->start($this->validated([$this->row()]), $this->manager, [], (string) Str::uuid(), 1, 'draft');
        $this->grant(['owner.tab']);
        $operations->process($operation->id);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertDatabaseCount('owners', 1);
    }

    public function test_failed_save_rolls_back_company_user_and_uploads(): void
    {
        $uploads = ['row1' => ['ssmCertificate' => $this->pdf()]];
        $row = $this->row(['companyName' => 'Company', 'uploadKey' => 'row1', 'fileFields' => ['ssmCertificate'],
            'bankName' => 'Bank', 'accountHolder' => 'Owner', 'accountNumber' => '001234']);
        $this->partialMock(BankAccountService::class, fn ($mock) => $mock->shouldReceive('saveForUser')->andThrow(new \RuntimeException('Failure')));
        try {
            app(OwnerBulkService::class)->save($this->validated([$row], $uploads), $this->manager, $uploads);
            $this->fail('Expected save failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('owners', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('companies', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_real_database_queue_encrypts_payload_and_processes_uploads(): void
    {
        (require database_path('migrations/0001_01_01_000002_create_jobs_table.php'))->up();
        $uploads = ['row1' => ['ssmCertificate' => $this->pdf()]];
        $row = $this->row(['companyName' => 'Company', 'uploadKey' => 'row1', 'fileFields' => ['ssmCertificate'],
            'bankName' => 'Bank', 'accountHolder' => 'Owner', 'accountNumber' => '00123456789']);
        $operation = app(OwnerBulkOperationService::class)->start($this->validated([$row], $uploads), $this->manager, $uploads, (string) Str::uuid(), 1, 'draft');
        $this->assertStringNotContainsString('00123456789', DB::table('bulk_operations')->value('payload'));
        $this->assertDatabaseCount('owners', 0);
        $this->assertSame(1, DB::table('jobs')->where('queue', 'bulk')->count());
        app('queue')->connection('bulk')->pop('bulk')->fire();
        $this->assertSame('succeeded', $operation->fresh()->status, json_encode($operation->fresh()->errors));
        $owner = Owners::firstOrFail();
        $path = $owner->user->companies->first()->ssm_cert_path;
        Storage::disk('local')->assertExists($path);
        $this->assertSame([], Storage::disk('local')->files('owner-bulk-staging/'.$operation->id));
        $this->get(route('admin.owners.bulk-file', [$owner, 'ssmCertificate']))->assertOk();
        $owner->update(['agent_id' => 'other-manager']);
        $this->get(route('admin.owners.bulk-file', [$owner, 'ssmCertificate']))->assertNotFound();
    }

    public function test_grid_masks_bank_number_and_renders_owner_sections_without_tenant_contacts(): void
    {
        $this->create(['companyName' => 'Company', 'bankName' => 'Bank', 'accountHolder' => 'Owner', 'accountNumber' => '0012345678']);
        $this->getJson(route('admin.owners.bulk-edit'))->assertOk()->assertJsonPath('rows.0.accountNumber', '')->assertJsonPath('rows.0.accountLastFour', '5678');
        $request = \Illuminate\Http\Request::create('/admin/owners/bulk-edit');
        $view = (new OwnerBulkController)->index($request, app(OwnerBulkService::class), app(OwnerBulkOperationService::class));
        $source = str_replace(['<x-app-layout>', '</x-app-layout>'], '', file_get_contents($view->getPath()));
        $html = Blade::render($source, $view->getData());
        foreach (['Bulk Edit Owners', 'Owner Address', 'Company (Optional)', 'Bank Account (Optional)', 'ownerBulkEditor', 'Saving Owners'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Emergency Contacts', $html);
        $this->assertStringNotContainsString('<x-form.', $html);
        $this->assertStringNotContainsString('0012345678', $html);
    }

    public function test_progress_is_actor_scoped_and_stays_below_one_hundred_until_commit(): void
    {
        Queue::fake();
        $operation = app(OwnerBulkOperationService::class)->start($this->validated([$this->row()]), $this->manager, [], (string) Str::uuid(), 1, 'draft');
        Cache::store('file')->put('owner-bulk-progress:'.$operation->id, 1, 60);
        $url = route('admin.owners.bulk-progress', $operation->request_key);
        $this->getJson($url)->assertOk()->assertJsonPath('percent', 99);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'secret', 'role' => 'ownerAdmin', 'email_verified_at' => now()]);
        $this->actingAs($other)->getJson($url)->assertNotFound();
        Cache::store('file')->forget('owner-bulk-progress:'.$operation->id);
    }

    public function test_oversized_batches_and_invalid_files_are_rejected(): void
    {
        $this->post(route('admin.owners.bulk-save'), ['rows_json' => json_encode(array_fill(0, 301, $this->row())), 'page' => 1])->assertSessionHasErrors('rows');
        $this->post(route('admin.owners.bulk-save'), ['rows_json' => json_encode([$this->row(['uploadKey' => 'row1', 'fileFields' => ['companyLogo']])]),
            'uploads' => ['row1' => ['companyLogo' => UploadedFile::fake()->create('bad.txt', 10)]], 'page' => 1])->assertSessionHasErrors('uploads.row1.companyLogo');
    }

    public function test_three_hundred_rows_process_atomically_and_later_chunk_errors_keep_row_numbers(): void
    {
        Queue::fake();
        $rows = [];
        for ($i = 0; $i < 300; $i++) {
            $rows[] = $this->row(['rowNumber' => $i + 1, 'name' => 'Owner '.$i]);
        }
        $invalid = $rows;
        $invalid[55]['gender'] = '';
        $this->post(route('admin.owners.bulk-save'), ['rows_json' => json_encode($invalid), 'page' => 1])
            ->assertSessionHasErrors('rows.55.gender');
        $this->assertDatabaseCount('bulk_operations', 0);
        $operation = app(OwnerBulkOperationService::class)->start($rows, $this->manager, [], (string) Str::uuid(), 1, 'draft');
        app(OwnerBulkOperationService::class)->process($operation->id);
        $this->assertSame('succeeded', $operation->fresh()->status, json_encode($operation->fresh()->errors));
        $this->assertDatabaseCount('owners', 300);
        $this->assertSame(300, $operation->fresh()->counts['created']);
    }

    public function test_owner_progress_cannot_read_another_module_operation(): void
    {
        $key = (string) Str::uuid();
        BulkOperation::create(['module' => 'property', 'actor_id' => $this->manager->id, 'manager_id' => $this->manager->id,
            'request_key' => $key, 'status' => 'queued', 'total' => 1, 'page' => 1, 'draft_key' => 'other-module']);
        $this->getJson(route('admin.owners.bulk-progress', $key))->assertNotFound();
    }
    public function test_bulk_rejects_duplicate_ic_and_phone_in_same_batch_or_saved_account(): void
    {
        Queue::fake();
        foreach (['ic', 'phone'] as $field) {
            $first = $this->row();
            $second = $this->row([$field => $first[$field], 'rowNumber' => 2]);
            $this->post(route('admin.owners.bulk-save'), ['rows_json' => json_encode([$first, $second]), 'page' => 1])
                ->assertSessionHasErrors('rows.1.'.$field);
        }
        $owner = $this->create();
        foreach (['ic' => $owner->ic_number, 'phone' => $owner->phone] as $field => $value) {
            $this->post(route('admin.owners.bulk-save'), ['rows_json' => json_encode([$this->row([$field => $value])]), 'page' => 1])
                ->assertSessionHasErrors('rows.0.'.$field);
        }
        $this->assertDatabaseCount('bulk_operations', 0);
        Queue::assertNothingPushed();
    }

    public function test_bulk_allows_matching_identity_in_another_account_and_unchanged_self_edits(): void
    {
        $owner = $this->create();
        $other = User::create(['name' => 'Manager B', 'email' => 'manager-b@example.test', 'password' => 'secret', 'role' => 'ownerAdmin', 'email_verified_at' => now()]);
        $other->forceFill(['account_permissions' => ['owner.tab', 'owner.create', 'owner.edit']])->save();
        $this->actingAs($other);
        app(OwnerBulkService::class)->save($this->validated([$this->row(['ic' => $owner->ic_number, 'phone' => $owner->phone])]), $other);
        $second = Owners::where('agent_id', $other->id)->firstOrFail();
        app(OwnerBulkService::class)->save($this->validated([$this->editRow($second)]), $other);
        $this->assertDatabaseCount('owners', 2);
        // A super-admin can edit records across accounts without treating their shared identities as batch duplicates.
        $this->actingAs($this->manager);
        $this->manager->update(['role' => 'admin']);
        app(OwnerBulkService::class)->save($this->validated([$this->editRow($owner), $this->editRow($second)]), $this->manager);
        $this->assertDatabaseCount('owners', 2);
    }

    public function test_worker_rechecks_identity_duplicates_created_after_queuing(): void
    {
        Queue::fake();
        $row = $this->row();
        $operation = app(OwnerBulkOperationService::class)->start($this->validated([$row]), $this->manager, [], (string) Str::uuid(), 1, 'draft');
        $this->create(['phone' => $row['phone']]);
        app(OwnerBulkOperationService::class)->process($operation->id);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertArrayHasKey('rows.0.phone', $operation->fresh()->errors);
        $this->assertDatabaseCount('owners', 1);
    }

    public function test_existing_duplicate_identity_cannot_hide_behind_the_current_owner_record(): void
    {
        $first = $this->create();
        $second = $this->create();
        $second->update(['ic_number' => $first->ic_number]);
        try {
            app(OwnerBulkService::class)->save($this->validated([$this->editRow($second)]), $this->manager);
            $this->fail('Expected duplicate validation.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('rows.0.ic', $exception->errors());
        }
    }
    public function test_zero_identity_values_are_not_skipped_by_bulk_duplicate_checks(): void
    {
        foreach (['ic', 'phone'] as $field) {
            $this->post(route('admin.owners.bulk-save'), [
                'rows_json' => json_encode([$this->row([$field => '0']), $this->row([$field => '0', 'rowNumber' => 2])]),
                'page' => 1,
            ])->assertSessionHasErrors('rows.1.'.$field);
        }
        $this->assertDatabaseCount('owners', 0);
    }
}
