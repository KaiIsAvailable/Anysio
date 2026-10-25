import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import test from 'node:test';

const cases = JSON.parse(execFileSync('php', ['tests/Frontend/agreement-breakdown-fixture.php', 'descriptions'], { encoding: 'utf8' }));
const seed = readFileSync('database/seeders/AntzCustomTemplate.php', 'utf8');
const template = seed.match(/\$agreementHtml = <<<'HTML'\s*([\s\S]*?)\s*HTML;/)[1];
const legacy = 'CUSTOMER SERVICE (9AM-5.30PM), INDAH WATER,FACILITY ALLOWANCE, CCTV, FACILITY ROOM &amp;HIGH SPEED WIFI';
const entries = [
    ['resources/views/adminSide/leases/index.blade.php', 'viewAgreement'],
    ['resources/views/adminSide/leases/show.blade.php', 'viewAgreement'],
    ['resources/views/tenantSide/tenants/leases/show.blade.php', 'viewTenantAgreement'],
];
for (const entry of entries) {
    for (const item of cases) {
        test(`${entry[0]} description ${JSON.stringify(item.type)} and legacy template`, () => {
            const source = readFileSync(entry[0], 'utf8');
            const start = source.indexOf(`window.${entry[1]} = function`);
            let output;
            const context = { window: { dispatchEvent: e => { output = e.detail.content; } }, CustomEvent: class { constructor(type, args) { this.detail = args.detail; } }, console };
            vm.createContext(context);
            vm.runInContext(source.slice(start, source.indexOf('</script>', start)), context);
            for (const old of [false, true]) {
                context.window[entry[1]]({ dataset: { baseContent: old ? template.replace('{{ service_package_description }}', legacy) : template, replacements: JSON.stringify(item.variables), title: 'Test' } });
                assert(!output.includes('{{'));
                const description = output.match(/<td id="i75opp">([\s\S]*?)<\/td>/)[1];
                const expected = old ? legacy : `<strong>${item.variables.service_package_description.replaceAll('&', '&amp;')}</strong>`;
                assert.equal(description, expected);
            }
        });
    }
}
