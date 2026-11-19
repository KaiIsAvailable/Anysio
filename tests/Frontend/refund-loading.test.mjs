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

test('new checkout fee types update live deduction options and survive reopening without duplicates', () => {
    const handle = blade.match(/handleFeeTypeAdded\(detail\) \{[\s\S]*?\n {32}\},/)[0];
    const update = blade.match(/updateItemPrice\(index, eventOrId\) \{[\s\S]*?\n {32}\},/)[0];
    const window = { allFeeTypes: [{ id: 'existing', name: 'Existing', sell_price: 1000 }] };
    const state = vm.runInNewContext('({' + handle + update + '})', { window });
    Object.assign(state, { availableFeeTypes: window.allFeeTypes, manualDeductions: [{ fee_type_id: '', qty: 1, sell_price: 0 }] });
    const fee = { id: 'new', name: 'Cleaning', category: 'item', sell_price: 2500 };
    state.handleFeeTypeAdded(fee);
    assert.equal(state.availableFeeTypes.length, 2);
    assert.equal(state.manualDeductions[0].fee_type_id, 'new');
    assert.equal(state.manualDeductions[0].sell_price, 25);
    assert.equal(window.allFeeTypes[1].name, 'Cleaning');
    const optionsExpression = blade.match(/x-effect="options = (availableFeeTypes\.map\([^\n]+)"/)[1];
    const options = vm.runInNewContext(optionsExpression, { availableFeeTypes: state.availableFeeTypes });
    assert.equal(options[1].value, 'new');
    assert.equal(options[1].label, 'Cleaning (Item)');
    state.handleFeeTypeAdded({ ...fee, name: 'Updated Cleaning' });
    assert.equal(state.availableFeeTypes.length, 2);
    assert.equal(state.availableFeeTypes[1].name, 'Updated Cleaning');
    state.handleFeeTypeAdded({ id: 'rent', name: 'Rental', category: 'invoice' });
    assert.equal(state.availableFeeTypes.length, 2);
    state.manualDeductions = [];
    state.handleFeeTypeAdded({ id: 'another', name: 'Key', category: 'item', sell_price: 1000 });
    assert.equal(state.availableFeeTypes.length, 3);
    assert.equal(state.manualDeductions.length, 0);
    assert.equal(window.allFeeTypes.length, 3);
});

test('fresh checkout data is assigned to each reactive store only once', async () => {
    const { root, checkout, pending, load } = setup();
    let rootWrites = 0, checkoutWrites = 0;
    let rootValue = {}, checkoutValue = {};
    Object.defineProperty(root, 'refundData', { get: () => rootValue, set: value => { rootWrites++; rootValue = value; } });
    Object.defineProperty(checkout, 'refundData', { get: () => checkoutValue, set: value => { checkoutWrites++; checkoutValue = value; } });
    const loading = load('a');
    pending[0].resolve(preview());
    await loading;
    assert.equal(rootWrites, 1);
    assert.equal(checkoutWrites, 1);
});

test('forced refresh keeps financial data fresh and concurrent selections share one request', async () => {
    const { root, window, pending, load } = setup();
    window.leasePreviewData.a = preview('draft', 100000);
    root.refundForceRefresh = true;
    const first = load('a');
    const second = load('a');
    assert.equal(pending.length, 1);
    pending[0].resolve(preview('approved', 200000));
    await Promise.all([first, second]);
    assert.equal(root.refundData.a.total_deposit_collected_cents, 200000);
    assert.equal(root.refundForceRefresh, false);
});

test('refund template context preserves current deposit wallet unpaid invoices and edited deductions', () => {
    const source = blade.slice(blade.indexOf('const refundContext = {'), blade.indexOf("formData.set('refund_context'"));
    const result = vm.runInNewContext(source + '\nrefundContext;', {
        selectedLeaseData: { total_deposit_collected_cents: 100000, auto_deduction_total_cents: 30000, unpaid_invoices: [{ id: 'rent', balance_cents: 30000 }] },
        checkoutData: { walletRefundAmount: 200, availableFeeTypes: [{ id: 'clean', name: 'Cleaning' }], manualDeductions: [{ fee_type_id: 'clean', qty: 2, sell_price: 25 }] },
    });
    assert.equal(result.total_deposit_collected_cents, 100000);
    assert.equal(result.wallet_refund_cents, 20000);
    assert.equal(result.auto_deduction_total_cents, 30000);
    assert.equal(result.unpaid_invoices[0].id, 'rent');
    assert.equal(result.active_refund.items[0].description, 'Cleaning');
    assert.equal(result.active_refund.items[0].amount_cents, 5000);
    assert.equal(result.active_refund.items[0].qty, 2);
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
    const method = 'async ' + blade.match(/submitCheckout\(routeUrl, actionName, sourceForm = null\) \{[\s\S]*?\n\s{40}\}/)[0];
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

test('refund correction opens a dialog and only confirms with a nonblank reason', async () => {
    const request = blade.match(/requestRefundReason\(routeUrl, actionName\) \{[\s\S]*?\n {40}\},/)[0];
    const confirm = 'async ' + blade.match(/confirmRefundReason\(dialog\) \{[\s\S]*?\n {40}\},/)[0];
    const sourceForm = {}, events = [], submissions = [];
    const state = vm.runInNewContext('({' + request + confirm + '})', { $el: { closest: () => sourceForm } });
    Object.assign(state, { checkoutSubmitting: false, refundReady: true, refundLoading: false, refundLoadError: '', transitionReason: '' });
    state.$dispatch = event => events.push(event);
    state.submitCheckout = async (...args) => submissions.push(args);
    let validity = '';
    const field = { setCustomValidity: message => { validity = message; }, reportValidity: () => !validity };
    const dialog = { querySelector: () => field };
    for (const action of ['reject', 'revoke']) {
        state.requestRefundReason('/' + action, action);
        assert.equal(events.at(-1), 'open-refund-reason-modal');
        const before = submissions.length;
        assert.equal(submissions.length, before);
        state.transitionReason = '   ';
        await state.confirmRefundReason(dialog);
        assert.equal(submissions.length, before);
        assert.equal(validity, 'Enter a reason.');
        state.transitionReason = 'Correct the deduction';
        await state.confirmRefundReason(dialog);
        assert.deepEqual(submissions.at(-1), ['/' + action, action, sourceForm]);
        assert.equal(events.at(-1), 'close-refund-reason-modal');
    }
    state.refundLoading = true;
    const count = events.length;
    state.requestRefundReason('/reject', 'reject');
    assert.equal(events.length, count);
});

test('cancelling a refund reason clears the pending action without submitting and allows reopening', () => {
    const request = blade.match(/requestRefundReason\(routeUrl, actionName\) \{[\s\S]*?\n {40}\},/)[0];
    const cancel = blade.match(/cancelRefundReason\(\) \{[\s\S]*?\n {40}\},/)[0];
    const state = vm.runInNewContext('({' + request + cancel + '})', { $el: { closest: () => ({}) } });
    Object.assign(state, { checkoutSubmitting: false, refundReady: true, refundLoading: false, refundLoadError: '', refundReasonOpen: false });
    let submissions = 0;
    state.sendLeaseForm = () => { submissions++; };
    state.$dispatch = event => { state.refundReasonOpen = event === 'open-refund-reason-modal'; };
    for (const action of ['reject', 'revoke']) {
        state.requestRefundReason('/' + action, action);
        assert.equal(state.refundReasonOpen, true);
        state.cancelRefundReason();
        assert.equal(state.refundReasonOpen, false);
        assert.equal(state.transitionAction, '');
        assert.equal(state.transitionRoute, '');
        assert.equal(state.transitionForm, null);
        assert.equal(state.checkoutSubmitting, false);
        assert.equal(submissions, 0);
    }
    assert.match(blade, /<template x-if="refundReasonOpen">\s*<x-modals\.confirmation-modal id="refund-reason-modal"/);
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
