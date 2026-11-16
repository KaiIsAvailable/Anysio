import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/views/components/form/text-input.blade.php', import.meta.url), 'utf8');
const handler = source.match(/@wheel="([^"]+)"/)[1];

test('text and email fields allow scrolling without losing focus', () => {
    assert.equal(source.includes('@wheel.prevent'), false);
    for (const type of ['text', 'email', 'tel']) {
        const field = { type, blur() { throw new Error('Text focus should remain'); } };
        vm.runInNewContext(handler, { $el: field, document: { activeElement: field } });
    }
});

test('focused numeric inputs blur to prevent accidental wheel changes without canceling scroll', () => {
    let blurred = false;
    const field = { type: 'number', blur() { blurred = true; } };
    vm.runInNewContext(handler, { $el: field, document: { activeElement: field } });
    assert.equal(blurred, true);
    blurred = false;
    vm.runInNewContext(handler, { $el: field, document: { activeElement: null } });
    assert.equal(blurred, false);
});
