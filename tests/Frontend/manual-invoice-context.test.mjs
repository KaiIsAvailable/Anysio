import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const blade = readFileSync(new URL('../../resources/views/components/modals/manual-invoice-modal.blade.php', import.meta.url), 'utf8');
const expression = blade.match(/x-data="([\s\S]*?)"\s+@open-manual-modal/)[1].replace('@js($feeTypes)', '[]');
const state = () => vm.runInNewContext('(' + expression + ')');

test('The post-refund section is only available on checked-out leases with a paid refund', () => {
    const modal = state();
    for (const leaseStatus of ['New', 'Renew', 'Check Out', 'refunded']) {
        for (const refundStatus of [undefined, 'draft', 'pending', 'approved', 'processing', 'paid']) {
            modal.configureAdjustment({ leaseStatus, refundStatus });
            assert.equal(modal.showPostRefundAdjustment,
                ['Check Out', 'refunded'].includes(leaseStatus) && refundStatus === 'paid',
                `${leaseStatus}/${refundStatus}`);
        }
    }
});

test('Switching leases clears a previous adjustment selection and reason', () => {
    const modal = state();
    modal.configureAdjustment({ leaseStatus: 'Check Out', refundStatus: 'paid' });
    modal.postRefundAdjustment = true;
    modal.adjustmentReason = 'Old reason';
    modal.configureAdjustment({ leaseStatus: 'New' });
    assert.equal(modal.showPostRefundAdjustment, false);
    assert.equal(modal.postRefundAdjustment, false);
    assert.equal(modal.adjustmentReason, '');
    modal.configureAdjustment({ leaseStatus: 'Check Out', refundStatus: 'paid' });
    assert.equal(modal.showPostRefundAdjustment, true);
    assert.equal(modal.postRefundAdjustment, false);
    assert.equal(modal.adjustmentReason, '');
});
