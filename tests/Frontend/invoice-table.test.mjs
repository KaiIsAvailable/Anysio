import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/invoice-table.js', import.meta.url), 'utf8').replace('export function', 'function');
function create(fetch, options = {}) {
    const factory = vm.runInNewContext(source + '\ninvoiceTable;', { fetch });
    const state = factory({ endpoint: '/leases/one/rows', leaseId: 'one', paymentUrl: '/invoices/__INVOICE__/payment', ...options });
    const events = [];
    state.$dispatch = (name, detail) => events.push({ name, detail });
    state.$watch = () => {};
    return { state, events };
}
const response = (invoices = [], leaseId = 'one') => ({ ok: true, json: async () => ({
    invoices, lease_id: leaseId, wallet_balance: '7.00', pagination: { current_page: 1, last_page: 1, total: invoices.length }
}) });

test('table shows its loading state until the server returns the selected page', async () => {
    let finish;
    const { state, events } = create(() => new Promise(resolve => { finish = resolve; }));
    const request = state.refreshTable();
    assert.equal(state.loading, true);
    finish(response([{ id: 'invoice-one' }]));
    await request;
    assert.equal(state.loading, false);
    assert.equal(state.invoices[0].id, 'invoice-one');
    assert.equal(events[0].name, 'invoice-table-updated');
});

test('late response from the previous lease cannot replace the new lease rows', async () => {
    const pending = [];
    const { state } = create(url => new Promise(resolve => pending.push({ url, resolve })));
    const old = state.refreshTable();
    state.setEndpoint('/leases/two/rows', 'two');
    pending[1].resolve(response([{ id: 'new-lease-invoice' }], 'two'));
    await new Promise(resolve => setImmediate(resolve));
    pending[0].resolve(response([{ id: 'old-lease-invoice' }]));
    await old;
    assert.equal(state.leaseId, 'two');
    assert.equal(state.invoices[0].id, 'new-lease-invoice');
    assert.equal(state.loading, false);
});

test('record payment uses the selected rows wallet and previous payment values', () => {
    const { state, events } = create(() => {}, { leaseId: null });
    for (const [id, wallet] of [['one', '7.00'], ['two', '23.00']]) {
        state.recordPayment({ id, invoice_no: id, amount_balance: '3.00', total_amount: '10.00', amount_paid: '7.00', wallet_balance: wallet, invoice_items: [] });
        const payload = events.at(-1).detail;
        assert.equal(payload.walletBalance, wallet);
        assert.equal(payload.previouslyPaid, '7.00');
        assert.equal(payload.totalAmount, '3.00');
        assert.equal(payload.actionUrl, `/invoices/${id}/payment`);
        assert.equal(payload.asyncRefresh, true);
    }
});

test('lease table ignores unrelated payments while all-invoices table refreshes', async () => {
    let calls = 0;
    const { state } = create(async () => { calls++; return response(); });
    const unrelated = { detail: { lease_id: 'two', handled: false } };
    state.paymentRecorded(unrelated);
    assert.equal(unrelated.detail.handled, false);
    assert.equal(calls, 0);
    state.scope = 'all';
    state.paymentRecorded(unrelated);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(unrelated.detail.handled, true);
    assert.equal(calls, 1);
});

test('row failures stop loading and show a retry error without discarding existing records', async () => {
    const { state } = create(async () => ({ ok: false }));
    state.invoices = [{ id: 'existing' }];
    await state.refreshTable();
    assert.equal(state.loading, false);
    assert.match(state.invoiceError, /Unable to refresh/);
    assert.equal(state.invoices[0].id, 'existing');
});

test('controller-paginated mode starts from server rows without AJAX and keeps native payment refresh', () => {
    let requests = 0;
    const { state, events } = create(() => { requests++; }, {
        ajax: false, leaseId: null, initialPage: 2,
        initialRows: [{ id: 'server-row', invoice_no: 'INV1', amount_balance: '3.00', total_amount: '10.00', amount_paid: '7.00', wallet_balance: '7.00' }],
        pagination: { last_page: 4, total: 32 }
    });
    state.init();
    assert.equal(requests, 0);
    assert.equal(state.invoicePage, 2);
    assert.equal(state.invoices[0].id, 'server-row');
    state.recordPayment(state.invoices[0]);
    assert.equal(events.at(-1).detail.asyncRefresh, false);
    const payment = { detail: { lease_id: 'one', handled: false } };
    state.paymentRecorded(payment);
    assert.equal(payment.detail.handled, false);
    assert.equal(requests, 0);
});
