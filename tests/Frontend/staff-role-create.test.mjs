import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

const rendered = JSON.parse(execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
Illuminate\\Support\\Facades\\Auth::setUser(new App\\Models\\User(['role' => 'admin']));
config(['permissions.modules' => []]);
view()->share('errors', new Illuminate\\Support\\ViewErrorBag());
echo json_encode([
    'create' => Illuminate\\Support\\Facades\\Blade::render('<x-staff.create-modal :roles="$roles" management-id="workspace" management-user-id="manager" />', [
        'roles' => collect([(object) ['name' => 'Existing role']]),
        'managementList' => [],
        'errors' => new Illuminate\\Support\\ViewErrorBag(),
    ]),
    'defaultModal' => Illuminate\\Support\\Facades\\Blade::render('<x-modals.staff-role-modal management-user-id="manager" />'),
    'managementModal' => Illuminate\\Support\\Facades\\Blade::render('<x-staff.create-modal :management-options="$accounts" />', [
        'accounts' => [
            ['value' => 'workspace-a', 'label' => 'First account', 'user_id' => 'manager-a', 'roles' => [['name' => 'First role']]],
            ['value' => 'workspace-b', 'label' => 'Second account', 'user_id' => 'manager-b', 'roles' => [['name' => 'Second role']]],
        ],
    ]),
    'edit' => (function () {
        Illuminate\\Support\\Facades\\Auth::setUser(new App\\Models\\User(['role' => 'ownerAdmin']));
        $source = file_get_contents(resource_path('views/adminSide/userManagement/staff/edit.blade.php'));
        $source = str_replace(['<x-app-layout>', '</x-app-layout>'], ['<div>', '</div>'], $source);
        $staff = new App\\Models\\Staff(['role' => 'Saved role', 'is_active' => true]);
        $staff->id = 'staff';
        $staff->setRelation('user', new App\\Models\\User(['name' => 'Staff', 'email' => 'staff@example.com']));
        $data = ['staff' => $staff, 'roles' => collect([(object) ['name' => 'Saved role'], (object) ['name' => 'Other role']])];
        $initial = Illuminate\\Support\\Facades\\Blade::render($source, $data);
        request()->setLaravelSession(session()->driver());
        session()->flashInput(['role' => 'Other role']);
        return [$initial, Illuminate\\Support\\Facades\\Blade::render($source, $data)];
    })(),
]);
`], {
    encoding: 'utf8', cwd: new URL('../../', import.meta.url),
    env: { ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_STORE: 'array', SESSION_DRIVER: 'array' },
}));

function attribute(html, name) {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return html.match(new RegExp(`${escaped}="([\\s\\S]*?)"`))?.[1]
        ?.replaceAll('&quot;', '"').replaceAll('&#039;', "'").replaceAll('&gt;', '>').replaceAll('&lt;', '<').replaceAll('&amp;', '&');
}

function popup(fetch) {
    const state = {
        roleSaving: false, reloadAfterCreate: false, loading: false, newRoleName: 'New role', selectedPermissions: ['staff.tab'],
        showRoleModal: true, errorMessage: '',
    };
    const events = [];
    let reloads = 0;
    const context = vm.createContext({
        state, fetch, FormData: class { get() { return null; } }, $el: {},
        $dispatch: (name, detail) => events.push({ name, detail }),
        window: { location: { reload() { reloads++; } } },
    });
    const submit = () => vm.runInContext(`(function () { with (state) { ${attribute(rendered.create, '@submit.prevent.stop')} } })()`, context);
    return { state, events, submit, reloads: () => reloads };
}

test('creating a role updates the staff page without reloading or touching its submission state', async () => {
    let finish;
    const p = popup(() => new Promise(resolve => { finish = resolve; }));
    const pending = p.submit();
    assert.equal(p.state.roleSaving, true);
    assert.equal(p.state.loading, false);
    finish({ status: 201, json: async () => ({ role: { name: 'New role' }, message: 'Role created' }) });
    await pending;
    assert.equal(p.reloads(), 0);
    assert.equal(p.state.showRoleModal, false);
    assert.equal(p.state.roleSaving, false);
    assert.equal(p.state.newRoleName, '');
    assert.equal(p.events[0].name, 'staff-role-created');
    assert.equal(p.events[0].detail.role.name, 'New role');
    assert.equal(p.events[1].name, 'session-status');
    assert.match(rendered.create, /:disabled="roleSaving"/);
    assert.match(rendered.create, /<svg x-show="roleSaving"/);
});

test('the returned role is added to the dropdown and selected, retaining existing roles', () => {
    const state = { roles: [{ name: 'Existing role' }], selectedRole: ['Existing role'] };
    const context = vm.createContext({ state, $event: { detail: { role: { name: 'New role' } } }, $nextTick: fn => fn() });
    const handler = attribute(rendered.create, '@staff-role-created.window');
    for (let i = 0; i < 2; i++) vm.runInContext(`with (state) { ${handler} }`, context);
    assert.deepEqual(state.roles.map(role => role.name), ['Existing role', 'New role']);
    assert.equal(state.selectedRole[0], 'New role');
    const selectContext = vm.createContext({ roles: state.roles, options: [] });
    vm.runInContext(attribute(rendered.create, 'x-effect'), selectContext);
    assert.equal(selectContext.options[1].value, 'New role');
    assert.equal(selectContext.options[1].label, 'New role');
});

test('validation and network errors keep the popup open and release its loading state', async () => {
    for (const fetch of [
        async () => ({ status: 422, json: async () => ({ message: 'The name has already been taken.' }) }),
        async () => { throw new Error('Network failed'); },
    ]) {
        const p = popup(fetch);
        await p.submit();
        assert.equal(p.state.roleSaving, false);
        assert.equal(p.state.showRoleModal, true);
        assert.equal(p.state.newRoleName, 'New role');
        assert.ok(p.state.errorMessage);
        assert.equal(p.reloads(), 0);
        assert.equal(p.events.length, 0);
    }
});

test('other uses of the shared role popup retain their existing reload behavior', async () => {
    assert.match(rendered.defaultModal, /reloadAfterCreate: true/);
    assert.match(rendered.create, /reloadAfterCreate: false/);
    const p = popup(async () => ({ status: 201, json: async () => ({ role: { name: 'New role' } }) }));
    p.state.reloadAfterCreate = true;
    await p.submit();
    assert.equal(p.reloads(), 1);
});

test('management selection scopes available staff roles and the role creation target', () => {
    const state = vm.runInNewContext(`(${attribute(rendered.managementModal, 'x-data')})`);
    assert.equal(state.selectedManagementUserId, '');
    assert.equal(state.selectedManagementRoles.length, 0);
    state.selectedManagement = ['workspace-a'];
    assert.equal(state.selectedManagementUserId, 'manager-a');
    assert.equal(state.selectedManagementRoles[0].name, 'First role');
    state.selectedManagement = ['workspace-b'];
    assert.equal(state.selectedManagementUserId, 'manager-b');
    assert.equal(state.selectedManagementRoles[0].name, 'Second role');
    assert.match(rendered.managementModal, /name="user_id" :value="selectedManagementUserId"/);
});

test('staff edit initializes its role binding from the saved role and preserves old input', () => {
    for (const [index, expected] of ['Saved role', 'Other role'].entries()) {
        const html = rendered.edit[index];
        const parent = vm.runInNewContext(`(${attribute(html, 'x-data')})`);
        assert.equal(parent.selectedRole[0], expected);
        const expression = [...html.matchAll(/x-data="([\s\S]*?)"/g)]
            .map(match => match[1]).find(value => value.includes('selectedValues:'));
        const select = vm.runInNewContext(`(${expression})`);
        assert.equal(select.selectedValues[0], expected);
        assert.equal(select.selectedLabel, expected);
        assert.match(html, /x-model="selectedRole"/);
    }
});
