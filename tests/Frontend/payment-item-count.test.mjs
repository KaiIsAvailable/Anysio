import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/components/modals/payment-modal.blade.php', import.meta.url), 'utf8');
const expression = blade.match(/x-data="([\s\S]*?)"\s+class="fixed/)[1]
    .replace('@js($processingFeeConfig)', '{ enabled: false, amount: 0, methods: [] }')
    .replace(/{{[\s\S]*?}}/g, 'test');
const count = blade.match(/x-text="([^"]+)"[^>]*><\/span>\s*<\/div>\s*<div class="divide-y/)[1];

test('empty penalty responses do not count an invisible third item', () => {
    const modal = vm.runInNewContext('(' + expression + ')');
    modal.paymentData = { invoiceItems: [{ amount: 10 }, { amount: 20 }] };
    for (const response of [null, [], {}]) {
        modal.dynamicPenalty = response;
        assert.equal(modal.penaltyDetails, null);
        assert.equal(vm.runInNewContext(count, modal), '2 Item(s)');
    }
    modal.dynamicPenalty = { amount: 10, title: 'Late Penalty' };
    modal.overridePenaltyAmount = 10;
    assert.equal(vm.runInNewContext(count, modal), '3 Item(s)');
    modal.penaltyCancelled = true;
    assert.equal(vm.runInNewContext(count, modal), '2 Item(s)');
});
