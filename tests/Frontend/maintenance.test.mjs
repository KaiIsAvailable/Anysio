import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../resources/js/maintenance.js', import.meta.url), 'utf8')
    .replace("import { createPdfThumbnails } from './ticket-pdf-thumbnails';", '')
    .replaceAll('export function ', 'function ');

class Element {
    constructor() { this.children = []; this.handlers = {}; this.dataset = {}; this.value = ''; this.disabled = false; this.textContent = ''; }
    addEventListener(name, fn) { this.handlers[name] = fn; }
    fire(name) { return this.handlers[name]?.({ preventDefault() {} }); }
    append(...nodes) { this.children.push(...nodes); }
    replaceChildren(...nodes) { this.children = nodes; }
}

function setup(request) {
    const form = new Element(), error = new Element(), picker = new Element(), list = new Element(), title = new Element(), submit = new Element();
    title.value = 'Verified repair'; form.action = '/isolated/complete';
    const controls = [picker, title, submit];
    form.querySelector = key => ({ '[data-maintenance-error]': error, '[data-maintenance-files]': picker,
        '[data-maintenance-files-list]': list, '[name="title"]': title })[key];
    form.querySelectorAll = () => [...controls, ...list.children.flatMap(row => row.children.slice(1))];
    form.reset = () => { title.value = ''; picker.value = ''; };
    class Body extends FormData {
        constructor() { super(); this.set('title', title.value); this.set('version', '2'); }
    }
    const context = vm.createContext({ document: { addEventListener() {}, createElement: () => new Element() },
        FormData: Body, AbortController, setTimeout, clearTimeout });
    vm.runInContext(source, context);
    let successes = 0, serial = 0;
    const api = context.initMaintenanceForm(form, { request, uuid: () => 'key-' + ++serial, success: () => successes++ });
    return { form, error, picker, list, title, submit, api, successes: () => successes,
        select: async files => { picker.files = files; await picker.fire('change'); } };
}
const file = () => new File(['repair evidence'], 'completed.txt', { type: 'text/plain' });
const response = (status, data = {}) => ({ ok: status < 400, status, json: async () => data });

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
