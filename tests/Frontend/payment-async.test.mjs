import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/components/modals/payment-modal.blade.php', import.meta.url), 'utf8');
const expression = blade.match(/x-data="([\s\S]*?)"\s+class="fixed/)[1]
    .replace('@js($processingFeeConfig)', '{ enabled: false, amount: 0, methods: [] }')
    .replace(/{{[\s\S]*?}}/g, 'test');

function setup(fetch) {
    const events = [];
    let reloads = 0;
    const modal = vm.runInNewContext('(' + expression + ')', {
        fetch, FormData: class { constructor(form) { this.form = form; } append() {} },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        window: { location: { reload() { reloads++; } }, dispatchEvent(event) {
            events.push(event);
            if (event.type === 'payment-recorded') event.detail.handled = true;
        } }
    });
    modal.openPayment = true;
    modal.paymentData = { asyncRefresh: true };
    modal.$dispatch = (type, detail) => events.push({ type, detail });
    return { modal, events, reloads: () => reloads };
}
const event = { target: { action: '/payment' } };

test('pending payment prevents a second financial submission', async () => {
    let resolve;
    let calls = 0;
    const { modal } = setup(() => { calls++; return new Promise(done => { resolve = done; }); });
    const pending = modal.submitPayment(event);
    await modal.submitPayment(event);
    assert.equal(calls, 1);
    assert.equal(modal.submittingPayment, true);
    resolve({ ok: true, json: async () => ({ success: true, lease_id: 'lease' }) });
    await pending;
    assert.equal(modal.submittingPayment, false);
});

test('successful payment updates the lease and summary without reloading', async () => {
    const { modal, events, reloads } = setup(async () => ({ ok: true, json: async () => ({
        success: true, lease_id: 'lease', wallet_balance: '10.00', message: 'Payment recorded', wallet_summary_html: '<p>INV123 paid</p>'
    }) }));
    await modal.submitPayment(event);
    assert.equal(modal.openPayment, false);
    assert.equal(reloads(), 0);
    assert.equal(events.find(e => e.type === 'payment-recorded').detail.wallet_balance, '10.00');
    assert.equal(events.find(e => e.type === 'wallet-payment-summary').detail.html, '<p>INV123 paid</p>');
    assert.equal(events.at(-1).detail, false);
});

test('validation rejection keeps the modal open and displays the server error', async () => {
    const { modal, events } = setup(async () => ({ ok: false, json: async () => ({ errors: { refund: ['Invoice locked to refund'] } }) }));
    await modal.submitPayment(event);
    assert.equal(modal.openPayment, true);
    assert.equal(events.find(e => e.type === 'session-status').detail.message, 'Invoice locked to refund');
    assert.equal(events.some(e => e.type === 'payment-recorded'), false);
    assert.equal(modal.submittingPayment, false);
});

test('a lost response never automatically retries a financial submission', async () => {
    let calls = 0;
    const { modal } = setup(async () => { calls++; throw new Error('Connection lost'); });
    await modal.submitPayment(event);
    assert.equal(calls, 1);
    assert.equal(modal.openPayment, true);
    assert.match(modal.paymentError, /Refresh and check the invoice/);
});

test('payment errors also keep modals open on screens without a partial table refresh', async () => {
    const { modal, events, reloads } = setup(async () => ({ ok: false, json: async () => ({
        success: false, session_status: { type: 'error', message: 'Clear earlier invoices first' }
    }) }));
    modal.paymentData = {};
    await modal.submitPayment(event);
    assert.equal(modal.openPayment, true);
    assert.equal(reloads(), 0);
    assert.equal(events.find(e => e.type === 'session-status').detail.message, 'Clear earlier invoices first');
});

test('the shared session-status display accepts new messages and resets its dismissal timer', () => {
    const component = readFileSync(new URL('../../resources/views/components/auth-session-status.blade.php', import.meta.url), 'utf8');
    const expression = component.match(/x-data="([\s\S]*?)"/)[1]
        .replace("@js($message ?? '')", "''").replace('@js($isError)', 'false');
    const cleared = [];
    let timer = 0;
    const display = vm.runInNewContext('(' + expression + ')', { clearTimeout: id => cleared.push(id), setTimeout: () => ++timer });
    display.display({ type: 'error', message: 'Payment rejected' });
    assert.equal(display.show, true);
    assert.equal(display.isError, true);
    display.display({ type: 'success', message: 'Payment recorded' });
    assert.equal(display.isError, false);
    assert.equal(display.message, 'Payment recorded');
    assert.equal(cleared.at(-1), 1);
});

test('closing a loading document prevents a late response from reopening it', async () => {
    const code = readFileSync(new URL('../../resources/js/lease-invoice-documents.js', import.meta.url), 'utf8');
    const listeners = {};
    const previews = [];
    const events = [];
    let resolve;
    const window = { addEventListener(type, listener) { listeners[type] = listener; },
        dispatchEvent(event) { events.push(event); }, openDocumentPreview(...args) { previews.push(args); } };
    vm.runInNewContext(code, { window, document: { getElementById: () => ({ innerHTML: 'Skeleton' }) },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        fetch: () => new Promise(done => { resolve = done; }) });
    const pending = window.openLeaseInvoiceDocument('/document', 'Invoice');
    assert.equal(events[0].type, 'open-lease-preview-loading');
    assert.equal(events[0].detail.title, 'Invoice');
    listeners['lease-document-preview-closed']();
    resolve({ ok: true, json: async () => ({ content: 'Invoice HTML' }) });
    await pending;
    assert.equal(previews.length, 0);
});

test('invoice and receipt documents request the agreement preview and retain receipt values', async () => {
    const code = readFileSync(new URL('../../resources/js/lease-invoice-documents.js', import.meta.url), 'utf8');
    const previews = [];
    const window = { addEventListener() {}, dispatchEvent() {}, openDocumentPreview(...args) { previews.push(args); } };
    vm.runInNewContext(code, { window,
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        fetch: async () => ({ ok: true, json: async () => ({ type: 'receipt', title: 'Receipt', content: '<p>Receipt</p>',
            variables: {}, items: [], extra: { paidAmount: '100.00', receiptNo: 'REC001' } }) }) });
    await window.openLeaseInvoiceDocument('/receipt', 'Receipt');
    assert.equal(previews.length, 1);
    assert.equal(previews[0][5].previewTarget, 'agreement');
    assert.equal(previews[0][5].paidAmount, '100.00');
    assert.equal(previews[0][5].receiptNo, 'REC001');
    assert.match(previews[0][5].previewRequestId, /^invoice-document-/);
});
