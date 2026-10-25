import test from 'node:test';
import assert from 'node:assert/strict';
import { invoiceStatement } from '../../resources/js/invoice-statement.js';

const endpoint = 'http://localhost/admin/invoices/statement';
const setup = () => {
    const state = invoiceStatement(endpoint);
    state.$nextTick = callback => callback();
    state.$refs = { content: { innerHTML: '', contains: () => true } };
    return state;
};

test('loads lazily, renders results and refreshes on reopening', async () => {
    let calls = 0, focused = false;
    globalThis.document = { activeElement: { focus: () => { focused = true; } } };
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ html: `result ${++calls}` }) });
    const state = setup();
    assert.equal(calls, 0);
    state.openStatement();
    assert.equal(state.statementLoading, true);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(state.$refs.content.innerHTML, 'result 1');
    state.closeStatement();
    assert.equal(focused, true);
    state.openStatement();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(state.$refs.content.innerHTML, 'result 2');
});

test('closing or replacing requests prevents stale response rendering', async () => {
    const pending = [];
    globalThis.fetch = () => new Promise(resolve => pending.push(resolve));
    const state = setup();
    const old = state.load(endpoint);
    state.closeStatement();
    const current = state.load(endpoint + '?statement_page=2');
    pending[1]({ ok: true, json: async () => ({ html: 'page two' }) });
    await current;
    pending[0]({ ok: true, json: async () => ({ html: 'stale' }) });
    await old;
    assert.equal(state.$refs.content.innerHTML, 'page two');
    assert.equal(state.statementLoading, false);
});

test('validation and network failures are recoverable inside the modal', async () => {
    const state = setup();
    globalThis.fetch = async () => ({ ok: false, status: 422, json: async () => ({ errors: { statement_from: ['Invalid date'] } }) });
    await state.load(endpoint);
    assert.equal(state.error, 'Invalid date');
    globalThis.fetch = async () => { throw new Error('Network unavailable'); };
    await state.load(endpoint);
    assert.equal(state.statementLoading, false);
    assert.equal(state.error, 'Network unavailable');
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ html: 'No statement records' }) });
    await state.load(state.lastUrl);
    assert.equal(state.error, '');
    assert.equal(state.$refs.content.innerHTML, 'No statement records');
});

test('filter submission and pagination stay on the isolated endpoint', () => {
    globalThis.window = { location: { origin: 'http://localhost', href: 'http://localhost/admin/invoices?page=3&search=rent' } };
    globalThis.HTMLFormElement = class {};
    globalThis.FormData = class { *[Symbol.iterator]() { yield ['statement_invoice', 'IA']; } };
    const state = setup();
    let loaded;
    state.load = url => { loaded = url; };
    state.submit({ target: new HTMLFormElement() });
    assert.equal(loaded, endpoint + '?statement_invoice=IA');
    let prevented = false;
    state.navigate({ preventDefault: () => { prevented = true; }, target: { closest: () => ({ href: endpoint + '?statement_page=2' }) } });
    assert.equal(prevented, true);
    assert.equal(loaded, endpoint + '?statement_page=2');
    assert.equal(window.location.href, 'http://localhost/admin/invoices?page=3&search=rent');
});
