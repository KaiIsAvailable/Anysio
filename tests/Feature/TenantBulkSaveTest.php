<?php

namespace Tests\Feature;

use App\Http\Requests\BulkTenantRequest;
use App\Models\Tenants;
use App\Models\User;
use App\Services\BankAccountService;
use App\Services\CompanyService;
use App\Services\TenantBulkService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantBulkSaveTest extends TestCase
{
    private User $manager;

    private bool $superAdmin = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $definitions = [
            'users' => ['name', 'email', 'password', 'role', 'email_verified_at'],
            'tenants' => ['user_id', 'created_by', 'owner_id', 'tenant_type', 'phone', 'ic_number', 'passport', 'nationality', 'gender', 'occupation', 'ic_photo_path'],
            'companies' => ['name', 'ssm_new_number', 'ssm_old_number', 'address', 'phone', 'email', 'logo_path', 'ssm_cert_path'],
            'company_user' => ['company_id', 'user_id'],
            'emergency_contacts' => ['tenant_id', 'name', 'phone', 'relationship'],
            'bank_details' => ['bank_name', 'bank_code', 'account_holder_name', 'account_number'],
            'bank_user' => ['bank_detail_id', 'user_id'],
            'leases' => ['tenant_id', 'status'],
            'audit_logs' => ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'],
        ];
        foreach ($definitions as $table => $fields) {
            Schema::create($table, function (Blueprint $schema) use ($fields, $table) {
                $schema->string('id')->primary();
                foreach ($fields as $field) {
                    $schema->text($field)->nullable();
                }
                if ($table === 'tenants') {
                    $schema->string('status')->default('active');
                }
                if ($table === 'bank_user') {
                    $schema->boolean('is_default')->default(true);
                }
                if ($table === 'users') {
                    $schema->unique('email');
                }
                if ($table === 'tenants') {
                    $schema->unique(['ic_number', 'created_by', 'status']);
                    $schema->unique('passport');
                }
                $schema->timestamps();
            });
        }
        (require database_path('migrations/2026_11_16_000000_create_tenant_bulk_operations_table.php'))->up();
        $this->manager = User::create(['name' => 'Manager', 'email' => 'manager@example.com', 'password' => 'secret', 'role' => 'ownerAdmin']);
        $this->actingAs($this->manager);
        Gate::before(fn ($user, $ability) => $ability === 'super-admin' ? $this->superAdmin : true);
    }

    private function row(array $overrides = []): array
    {
        return array_replace([
            'action' => 'create', 'id' => null, 'version' => null, 'rowNumber' => 1,
            'name' => 'Tenant', 'email' => '', 'type' => 'individual', 'phone' => '0123456789',
            'ic' => '900101145678', 'passport' => '', 'nationality' => 'MALAYSIAN', 'gender' => 'Male', 'occupation' => '',
            'contacts' => [['name' => 'Contact', 'phone' => '0199999999', 'relationship' => 'Parent'], ['name' => '', 'phone' => '', 'relationship' => '']],
        ], $overrides);
    }

    public function test_bulk_rows_load_in_scoped_batches_and_can_restore_specific_draft_records(): void
    {
        $this->superAdmin = false;
        Gate::partialMock()->shouldReceive('allows')->andReturnUsing(fn ($ability) => $ability !== 'super-admin');
        Gate::shouldReceive('denies')->andReturnUsing(fn ($ability) => $ability === 'super-admin');
        $this->manager->update(['email_verified_at' => now()]);
        $ids = [];
        for ($index = 0; $index < 55; $index++) {
            $ids[] = (string) Str::ulid();
            DB::table('tenants')->insert(['id' => $ids[$index], 'user_id' => $this->manager->id,
                'created_by' => $this->manager->id, 'tenant_type' => 'individual', 'status' => 'active']);
        }
        sort($ids);
        $outside = (string) Str::ulid();
        DB::table('tenants')->insert(['id' => $outside, 'user_id' => $this->manager->id,
            'created_by' => (string) Str::ulid(), 'tenant_type' => 'individual', 'status' => 'active']);
        $url = route('admin.tenants.bulk-edit');
        $first = $this->getJson($url)->assertOk()->assertJsonCount(50, 'rows')
            ->assertJsonPath('nextCursor', $ids[49]);
        $this->assertSame(array_slice($ids, 0, 50), array_column($first->json('rows'), 'key'));
        $last = $this->getJson($url.'?after='.$ids[49])->assertOk()->assertJsonCount(5, 'rows')->assertJsonPath('nextCursor', null);
        $this->assertSame(array_slice($ids, 50), array_column($last->json('rows'), 'key'));
        $this->getJson($url.'?'.http_build_query(['ids' => [$ids[54], $outside]]))
            ->assertOk()->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.key', $ids[54]);
        $this->getJson($url.'?after=invalid')->assertUnprocessable();
    }

    private function validated(array $rows, array $uploads = []): array
    {
        $request = BulkTenantRequest::create('/', 'POST', ['rows_json' => json_encode($rows), 'page' => 1]);
        $request->validateRowDetails = true;
        $request->files->set('uploads', $uploads);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->validateResolved();

        return $request->validated('rows');
    }

    private function existing(): Tenants
    {
        return Tenants::with(['user.companies', 'user.bankDetails', 'emergencyContacts'])->firstOrFail();
    }

    public function test_create_update_and_remove_keep_account_links_and_audit_history(): void
    {
        $service = app(TenantBulkService::class);
        $counts = $service->save($this->validated([$this->row()]), $this->manager);
        $this->assertSame(1, $counts['created']);
        $tenant = $this->existing();
        $this->assertStringEndsWith('@anysio.local', $tenant->user->email);
        $this->assertSame($this->manager->id, $tenant->created_by);
        $this->assertCount(1, $tenant->emergencyContacts);
        $contactId = $tenant->emergencyContacts->first()->id;
        $this->assertSame(0, DB::table('bank_details')->count());
        $version = $service->revision($tenant, true);
        $service->save($this->validated([$this->row(['action' => 'update', 'id' => $tenant->id, 'version' => $version, 'name' => 'Updated', 'email' => $tenant->user->email])]), $this->manager);
        $tenant = $this->existing();
        $this->assertSame('Updated', $tenant->user->name);
        $this->assertSame($contactId, $tenant->emergencyContacts->first()->id);
        $service->save($this->validated([['action' => 'remove', 'id' => $tenant->id, 'version' => $service->revision($tenant, true), 'rowNumber' => 1]]), $this->manager);
        $this->assertSame('inactive', $tenant->fresh()->status);
        $this->assertNotNull($tenant->user->fresh());
        $this->assertGreaterThan(0, DB::table('audit_logs')->count());
    }

    public function test_corporate_company_and_optional_bank_are_saved_without_changing_leading_zeroes(): void
    {
        $row = $this->row(['type' => 'corporate', 'companyName' => 'Example Ltd', 'ssmNew' => '202600123456', 'ssmOld' => 'OLD123', 'companyAddress' => 'Address', 'companyPhone' => '0123456', 'companyEmail' => 'office@example.com', 'bankName' => 'Bank', 'accountHolder' => 'Tenant', 'accountNumber' => '00123456']);
        app(TenantBulkService::class)->save($this->validated([$row]), $this->manager);
        $tenant = $this->existing();
        $this->assertNull($tenant->ic_number);
        $this->assertSame('Example Ltd', $tenant->user->companies->first()->name);
        $this->assertSame('00123456', $tenant->user->bankDetails->first()->account_number);
        $this->assertSame(1, DB::table('company_user')->count());
    }

    public function test_duplicate_batch_is_rejected_before_any_records_are_written(): void
    {
        $before = User::count();
        try {
            app(TenantBulkService::class)->save($this->validated([$this->row(), $this->row(['rowNumber' => 2])]), $this->manager);
            $this->fail('Expected duplicate validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows.1.ic', $exception->errors());
        }
        $this->assertSame($before, User::count());
        $this->assertSame(0, Tenants::count());
    }

    public function test_ic_can_repeat_across_management_accounts_but_not_for_active_tenants_in_the_same_account(): void
    {
        $service = app(TenantBulkService::class);
        $service->save($this->validated([$this->row()]), $this->manager);
        $other = User::create(['name' => 'Other manager', 'email' => 'other-manager@example.com', 'password' => 'secret', 'role' => 'ownerAdmin']);
        $service->save($this->validated([$this->row()]), $other);
        $this->assertSame(2, Tenants::where('ic_number', $this->row()['ic'])->count());

        try {
            $service->save($this->validated([$this->row()]), $other);
            $this->fail('Expected a duplicate IC within the same account to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows.0.ic', $exception->errors());
        }
        $this->assertSame(2, Tenants::count());
    }

    public function test_updates_validate_ic_in_the_tenants_own_account_even_when_saved_by_an_admin(): void
    {
        $service = app(TenantBulkService::class);
        $other = User::create(['name' => 'Other manager', 'email' => 'other-manager@example.com', 'password' => 'secret', 'role' => 'ownerAdmin']);
        $service->save($this->validated([$this->row()]), $this->manager);
        $service->save($this->validated([$this->row()]), $other);
        $tenant = Tenants::where('created_by', $other->id)->with(['user.companies', 'user.bankDetails', 'emergencyContacts'])->firstOrFail();
        $row = $this->row(['action' => 'update', 'id' => $tenant->id, 'version' => $service->revision($tenant, true), 'name' => 'Updated tenant']);
        $firstTenant = Tenants::where('created_by', $this->manager->id)->with(['user.companies', 'user.bankDetails', 'emergencyContacts'])->firstOrFail();
        $firstRow = $this->row(['action' => 'update', 'id' => $firstTenant->id, 'version' => $service->revision($firstTenant, true), 'rowNumber' => 2]);
        $service->save($this->validated([$row, $firstRow]), $this->manager);
        $this->assertSame('Updated tenant', $tenant->user->fresh()->name);
        $this->assertSame($other->id, $tenant->fresh()->created_by);
    }

    public function test_an_inactive_tenants_ic_can_be_reused_for_a_new_active_tenant(): void
    {
        $service = app(TenantBulkService::class);
        $service->save($this->validated([$this->row()]), $this->manager);
        Tenants::firstOrFail()->update(['status' => 'inactive']);
        $service->save($this->validated([$this->row()]), $this->manager);
        $this->assertSame(1, Tenants::where('status', 'active')->count());
        $this->assertSame(2, Tenants::count());
    }

    public function test_stale_revision_and_active_lease_removal_are_rejected(): void
    {
        $service = app(TenantBulkService::class);
        $service->save($this->validated([$this->row()]), $this->manager);
        $tenant = $this->existing();
        $version = $service->revision($tenant, true);
        $tenant->user->update(['name' => 'Edited elsewhere']);
        try {
            $service->save($this->validated([$this->row(['action' => 'update', 'id' => $tenant->id, 'version' => $version])]), $this->manager);
            $this->fail('Expected stale revision rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows.0.version', $exception->errors());
        }
        $tenant = $this->existing();
        DB::table('leases')->insert(['id' => (string) Str::ulid(), 'tenant_id' => $tenant->id, 'status' => 'active']);
        try {
            $service->save($this->validated([['action' => 'remove', 'id' => $tenant->id, 'version' => $service->revision($tenant, true), 'rowNumber' => 1]]), $this->manager);
            $this->fail('Expected active lease rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rows.0.action', $exception->errors());
        }
        $this->assertSame('active', $tenant->fresh()->status);
    }

    public function test_account_boundary_is_enforced_even_with_module_permissions(): void
    {
        $service = app(TenantBulkService::class);
        $service->save($this->validated([$this->row()]), $this->manager);
        $tenant = $this->existing();
        $this->superAdmin = false;
        $otherManager = new User(['role' => 'ownerAdmin']);
        $otherManager->id = (string) Str::ulid();
        $this->expectException(AuthorizationException::class);
        $service->save($this->validated([$this->row(['action' => 'update', 'id' => $tenant->id, 'version' => $service->revision($tenant, true)])]), $otherManager);
    }

    public function test_failure_after_user_creation_rolls_back_the_entire_batch(): void
    {
        $this->mock(CompanyService::class)->shouldReceive('saveForTenant')->once()->andThrow(new \RuntimeException('Simulated failure'));
        $before = User::count();
        try {
            app(TenantBulkService::class)->save($this->validated([$this->row(['type' => 'corporate', 'companyName' => 'Company', 'ssmNew' => '202600123456', 'companyAddress' => 'Address'])]), $this->manager);
            $this->fail('Expected failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated failure', $exception->getMessage());
        }
        $this->assertSame($before, User::count());
        $this->assertSame(0, Tenants::count());
    }

    public function test_save_endpoint_redirects_with_session_message_and_draft_clear_marker(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->manager->update(['email_verified_at' => now()]);
        $this->post(route('admin.tenants.bulk-save'), ['rows_json' => json_encode([$this->row()]), 'page' => 1])
            ->assertSessionHasNoErrors();
        $operation = \App\Models\TenantBulkOperation::firstOrFail();
        $this->assertSame(0, Tenants::count());
        $this->assertSame('queued', $operation->status);
        app(\App\Services\TenantBulkOperationService::class)->process($operation->id);
        $this->get(route('admin.tenants.bulk-complete', $operation->request_key))
            ->assertRedirect(route('admin.tenants.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('tenant_bulk_saved', true)
            ->assertSessionHas('tenant_bulk_saved_draft_key', fn ($key) => str_starts_with($key, 'tenant-bulk:v1:'.$this->manager->id.':'.$this->manager->id.':1:') && preg_match('/:[01]{5}$/', $key));
        $this->assertSame(1, Tenants::count());
    }

    public function test_request_rejects_invalid_phone_contact_and_oversized_batches(): void
    {
        foreach ([
            [$this->row(['phone' => 'ABC'])],
            [$this->row(['contacts' => [['name' => 'Contact', 'phone' => '0123456789']]])],
            array_fill(0, 301, $this->row()),
        ] as $rows) {
            try {
                $this->validated($rows);
                $this->fail('Expected invalid batch rejection.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(0, Tenants::count());
    }

    public function test_multiple_new_tenants_use_one_email_lookup_and_bounded_queries(): void
    {
        $rows = [];
        for ($index = 0; $index < 10; $index++) {
            $rows[] = $this->row(['rowNumber' => $index + 1, 'email' => "tenant{$index}@example.com", 'ic' => '9001011456'.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
        }
        $validated = $this->validated($rows);
        DB::enableQueryLog();
        $counts = app(TenantBulkService::class)->save($validated, $this->manager);
        $queries = collect(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(10, $counts['created']);
        $this->assertSame(1, $queries->filter(fn ($query) => str_contains($query['query'], 'where LOWER(users.email) in'))->count());
        $this->assertLessThan(100, $queries->count());
    }

    private function pdf(string $name = 'document.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
    }

    private function logo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    public function test_optional_identity_upload_is_saved_replaced_and_preserved_when_omitted(): void
    {
        Storage::fake('local');
        $service = app(TenantBulkService::class);
        $row = $this->row(['uploadKey' => 'new-1', 'fileFields' => ['icPhoto']]);
        $uploads = ['new-1' => ['icPhoto' => $this->pdf()]];
        $service->save($this->validated([$row], $uploads), $this->manager, $uploads);
        $tenant = $this->existing();
        $oldPath = $tenant->ic_photo_path;
        Storage::disk('local')->assertExists($oldPath);

        $row = $this->row(['action' => 'update', 'id' => $tenant->id, 'version' => $service->revision($tenant, true), 'uploadKey' => $tenant->id, 'fileFields' => ['icPhoto']]);
        $uploads = [$tenant->id => ['icPhoto' => $this->pdf('replacement.pdf')]];
        $service->save($this->validated([$row], $uploads), $this->manager, $uploads);
        $tenant = $this->existing();
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($tenant->ic_photo_path);
        $path = $tenant->ic_photo_path;
        $row['version'] = $service->revision($tenant, true);
        $row['fileFields'] = [];
        $service->save($this->validated([$row]), $this->manager);
        $this->assertSame($path, $this->existing()->ic_photo_path);
    }

    public function test_endpoint_saves_company_logo_and_ssm_certificate(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->manager->update(['email_verified_at' => now()]);
        $row = $this->row(['type' => 'corporate', 'companyName' => 'Company', 'ssmNew' => '202600123456', 'companyAddress' => 'Address', 'uploadKey' => 'new-1', 'fileFields' => ['companyLogo', 'ssmCertificate']]);
        $this->post(route('admin.tenants.bulk-save'), ['rows_json' => json_encode([$row]), 'page' => 1, 'uploads' => ['new-1' => ['companyLogo' => $this->logo(), 'ssmCertificate' => $this->pdf()]]])
            ->assertSessionHasNoErrors();
        $operation = \App\Models\TenantBulkOperation::firstOrFail();
        app(\App\Services\TenantBulkOperationService::class)->process($operation->id);
        $this->assertSame('succeeded', $operation->fresh()->status, json_encode($operation->fresh()->errors));
        $company = $this->existing()->user->companies->first();
        Storage::disk('public')->assertExists($company->logo_path);
        Storage::disk('local')->assertExists($company->ssm_cert_path);
    }

    public function test_failed_batch_cleans_up_new_company_uploads_and_keeps_old_files(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $service = app(TenantBulkService::class);
        $row = $this->row(['type' => 'corporate', 'companyName' => 'Company', 'ssmNew' => '202600123456', 'companyAddress' => 'Address', 'uploadKey' => 'new-1', 'fileFields' => ['companyLogo', 'ssmCertificate']]);
        $uploads = ['new-1' => ['companyLogo' => $this->logo(), 'ssmCertificate' => $this->pdf()]];
        $service->save($this->validated([$row], $uploads), $this->manager, $uploads);
        $tenant = $this->existing();
        $company = $tenant->user->companies->first();
        $row = array_replace($row, ['action' => 'update', 'id' => $tenant->id, 'version' => $service->revision($tenant, true), 'bankName' => 'Bank', 'accountHolder' => 'Tenant', 'accountNumber' => '001234']);
        $this->mock(BankAccountService::class)->shouldReceive('saveForUser')->once()->andThrow(new \RuntimeException('Simulated bank failure'));
        $uploads = ['new-1' => ['companyLogo' => $this->logo(), 'ssmCertificate' => $this->pdf()]];
        try {
            app(TenantBulkService::class)->save($this->validated([$row], $uploads), $this->manager, $uploads);
            $this->fail('Expected batch failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated bank failure', $exception->getMessage());
        }
        Storage::disk('public')->assertExists($company->logo_path);
        Storage::disk('local')->assertExists($company->ssm_cert_path);
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame($company->logo_path, $company->fresh()->logo_path);
    }

    public function test_upload_validation_rejects_invalid_missing_and_inapplicable_files(): void
    {
        $row = $this->row(['uploadKey' => 'new-1', 'fileFields' => ['icPhoto']]);
        foreach ([[], ['new-1' => ['icPhoto' => UploadedFile::fake()->createWithContent('bad.txt', 'Not an image or PDF')]], ['new-1' => ['icPhoto' => UploadedFile::fake()->create('large.pdf', 2049, 'application/pdf')]]] as $uploads) {
            try {
                $this->validated([$row], $uploads);
                $this->fail('Expected file validation error.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('uploads.new-1.icPhoto', $exception->errors());
            }
        }
        $this->expectException(ValidationException::class);
        $this->validated([$row], ['new-1' => ['icPhoto' => $this->pdf(), 'companyLogo' => $this->logo()]]);
    }

    public function test_existing_file_metadata_and_preview_enforce_account_and_document_access(): void
    {
        Storage::fake('local');
        $service = app(TenantBulkService::class);
        $row = $this->row(['uploadKey' => 'new-1', 'fileFields' => ['icPhoto']]);
        $uploads = ['new-1' => ['icPhoto' => $this->pdf()]];
        $service->save($this->validated([$row], $uploads), $this->manager, $uploads);
        $tenant = $this->existing();
        $controller = app(\App\Http\Controllers\TenantsController::class);
        $files = app(\App\Services\FileService::class);
        $data = $controller->bulkEdit($service)->getData()['rows']->first();
        $this->assertSame(basename($tenant->ic_photo_path), $data['existingFiles']['icPhoto']['name']);
        $this->assertSame(route('admin.tenants.bulk-file', ['tenant' => $tenant->id, 'field' => 'icPhoto']), $data['existingFiles']['icPhoto']['url']);
        $this->assertSame('application/pdf', $controller->bulkFile($tenant, 'icPhoto', $files)->headers->get('Content-Type'));

        $documentDenied = true;
        \Illuminate\Support\Facades\Response::partialMock()->shouldReceive('view')
            ->with('errors.403', [], 403)->once()->andReturn(new \Illuminate\Http\Response('Access denied', 403));
        Gate::partialMock()->shouldReceive('denies')->andReturnUsing(function ($ability) use (&$documentDenied) {
            return $ability === 'super-admin' || ($documentDenied && $ability === 'tenant.view IC');
        });
        $this->assertSame(403, $controller->bulkFile($tenant, 'icPhoto', $files)->getStatusCode());
        $documentDenied = false;
        $tenant->created_by = (string) Str::ulid();
        try {
            $controller->bulkFile($tenant, 'icPhoto', $files);
            $this->fail('Expected another account to be denied.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_three_hundred_rows_are_queued_once_and_processed_atomically(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->manager->update(['email_verified_at' => now()]);
        $rows = [];
        for ($index = 0; $index < 300; $index++) {
            $rows[] = $this->row(['rowNumber' => $index + 1, 'name' => 'Batch Tenant '.$index, 'ic' => '900101'.str_pad((string) $index, 6, '0', STR_PAD_LEFT)]);
        }
        $key = (string) Str::uuid();
        $body = ['rows_json' => json_encode($rows), 'request_key' => $key, 'page' => 1];
        $this->post(route('admin.tenants.bulk-save'), $body)->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.tenants.bulk-edit', ['page' => 1, 'operation' => $key]));
        $this->post(route('admin.tenants.bulk-save'), $body)->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SaveTenantBulkOperation::class, 1);
        $this->assertSame(1, \App\Models\TenantBulkOperation::count());
        $this->assertSame(0, Tenants::count());
        $operation = \App\Models\TenantBulkOperation::firstOrFail();
        $this->assertCount(300, $operation->payload['rows']);
        $this->assertStringNotContainsString('Batch Tenant', $operation->getRawOriginal('payload'));
        $operations = app(\App\Services\TenantBulkOperationService::class);
        $operations->process($operation->id);
        $this->assertSame('succeeded', $operation->fresh()->status, json_encode($operation->fresh()->errors));
        $this->assertSame(300, Tenants::count());
        $this->assertSame(300, $operation->fresh()->processed);
        $this->assertNull($operation->fresh()->payload);
        $operations->process($operation->id);
        $this->assertSame(300, Tenants::count());
    }

    public function test_progress_shows_real_counts_but_reaches_one_hundred_only_after_commit(): void
    {
        $this->manager->update(['email_verified_at' => now()]);
        $operation = \App\Models\TenantBulkOperation::create(['actor_id' => $this->manager->id, 'manager_id' => $this->manager->id, 'request_key' => (string) Str::uuid(), 'status' => 'processing', 'total' => 300, 'page' => 1, 'draft_key' => 'test']);
        $cache = \Illuminate\Support\Facades\Cache::store('file');
        $cache->put('tenant-bulk-progress:'.$operation->id, 150, 60);
        $url = route('admin.tenants.bulk-progress', $operation->request_key);
        $this->getJson($url)->assertOk()->assertJsonPath('processed', 150)->assertJsonPath('percent', 50);
        $cache->put('tenant-bulk-progress:'.$operation->id, 300, 60);
        $this->getJson($url)->assertJsonPath('percent', 99);
        $operation->update(['status' => 'succeeded', 'processed' => 300]);
        $this->getJson($url)->assertJsonPath('percent', 100);
        $cache->forget('tenant-bulk-progress:'.$operation->id);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => 'secret', 'role' => 'ownerAdmin', 'email_verified_at' => now()]);
        $this->actingAs($other)->getJson($url)->assertNotFound();
    }

    public function test_invalid_duplicates_are_rejected_before_any_save_is_queued(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->manager->update(['email_verified_at' => now()]);
        $this->from(route('admin.tenants.bulk-edit'))->post(route('admin.tenants.bulk-save'), [
            'rows_json' => json_encode([$this->row(), $this->row(['rowNumber' => 2])]),
            'request_key' => (string) Str::uuid(), 'page' => 1,
        ])->assertRedirect(route('admin.tenants.bulk-edit'))->assertSessionHasErrors('rows.1.ic');
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
        $this->assertSame(0, \App\Models\TenantBulkOperation::count());
        $this->assertSame(0, Tenants::count());
    }

    public function test_worker_rechecks_conflicts_created_after_preflight_and_returns_session_errors(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->manager->update(['email_verified_at' => now()]);
        $key = (string) Str::uuid();
        $this->post(route('admin.tenants.bulk-save'), ['rows_json' => json_encode([$this->row()]), 'request_key' => $key, 'page' => 1])->assertSessionHasNoErrors();
        $operation = \App\Models\TenantBulkOperation::firstOrFail();
        app(TenantBulkService::class)->save($this->validated([$this->row()]), $this->manager);
        app(\App\Services\TenantBulkOperationService::class)->process($operation->id);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertSame(1, Tenants::count());
        $this->get(route('admin.tenants.bulk-complete', $key))->assertRedirect(route('admin.tenants.bulk-edit', ['page' => 1]))
            ->assertSessionHasErrors('rows.0.ic')->assertSessionMissing('tenant_bulk_saved');
    }

    public function test_dedicated_database_queue_runs_the_serialized_job(): void
    {
        (require database_path('migrations/0001_01_01_000002_create_jobs_table.php'))->up();
        $operations = app(\App\Services\TenantBulkOperationService::class);
        $operation = $operations->start($this->validated([$this->row()]), $this->manager, [], (string) Str::uuid(), 1, 'draft-key');
        $this->assertSame(1, DB::table('jobs')->where('queue', 'tenant-bulk')->count());
        $this->assertSame(0, Tenants::count());
        app('queue')->connection('tenant_bulk')->pop('tenant-bulk')->fire();
        $this->assertSame('succeeded', $operation->fresh()->status, json_encode($operation->fresh()->errors));
        $this->assertSame(1, Tenants::count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_preflight_reports_all_field_errors_including_rows_after_the_first_fifty(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->manager->update(['email_verified_at' => now()]);
        $rows = [];
        for ($index = 0; $index < 60; $index++) {
            $rows[] = $this->row(['rowNumber' => $index + 8, 'gender' => '', 'contacts' => []]);
        }
        $this->post(route('admin.tenants.bulk-save'), ['rows_json' => json_encode($rows), 'page' => 1])
            ->assertSessionHasErrors(['rows.0.contacts', 'rows.59.contacts', 'rows.59.gender']);
        \Illuminate\Support\Facades\Queue::assertNothingPushed();
        $this->assertSame(0, \App\Models\TenantBulkOperation::count());
        $this->assertSame(0, Tenants::count());
    }

    public function test_bulk_contacts_require_a_relationship_and_at_least_one_complete_contact(): void
    {
        foreach ([[], [['name' => 'Contact', 'phone' => '0199999999', 'relationship' => '']]] as $contacts) {
            try {
                $this->validated([$this->row(['contacts' => $contacts])]);
                $this->fail('Expected incomplete contact validation to fail.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($contacts ? 'rows.0.contacts.0.relationship' : 'rows.0.contacts', $exception->errors());
            }
        }
        $rows = $this->validated([$this->row()]);
        $this->assertCount(1, $rows[0]['contacts']);
        $this->assertSame('Parent', $rows[0]['contacts'][0]['relationship']);
    }

    public function test_action_permissions_are_checked_once_per_batch_instead_of_per_row(): void
    {
        Gate::partialMock()->shouldReceive('allows')->with('super-admin')->once()->andReturn(false);
        Gate::shouldReceive('denies')->with('tenant.tab')->once()->andReturn(false);
        Gate::shouldReceive('denies')->with('tenant.bulk edit')->once()->andReturn(false);
        Gate::shouldReceive('allows')->with('tenant.modify bank details')->once()->andReturn(false);
        Gate::shouldReceive('allows')->with('tenant.create')->once()->andReturn(true);
        $this->assertSame([false, false], app(TenantBulkService::class)->authorizeRows(array_fill(0, 300, $this->row()), $this->manager));
    }

    public function test_revoking_bulk_edit_denies_the_page_save_and_queued_writes(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $this->manager->update(['email_verified_at' => now()]);
        $operations = app(\App\Services\TenantBulkOperationService::class);
        $operation = $operations->start([$this->row()], $this->manager, [], (string) Str::uuid(), 1, 'draft');
        Gate::partialMock()->shouldReceive('allows')->andReturnUsing(fn ($ability) => $ability !== 'super-admin');
        Gate::shouldReceive('denies')->andReturnUsing(fn ($ability) => in_array($ability, ['super-admin', 'tenant.bulk edit'], true));

        $view = app(\App\Http\Controllers\TenantsController::class)->bulkEdit(app(TenantBulkService::class));
        $this->assertSame('errors.403', $view->name());
        $this->post(route('admin.tenants.bulk-save'), ['rows_json' => json_encode([$this->row()]), 'page' => 1])
            ->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        $this->get(route('admin.tenants.bulk-progress', $operation->request_key))
            ->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        $this->get(route('admin.tenants.bulk-complete', $operation->request_key))
            ->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        $this->assertNull($operation->fresh()->acknowledged_at);
        $operations->process($operation->id);
        $this->assertSame('failed', $operation->fresh()->status);
        $this->assertSame(['bulk' => ['You have no permission.']], $operation->fresh()->errors);
        $this->assertDatabaseCount('tenants', 0);
    }
}
