import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const render = values => execFileSync('php', ['tests/Frontend/recurring-settings-fixture.php', JSON.stringify(values)], { encoding: 'utf8' });
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = readFileSync('public/build/' + manifest['resources/css/app.css'].file, 'utf8');
const browser = await chromium.launch({ channel: 'msedge', headless: true });
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
    await page.route('**/*', route => route.fulfill({ json: [] }));
    const load = async values => {
        await page.goto('about:blank');
        // Preserve the entire production DOM and inline scripts. External libraries
        // unrelated to Settings are blocked; Alpine is the installed project version.
        const html = render(values).replace(/<script\b[^>]*\bsrc=[^>]*><\/script>/gi, '').replace(/<link\b[^>]*>/gi, '');
        await page.setContent(html);
        await page.addStyleTag({ content: css + '\n[x-cloak]{display:none!important}' });
        await page.addScriptTag({ content: readFileSync('resources/js/agreement-breakdown.js', 'utf8').replaceAll('export function ', 'function ') + '\nwindow.agreementBreakdown = agreementBreakdown;' });
        await page.addScriptTag({ path: 'node_modules/alpinejs/dist/cdn.js' });
        await page.getByRole('button', { name: 'Lease', exact: true }).click();
        await page.getByRole('heading', { name: 'Recurring Invoice Settings', exact: true }).waitFor({ state: 'visible' });
    };
    await load({ recurring_type: 'both' });
    if (process.argv.includes('--diagnose')) {
        console.log(JSON.stringify({ errors, recurringState: await page.locator('form').filter({ hasText: 'Recurring Invoice Settings' }).getAttribute('x-data') }, null, 2));
    } else {
        const form = page.locator('form').filter({ hasText: 'Recurring Invoice Settings' });
        const type = form.locator('[name="due_date_config[recurring_type]"]').locator('..');
        const offset = form.locator('[name="due_date_config[recurring_feq_days]"]');
        const due = form.locator('[name="due_date_config[days]"]');
        assert.equal(await form.getByRole('button', { name: 'Enable Edit', exact: true }).isVisible(), true);
        assert.equal(await due.isDisabled(), true);
        assert.equal(await type.locator('input[type=text]').isDisabled(), true);
        assert.equal(await offset.isVisible(), true);
        assert.equal(await offset.isDisabled(), true);
        const pending = page.locator('form').filter({ hasText: 'Pending Renewal Settings' });
        assert.equal(await pending.getByRole('button', { name: 'Enable Edit', exact: true }).isVisible(), true);
        assert.equal(await pending.locator('input[type=number]').isDisabled(), true);
        await form.getByRole('button', { name: 'Enable Edit', exact: true }).click();
        assert.equal(await due.isDisabled(), false);
        const choose = async (field, label) => {
            const select = form.locator(`[name="due_date_config[${field}]"]`).locator('..');
            await select.locator('input[type=text]').click();
            await select.getByText(label, { exact: true }).click();
        };
        for (const [label, visible] of [['Manual', false], ['Auto', true], ['Both', true]]) {
            await choose('recurring_type', label);
            await page.waitForFunction(({visible}) => !!document.querySelector('#recurring_feq_days').offsetParent === visible, {visible});
        }
        await choose('recurring_feq', 'After');
        const capture = async () => page.evaluate(() => {
            window.submissions = [];
            document.addEventListener('submit', event => {
                event.preventDefault();
                window.submissions.push(Object.fromEntries(new FormData(event.target)));
            }, true);
        });
        await capture();
        for (const invalid of ['-1', '32', '1.5']) {
            await offset.fill(invalid);
            await due.fill('32');
            await offset.press('Enter');
            await due.press('Enter');
            await form.getByRole('button', { name: 'Save Recurring Invoice Settings' }).click();
            assert.equal(await offset.evaluate(el => el.validity.valid), false);
            assert.equal(await page.evaluate(() => submissions.length), 0);
        }
        for (const valid of ['0', '31']) {
            await offset.fill(valid);
            assert.equal(await offset.evaluate(el => el.validity.valid), true);
        }
        await due.fill('366');
        await due.press('Enter');
        await form.getByRole('button', { name: 'Save Recurring Invoice Settings' }).click();
        assert.equal(await page.evaluate(() => submissions.length), 0);
        await due.fill('32');
        await due.press('Enter');
        const submitted = await page.evaluate(() => submissions.at(-1));
        assert.equal(submitted['due_date_config[days]'], '32');
        assert.equal(submitted['due_date_config[recurring_feq_days]'], '31');
        assert.equal(submitted['due_date_config[recurring_type]'], 'both');
        const saved = Object.fromEntries(Object.entries(submitted).filter(([key]) => key.startsWith('due_date_config[')).map(([key,value]) => [key.slice(16,-1),value]));
        await load(saved);
        await capture();
        assert.equal(await due.inputValue(), '32');
        assert.equal(await offset.inputValue(), '31');
        assert.equal(await due.isDisabled(), true);
        assert.equal(await offset.isVisible(), true);
        assert.equal(await form.locator('[name="due_date_config[recurring_feq]"]').inputValue(), 'after');
        await form.getByRole('button', { name: 'Enable Edit', exact: true }).click();
        await form.getByRole('button', { name: 'Save Recurring Invoice Settings' }).click();
        assert.deepEqual(await page.evaluate(() => submissions.at(-1)), submitted);
        assert.deepEqual(errors, []);
        console.log('PASS full Settings Blade: edit lock, modes, offset validation, Enter/click payload, controller save and persisted re-render.');
        console.log('Console: no errors.');
    }
} finally { await browser.close(); }
