import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/components/modals/payment-modal.blade.php', import.meta.url), 'utf8');
const expression = blade.match(/x-data="([\s\S]*?)"\s+class=/)[1]
    .replace(/\{\{ route\([\s\S]*?\) \}\}/g, '/preview/__ID__')
    .replace(/\{\{ csrf_token\(\) \}\}/g, 'csrf');

function setup() {
    const requests = [];
    const pending = [];
    const fetch = (url, options) => {
        requests.push({ url, options });
        return new Promise((resolve, reject) => pending.push({
            resolve: (data, ok = true) => resolve({ ok, json: async () => data }), reject,
        }));
    };
    const state = vm.runInNewContext('(' + expression + ')', { fetch });
    state.paymentData = { id: 'invoice', totalAmount: '100.00', initialPenalty: { amount: 99 } };
    state.form.payment_date = '2026-10-20';
    return { state, pending, requests };
}

test('Zero or disabled preview clears old penalties and sends only the selected payment date', async () => {
    const { state, pending, requests } = setup();
    state.dynamicPenalty = { amount: 10 };
    const promise = state.updatePenalty();
    assert.equal(state.penaltyDetails, null);
    pending[0].resolve(null);
    await promise;
    assert.equal(state.penaltyDetails, null);
    assert.equal(state.computedAmount, '100.00');
    assert.deepEqual(JSON.parse(requests[0].options.body), { payment_date: '2026-10-20' });
});

test('Due date itself requests a quote and displays the first RM10 penalty with RM110 total', async () => {
    const { state, pending, requests } = setup();
    state.form.payment_date = '2026-10-12';
    state.form.due_date = '2026-10-12';
    state.paymentData.invoiceItems = [{ amount: 100 }];
    const promise = state.updatePenalty();
    assert.equal(requests.length, 1);
    assert.deepEqual(JSON.parse(requests[0].options.body), { payment_date: '2026-10-12' });
    pending[0].resolve({ amount: 10, title: 'Late Penalty', start_date: '2026-10-12', periods: 1 });
    await promise;
    assert.equal(state.penaltyDetails.amount, 10);
    assert.equal(state.computedAmount, '110.00');
    assert.equal(state.penaltyError, '');
});

test('Later date selection wins when an older preview finishes last', async () => {
    const { state, pending } = setup();
    const older = state.updatePenalty();
    state.form.payment_date = '2026-10-23';
    const newer = state.updatePenalty();
    pending[1].resolve({ amount: 20 });
    await newer;
    pending[0].resolve({ amount: 10 });
    await older;
    assert.equal(state.penaltyDetails.amount, 20);
    assert.equal(state.computedAmount, '120.00');
    assert.equal(state.penaltyLoading, false);
});

test('Review errors clear the quote and prevent confirmation until a valid retry', async () => {
    const { state, pending } = setup();
    const failure = state.updatePenalty();
    pending[0].resolve({ errors: { penalty: ['Review the existing penalty.'] } }, false);
    await failure;
    assert.equal(state.penaltyError, 'Review the existing penalty.');
    assert.equal(state.penaltyDetails, null);
    assert.match(blade, /x-bind:disabled="penaltyLoading \|\| !!penaltyError"/);
    const retry = state.updatePenalty();
    pending[1].resolve({ amount: 10 });
    await retry;
    assert.equal(state.penaltyError, '');
    assert.equal(state.penaltyDetails.amount, 10);
});

test('A failed stale request cannot overwrite a newer successful quote', async () => {
    const { state, pending } = setup();
    const older = state.updatePenalty();
    const newer = state.updatePenalty();
    pending[1].resolve({ amount: 20 });
    await newer;
    pending[0].reject(new Error('Old network error'));
    await older;
    assert.equal(state.penaltyDetails.amount, 20);
    assert.equal(state.penaltyError, '');
});
