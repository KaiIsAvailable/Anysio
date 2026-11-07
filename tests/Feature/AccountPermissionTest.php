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
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id'); $table->string('team_id')->nullable(); $table->string('name'); $table->string('guard_name');
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

    private function user(string $name): User
    {
        return User::findOrFail($this->ids[$name]);
    }

    private function grant(string $name, array $permissions): void
    {
        DB::table('users')->where('id', $this->ids[$name])->update(['account_permissions' => json_encode($permissions)]);
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

    public function test_shared_staff_roles_still_work_and_manager_revocation_does_not_modify_roles(): void
    {
        $this->actingAs($this->user('def'));
        // Gate::forUser must resolve the staff's own manager, not the signed-in account.
        foreach (['staff-a', 'staff-b'] as $staff) {
            $this->assertTrue(Gate::forUser($this->user($staff))->allows('tenant.edit'));
            $this->assertFalse(Gate::forUser($this->user($staff))->allows('tenant.create'));
        }
        $this->grant('abc', []);
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
