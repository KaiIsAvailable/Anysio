import test from 'node:test';
import assert from 'node:assert/strict';
import { invoiceStatement } from '../../resources/js/invoice-statement.js';

const endpoint = 'http://localhost/admin/invoices/statement';
const setup = () => {
    globalThis.window ??= { location: { origin: 'http://localhost', href: 'http://localhost/admin/invoices?statement_kind=payment' } };
    const state = invoiceStatement(endpoint);
    state.$nextTick = callback => callback();
    state.$refs = { content: { innerHTML: '', contains: () => true } };
    return state;
};

test('opening discards stale statement filters and pagination without a form', () => {
    globalThis.document = { activeElement: null };
    const state = setup();
    const filtered = invoiceStatement(endpoint + '?statement_kind=payment&statement_page=3&statement_from=2026-01-01');
    Object.assign(filtered, { $refs: state.$refs, $nextTick: state.$nextTick });
    let loaded;
    filtered.load = url => { loaded = url; };
    filtered.openStatement();
    assert.equal(loaded, endpoint);
    filtered.clearFilters(null);
});

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

test('pagination keeps rows while pending, deduplicates requests and preserves rows on failure', async () => {
    const pending = [];
    globalThis.fetch = (url, options) => new Promise(resolve => pending.push({ resolve, signal: options.signal }));
    const state = setup();
    state.$refs.content.innerHTML = 'page one';
    const url = endpoint + '?statement_page=2';
    const next = state.load(url);
    await state.load(url);
    assert.equal(pending.length, 1);
    assert.equal(state.$refs.content.innerHTML, 'page one');
    pending[0].resolve({ ok: false, status: 500, json: async () => ({}) });
    await next;
    assert.equal(state.$refs.content.innerHTML, 'page one');
    assert.match(state.error, /Unable to load/);
    const retry = state.load(state.lastUrl);
    pending[1].resolve({ ok: true, json: async () => ({ html: 'page two' }) });
    await retry;
    assert.equal(state.$refs.content.innerHTML, 'page two');
});

test('switching leases and reopening cannot display old rows or accept a cancelled response', async () => {
    const pending = [];
    globalThis.document = { activeElement: null };
    globalThis.fetch = (url, options) => new Promise(resolve => pending.push({ url, resolve, signal: options.signal }));
    const first = setup();
    first.$refs.content.innerHTML = 'previous lease';
    first.openStatement();
    assert.equal(first.$refs.content.innerHTML, '');
    first.closeStatement();
    assert.equal(pending[0].signal.aborted, true);
    const second = invoiceStatement('http://localhost/admin/leases/second/statement');
    Object.assign(second, { $refs: first.$refs, $nextTick: first.$nextTick });
    second.openStatement();
    assert.equal(second.$refs.content.innerHTML, '');
    pending[1].resolve({ ok: true, json: async () => ({ html: 'second lease' }) });
    await new Promise(resolve => setImmediate(resolve));
    pending[0].resolve({ ok: true, json: async () => ({ html: 'stale first lease' }) });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(second.$refs.content.innerHTML, 'second lease');
    assert.equal(pending[1].url, 'http://localhost/admin/leases/second/statement');
    second.closeStatement();
    assert.equal(second.$refs.content.innerHTML, '');
    second.openStatement();
    assert.equal(second.$refs.content.innerHTML, '');
    pending[2].resolve({ ok: true, json: async () => ({ html: 'fresh second lease' }) });
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(second.$refs.content.innerHTML, 'fresh second lease');
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
    state.navigate({ preventDefault: () => { prevented = true; }, target: { closest: selector => selector === 'a' ? ({ href: endpoint + '?statement_page=2', closest: () => ({}) }) : null } });
    assert.equal(prevented, true);
    assert.equal(loaded, endpoint + '?statement_page=2');
    assert.equal(window.location.href, 'http://localhost/admin/invoices?page=3&search=rent');
});
