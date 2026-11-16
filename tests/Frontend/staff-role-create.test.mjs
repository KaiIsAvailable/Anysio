import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

const rendered = JSON.parse(execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
Illuminate\\Support\\Facades\\Auth::setUser(new App\\Models\\User(['role' => 'ownerAdmin']));
config(['permissions.modules' => []]);
$source = file_get_contents(resource_path('views/adminSide/userManagement/staff/create.blade.php'));
$source = str_replace(['<x-app-layout>', '</x-app-layout>'], ['<div>', '</div>'], $source);
echo json_encode([
    'create' => Illuminate\\Support\\Facades\\Blade::render($source, [
        'roles' => collect([(object) ['name' => 'Existing role']]),
        'managementList' => [],
        'errors' => new Illuminate\\Support\\ViewErrorBag(),
    ]),
    'defaultModal' => Illuminate\\Support\\Facades\\Blade::render('<x-modals.staff-role-modal />'),
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
