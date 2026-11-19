import test from 'node:test';
import assert from 'node:assert/strict';
import { feeTypeSettings } from '../../resources/js/fee-type-settings.js';

test('New fees default to an allowed category when invoice is hidden', () => {
    const state = feeTypeSettings({ rows: {}, fee: { category: 'deposit' }, defaultCategory: 'deposit', editing: false });
    state.$dispatch = () => {};
    state.openEditor();
    assert.equal(state.fee.category, 'deposit');
    assert.deepEqual(state.feeCategories, ['deposit']);
});

function setup() {
    const events = [], calls = [];
    const row = { name: 'Key', category: 'item', original_price: '10.00', sell_price: '15.00',
        is_active: true, editable: true, can_toggle: true, used: false, deleted: false, delete_url: '/fees/a' };
    const state = feeTypeSettings({ rows: { a: row, b: { ...row, name: 'Chair', delete_url: '/fees/b' } },
        editing: true, fee: { category: 'invoice' }, saveUrl: '/fees/batch', saveError: 'Save failed', deleteError: 'Delete failed' });
    state.$dispatch = (type, detail) => events.push({ type, detail });
    global.window = { axios: {
        put: async (url, body) => { calls.push({ url, body }); return { data: { success: true, fees: body.fees.map(fee => ({
            id: fee.id, name: state.rows[fee.id].name.trim(), original_price: 1000, sell_price: 1525, effective_active: false,
        })), session_status: { type: 'success', message: 'Saved' } } }; },
        delete: async url => { calls.push({ url }); return { data: { success: true, session_status: { type: 'success', message: 'Deleted' } } }; },
    } };
    return { state, calls, events };
}

test('Tracks only changed fields, compares prices in cents, and ignores reverted values', () => {
    const { state } = setup();
    state.rows.a.original_price = '10';
    assert.deepEqual(state.changes, []);
    state.rows.a.sell_price = '15.25';
    state.rows.a.is_active = false;
    assert.deepEqual(state.changes, [{ id: 'a', sell_price: 1525, is_active: false }]);
    state.rows.a.sell_price = '15.00';
    state.rows.a.is_active = true;
    assert.deepEqual(state.changes, []);
});

test('One save request persists changes, prevents double submits, and resets the baseline', async () => {
    const { state, calls, events } = setup();
    state.rows.a.sell_price = '15.25';
    state.rows.a.is_active = false;
    const pending = state.saveFees();
    assert.equal(state.savingFees, true);
    await state.saveFees();
    await pending;
    assert.equal(calls.length, 1);
    assert.deepEqual(calls[0].body.fees, [{ id: 'a', sell_price: 1525, is_active: false }]);
    assert.equal(state.savingFees, false);
    assert.equal(state.editingFees, false);
    assert.deepEqual(state.changes, []);
    assert.equal(events.at(-1).detail.message, 'Saved');
});

test('Save failure retains all pending edits, reports the error and allows retry', async () => {
    const { state, events } = setup();
    state.rows.a.name = 'Duplicate';
    window.axios.put = async () => { throw { response: { data: { message: 'Already exists' } } }; };
    await state.saveFees();
    assert.equal(state.savingFees, false);
    assert.equal(state.editingFees, true);
    assert.deepEqual(state.changes, [{ id: 'a', name: 'Duplicate' }]);
    assert.equal(events.at(-1).detail.message, 'Already exists');
    state.resetFees();
    assert.deepEqual(state.changes, []);
});

test('Confirmation deletes the selected URL only once and preserves other pending edits', async () => {
    const { state, calls, events } = setup();
    state.rows.b.name = 'Updated chair';
    state.openDelete('a');
    assert.equal(calls.length, 0);
    assert.equal(events.at(-1).type, 'open-delete-fee-type');
    const pending = state.confirmDeleteFee();
    await state.confirmDeleteFee();
    await pending;
    assert.deepEqual(calls, [{ url: '/fees/a' }]);
    assert.equal(state.rows.a.deleted, true);
    assert.equal(state.deletingFee, false);
    assert.deepEqual(state.changes, [{ id: 'b', name: 'Updated chair' }]);
    assert.equal(events.some(event => event.type === 'close-delete-fee-type'), true);
    state.resetFees();
    assert.equal(state.rows.a.deleted, true);
});

test('Delete failure keeps the dialog and record, reports the error and resets loading', async () => {
    const { state, events } = setup();
    window.axios.delete = async () => { throw { response: { data: { message: 'Now in use' } } }; };
    state.openDelete('a');
    await state.confirmDeleteFee();
    assert.equal(state.rows.a.deleted, false);
    assert.equal(state.deleting.id, 'a');
    assert.equal(state.deletingFee, false);
    assert.equal(events.at(-1).detail.message, 'Now in use');
    assert.equal(events.some(event => event.type === 'close-delete-fee-type'), false);
});

test('Used fees can be renamed but not deleted; unavailable actions are excluded and history resets loading', () => {
    const { state, events } = setup();
    Object.assign(state.rows.a, { used: true, name: 'Renamed', sell_price: '' });
    Object.assign(state.rows.b, { editable: false, can_toggle: false, name: 'Forbidden', is_active: false });
    state.openDelete('a');
    assert.equal(events.length, 0);
    assert.deepEqual(state.changes, [{ id: 'a', name: 'Renamed', sell_price: null }]);
    state.savingFees = true; state.deletingFee = true; state.savingFee = true;
    state.resetLoading();
    assert.equal(state.savingFees || state.deletingFee || state.savingFee, false);
});
