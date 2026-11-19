<?php

namespace Tests\Feature;

use App\Http\Controllers\{AccountPermissionController, SettingsController, TenantsController};
use App\Models\User;
use App\Services\{AccountPermissionService, SettingService};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Blade, DB, Gate, Schema};
use Illuminate\Support\{Str, ViewErrorBag};
use Tests\TestCase;

class AccountPermissionTest extends TestCase
{
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('role');
            $table->string('password')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        foreach (['system' => 'admin', 'abc' => 'agentAdmin', 'def' => 'agentAdmin', 'ghi' => 'ownerAdmin', 'staff-a' => 'staff', 'staff-b' => 'staff'] as $name => $role) {
            $this->ids[$name] = (string) Str::ulid();
            DB::table('users')->insert(['id' => $this->ids[$name], 'name' => $name, 'email' => $name.'@example.com', 'role' => $role, 'email_verified_at' => now()]);
        }
        (require database_path('migrations/2026_11_07_000001_add_account_permissions_to_users_table.php'))->up();

        Schema::create('user_management', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('user_id'); $table->softDeletes();
        });
        Schema::create('staff', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('user_id'); $table->string('user_mgnt_id');
        });
        (require database_path('migrations/2026_11_19_000001_add_phone_to_staff_table.php'))->up();
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id'); $table->string('team_id')->nullable(); $table->string('name'); $table->string('guard_name'); $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->increments('id'); $table->string('name'); $table->string('guard_name');
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->integer('role_id'); $table->integer('permission_id');
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->integer('role_id'); $table->string('model_type'); $table->string('model_id'); $table->string('team_id');
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->string('id')->primary(); $table->string('user_id')->nullable();
            foreach (['event', 'auditable_type', 'auditable_id', 'old_values', 'new_values', 'ip_address', 'user_agent'] as $column) $table->text($column)->nullable();
            $table->timestamps();
        });
        DB::table('user_management')->insert(['id' => 'workspace', 'user_id' => $this->ids['abc']]);
        DB::table('roles')->insert(['id' => 1, 'team_id' => $this->ids['abc'], 'name' => 'Accountant', 'guard_name' => 'web']);
        DB::table('permissions')->insert(['id' => 1, 'name' => 'tenant.edit', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => 1]);
        foreach (['staff-a', 'staff-b'] as $name) {
            DB::table('staff')->insert(['id' => $name, 'user_id' => $this->ids[$name], 'user_mgnt_id' => 'workspace']);
            DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => User::class, 'model_id' => $this->ids[$name], 'team_id' => $this->ids['abc']]);
        }
    }

    public function test_permission_registration_preserves_existing_grants_and_uses_one_lookup_when_current(): void
    {
        Schema::table('permissions', fn (Blueprint $table) => $table->timestamps());
        config(['permissions.modules' => ['leases' => ['show', 'check out lease' => ['save draft', 'approve refund']]]]);
        $provider = new \App\Providers\AppServiceProvider(app());
        $provider->boot();
        foreach (['leases.show', 'leases.check out lease', 'leases.save draft', 'leases.approve refund'] as $name) {
            $this->assertTrue(DB::table('permissions')->where('name', $name)->exists());
        }
        $this->assertTrue(DB::table('role_has_permissions')->where('permission_id', 1)->exists());
        DB::enableQueryLog();
        DB::flushQueryLog();
        $provider->boot();
        $permissionReads = collect(DB::getQueryLog())->filter(fn ($entry) =>
            str_starts_with(strtolower($entry['query']), 'select') && str_contains($entry['query'], '"permissions"'));
        $this->assertCount(1, $permissionReads);
        DB::disableQueryLog();
        $this->assertSame(5, DB::table('permissions')->count());
    }

    private function user(string $name): User
    {
        return User::findOrFail($this->ids[$name]);
    }

    public function test_ongoing_maintenance_dashboard_permission_requires_both_the_account_and_staff_role_grants(): void
    {
        $ability = 'dashboard.ongoing maintenance list';
        $this->assertContains($ability, AccountPermissionService::names());
        $this->grant('abc', [$ability]);
        $this->assertTrue(Gate::forUser($this->user('abc'))->allows($ability));
        $this->assertFalse(Gate::forUser($this->user('staff-a'))->allows($ability));
        DB::table('permissions')->insert(['id' => 2, 'name' => $ability, 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => 2]);
        $this->assertTrue(Gate::forUser($this->user('staff-a'))->allows($ability));
        $this->grant('abc', []);
        $this->assertFalse(Gate::forUser($this->user('staff-a'))->allows($ability));
        $this->assertTrue(Gate::forUser($this->user('system'))->allows($ability));
    }

    public function test_staff_role_can_grant_utilities_only_without_invoice_property_or_lease_access(): void
    {
        DB::table('role_has_permissions')->delete();
        foreach (['utilities.tab', 'utilities.enter readings'] as $index => $ability) {
            DB::table('permissions')->insert(['id' => $index + 2, 'name' => $ability, 'guard_name' => 'web']);
            DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => $index + 2]);
        }
        $gate = Gate::forUser($this->user('staff-a'));
        $this->assertTrue($gate->allows('utilities.tab'));
        $this->assertTrue($gate->allows('utilities.enter readings'));
        $this->assertSame('admin.utilities.index', app(\App\Services\LandingPageService::class)->routeFor($this->user('staff-a')));
        foreach (['invoice.tab', 'property.edit', 'leases.add manual invoice', 'utilities.generate invoices',
            'utilities.manage meters', 'utilities.edit tariffs'] as $ability) $this->assertFalse($gate->allows($ability));
        DB::table('roles')->where('id', 1)->update(['team_id' => $this->ids['def']]);
        $this->assertFalse($gate->allows('utilities.tab'));
    }

    private function grant(string $name, array $permissions): void
    {
        DB::table('users')->where('id', $this->ids[$name])->update(['account_permissions' => json_encode($permissions)]);
    }

    public function test_authorized_staff_can_create_staff_in_their_own_management_account(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_agree')->default(false);
        });
        Schema::table('staff', function (Blueprint $table) {
            $table->string('role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        DB::table('permissions')->insert(['id' => 2, 'name' => 'staff.create', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => 2]);
        // Creating staff requires both the assigned role and the manager's grant.
        $this->grant('abc', ['staff.create']);
        DB::table('user_management')->insert(['id' => 'other-workspace', 'user_id' => $this->ids['def']]);

        $this->actingAs($this->user('staff-a'))->post(route('admin.staff.store'), [
            'name' => 'New colleague',
            'email' => 'new-colleague@example.com',
            'phone' => '01234567890123456789',
            'password' => 'test-password',
            'password_confirmation' => 'test-password',
            'role' => 'Accountant',
            'verify_email_now' => '1',
            'user_mgnt_id' => 'other-workspace',
        ])->assertRedirect(route('admin.staff.index'))
            ->assertSessionHas('success', 'Staff created successfully!');

        $created = User::where('email', 'new-colleague@example.com')->firstOrFail();
        $this->assertDatabaseHas('staff', [
            'user_id' => $created->id,
            'user_mgnt_id' => 'workspace',
            'role' => 'Accountant',
        ]);
        $this->assertDatabaseHas('staff', ['user_id' => $created->id, 'phone' => '01234567890123456789']);
        $this->assertDatabaseHas('model_has_roles', [
            'model_id' => $created->id,
            'role_id' => 1,
            'team_id' => $this->ids['abc'],
        ]);
        $this->assertTrue(Gate::forUser($created)->allows('staff.create'));

        $this->grant('abc', ['staff.create', 'staff.edit']);
        $this->actingAs($this->user('abc'));
        $update = ['name' => $created->name, 'email' => $created->email, 'role' => 'Accountant', 'is_active' => '1'];
        foreach ([str_repeat('1', 21), '+60123456789', '012 3456789', '012abc', '1e10', '123.45'] as $phone) {
            $this->postJson(route('admin.staff.store'), $update + ['phone' => $phone, 'password' => 'test-password', 'password_confirmation' => 'test-password'])
                ->assertUnprocessable()->assertJsonValidationErrors('phone');
            $this->putJson(route('admin.staff.update', $created->id), $update + ['phone' => $phone])
                ->assertUnprocessable()->assertJsonValidationErrors('phone');
        }
        $this->assertDatabaseHas('staff', ['user_id' => $created->id, 'phone' => '01234567890123456789']);
        $this->put(route('admin.staff.update', $created->id), $update + ['phone' => '0123456789'])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('staff', ['user_id' => $created->id, 'phone' => '0123456789']);
        $this->put(route('admin.staff.update', $created->id), $update)->assertRedirect();
        $this->assertDatabaseHas('staff', ['user_id' => $created->id, 'phone' => '0123456789']);
        $this->put(route('admin.staff.update', $created->id), $update + ['phone' => ''])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('staff', ['user_id' => $created->id, 'phone' => null]);
    }

    public function test_staff_without_create_permission_cannot_use_their_managers_grant(): void
    {
        $this->grant('abc', ['staff.create']);
        $this->actingAs($this->user('staff-a'))->post(route('admin.staff.store'), [
            'email' => 'blocked-colleague@example.com',
        ])->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        $this->assertDatabaseMissing('users', ['email' => 'blocked-colleague@example.com']);
    }

    public function test_staff_create_page_redirects_to_the_reusable_modal_and_preserves_authorization(): void
    {
        $this->actingAs($this->user('abc'))->get(route('admin.staff.create'))
            ->assertRedirect(route('admin.staff.index', ['create' => 1]));
        $this->grant('abc', []);
        $this->actingAs($this->user('abc'));
        $this->assertSame('errors.403', app(\App\Http\Controllers\StaffController::class)->create(request())->name());
    }

    public function test_staff_list_reuses_creation_modal_with_scoped_management_roles(): void
    {
        view()->share('errors', new ViewErrorBag());
        Schema::table('staff', fn (Blueprint $table) => $table->timestamps());
        DB::table('user_management')->insert(['id' => 'other-workspace', 'user_id' => $this->ids['def']]);
        DB::table('roles')->insert(['id' => 2, 'team_id' => $this->ids['def'], 'name' => 'Other role', 'guard_name' => 'web']);
        $source = file_get_contents(resource_path('views/adminSide/userManagement/staff/index.blade.php'));
        $source = str_replace(['<x-app-layout>', '</x-app-layout>'], ['<div>', '</div>'], $source);

        foreach (['abc', 'system'] as $actor) {
            $this->actingAs($this->user($actor));
            $view = app(\App\Http\Controllers\StaffController::class)->index(request());
            $data = $view->getData();
            $modalData = $data['staffCreateData'];
            if ($actor === 'abc') {
                $this->assertSame('workspace', $modalData['managementId']);
                $this->assertSame([], $modalData['managementOptions']);
                $this->assertSame(['Accountant'], $modalData['roles']->pluck('name')->all());
            } else {
                $this->assertCount(2, $modalData['managementOptions']);
                $this->assertSame(['Accountant'], $modalData['managementOptions'][0]['roles']->pluck('name')->all());
                $this->assertSame(['Other role'], $modalData['managementOptions'][1]['roles']->pluck('name')->all());
            }
            $html = Blade::render($source, array_merge($data, ['errors' => new ViewErrorBag()]));
            $this->assertStringContainsString('quick-staff-name', $html);
            $this->assertStringContainsString('submitStaff($el)', $html);
            $this->assertStringNotContainsString(route('admin.staff.create'), $html);
        }
    }

    public function test_staff_fee_category_access_requires_both_manager_and_role_grants(): void
    {
        DB::table('permissions')->insert(['id' => 2, 'name' => 'fee types.deposit', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => 1, 'permission_id' => 2]);
        $this->grant('abc', ['fee types.deposit']);
        $this->actingAs($this->user('staff-a'));
        $this->assertSame(['deposit'], \App\Services\FeeTypeService::allowedCategories());
        $this->grant('abc', []);
        $this->assertSame([], \App\Services\FeeTypeService::allowedCategories());
        $this->assertDatabaseHas('role_has_permissions', ['role_id' => 1, 'permission_id' => 2]);
    }

    public function test_migration_preserves_existing_accounts_and_new_accounts_start_without_grants(): void
    {
        foreach (['abc', 'def', 'ghi'] as $name) {
            $this->assertEquals(AccountPermissionService::names(), $this->user($name)->account_permissions);
        }
        $new = new User(['role' => 'agentAdmin']);
        $this->assertFalse(Gate::forUser($new)->allows('tenant.edit'));
        $this->assertNotContains('account_permissions', $new->getFillable());
    }

    public function test_accounts_with_the_same_type_have_independent_permissions_and_role_gates_remain(): void
    {
        $this->grant('abc', ['tenant.edit']);
        $this->grant('def', ['tenant.create']);
        $this->grant('ghi', []);
        $this->assertTrue(Gate::forUser($this->user('abc'))->allows('tenant.edit'));
        $this->assertFalse(Gate::forUser($this->user('def'))->allows('tenant.edit'));
        $this->assertTrue(Gate::forUser($this->user('def'))->allows('tenant.create'));
        $this->assertFalse(Gate::forUser($this->user('ghi'))->allows('tenant.edit'));
        $this->assertTrue(Gate::forUser($this->user('ghi'))->allows('owner-admin'));
        $this->assertTrue(Gate::forUser($this->user('abc'))->allows('agent-admin'));
        $this->assertFalse(Gate::forUser($this->user('ghi'))->allows('agent-admin'));
        $this->assertTrue(Gate::forUser($this->user('system'))->allows('tenant.edit'));
    }

    public function test_staff_access_requires_manager_grants_without_rewriting_shared_roles(): void
    {
        $this->actingAs($this->user('def'));
        // Gate::forUser must resolve the staff's own manager, not the signed-in account.
        foreach (['staff-a', 'staff-b'] as $staff) {
            $this->assertTrue(Gate::forUser($this->user($staff))->allows('tenant.edit'));
            $this->assertFalse(Gate::forUser($this->user($staff))->allows('tenant.create'));
        }
        $this->grant('abc', []);
        $this->assertFalse(Gate::forUser($this->user('abc'))->allows('tenant.edit'));
        foreach (['staff-a', 'staff-b'] as $staff) {
            $this->assertFalse(Gate::forUser($this->user($staff))->allows('tenant.edit'));
        }
        $this->assertSame(1, DB::table('role_has_permissions')->count());
        $this->assertSame(2, DB::table('model_has_roles')->count());
        $this->assertTrue(Gate::forUser($this->user('def'))->allows('tenant.edit'));
        $this->grant('abc', ['tenant.edit']);
        $this->assertTrue(Gate::forUser($this->user('staff-a'))->allows('tenant.edit'));
        DB::table('role_has_permissions')->delete();
        $this->assertFalse(Gate::forUser($this->user('staff-a'))->allows('tenant.edit'));
    }

    public function test_staff_cannot_inherit_a_role_from_another_workspace(): void
    {
        DB::table('roles')->where('id', 1)->update(['team_id' => $this->ids['def']]);
        $this->assertFalse(Gate::forUser($this->user('staff-a'))->allows('tenant.edit'));
    }

    public function test_processing_fee_controls_require_both_manager_and_staff_permissions(): void
    {
        $abilities = ['invoice.edit processing fee', 'invoice.remove processing fee'];
        foreach ($abilities as $index => $ability) {
            DB::table('permissions')->insert(['id' => $index + 2, 'name' => $ability, 'guard_name' => 'web']);
        }
        $source = file_get_contents(resource_path('views/components/modals/payment-modal.blade.php'));
        $section = substr($source, strpos($source, '{{-- Processing fee follows'));
        $section = substr($section, 0, strpos($section, '{{-- Cancelled Penalty State'));

        foreach ([false, true] as $managerAllowed) {
            $this->grant('abc', $managerAllowed ? $abilities : []);
            foreach ([false, true] as $staffAllowed) {
                DB::table('role_has_permissions')->whereIn('permission_id', [2, 3])->delete();
                if ($staffAllowed) {
                    DB::table('role_has_permissions')->insert([
                        ['role_id' => 1, 'permission_id' => 2],
                        ['role_id' => 1, 'permission_id' => 3],
                    ]);
                }
                foreach ($abilities as $ability) {
                    $this->assertSame($managerAllowed, Gate::forUser($this->user('abc'))->allows($ability));
                    foreach (['staff-a', 'staff-b'] as $staff) {
                        $this->assertSame($staffAllowed && $managerAllowed, Gate::forUser($this->user($staff))->allows($ability));
                    }
                }
                foreach (['abc' => $managerAllowed, 'staff-a' => $staffAllowed && $managerAllowed] as $actor => $allowed) {
                    $this->actingAs($this->user($actor));
                    $html = Blade::render($section);
                    $this->assertSame(!$allowed, str_contains($html, 'readonly'));
                    $this->assertSame($allowed, str_contains($html, 'Cancel/Remove Processing Fee'));
                }
            }
        }
    }

    public function test_manager_can_only_delegate_processing_fee_permissions_they_have(): void
    {
        $this->grant('abc', ['settings.edit user role']);
        DB::table('permissions')->insert([
            ['id' => 2, 'name' => 'invoice.edit processing fee', 'guard_name' => 'web'],
            ['id' => 3, 'name' => 'invoice.remove processing fee', 'guard_name' => 'web'],
        ]);
        $this->actingAs($this->user('abc'));
        $url = route('admin.settings.user-role-permissions.update');
        $payload = ['permissions' => [1 => [
            'invoice.edit processing fee' => '1',
            'invoice.remove processing fee' => '1',
        ]]];
        $this->put($url, $payload)->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        $this->assertDatabaseHas('role_has_permissions', ['role_id' => 1, 'permission_id' => 1]);
        $this->assertFalse(Gate::forUser($this->user('staff-a'))->allows('invoice.edit processing fee'));

        $this->grant('abc', ['settings.edit user role', 'invoice.edit processing fee', 'invoice.remove processing fee']);
        $this->actingAs($this->user('abc'));
        $this->put($url, $payload)->assertRedirect()->assertSessionHas('success');
        $this->assertTrue(Gate::forUser($this->user('staff-a'))->allows('invoice.edit processing fee'));
        $this->grant('abc', []);
        $this->assertFalse(Gate::forUser($this->user('staff-a'))->allows('invoice.edit processing fee'));
        $this->assertDatabaseHas('role_has_permissions', ['role_id' => 1, 'permission_id' => 2]);
    }

    public function test_existing_staff_role_editor_still_changes_shared_permissions_only_in_its_workspace(): void
    {
        DB::table('roles')->insert(['id' => 2, 'team_id' => $this->ids['def'], 'name' => 'Accountant', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert(['role_id' => 2, 'permission_id' => 1]);
        $originalAccountGrants = $this->user('abc')->account_permissions;
        $this->actingAs($this->user('abc'));
        $url = route('admin.settings.user-role-permissions.update');
        $this->put($url, ['permissions' => []])->assertRedirect()
            ->assertSessionHas('success', 'Staff role permissions updated successfully.');
        $this->assertFalse(Gate::forUser($this->user('staff-a'))->allows('tenant.edit'));
        $this->assertFalse(Gate::forUser($this->user('staff-b'))->allows('tenant.edit'));
        $this->assertTrue(DB::table('role_has_permissions')->where('role_id', 2)->exists());
        $this->assertSame($originalAccountGrants, $this->user('abc')->account_permissions);

        $this->put($url, ['permissions' => [1 => ['tenant.edit' => '1']]])->assertRedirect();
        $this->assertTrue(Gate::forUser($this->user('staff-a'))->allows('tenant.edit'));
        $this->assertTrue(Gate::forUser($this->user('staff-b'))->allows('tenant.edit'));
    }

    public function test_role_creation_cannot_assign_permissions_missing_from_the_manager(): void
    {
        $this->grant('abc', ['settings.create new role']);
        $this->actingAs($this->user('abc'));
        $payload = ['name' => 'New role', 'permissions' => ['tenant.edit']];
        $this->post(route('admin.roles.store'), $payload)->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        $this->assertDatabaseMissing('roles', ['name' => 'New role']);

        $this->grant('abc', ['settings.create new role', 'tenant.edit']);
        $this->actingAs($this->user('abc'));
        $this->postJson(route('admin.roles.store'), $payload)->assertCreated();
        $this->assertDatabaseHas('roles', ['name' => 'New role', 'team_id' => $this->ids['abc']]);
    }

    public function test_rejected_role_batch_does_not_partially_clear_other_roles(): void
    {
        $this->grant('abc', ['settings.edit user role', 'tenant.edit']);
        DB::table('roles')->insert(['id' => 2, 'team_id' => $this->ids['abc'], 'name' => 'Second role', 'guard_name' => 'web']);
        $this->actingAs($this->user('abc'))->put(route('admin.settings.user-role-permissions.update'), [
            'permissions' => [1 => [], 2 => ['invoice.edit processing fee' => '1']],
        ])->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        $this->assertDatabaseHas('role_has_permissions', ['role_id' => 1, 'permission_id' => 1]);
        $this->assertSame(1, DB::table('role_has_permissions')->count());
    }

    public function test_staff_editor_disables_permissions_not_granted_to_the_manager(): void
    {
        $this->grant('abc', ['settings.create new role', 'settings.edit user role', 'tenant.edit']);
        $this->actingAs($this->user('abc'));
        $html = view('adminSide.setting.partials.staff-role-permissions', [
            'roles' => \App\Models\Role::with('permissions')->where('team_id', $this->ids['abc'])->get(),
            'modules' => config('permissions.modules'), 'errors' => new ViewErrorBag(),
        ])->render();
        $this->assertStringContainsString('value="invoice.edit processing fee" x-model="selected" :disabled="!editingPermissions || true"', $html);
        $this->assertStringContainsString('value="tenant.edit" x-model="selected" :disabled="!editingPermissions || false"', $html);
        $this->assertMatchesRegularExpression('/value="invoice.edit processing fee"\s+disabled/', $html);
    }

    public function test_system_admin_can_save_only_the_selected_account_and_clear_all_grants(): void
    {
        $this->actingAs($this->user('system'));
        $url = route('admin.settings.account-permissions.update', $this->user('abc'));
        $this->put($url, ['permissions' => ['tenant.edit']])->assertRedirect(route('admin.settings.index', ['account_id' => $this->ids['abc']]))
            ->assertSessionHas('success', 'Permissions updated for abc.');
        $this->assertSame(['tenant.edit'], $this->user('abc')->account_permissions);
        $this->assertContains('tenant.create', $this->user('def')->account_permissions);
        $this->assertSame(1, DB::table('role_has_permissions')->count());
        $this->assertSame(1, DB::table('audit_logs')->count());
        $this->put($url, [])->assertRedirect();
        $this->assertSame([], $this->user('abc')->account_permissions);
    }

    public function test_account_admins_and_staff_cannot_change_account_grants(): void
    {
        foreach (['abc', 'ghi', 'staff-a'] as $name) {
            $this->actingAs($this->user($name))->putJson(route('admin.settings.account-permissions.update', $this->user('def')), ['permissions' => []])->assertForbidden();
            // Account types must also remain under system-admin control, otherwise
            // an account could change itself to admin and bypass its grants.
            $this->putJson(route('admin.userManagement.update', 'workspace'), ['role_type' => 'admin'])->assertForbidden();
        }
        $this->assertNotEmpty($this->user('def')->account_permissions);
    }

    public function test_unknown_permissions_and_non_manager_targets_are_rejected(): void
    {
        $this->actingAs($this->user('system'));
        $this->putJson(route('admin.settings.account-permissions.update', $this->user('abc')), ['permissions' => ['super-admin']])->assertUnprocessable();
        $this->putJson(route('admin.settings.account-permissions.update', $this->user('staff-a')), ['permissions' => []])->assertNotFound();
        $this->assertNotEmpty($this->user('abc')->account_permissions);
    }

    public function test_admin_role_gate_cannot_bypass_a_denied_controller_action(): void
    {
        foreach (['abc', 'ghi', 'staff-a'] as $name) {
            $this->grant($name, []);
            $this->actingAs($this->user($name));
            $response = app(TenantsController::class)->create();
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('You have no permission.', session('error'));
        }
    }

    public function test_settings_requests_cannot_bypass_section_permissions(): void
    {
        $this->grant('abc', []);
        $this->actingAs($this->user('abc'));
        foreach ([
            ['payment_config' => ['allow_unpaid_penalty' => true]],
            ['pending_renewal_config' => ['number' => 3]],
            ['fee_types_config' => []],
            ['settings' => ['late_penalty_config' => ['amount' => 10]]],
        ] as $payload) {
            $this->patch(route('admin.settings.update'), $payload)->assertRedirect()->assertSessionHas('error', 'You have no permission.');
        }
    }

    public function test_settings_renders_compact_account_editor_for_system_admin(): void
    {
        (require database_path('migrations/2026_01_03_144458_create_fee_type_table.php'))->up();
        (require database_path('migrations/2026_10_20_112823_add_prices_to_fee_types_table.php'))->up();
        foreach (\App\Services\FeeTypeService::REFERENCE_TABLES as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id(); $table->string('fee_type_id')->nullable();
            });
        }
        Schema::create('settings', function (Blueprint $table) {
            $table->id(); $table->string('user_id'); $table->string('key'); $table->text('value'); $table->boolean('is_active');
        });
        $this->actingAs($this->user('system'));
        $view = app(SettingsController::class)->index(app(SettingService::class));
        $this->assertSame('adminSide.setting.account-permissions', $view->name());
        $this->assertCount(3, $view->getData()['accounts']);
        // Render the actual editor body independently of the global subscription layout.
        $source = file_get_contents(resource_path('views/adminSide/setting/account-permissions.blade.php'));
        $source = preg_replace('/<x-slot name="header">.*?<\/x-slot>/s', '', $source);
        $source = str_replace(['<x-app-layout>', '</x-app-layout>'], ['<div>', '</div>'], $source);
        $html = Blade::render($source, array_merge($view->getData(), ['errors' => new ViewErrorBag()]));
        $this->assertStringContainsString('Account Permissions', $html);
        $this->assertStringContainsString('Edit Permissions', $html);
        $this->assertStringContainsString('tenant.edit', $html);
        $this->assertStringContainsString('Save Permissions', $html);
        $this->assertStringNotContainsString('Create New Role', $html);
    }

    public function test_staff_role_editor_renders_shared_controls_and_preserves_its_submission_format(): void
    {
        $this->actingAs($this->user('abc'));
        $html = view('adminSide.setting.partials.staff-role-permissions', [
            'roles' => \App\Models\Role::with('permissions')->where('team_id', $this->ids['abc'])->get(),
            'modules' => config('permissions.modules'),
            'errors' => new ViewErrorBag(),
        ])->render();
        $this->assertStringContainsString('Staff Role Permissions', $html);
        $this->assertStringContainsString('Search roles...', $html);
        $this->assertStringContainsString('permissions[1][', $html);
        $this->assertStringContainsString('role="switch"', $html);
        $this->assertStringContainsString('Find module...', $html);
        $this->assertStringContainsString('reset-staff-permissions', $html);
        $this->assertStringContainsString('Delete Role', $html);
        $this->assertStringContainsString('Save Permissions', $html);
        $this->assertStringNotContainsString('name="permissions[]"', $html);
    }

    public function test_login_opens_first_allowed_page_for_each_account(): void
    {
        foreach ([
            ['abc', ['property.tab', 'tenant.tab'], 'admin.tenants.index'],
            ['ghi', ['invoice.tab'], 'admin.invoices.index'],
            ['ghi', ['utilities.tab', 'utilities.enter readings'], 'admin.utilities.index'],
            ['def', ['dashboard.tab', 'tenant.tab'], 'dashboard'],
        ] as [$name, $grants, $route]) {
            \Illuminate\Support\Facades\Auth::logout();
            $this->grant($name, $grants);
            DB::table('users')->where('id', $this->ids[$name])->update(['password' => \Illuminate\Support\Facades\Hash::make('test-password')]);
            $this->post(route('login'), ['email' => $name.'@example.com', 'password' => 'test-password'])->assertRedirect(route($route));
            $this->assertAuthenticatedAs($this->user($name));
        }
    }

    public function test_staff_login_uses_staff_permissions_instead_of_managers_landing_page(): void
    {
        DB::table('permissions')->where('id', 1)->update(['name' => 'tenant.tab']);
        $this->grant('abc', ['dashboard.tab', 'tenant.tab']);
        DB::table('users')->where('id', $this->ids['staff-a'])->update(['password' => \Illuminate\Support\Facades\Hash::make('test-password')]);
        $this->post(route('login'), ['email' => 'staff-a@example.com', 'password' => 'test-password'])->assertRedirect(route('admin.tenants.index'));

        $this->grant('abc', ['dashboard.tab']);
        $this->assertSame('profile.edit', app(\App\Services\LandingPageService::class)->routeFor($this->user('staff-a')));
    }

    public function test_denied_dashboard_redirects_to_an_allowed_page_without_a_loop(): void
    {
        $this->grant('ghi', ['property.tab']);
        $this->actingAs($this->user('ghi'))->get(route('dashboard'))->assertRedirect(route('admin.properties.index'));
        $this->grant('ghi', []);
        $this->actingAs($this->user('ghi'))->get(route('dashboard'))->assertRedirect(route('profile.edit'));
    }

    public function test_landing_page_uses_settings_or_safe_profile_fallback_and_preserves_portals(): void
    {
        $landing = app(\App\Services\LandingPageService::class);
        $this->grant('abc', ['profile menu.setting']);
        $this->assertSame('admin.settings.index', $landing->routeFor($this->user('abc')));
        $this->grant('abc', []);
        $this->assertSame('profile.edit', $landing->routeFor($this->user('abc')));
        $this->assertSame('dashboard', $landing->routeFor($this->user('system')));
        $this->assertSame('admin.owners.dashboard', $landing->routeFor(new User(['role' => 'owner'])));
        $this->assertSame('tenants.dashboard', $landing->routeFor(new User(['role' => 'tenant'])));
    }
}
