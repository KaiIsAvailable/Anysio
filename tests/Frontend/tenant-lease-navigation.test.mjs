import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/tenant-lease-navigation.js', import.meta.url), 'utf8');

function setup() {
    const navigations = [];
    let selection = '';
    const rows = ['desktop', 'mobile'].map((name) => ({
        dataset: { tenantLeaseUrl: '/leases/' + name },
        listeners: {},
        addEventListener(event, callback) { this.listeners[event] = callback; },
        closest() { return this; },
        fire(type, extra = {}) {
            const event = { target: this, button: 0, key: '', defaultPrevented: false,
                preventDefault() { this.defaultPrevented = true; }, ...extra };
            this.listeners[type](event);
            return event;
        },
    }));
    const document = {
        addEventListener(type, callback) { assert.equal(type, 'DOMContentLoaded'); callback(); },
        querySelectorAll(selector) { assert.equal(selector, '[data-tenant-lease-url]'); return rows; },
    };
    const window = { location: { assign: (url) => navigations.push(url) }, getSelection: () => ({ toString: () => selection }) };
    vm.runInNewContext(source, { document, window });
    return { rows, navigations, selectText: (value) => { selection = value; } };
}

test('Click, Enter and Space open the correct desktop row and mobile card', () => {
    const { rows, navigations } = setup();
    for (const row of rows) {
        row.fire('click', { target: { closest: () => row } });
        assert.equal(row.fire('keydown', { key: 'Enter' }).defaultPrevented, true);
        assert.equal(row.fire('keydown', { key: ' ' }).defaultPrevented, true);
    }
    assert.deepEqual(navigations, ['/leases/desktop', '/leases/desktop', '/leases/desktop', '/leases/mobile', '/leases/mobile', '/leases/mobile']);
});

test('Nested links, buttons, inputs and other controls retain their own actions', () => {
    const { rows: [row], navigations } = setup();
    for (const selector of ['a', 'button', 'input', 'select', 'textarea', 'label', 'summary', '[role="button"]', '[role="link"]', '[tabindex]', '[data-no-row-navigation]']) {
        const control = {};
        const target = { closest: (selectors) => { assert.ok(selectors.includes(selector)); return control; } };
        assert.equal(row.fire('click', { target }).defaultPrevented, false);
        assert.equal(row.fire('keydown', { target, key: 'Enter' }).defaultPrevented, false);
        assert.equal(row.fire('keydown', { target, key: ' ' }).defaultPrevented, false);
    }
    assert.deepEqual(navigations, []);
});

test('Modified clicks, prevented events, text selection and unrelated keys do not navigate', () => {
    const { rows: [row], navigations, selectText } = setup();
    for (const extra of [{ button: 1 }, { button: 2 }, { ctrlKey: true }, { metaKey: true }, { shiftKey: true }, { altKey: true }, { defaultPrevented: true }]) row.fire('click', extra);
    row.fire('keydown', { key: 'Tab' });
    row.fire('keydown', { key: 'Enter', repeat: true });
    row.fire('keydown', { key: 'Enter', ctrlKey: true });
    selectText('Selected lease name');
    row.fire('click');
    assert.deepEqual(navigations, []);
});
