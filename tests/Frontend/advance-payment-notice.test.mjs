import test from 'node:test';
import assert from 'node:assert/strict';
import { advancePaymentNotice } from '../../resources/js/advance-payment-notice.js';

function setup() {
    const values = new Map();
    const storage = {
        getItem: key => values.get(key) ?? null,
        setItem: (key, value) => values.set(key, value),
        removeItem: key => values.delete(key),
    };
    const page = (loginKey = 'login-one', storageKey = 'account-one.invoice-one') => {
        const state = advancePaymentNotice({ storageKey, loginKey }, storage);
        state.init();
        return state;
    };
    return { page };
}

test('Close without checking the option minimizes; the next page opens the notice again', () => {
    const { page } = setup();
    const notice = page();
    assert.equal(notice.expanded, true);
    notice.close();
    assert.equal(notice.visible, true);
    assert.equal(notice.expanded, false);
    assert.equal(page().expanded, true);
});

test('Checking the option and closing fully hides the notice across pages in this login', () => {
    const { page } = setup();
    const notice = page();
    notice.savePreference(true);
    notice.close();
    assert.equal(notice.visible, false);
    assert.equal(notice.expanded, false);
    assert.equal(page().expanded, false);
    assert.equal(page().visible, false);
    assert.equal(page().remember, true);
});

test('Minimize remembers the choice; expanding remains available without clearing it', () => {
    const { page } = setup();
    const notice = page();
    notice.collapse();
    assert.equal(page().expanded, false);
    notice.expand();
    assert.equal(notice.expanded, true);
    assert.equal(page().expanded, false);
    assert.equal(notice.remember, false);
});

test('A new login always opens the notice again', () => {
    const { page } = setup();
    const previous = page();
    previous.savePreference(true);
    previous.close();
    const notice = page('login-two');
    assert.equal(notice.expanded, true);
    assert.equal(notice.visible, true);
    assert.equal(notice.remember, false);
});

test('Preferences are isolated by actor and subscription invoice', () => {
    const { page } = setup();
    const previous = page();
    previous.savePreference(true);
    previous.close();
    assert.equal(page('login-one', 'account-two.invoice-one').expanded, true);
    assert.equal(page('login-one', 'account-one.invoice-two').expanded, true);
});

test('Blocked browser storage does not break close, minimize or expand', () => {
    const unavailable = () => { throw new Error('Storage unavailable'); };
    const notice = advancePaymentNotice({ storageKey: 'notice', loginKey: 'login' }, {
        getItem: unavailable, setItem: unavailable, removeItem: unavailable,
    });
    notice.init();
    assert.equal(notice.expanded, true);
    notice.collapse();
    assert.equal(notice.expanded, false);
    notice.expand();
    notice.savePreference(false);
    notice.close();
    assert.equal(notice.visible, true);
    assert.equal(notice.expanded, false);
    notice.expand();
    notice.savePreference(true);
    notice.close();
    assert.equal(notice.visible, false);
});

test('Checking the option alone does not dismiss the notice until Close is clicked', () => {
    const { page } = setup();
    const notice = page();
    notice.savePreference(true);
    assert.equal(notice.visible, true);
    assert.equal(notice.expanded, true);
    assert.equal(page().visible, true);
    notice.savePreference(false);
    notice.close();
    assert.equal(page().expanded, true);
});
