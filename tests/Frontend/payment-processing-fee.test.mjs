import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/components/modals/payment-modal.blade.php', import.meta.url), 'utf8');
const expression = blade.match(/x-data="([\s\S]*?)"\s+class="fixed/)[1]
    .replace('@js($processingFeeConfig)', '{ enabled: true, amount: 10, methods: [\'Cash\'] }')
    .replace(/{{[\s\S]*?}}/g, 'test');
const state = () => vm.runInNewContext('(' + expression + ')');

test('Cash displays the configured processing fee when the modal opens', () => {
    const modal = state();
    assert.equal(modal.method, 'Cash');
    assert.equal(modal.processingFeeDetails.amount, 10);
});

test('Switching payment methods updates eligibility for every selected method', () => {
    const modal = state();
    modal.method = 'Bank Transfer';
    assert.equal(modal.processingFeeDetails, null);
    modal.processingFeeConfig.methods.push('Bank Transfer');
    assert.equal(modal.processingFeeDetails.amount, 10);
    modal.method = 'Cheque';
    assert.equal(modal.processingFeeDetails, null);
    modal.method = 'Cash';
    assert.equal(modal.processingFeeDetails.amount, 10);
});

test('Disabled settings and nonpositive fees hide the processing fee', () => {
    const modal = state();
    modal.processingFeeConfig.enabled = false;
    assert.equal(modal.processingFeeDetails, null);
    modal.processingFeeConfig.enabled = true;
    for (const amount of [0, -10, NaN, Infinity]) {
        modal.processingFeeConfig.amount = amount;
        assert.equal(modal.processingFeeDetails, null);
    }
});

test('Wallet use hides the fee and switching back restores it', () => {
    const modal = state();
    modal.useWallet = true;
    assert.equal(modal.processingFeeDetails, null);
    modal.method = 'Wallet';
    modal.useWallet = false;
    assert.equal(modal.processingFeeDetails, null);
    modal.method = 'Cash';
    assert.equal(modal.processingFeeDetails.amount, 10);
});

test('Editing the fee supports decimals, zero and clearing without hiding the input', () => {
    const modal = state();
    assert.equal(modal.processingFeeInputAmount, 10);
    modal.processingFeeInputAmount = '12.50';
    assert.equal(modal.processingFeeDetails.amount, 12.5);
    for (const amount of ['0', '']) {
        modal.overrideProcessingFeeAmount = amount;
        assert.equal(modal.processingFeeDetails.amount, 0);
    }
    modal.overrideProcessingFeeAmount = '5';
    modal.method = 'Cheque';
    assert.equal(modal.processingFeeDetails, null);
    modal.method = 'Cash';
    assert.equal(modal.processingFeeDetails.amount, 5);
});

test('Cancelling and restoring preserves the edited amount', () => {
    const modal = state();
    modal.overrideProcessingFeeAmount = '7.50';
    modal.processingFeeCancelled = true;
    assert.equal(modal.processingFeeDetails, null);
    assert.equal(modal.processingFeeApplicable, true);
    modal.processingFeeCancelled = false;
    assert.equal(modal.processingFeeDetails.amount, 7.5);
});

test('Opening another payment clears processing fee edits and cancellation', () => {
    const modal = state();
    modal.overrideProcessingFeeAmount = 2;
    modal.processingFeeCancelled = true;
    modal.paymentData = {};
    modal.form = {};
    modal.updatePenalty = () => {};
    const handler = blade.match(/@open-payment.window="([\s\S]*?)"/)[1]
        .replace(/{{[\s\S]*?}}/g, '2026-11-10');
    const context = vm.createContext({ modal, $event: { detail: {} }, $el: { querySelectorAll: () => [] } });
    vm.runInContext('with (modal) { ' + handler + ' }', context);
    assert.equal(modal.overrideProcessingFeeAmount, null);
    assert.equal(modal.processingFeeCancelled, false);
    assert.equal(modal.processingFeeDetails.amount, 10);
});

test('Amount Paid includes the active processing fee and reacts to edits, removal and method changes', () => {
    const modal = state();
    modal.paymentData = { totalAmount: '1,000.00' };
    modal.dynamicPenalty = { amount: 20 };
    modal.overridePenaltyAmount = 20;
    assert.equal(modal.computedAmount, '1030.00');
    modal.processingFeeInputAmount = '7.50';
    assert.equal(modal.computedAmount, '1027.50');
    modal.processingFeeCancelled = true;
    assert.equal(modal.computedAmount, '1020.00');
    modal.processingFeeCancelled = false;
    modal.method = 'Bank Transfer';
    assert.equal(modal.computedAmount, '1020.00');
    modal.method = 'Cash';
    modal.penaltyCancelled = true;
    assert.equal(modal.computedAmount, '1007.50');
    modal.useWallet = true;
    assert.equal(modal.computedAmount, '0.00');
});
