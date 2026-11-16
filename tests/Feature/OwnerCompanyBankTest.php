<?php

namespace Tests\Feature;

use App\Http\Controllers\OwnersController;
use App\Models\{Owners, User};
use App\Services\BankAccountService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\{Blade, DB, Schema, Storage};
use Tests\TestCase;

class OwnerCompanyBankTest extends TestCase
{
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $definitions = [
            'users' => ['name', 'email', 'password', 'role', 'email_verified_at', 'account_permissions', 'status'],
            'owners' => ['user_id', 'agent_id', 'company_name', 'ic_number', 'phone', 'gender', 'address', 'postcode', 'city', 'state'],
            'companies' => ['name', 'ssm_new_number', 'ssm_old_number', 'address', 'phone', 'email', 'logo_path', 'ssm_cert_path'],
            'company_user' => ['company_id', 'user_id'],
            'properties' => ['owner_id', 'name'],
            'units' => ['owner_id', 'property_id', 'unit_no'],
            'rooms' => ['unit_id', 'room_no'],
            'bank_details' => ['bank_name', 'bank_code', 'account_holder_name', 'account_number'],
            'bank_user' => ['bank_detail_id', 'user_id'],
            'user_management' => ['user_id', 'deleted_at'],
            'audit_logs' => ['user_id', 'event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'],
        ];
        foreach ($definitions as $table => $fields) {
            Schema::create($table, function (Blueprint $schema) use ($fields, $table) {
                $schema->string('id')->primary();
                foreach ($fields as $field) $schema->text($field)->nullable();
                if ($table === 'bank_user') $schema->boolean('is_default')->default(true);
                if ($table === 'users') $schema->unique('email');
                $schema->timestamps();
            });
        }
        Storage::fake('local');
        Storage::fake('public');
        $this->manager = User::create([
            'name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'secret',
            'role' => 'ownerAdmin', 'email_verified_at' => now(),
        ]);
        $this->grants(['owner.tab', 'owner.create', 'owner.edit', 'owner.show', 'owner.delete']);
        $this->actingAs($this->manager);
    }

    private function grants(array $abilities): void
    {
        $this->manager->forceFill(['account_permissions' => $abilities])->save();
    }

    private function data(array $extra = []): array
    {
        return array_replace([
            'name' => 'Owner', 'email' => 'owner@example.test',
            'phone' => '0123456789', 'ic_number' => '900101145678', 'gender' => 'Male',
        ], $extra);
    }

    private function bank(): array
    {
        return ['bank_name' => 'Example Bank', 'account_holder_name' => 'Owner', 'account_number' => '0012345678'];
    }

    private function createOwner(array $extra = []): Owners
    {
        $this->post(route('admin.owners.store'), $this->data($extra))
            ->assertSessionHasNoErrors()->assertSessionHas('success')->assertRedirect(route('admin.owners.index'));
        return Owners::firstOrFail();
    }

    public function test_owner_can_be_created_without_company_or_bank(): void
    {
        $owner = $this->createOwner();
        $this->assertSame($this->manager->id, $owner->agent_id);
        $this->assertNull($owner->company_name);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('bank_details', 0);
    }

    public function test_company_only_and_bank_only_are_optional_independently(): void
    {
        $owner = $this->createOwner(['company_name' => 'Owner Company']);
        $this->assertSame('Owner Company', $owner->user->companies->first()->name);
        $this->assertDatabaseCount('bank_details', 0);
        $this->post(route('admin.owners.store'), $this->data($this->bank() + ['email' => 'second@example.test', 'ic_number' => '900101145679', 'phone' => '0123456780']))
            ->assertSessionHas('success');
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('bank_details', 1);
    }

    public function test_company_bank_and_certificate_are_linked_to_owner_and_account_is_encrypted(): void
    {
        $owner = $this->createOwner($this->bank() + [
            'company_name' => 'Owner Company', 'ssm_new_number' => '2026001234',
            'ssm_cert_path' => UploadedFile::fake()->create('ssm.pdf', 10, 'application/pdf'),
        ]);
        $company = $owner->user->companies->first();
        $this->assertStringContainsString('/owners/ssm_certs/', $company->ssm_cert_path);
        Storage::disk('local')->assertExists($company->ssm_cert_path);
        $bank = app(BankAccountService::class)->getDefaultForUser($owner->user);
        $this->assertSame('0012345678', $bank->account_number);
        $this->assertNotSame('0012345678', DB::table('bank_details')->value('account_number'));
        $this->assertSame($owner->user_id, DB::table('company_user')->value('user_id'));
        $this->assertSame($owner->user_id, DB::table('bank_user')->value('user_id'));
    }

    public function test_partial_company_or_bank_details_are_rejected_before_writing(): void
    {
        $this->post(route('admin.owners.store'), $this->data(['company_email' => 'office@example.test']))
            ->assertSessionHasErrors('company_name');
        $this->post(route('admin.owners.store'), $this->data(['bank_name' => 'Bank']))
            ->assertSessionHasErrors(['account_holder_name', 'account_number']);
        $this->post(route('admin.owners.store'), $this->data(array_replace($this->bank(), ['account_number' => 'ABC123'])))
            ->assertSessionHasErrors('account_number');
        $this->assertDatabaseCount('owners', 0);
    }

    public function test_edit_preserves_number_and_files_and_updates_linked_company_without_duplicates(): void
    {
        $owner = $this->createOwner($this->bank() + [
            'company_name' => 'Owner Company', 'ssm_new_number' => '2026001234',
            'ssm_cert_path' => UploadedFile::fake()->create('ssm.pdf', 10, 'application/pdf'),
        ]);
        $path = $owner->user->companies->first()->ssm_cert_path;
        $this->put(route('admin.owners.update', $owner), $this->data([
            'company_name' => 'Updated Company', 'ssm_new_number' => '2026001234',
            'bank_name' => 'Updated Bank', 'account_holder_name' => 'Updated Holder', 'account_number' => '',
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');
        $user = $owner->fresh()->user;
        $this->assertSame('Updated Company', $user->companies->first()->name);
        $this->assertSame($path, $user->companies->first()->ssm_cert_path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('0012345678', app(BankAccountService::class)->getDefaultForUser($user)->account_number);
        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('bank_details', 1);
        $this->put(route('admin.owners.update', $owner), $this->data(['name' => 'Updated Owner']))
            ->assertSessionHas('success');
        $this->assertSame('Updated Company', $owner->fresh()->user->companies->first()->name);
    }

    public function test_create_and_edit_permissions_include_optional_bank_access(): void
    {
        $this->grants(['owner.create']);
        $owner = $this->createOwner($this->bank());
        $view = (new OwnersController())->create();
        $html = view('adminSide.owners.partials.company-bank-fields', $view->getData())->render();
        $this->assertStringContainsString('name="account_number"', $html);

        $this->grants(['owner.edit']);
        $this->put(route('admin.owners.update', $owner), $this->data(array_replace($this->bank(), ['account_number' => '009999'])))
            ->assertSessionHas('success');
        $this->assertSame('009999', app(BankAccountService::class)->getDefaultForUser($owner->fresh()->user)->account_number);
        $view = (new OwnersController())->edit($owner->fresh());
        $this->assertNotNull($view->getData()['bankDetail']);
        $html = view('adminSide.owners.partials.company-bank-fields', $view->getData())->render();
        $this->assertStringContainsString('name="account_number"', $html);

        $this->grants(['owner.show']);
        $this->put(route('admin.owners.update', $owner), $this->data(array_replace($this->bank(), ['account_number' => '7777'])))
            ->assertSessionHas('error', 'You have no permission.');
        $this->assertSame('009999', app(BankAccountService::class)->getDefaultForUser($owner->fresh()->user)->account_number);
    }

    public function test_foreign_owner_cannot_be_edited_or_updated_and_permission_denials_flash(): void
    {
        $owner = $this->createOwner();
        $owner->update(['agent_id' => 'other-account']);
        $this->assertSame('errors.403', (new OwnersController())->edit($owner)->name());
        $this->put(route('admin.owners.update', $owner), $this->data(['name' => 'Intruder']))
            ->assertSessionHas('error', 'You have no permission.');
        $this->assertSame('Owner', $owner->fresh()->user->name);
        $this->grants([]);
        $this->post(route('admin.owners.store'), $this->data(['email' => 'denied@example.test']))
            ->assertSessionHas('error', 'You have no permission.');
        $this->assertDatabaseCount('owners', 1);
    }

    public function test_failure_rolls_back_all_records_and_removes_uploaded_files(): void
    {
        $this->mock(BankAccountService::class, function ($mock) {
            $mock->shouldReceive('saveForUser')->once()->andThrow(new \RuntimeException('Private failure details'));
        });
        $this->post(route('admin.owners.store'), $this->data($this->bank() + [
            'company_name' => 'Owner Company',
            'ssm_cert_path' => UploadedFile::fake()->create('ssm.pdf', 10, 'application/pdf'),
        ]))->assertSessionHas('error', 'Unable to create owner. Please try again.');
        $this->assertDatabaseCount('owners', 0);
        $this->assertDatabaseCount('companies', 0);
        $this->assertDatabaseCount('company_user', 0);
        $this->assertDatabaseCount('users', 1);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_create_edit_fields_render_and_saved_number_is_only_masked(): void
    {
        $owner = $this->createOwner($this->bank() + ['company_name' => 'Owner Company']);
        $controller = new OwnersController();
        foreach ([$controller->create(), $controller->edit($owner)] as $view) {
            $source = str_replace(['<x-app-layout>', '</x-app-layout>'], '', file_get_contents($view->getPath()));
            $html = Blade::render($source, $view->getData());
            $this->assertStringContainsString('Company Information', $html);
            $this->assertStringContainsString('Bank Account', $html);
            $this->assertSame(1, substr_count($html, 'name="company_name"'));
            $this->assertStringContainsString('multipart/form-data', $html);
            $this->assertStringNotContainsString('value="0012345678"', $html);
        }
    }

    public function test_certificate_access_is_scoped_to_the_owners_account(): void
    {
        $owner = $this->createOwner([
            'company_name' => 'Owner Company',
            'ssm_cert_path' => UploadedFile::fake()->create('ssm.pdf', 10, 'application/pdf'),
        ]);
        $controller = new OwnersController();
        $this->assertSame(200, $controller->companyCertificate($owner)->getStatusCode());
        $owner->update(['agent_id' => 'other-account']);
        $this->assertSame('errors.403', $controller->companyCertificate($owner)->name());
    }

    public function test_update_failure_preserves_original_user_company_bank_and_certificate(): void
    {
        $owner = $this->createOwner($this->bank() + [
            'company_name' => 'Original Company',
            'ssm_cert_path' => UploadedFile::fake()->create('original.pdf', 10, 'application/pdf'),
        ]);
        $originalPath = $owner->user->companies->first()->ssm_cert_path;
        $this->partialMock(BankAccountService::class, function ($mock) {
            $mock->shouldReceive('saveForUser')->once()->andThrow(new \RuntimeException('Bank write failed'));
        });
        $this->put(route('admin.owners.update', $owner), $this->data($this->bank() + [
            'name' => 'Changed Owner', 'company_name' => 'Changed Company',
            'ssm_cert_path' => UploadedFile::fake()->create('replacement.pdf', 10, 'application/pdf'),
        ]))->assertSessionHas('error', 'Unable to update owner. Please try again.');
        $user = $owner->fresh()->user;
        $this->assertSame('Owner', $user->name);
        $this->assertSame('Original Company', $user->companies->first()->name);
        $this->assertSame($originalPath, $user->companies->first()->ssm_cert_path);
        $this->assertSame([$originalPath], Storage::disk('local')->allFiles());
        $this->assertSame('0012345678', app(BankAccountService::class)->getDefaultForUser($user)->account_number);
    }

    public function test_invalid_uploads_and_duplicate_ssm_are_rejected(): void
    {
        $this->createOwner(['company_name' => 'Existing Company', 'ssm_new_number' => '2026001234']);
        $this->post(route('admin.owners.store'), $this->data([
            'email' => 'second@example.test', 'company_name' => 'Another Company', 'ssm_new_number' => '2026001234',
        ]))->assertSessionHasErrors('ssm_new_number');
        $this->post(route('admin.owners.store'), $this->data([
            'email' => 'second@example.test', 'company_name' => 'Another Company',
            'ssm_cert_path' => UploadedFile::fake()->create('bad.txt', 10, 'text/plain'),
            'company_logo' => UploadedFile::fake()->create('big.png', 2049, 'image/png'),
        ]))->assertSessionHasErrors(['ssm_cert_path', 'company_logo']);
        $this->assertDatabaseCount('owners', 1);
    }

    public function test_show_displays_company_and_masked_bank_details_for_show_permission(): void
    {
        $owner = $this->createOwner($this->bank() + [
            'company_name' => 'Owner Company', 'company_address' => 'Company Street',
            'company_phone' => '0312345678', 'company_email' => 'office@example.test',
            'ssm_old_number' => 'OLD123', 'ssm_new_number' => '2026001234',
            'ssm_cert_path' => UploadedFile::fake()->create('ssm.pdf', 10, 'application/pdf'),
        ]);
        $this->grants(['owner.show']);
        $view = (new OwnersController())->show($owner);
        $source = str_replace(['<x-app-layout>', '</x-app-layout>'], '', file_get_contents($view->getPath()));
        $html = Blade::render($source, $view->getData());
        foreach (['Company Details', 'Owner Company', 'Company Street', '0312345678', 'office@example.test', 'OLD123', '2026001234', 'Bank Details', 'Example Bank', '******5678', 'Preview SSM Certificate'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('0012345678', $html);
        $this->assertSame(200, (new OwnersController())->companyCertificate($owner)->getStatusCode());
        $owner->update(['agent_id' => 'another-account']);
        $this->assertSame('errors.403', (new OwnersController())->show($owner)->name());
        $this->assertSame('errors.403', (new OwnersController())->companyCertificate($owner)->name());
    }

    public function test_show_omits_optional_company_and_bank_sections_when_missing(): void
    {
        $owner = $this->createOwner();
        $view = (new OwnersController())->show($owner);
        $source = str_replace(['<x-app-layout>', '</x-app-layout>'], '', file_get_contents($view->getPath()));
        $html = Blade::render($source, $view->getData());
        $this->assertStringNotContainsString('Company Details', $html);
        $this->assertStringNotContainsString('Bank Details', $html);
        $this->assertStringContainsString('Owner Details', $html);
    }

    public function test_company_search_and_sort_use_company_table(): void
    {
        $owner = $this->createOwner(['company_name' => 'Linked Company']);
        $view = (new OwnersController())->index(Request::create('/admin/owners', 'GET', ['search' => 'Linked', 'sort' => 'c_asc']));
        $this->assertSame([$owner->id], $view->getData()['owners']->pluck('id')->all());
    }
    public function test_ic_and_phone_are_unique_per_management_account_on_create_and_edit(): void
    {
        $first = $this->createOwner();
        $this->post(route('admin.owners.store'), $this->data(['email' => 'second@example.test', 'phone' => '0123456780']))
            ->assertSessionHasErrors('ic_number');
        $this->post(route('admin.owners.store'), $this->data(['email' => 'second@example.test', 'ic_number' => '900101145679']))
            ->assertSessionHasErrors('phone');
        $this->put(route('admin.owners.update', $first), $this->data(['name' => 'Updated']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->post(route('admin.owners.store'), $this->data([
            'email' => 'second@example.test', 'phone' => '0123456780', 'ic_number' => '900101145679',
        ]))->assertSessionHas('success');
        $second = Owners::where('user_id', User::where('email', 'second@example.test')->value('id'))->firstOrFail();
        $this->put(route('admin.owners.update', $second), $this->data(['email' => 'second@example.test']))
            ->assertSessionHasErrors(['ic_number', 'phone']);
        $this->assertSame('0123456780', $second->fresh()->phone);
    }

    public function test_other_management_account_can_create_and_edit_the_same_ic_and_phone(): void
    {
        $this->createOwner();
        $other = User::create(['name' => 'Manager B', 'email' => 'manager-b@example.test', 'password' => 'secret', 'role' => 'ownerAdmin', 'email_verified_at' => now()]);
        $other->forceFill(['account_permissions' => ['owner.create', 'owner.edit']])->save();
        $this->actingAs($other)->post(route('admin.owners.store'), $this->data(['email' => 'owner-b@example.test']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $owner = Owners::where('agent_id', $other->id)->firstOrFail();
        $this->put(route('admin.owners.update', $owner), $this->data(['email' => 'owner-b@example.test']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertDatabaseCount('owners', 2);
    }

    public function test_super_admin_edit_uses_the_owners_account_and_inactive_duplicates_are_not_exempt(): void
    {
        $first = $this->createOwner();
        $first->user->update(['status' => 'inactive']);
        $this->post(route('admin.owners.store'), $this->data(['email' => 'second@example.test']))
            ->assertSessionHasErrors(['ic_number', 'phone']);
        $other = User::create(['name' => 'Manager B', 'email' => 'manager-b@example.test', 'password' => 'secret', 'role' => 'ownerAdmin']);
        $secondUser = User::create(['name' => 'Other Owner', 'email' => 'owner-b@example.test', 'password' => 'secret', 'role' => 'owner']);
        $second = Owners::create(['user_id' => $secondUser->id, 'agent_id' => $other->id, 'ic_number' => $first->ic_number, 'phone' => $first->phone, 'gender' => 'Male']);
        $this->manager->update(['role' => 'admin']);
        $this->put(route('admin.owners.update', $second), $this->data(['email' => 'owner-b@example.test']))
            ->assertSessionHasNoErrors()->assertSessionHas('success');
    }
}
