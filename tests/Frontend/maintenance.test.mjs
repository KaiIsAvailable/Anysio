import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const source = readFileSync(new URL('../../resources/js/maintenance.js', import.meta.url), 'utf8')
    .replace("import { createPdfThumbnails } from './ticket-pdf-thumbnails';", '')
    .replaceAll('export function ', 'function ');

const assignmentHtml = execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
Illuminate\\Support\\Facades\\Auth::setUser(new App\\Models\\User(['id' => 'manager', 'role' => 'admin']));
view()->share('errors', new Illuminate\\Support\\ViewErrorBag());
$maintenance = new App\\Models\\Maintenance(['id' => 'order', 'status' => 'pending', 'management_user_id' => 'manager']);
$staffCreateData = ['managementId' => 'workspace', 'managementUserId' => 'manager', 'roles' => collect()];
echo Illuminate\\Support\\Facades\\Blade::render('<x-maintenance.action-modals :maintenance="$maintenance" :staff-create-data="$staffCreateData" />', compact('maintenance', 'staffCreateData'));
`], { encoding: 'utf8', cwd: new URL('../../', import.meta.url), env: {
    ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_STORE: 'array', SESSION_DRIVER: 'array',
} });
const staffModalExpression = assignmentHtml.match(/x-data="(\{\s*savingStaff:[\s\S]*?)" x-on:pageshow/)[1];
const assignmentExpression = assignmentHtml.match(/x-data="(\{\s*availableStaff:[\s\S]*?)" x-on:staff-created/)[1];

function staffModal(response) {
    const events = [], requests = [];
    let resets = 0;
    const modal = vm.runInNewContext('(' + staffModalExpression + ')', {
        fetch: async (_, options) => { requests.push(options); return response; },
        FormData: class { constructor(form) { this.form = form; } },
    });
    modal.$dispatch = (type, detail) => events.push({ type, detail });
    const form = { action: '/admin/staff', reset() { resets++; } };
    return { modal, events, requests, form, resets: () => resets };
}

test('Inline staff creation publishes the new staff and closes without navigating', async () => {
    const ui = staffModal({ ok: true, json: async () => ({ message: 'Created', staff: { id: 'staff', name: 'Contractor', email: 'staff@example.test', management_user_id: 'manager' } }) });
    const pending = ui.modal.submitStaff(ui.form);
    ui.modal.submitStaff(ui.form);
    assert.equal(ui.modal.savingStaff, true);
    await pending;
    assert.equal(ui.requests.length, 1);
    assert.equal(ui.requests[0].redirect, 'manual');
    assert.equal(ui.events.find(event => event.type === 'staff-created').detail.id, 'staff');
    assert.equal(ui.events.find(event => event.type === 'close-modal').detail, 'create-staff');
    assert.equal(ui.resets(), 1);
    assert.equal(ui.modal.savingStaff, false);
});

test('Inline staff validation and expired-session failures preserve input and reset loading', async () => {
    for (const response of [
        { ok: false, json: async () => ({ errors: { email: ['Email already exists.'] } }) },
        { type: 'opaqueredirect' },
    ]) {
        const ui = staffModal(response);
        await ui.modal.submitStaff(ui.form);
        assert.ok(ui.modal.staffError);
        assert.equal(ui.resets(), 0);
        assert.equal(ui.events.length, 0);
        assert.equal(ui.modal.savingStaff, false);
    }
});

test('Assignment selects new staff from this account and does not duplicate options', () => {
    const assignment = vm.runInNewContext('(' + assignmentExpression + ')');
    assignment.addAssignedStaff({ id: 'foreign', name: 'Foreign', management_user_id: 'other' });
    assert.equal(assignment.availableStaff.length, 0);
    const staff = { id: 'staff', name: 'Contractor', email: 'staff@example.test', management_user_id: 'manager' };
    assignment.addAssignedStaff(staff);
    assignment.addAssignedStaff(staff);
    assert.equal(assignment.availableStaff.length, 1);
    assert.equal(assignment.selectedAssignedStaff[0], 'staff');
});

test('Closing inline staff creation returns to the assignment modal', () => {
    const watcher = [...assignmentHtml.matchAll(/x-init="(\$watch\('show',[\s\S]*?)"/g)]
        .map(match => match[1]).find(expression => expression.includes('assign-maintenance'));
    const events = [];
    let callback;
    vm.runInNewContext(watcher, {
        $watch: (_, handler) => { callback = handler; },
        $dispatch: (type, detail) => events.push({ type, detail }),
        document: { body: { classList: { add() {}, remove() {} } } },
    });
    callback(false);
    assert.equal(events[0].type, 'open-modal');
    assert.equal(events[0].detail, 'assign-maintenance');
});

function editorLoader(request) {
    const form = { dataset: {} }, events = [], reports = [], opened = [];
    const host = { innerHTML: '', querySelector: () => form };
    const context = vm.createContext({ document: { addEventListener() {} } });
    vm.runInContext(source, context);
    const load = context.initMaintenanceAction(host, {
        request,
        initialize: () => events.push('initialize'),
        ready: async () => events.push('ready'),
        open: modal => { events.push('open'); opened.push(modal); },
        report: message => reports.push(message),
    });
    const trigger = href => ({ href, dataset: {}, attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
    });
    return { form, host, events, reports, opened, load, trigger };
}

test('Edit opens the fetched component after initialization and clears trigger loading', async () => {
    const ui = editorLoader(async (_, options) => {
        assert.equal(options.headers.Accept, 'application/json');
        return response(200, { html: '<form>Selected maintenance</form>' });
    });
    const trigger = ui.trigger('/maintenance/one/edit');
    await ui.load(trigger);
    assert.match(ui.host.innerHTML, /Selected maintenance/);
    assert.deepEqual(ui.events, ['initialize', 'ready', 'open']);
    assert.equal(trigger.dataset.loading, undefined);
    assert.equal(trigger.attributes['aria-busy'], undefined);
});

test('List assignment loads and initializes the shared assignment form before opening it', async () => {
    const ui = editorLoader(async () => response(200, { html: '<form>Assignment form</form>' }));
    const selectors = [];
    ui.host.querySelector = selector => { selectors.push(selector); return ui.form; };
    await ui.load(ui.trigger('/maintenance/one/assign'), 'assign');
    assert.ok(selectors.includes('[data-maintenance-form="assign"]'));
    assert.deepEqual(ui.events, ['initialize', 'ready', 'open']);
    assert.deepEqual(ui.opened, ['assign-maintenance']);
    assert.match(ui.host.innerHTML, /Assignment form/);
});

test('List completion initializes the evidence form and opens the completion modal', async () => {
    const ui = editorLoader(async () => response(200, { html: '<form>Completion evidence</form>' }));
    const selectors = [];
    ui.host.querySelector = selector => { selectors.push(selector); return ui.form; };
    await ui.load(ui.trigger('/maintenance/one/complete'), 'complete');
    assert.ok(selectors.includes('[data-maintenance-form="complete"]'));
    assert.deepEqual(ui.events, ['initialize', 'ready', 'open']);
    assert.deepEqual(ui.opened, ['complete-maintenance']);
});

function historyLoader(request) {
    const nodes = Object.fromEntries(['skeleton', 'error', 'error-message', ''].map(name =>
        [`[data-maintenance-history${name ? '-' + name : ''}]`, { hidden: name === 'error', dataset: {}, textContent: '' }]));
    const host = { innerHTML: '', attributes: {}, scrollTop: 100,
        setAttribute(name, value) { this.attributes[name] = value; }, querySelector: selector => nodes[selector] };
    const opened = [];
    const context = vm.createContext({ document: { addEventListener() {} } });
    vm.runInContext(source, context);
    const load = context.initMaintenanceHistory(host, 'skeleton', { request, open: () => opened.push('history-maintenance') });
    return { host, nodes, opened, load };
}

test('History opens with a skeleton before data arrives and pagination updates the open modal', async () => {
    const pending = new Map();
    const ui = historyLoader(url => new Promise(resolve => pending.set(url, resolve)));
    const button = { dataset: { historyUrl: '/history/room/one' } };
    const first = ui.load(button);
    assert.deepEqual(ui.opened, ['history-maintenance']);
    assert.equal(ui.host.innerHTML, 'skeleton');
    assert.equal(ui.host.attributes['aria-busy'], 'true');
    assert.equal(ui.host.scrollTop, 0);
    assert.equal(button.dataset.loading, undefined);
    pending.get('/history/room/one')(response(200, { html: 'First page' }));
    await first;
    assert.equal(ui.host.innerHTML, 'First page');
    assert.equal(ui.host.attributes['aria-busy'], 'false');
    assert.equal(ui.opened.length, 1);
    const second = ui.load({ href: '/history/room/one?page=2' });
    assert.equal(ui.host.innerHTML, 'skeleton');
    pending.get('/history/room/one?page=2')(response(200, { html: 'Second page' }));
    await second;
    assert.equal(ui.host.innerHTML, 'Second page');
    assert.equal(ui.opened.length, 2);
});

test('History load failures replace the skeleton with an inline error and retry the same page', async () => {
    let fail = true;
    const urls = [];
    const ui = historyLoader(async url => {
        urls.push(url);
        if (fail) throw new Error('Connection interrupted');
        return response(200, { html: 'Recovered history' });
    });
    await ui.load({ href: '/history/room/one?page=2' });
    assert.equal(ui.nodes['[data-maintenance-history-skeleton]'].hidden, true);
    assert.equal(ui.nodes['[data-maintenance-history-error]'].hidden, false);
    assert.equal(ui.nodes['[data-maintenance-history-error-message]'].textContent, 'Connection interrupted');
    assert.equal(ui.host.attributes['aria-busy'], 'false');
    fail = false;
    await ui.load(ui.nodes['[data-maintenance-history]']);
    assert.deepEqual(urls, ['/history/room/one?page=2', '/history/room/one?page=2']);
    assert.equal(ui.host.innerHTML, 'Recovered history');
});

test('A stale history response cannot overwrite the latest page or reopen a closed modal', async () => {
    const pending = new Map();
    const ui = historyLoader(url => new Promise(resolve => pending.set(url, resolve)));
    const first = ui.load({ href: '/first' });
    const second = ui.load({ href: '/second' });
    ui.opened.length = 0; // Simulate closing while the request is pending.
    pending.get('/second')(response(200, { html: 'Latest page' }));
    await second;
    pending.get('/first')(response(403, { message: 'Old request failed' }));
    await first;
    assert.equal(ui.host.innerHTML, 'Latest page');
    assert.equal(ui.host.attributes['aria-busy'], 'false');
    assert.equal(ui.opened.length, 0);
});

test('Edit loading ignores stale responses and guards pending submissions', async () => {
    const responses = new Map();
    const ui = editorLoader(url => new Promise(resolve => responses.set(url, resolve)));
    const first = ui.trigger('/first'), second = ui.trigger('/second');
    const pendingFirst = ui.load(first);
    await ui.load(first);
    const pendingSecond = ui.load(second);
    assert.equal(responses.size, 2);
    responses.get('/second')(response(200, { html: 'second' }));
    await pendingSecond;
    responses.get('/first')(response(200, { html: 'first' }));
    await pendingFirst;
    assert.equal(ui.host.innerHTML, 'second');
    assert.deepEqual(ui.events, ['initialize', 'ready', 'open']);
    ui.form.dataset.busy = 'true';
    await ui.load(ui.trigger('/third'));
    assert.equal(responses.size, 2);
});

test('Edit permission and load errors keep the modal closed and reset loading', async () => {
    for (const status of [403, 409, 500]) {
        const ui = editorLoader(async () => response(status, { message: 'Editing unavailable' }));
        const trigger = ui.trigger('/edit');
        await ui.load(trigger);
        assert.equal(ui.events.length, 0);
        assert.equal(ui.reports.length, 1);
        assert.equal(trigger.dataset.loading, undefined);
    }
});

class Element {
    constructor() { this.children = []; this.handlers = {}; this.dataset = {}; this.value = ''; this.disabled = false; this.textContent = ''; }
    addEventListener(name, fn) { this.handlers[name] = fn; }
    fire(name) { return this.handlers[name]?.({ preventDefault() {} }); }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) { this.children = nodes; }
}

function setup(request) {
    const form = new Element(), error = new Element(), picker = new Element(), list = new Element(), title = new Element(), submit = new Element();
    form.loadingEvents = [];
    form.dispatchEvent = event => { if (event.type === 'form-loading') form.loadingEvents.push(event.detail); };
    title.value = 'Verified repair'; form.action = '/isolated/complete';
    const controls = [picker, title, submit];
    form.querySelector = key => ({ '[data-maintenance-error]': error, '[data-maintenance-files]': picker,
        '[data-maintenance-files-list]': list, '[name="title"]': title })[key];
    form.querySelectorAll = () => [...controls, ...list.children.flatMap(row => row.children.slice(1))];
    form.reset = () => { title.value = ''; picker.value = ''; };
    class Body extends FormData {
        constructor() { super(); this.set('title', title.value); this.set('version', '2'); }
    }
    const createdUrls = [], revokedUrls = [], windowEvents = {};
    const context = vm.createContext({ document: { addEventListener() {}, createElement: () => new Element() },
        window: { addEventListener(name, callback) { windowEvents[name] = callback; } },
        URL: { createObjectURL() { const url = 'blob:preview-' + createdUrls.length; createdUrls.push(url); return url; }, revokeObjectURL(url) { revokedUrls.push(url); } },
        FormData: Body, AbortController, setTimeout, clearTimeout,
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } } });
    vm.runInContext(source, context);
    let successes = 0, serial = 0;
    const api = context.initMaintenanceForm(form, { request, uuid: () => 'key-' + ++serial, success: () => successes++ });
    return { form, error, picker, list, title, submit, api, createdUrls, revokedUrls, windowEvents, successes: () => successes,
        select: async files => { picker.files = files; await picker.fire('change'); } };
}
const file = () => new File(['repair evidence'], 'completed.txt', { type: 'text/plain' });
const response = (status, data = {}) => ({ ok: status < 400, status, json: async () => data });

test('Assignment distinguishes missing staff, unavailable maintenance and server failures', async () => {
    for (const [status, data, expected] of [
        [422, { errors: { assigned_staff_id: ['Select a staff member.'] } }, 'Select a staff member.'],
        [503, { message: 'Maintenance database upgrade is required.' }, 'Maintenance database upgrade is required.'],
        [500, {}, 'Unable to assign contractor. Your staff selection is retained. Please retry.'],
    ]) {
        const ui = setup(async () => response(status, data));
        ui.form.dataset.maintenanceForm = 'assign';
        await ui.form.fire('submit');
        assert.equal(ui.error.textContent, expected);
        assert.equal(ui.error.textContent.includes('files'), false);
        assert.equal(ui.successes(), 0);
        assert.equal(ui.submit.disabled, false);
        assert.deepEqual(ui.form.loadingEvents, [true, false]);
    }
});

test('selected images render thumbnails and release previews on removal, reset and page exit', async () => {
    const ui = setup(async () => response(200));
    const images = [1, 2, 3].map(index => new File(['image'], `repair-${index}.png`, { type: 'image/png' }));
    await ui.select(images);
    assert.equal(ui.list.children.length, 3);
    ui.list.children.forEach((row, index) => {
        const preview = row.children.find(child => child.src);
        assert.equal(preview.alt, images[index].name);
        assert.match(preview.src, /^blob:preview-/);
    });
    await ui.list.children[0].children[1].fire('click');
    assert.equal(ui.list.children.length, 2);
    assert.equal(ui.revokedUrls.length, 3);
    ui.api.reset();
    assert.equal(ui.list.children.length, 0);
    assert.deepEqual(ui.revokedUrls, ui.createdUrls);
    await ui.select(images);
    ui.windowEvents.pagehide();
    assert.deepEqual(ui.revokedUrls, ui.createdUrls);
});

test('failed selected-file upload does not complete or clear input; retry sends identical evidence and request key', async () => {
    const requests = [];
    const ui = setup(async (_, options) => { requests.push(options.body); return response(requests.length === 1 ? 500 : 200); });
    await ui.select([file()]);
    await ui.form.fire('submit');
    assert.equal(ui.successes(), 0);
    assert.equal(ui.title.value, 'Verified repair');
    assert.equal(ui.list.children.length, 1);
    assert.match(ui.error.textContent, /retained/);
    assert.equal(ui.submit.disabled, false);
    assert.deepEqual(ui.form.loadingEvents, [true, false]);
    await ui.form.fire('submit');
    assert.equal(ui.successes(), 1);
    assert.equal(requests[0].get('request_key'), requests[1].get('request_key'));
    assert.equal(await requests[1].get('attachments[]').text(), 'repair evidence');
    assert.equal(ui.list.children.length, 0);
});

test('explicit removal after failure allows completion without attachments and keeps other inputs', async () => {
    const requests = [];
    const ui = setup(async (_, options) => { requests.push(options.body); return response(requests.length === 1 ? 422 : 200, { errors: { attachments: ['Invalid file'] } }); });
    await ui.select([file()]); await ui.form.fire('submit');
    assert.equal(ui.successes(), 0); assert.match(ui.error.textContent, /Invalid file/);
    await ui.list.children[0].children[1].fire('click');
    assert.equal(ui.list.children.length, 0);
    await ui.form.fire('submit');
    assert.equal(requests[1].getAll('attachments[]').length, 0);
    assert.equal(requests[1].get('title'), 'Verified repair');
    assert.equal(ui.successes(), 1);
});

test('network failure preserves all selected files; an in-flight request blocks repeat submit and removal', async () => {
    let reject, calls = 0;
    const ui = setup(() => { calls++; return new Promise((_, fail) => { reject = fail; }); });
    await ui.select([file(), file()]);
    const pending = ui.form.fire('submit');
    await ui.form.fire('submit');
    await ui.list.children[0].children[1].fire('click');
    assert.equal(calls, 1); assert.equal(ui.submit.disabled, true); assert.equal(ui.list.children.length, 2);
    reject(new Error('Network unavailable')); await pending;
    assert.equal(ui.successes(), 0); assert.equal(ui.list.children.length, 2); assert.equal(ui.title.value, 'Verified repair');
    assert.equal(ui.submit.disabled, false); assert.match(ui.error.textContent, /Network/);
});

test('completion with no selected evidence succeeds; oversized request retains evidence', async () => {
    let body;
    const empty = setup(async (_, options) => { body = options.body; return response(200); });
    await empty.form.fire('submit'); assert.equal(empty.successes(), 1); assert.equal(body.getAll('attachments[]').length, 0);
    const large = setup(async () => response(413)); await large.select([file()]); await large.form.fire('submit');
    assert.equal(large.successes(), 0); assert.equal(large.list.children.length, 1); assert.match(large.error.textContent, /too large/);
});

test('unverified source blocks submission without losing its form state', async () => {
    let calls = 0; const ui = setup(async () => { calls++; return response(200); });
    ui.form.dataset.blocked = 'true'; await ui.select([file()]); await ui.form.fire('submit');
    assert.equal(calls, 0); assert.equal(ui.list.children.length, 1); assert.equal(ui.title.value, 'Verified repair');
});

test('Viewing work order details never opens a modal, including stale action hashes', () => {
    for (const hash of ['', '#assign', '#complete']) {
        let ready;
        const opened = [];
        const document = {
            addEventListener(name, callback) { if (name === 'DOMContentLoaded') ready = callback; },
            querySelectorAll() { return []; },
            querySelector() { return null; },
        };
        const window = { addEventListener() {}, dispatchEvent(event) { opened.push(event); } };
        vm.runInNewContext(source, {
            document, window, location: { hash },
            createPdfThumbnails: () => ({ scan() {}, destroy() {} }),
            CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        });
        ready();
        assert.equal(opened.length, 0, `No automatic action on ${hash || 'plain View'}`);
    }
});
