import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const layout = readFileSync('resources/views/layouts/app.blade.php', 'utf8');
const snippets = [...layout.matchAll(/const escapeItem = [\s\S]*?(?=let itemAmount =)/g)];

test('Invoice and Receipt preview keep fee names with optional descriptions', () => {
    assert.equal(snippets.length, 2);
    for (const [code] of snippets) {
        const render = (item, billing_period = '') => runInNewContext(`${code}; itemDesc`, {
            item, finalVariables: { billing_period }, console: { log() {} },
        });
        for (const description of [undefined, null, '', ' \t ']) {
            assert.equal(render({ name: 'Rental', description }), 'Rental');
            assert.equal(render({ fee_type: { name: 'Rental' }, description }), 'Rental');
        }
        assert.equal(render({ name: 'Water', description: 'Meter 123' }), 'Water<br><small>Meter 123</small>');
        assert.equal(render({ description: 'Legacy subscription' }), 'Legacy subscription');
        assert.equal(render({ name: '<Rent>', description: '<script>bad()</script>' }), '&lt;Rent&gt;<br><small>&lt;script&gt;bad()&lt;/script&gt;</small>');
    }
});
