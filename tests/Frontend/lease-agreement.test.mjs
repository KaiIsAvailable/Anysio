import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import test from 'node:test';

const previews = [
    ['resources/views/adminSide/leases/index.blade.php', 'viewAgreement'],
    ['resources/views/adminSide/leases/show.blade.php', 'viewAgreement'],
    ['resources/views/tenantSide/tenants/leases/show.blade.php', 'viewTenantAgreement'],
];

for (const packageValue of ['Condo', 'Studio', 'Master Room', 'Not recorded']) {
test(`all agreement entry points consistently render package ${packageValue}`, () => {
    const html = '<h1>{{ UNIT_NUMBER }}</h1><table><tr><td>{{ rent_price }}</td><td>{{ SECURITY_DEPOSIT }}</td><td>{{ LEASABLE_TYPE }}</td><td>{{ leasalable_type }}</td><td>&#123;&#123; service_package &#125;&#125;</td></tr></table><span data-variable="property_address">{{ property_address }}</span>';
    const replacements = { unit_number: 'A-12-08', rent_price: '1,234.56', security_deposit: '2,469.12', leasable_type: packageValue, leasalable_type: packageValue, service_package: packageValue, property_address: 'Unit A-12-08, <Street> & $&' };
    const outputs = previews.map(([file, name]) => {
        const source = readFileSync(file, 'utf8');
        const start = source.indexOf(`window.${name} = function`);
        let output;
        const context = { window: { dispatchEvent: event => { output = event.detail.content; } }, CustomEvent: class { constructor(type, args) { this.detail = args.detail; } }, console };
        vm.createContext(context);
        vm.runInContext(source.slice(start, source.indexOf('</script>', start)), context);
        context.window[name]({ dataset: { baseContent: html, replacements: JSON.stringify(replacements), title: 'Test' } });
        assert(!output.includes('{{'));
        assert(output.includes('1,234.56'));
        assert(output.includes('2,469.12'));
        assert.equal(output.split(packageValue).length - 1, 3);
        assert(output.includes('&lt;Street&gt; &amp; $&amp;'));
        return output;
    });
    assert.equal(outputs[0], outputs[1]);
    assert.equal(outputs[0], outputs[2]);
});

}

test('create and edit IC previews use links for PDF and images for the original formats', () => {
    for (const page of ['create', 'edit']) {
        const source = readFileSync(`resources/views/adminSide/tenants/${page}.blade.php`, 'utf8');
        const start = source.indexOf('function previewImage(');
        const container = { dataset: {}, classList: { remove() {} }, replaceChildren(child) { this.child = child; } };
        let alerts = 0;
        const context = {
            document: { getElementById: () => container, createElement: tag => ({ tag }) },
            URL: { createObjectURL: () => 'blob:test', revokeObjectURL() {} },
            alert: () => alerts++,
        };
        vm.createContext(context);
        vm.runInContext(source.slice(start, source.indexOf('// Dynamic Emergency Contacts', start)), context);
        for (const type of ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']) {
            context.previewImage({ files: [{ type, name: 'ic', size: 100 }], value: 'file' }, 'preview', 'container');
            assert.equal(container.child.tag, type === 'application/pdf' ? 'a' : 'img');
        }
        for (const file of [{ type: 'text/html', size: 100 }, { type: 'application/pdf', size: 2097153 }]) {
            const input = { files: [file], value: 'file' };
            context.previewImage(input, 'preview', 'container');
            assert.equal(input.value, '');
        }
        assert.equal(alerts, 2);
    }
});
