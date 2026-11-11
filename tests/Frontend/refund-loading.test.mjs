import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/components/modals/lease-create-modal.blade.php', import.meta.url), 'utf8');
const start = blade.indexOf('async function handleLeaseChange(');
const handler = blade.slice(start, blade.indexOf('\n    </script>', start))
    .replace(/\{\{ url\('admin\/leases'\) \}\}/g, '/admin/leases');

function setup() {
    const root = { action: 'checkout', formLeaseId: 'a', refundLoadSequence: 0, refundReady: false, refundLoading: false, refundLoadError: '', refundData: {} };
    const checkout = { refundData: {}, availableFeeTypes: [], manualDeductions: [] };
    const elements = {
        'lease-create-modal-root': {}, 'checkout_refund_section': {},
        'checkout-total-deposit': { innerText: '' },
        'auto-deductions-container': { classList: { add() {}, remove() {} } },
        'auto-deductions-list': { innerHTML: '' },
    };
    const pending = [];
    const window = {
        leasePreviewData: { a: { id: 'a' }, b: { id: 'b' } }, leasePreviewRequests: {},
        Alpine: { $data: element => element === elements['lease-create-modal-root'] ? root : checkout },
    };
    const context = vm.createContext({
        window, allLeases: {}, applyStoredCheckoutFields() {},
        document: { getElementById: id => elements[id] ?? null },
        console: { log() {}, group() {}, groupEnd() {}, error() {} },
        fetch: url => new Promise((resolve, reject) => pending.push({
            url, reject, resolve: (data, ok = true) => resolve({ ok, json: async () => data }),
        })),
    });
    vm.runInContext(handler, context);
    return { root, checkout, elements, window, pending, load: context.handleLeaseChange };
}

const preview = (status = 'approved', deposit = 100000) => ({
    unpaid_invoices: [], tenant_bank_details: [], auto_deduction_total_cents: 0,
    total_deposit_collected_cents: deposit, wallet_refund_cents: 37300,
    active_refund: status ? { id: 'refund', status, items: [] } : null,
});

test('Actions stay unavailable until the refund response has been populated', async () => {
    const { root, checkout, elements, pending, load } = setup();
    const loading = load('a');
    assert.equal(root.refundLoading, true);
    assert.equal(root.refundReady, false);
    pending[0].resolve(preview());
    await loading;
    assert.equal(root.refundLoading, false);
    assert.equal(root.refundReady, true);
    assert.equal(root.refundData.a.active_refund.status, 'approved');
    assert.equal(checkout.walletRefundAmount, 373);
    assert.equal(elements['checkout-total-deposit'].innerText, 'RM 1000.00');
});

test('Failed requests leave actions unavailable and can be retried successfully', async () => {
    const { root, pending, load } = setup();
    let loading = load('a');
    pending[0].resolve({}, false);
    await loading;
    assert.equal(root.refundReady, false);
    assert.equal(root.refundLoading, false);
    assert.ok(root.refundLoadError);
    loading = load('a');
    assert.equal(root.refundLoadError, '');
    pending[1].resolve(preview('pending'));
    await loading;
    assert.equal(root.refundReady, true);
    assert.equal(root.refundData.a.active_refund.status, 'pending');
});

test('Incomplete responses cannot enable checkout actions', async () => {
    const { root, pending, load } = setup();
    const loading = load('a');
    pending[0].resolve({ unpaid_invoices: [] });
    await loading;
    assert.equal(root.refundReady, false);
    assert.ok(root.refundLoadError);
});

test('A slow response for the previous lease cannot replace the selected settlement', async () => {
    const { root, elements, pending, load } = setup();
    const first = load('a');
    root.formLeaseId = 'b';
    const second = load('b');
    pending[1].resolve(preview('pending', 200000));
    await second;
    pending[0].resolve(preview('draft', 100000));
    await first;
    assert.equal(root.refundReady, true);
    assert.equal(root.refundLoading, false);
    assert.equal(root.refundData.b.active_refund.status, 'pending');
    assert.equal(elements['checkout-total-deposit'].innerText, 'RM 2000.00');
});

test('Cached complete data is populated without another request', async () => {
    const { root, window, pending, load } = setup();
    window.leasePreviewData.a = preview(null);
    await load('a');
    assert.equal(pending.length, 0);
    assert.equal(root.refundReady, true);
    assert.equal(root.refundLoading, false);
    assert.equal(root.refundData.a.active_refund, null);
});

test('The submit handler rejects calls while loading or after a load failure', async () => {
    const method = 'async ' + blade.match(/submitCheckout\(routeUrl, actionName\) \{[\s\S]*?\n\s{40}\}/)[0];
    let submissions = 0;
    let submittedUrl;
    const form = { action: '/leases/store' };
    const state = vm.runInNewContext('({' + method + '})', { $el: { closest: () => form } });
    state.sendLeaseForm = async (_, url) => { submissions++; submittedUrl = url; return false; };
    Object.assign(state, { refundReady: false, refundLoading: true, refundLoadError: '' });
    await state.submitCheckout('/pending', 'pending');
    Object.assign(state, { refundReady: false, refundLoading: false, refundLoadError: 'Failed' });
    await state.submitCheckout('/pending', 'pending');
    assert.equal(submissions, 0);
    Object.assign(state, { refundReady: true, refundLoading: false, refundLoadError: '' });
    await state.submitCheckout('/pending', 'pending');
    assert.equal(submissions, 1);
    assert.equal(submittedUrl, '/pending');
    assert.equal(form.action, '/leases/store');
    assert.equal(state.checkoutSubmitting, false);
    assert.equal(state.subAction, '');
});

function submission(fetch) {
    const method = 'async ' + blade.match(/sendLeaseForm\(form, url\) \{[\s\S]*?\n {8}\},/)[0];
    const messages = [], navigation = [];
    const state = vm.runInNewContext('({' + method + '})', {
        fetch, FormData: class { constructor(form) { this.form = form; } },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        window: { dispatchEvent: event => messages.push(event), location: {
            assign: url => navigation.push(url), reload: () => navigation.push('reload')
        } }
    });
    state.open = true;
    return { state, messages, navigation };
}

test('renewal errors leave the modal and entered dates intact without navigation', async () => {
    const { state, messages, navigation } = submission(async () => ({ ok: false, json: async () => ({
        success: false, errors: { start_date: ['Choose a later renewal date'] }
    }) }));
    const form = { start_date: '2027-09-01', end_date: '2028-08-31' };
    await state.sendLeaseForm(form, '/renew');
    assert.equal(state.open, true);
    assert.equal(form.start_date, '2027-09-01');
    assert.equal(form.end_date, '2028-08-31');
    assert.equal(navigation.length, 0);
    assert.equal(messages[0].detail.message, 'Choose a later renewal date');
});

test('checkout errors stay open and successful actions alone navigate', async () => {
    const failed = submission(async () => ({ ok: false, json: async () => ({ success: false, message: 'Review the updated refund' }) }));
    await failed.state.sendLeaseForm({}, '/approve');
    assert.equal(failed.state.open, true);
    assert.equal(failed.navigation.length, 0);
    const successful = submission(async () => ({ ok: true, json: async () => ({ success: true, redirect: '/leases' }) }));
    assert.equal(await successful.state.sendLeaseForm({}, '/renew'), true);
    assert.deepEqual(successful.navigation, ['/leases']);
});
