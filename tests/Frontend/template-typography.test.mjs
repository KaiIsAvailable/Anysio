import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import test from 'node:test';

for (const file of [
    'resources/views/adminSide/leases/create.blade.php',
    'resources/views/adminSide/leases/index.blade.php',
    'resources/views/adminSide/leases/show.blade.php',
    'resources/views/tenantSide/tenants/leases/show.blade.php'
]) {
    test(`variable typography is preserved in ${file}`, () => {
        const source = readFileSync(file, 'utf8');
        const start = source.indexOf('Object.keys(replacements).forEach(');
        const end = source.indexOf('\n', source.indexOf('});', start));
        const run = html => {
            const context = { content: html, replacements: { property_address: 'Street <A> & $&' } };
            vm.createContext(context);
            vm.runInContext(source.slice(start, end), context);
            return context.content;
        };
        const value = 'Street &lt;A&gt; &amp; $&amp;';
        assert.equal(run('<p>{{ property_address }}</p>'), `<p>${value}</p>`);
        assert.equal(run('<strong>{{ property_address }}</strong>'), `<strong>${value}</strong>`);
        assert.equal(
            run('<span data-variable="property_address" style="font-weight:700">{{ property_address }}</span>'),
            `<span data-variable="property_address" style="font-weight:700">${value}</span>`
        );
        assert.equal(
            run('<span data-variable="property_address"><b>{{ property_address }}</b></span>'),
            `<span data-variable="property_address"><b>${value}</b></span>`
        );
    });
}
