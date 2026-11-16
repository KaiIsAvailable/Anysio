import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const context = vm.createContext({ queueMicrotask });
vm.runInContext(readFileSync(new URL('../../resources/js/form-validation.js', import.meta.url), 'utf8').replace(/export function /g, 'function '), context);

function control(name, options = {}) {
    return { name, type: 'text', disabled: false, validity: { valid: true },
        getClientRects: () => [1], setAttribute() {}, addEventListener() {},
        scrollIntoView() { this.scrolled = true; }, focus() { this.focused = true; }, ...options };
}

test('Laravel errors focus the first erroneous field in document order, including nested names', () => {
    const first = control('rooms[0][room_no]'); const last = control('floor');
    context.focusFormErrors({ querySelectorAll: () => [first, last] }, ['floor', 'rooms.0.room_no']);
    assert.equal(first.focused, true); assert.equal(first.scrolled, true); assert.equal(last.focused, undefined);
});

test('shared select errors focus the visible control instead of its hidden input', () => {
    const select = control(''); const hidden = control('owner_id', { type: 'hidden', closest: () => ({ querySelector: () => select }) });
    context.focusFormErrors({ querySelectorAll: () => [hidden] }, ['owner_id']);
    assert.equal(select.focused, true); assert.equal(hidden.focused, undefined);
});

test('client validation skips disabled and hidden controls, and focuses the first invalid field once', async () => {
    const skipped = control('disabled', { disabled: true, validity: { valid: false } });
    const hidden = control('invisible', { getClientRects: () => [], validity: { valid: false } });
    const first = control('floor', { validity: { valid: false } }); let count = 0; first.focus = () => count++;
    const form = { querySelectorAll: () => [skipped, hidden, first] };
    const event = { currentTarget: form, preventDefault() {} };
    context.focusInvalidForm(event); context.focusInvalidForm(event);
    await new Promise(resolve => queueMicrotask(resolve));
    assert.equal(count, 1);
});
