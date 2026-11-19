import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';

const html = execFileSync('php', ['-r', `
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
Illuminate\\Support\\Facades\\Auth::setUser(new App\\Models\\User(['role' => 'admin']));
echo Illuminate\\Support\\Facades\\Blade::render('<x-modals.add-fee-type-modal />');
echo Illuminate\\Support\\Facades\\Blade::render('<x-maintenance.item-fields />');
`], { encoding: 'utf8', cwd: new URL('../../', import.meta.url), env: {
    ...process.env, APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_STORE: 'array', SESSION_DRIVER: 'array',
} });
const expression = html.match(/x-data="([\s\S]*?)"\s+@open-fee-type-modal/)[1];
const maintenanceExpression = html.match(/x-data="(\{\s*maintenanceItemOptions:[\s\S]*?)"\s+x-on:fee-type-added/)[1];

test('Maintenance quick-add selects new items immediately, ignores other categories and avoids duplicates', () => {
    const field = vm.runInNewContext('(' + maintenanceExpression + ')');
    field.addMaintenanceItem({ id: 'invoice', name: 'Invoice fee', category: 'invoice' });
    field.addMaintenanceItem(undefined);
    assert.equal(field.maintenanceItemOptions.length, 0);
    field.addMaintenanceItem({ id: 'item', name: 'Light bulb', category: 'item' });
    assert.equal(field.selectedMaintenanceItem[0], 'item');
    assert.equal(field.maintenanceItemOptions[0].label, 'Light bulb');
    field.addMaintenanceItem({ id: 'item', name: 'Updated bulb', category: 'item' });
    assert.equal(field.maintenanceItemOptions.length, 1);
    assert.equal(field.maintenanceItemOptions[0].label, 'Updated bulb');
});

function state(response) {
    const events = [], requests = [];
    const context = vm.createContext({
        fetch: async (url, options) => { requests.push(JSON.parse(options.body)); return response; },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        window: { dispatchEvent: event => events.push(event), location: { reload() {} } },
    });
    return { modal: vm.runInContext('(' + expression + ')', context), events, requests };
}

test('Hidden categories cannot open quick-add and report the permission error', () => {
    const { modal, events, requests } = state({});
    modal.allowedCategories = ['item'];
    modal.openModal('deposit');
    assert.equal(modal.openFeeModal, false);
    assert.equal(requests.length, 0);
    assert.equal(events[0].detail.message, 'You have no permission.');
    modal.openModal('item');
    assert.equal(modal.openFeeModal, true);
});

test('Quick-add sends item prices in cents and retains the fee-type-added event', async () => {
    const data = { success: true, feeType: { id: 'fee', name: 'Key', category: 'item' }, session_status: { type: 'success', message: 'Saved' } };
    const { modal, events, requests } = state({ ok: true, json: async () => data });
    modal.openModal('item');
    modal.form.name = 'Key';
    modal.form.original_price = '10.50';
    modal.form.sell_price = '15.25';
    const pending = modal.submitForm();
    assert.equal(modal.savingQuickFee, true);
    await pending;
    assert.equal(requests[0].original_price, 1050);
    assert.equal(requests[0].sell_price, 1525);
    assert.equal(events.find(event => event.type === 'fee-type-added').detail.sell_price, 1525);
    assert.equal(events.find(event => event.type === 'session-status').detail.message, 'Saved');
    assert.equal(modal.openFeeModal, false);
    assert.equal(modal.savingQuickFee, false);
});

test('Validation failure uses the layout status event, keeps entered values and resets loading', async () => {
    const { modal, events } = state({ ok: false, json: async () => ({ session_status: { type: 'error', message: 'Duplicate fee' } }) });
    modal.openModal('invoice');
    modal.form.name = 'Duplicate';
    await modal.submitForm();
    assert.equal(modal.openFeeModal, true);
    assert.equal(modal.form.name, 'Duplicate');
    assert.equal(modal.savingQuickFee, false);
    assert.equal(events[0].detail.type, 'error');
    assert.equal(events.some(event => event.type === 'fee-type-added'), false);
});

test('An action already pending cannot submit twice', async () => {
    const { modal, requests } = state({ ok: true, json: async () => ({ success: true, feeType: { id: 'fee' } }) });
    const pending = modal.submitForm();
    modal.submitForm();
    await pending;
    assert.equal(requests.length, 1);
});
