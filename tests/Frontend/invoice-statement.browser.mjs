// Run with PLAYWRIGHT_MODULE pointing to an installed playwright package if needed.
// FLATPICKR_SCRIPT can point to a local copy of the project's CDN script for offline runs.
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const flatpickr = process.env.FLATPICKR_SCRIPT
    ? readFileSync(process.env.FLATPICKR_SCRIPT)
    : await fetch('https://cdn.jsdelivr.net/npm/flatpickr').then(response => {
        if (!response.ok) throw new Error('Could not load the project Flatpickr script');
        return response.text();
    });
const browser = await chromium.launch({ channel: 'msedge', headless: true });
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const fixture = execFileSync('php', ['tests/Frontend/invoice-statement-fixture.php'], { encoding: 'utf8' });
    const results = execFileSync('php', ['tests/Frontend/invoice-statement-fixture.php', 'results'], { encoding: 'utf8' });
    const logic = readFileSync('resources/js/invoice-statement.js', 'utf8').replace('export function', 'function');
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const css = readFileSync('public/build/' + manifest['resources/css/app.css'].file, 'utf8');
    let requests = 0, delay = 0;
    await page.route('http://statement.test/**', async route => {
        const path = new URL(route.request().url()).pathname;
        if (path === '/statement') {
            requests++;
            if (delay) await new Promise(resolve => setTimeout(resolve, delay));
            return route.fulfill({ json: { html: results + '<p>Statement results</p><a href="http://statement.test/statement?statement_page=2">Next</a>' } });
        }
        if (path === '/flatpickr.js') return route.fulfill({ contentType: 'text/javascript', body: flatpickr });
        if (path === '/alpine.js') return route.fulfill({ contentType: 'text/javascript', body: readFileSync('node_modules/alpinejs/dist/cdn.js') });
        return route.fulfill({ contentType: 'text/html', body: `<html><head><style>${css}</style><script src="/flatpickr.js"></script><script>${logic}; document.addEventListener('alpine:init', () => Alpine.data('invoiceStatement', invoiceStatement));</script><script defer src="/alpine.js"></script></head><body>${fixture}</body></html>` });
    });
    await page.goto('http://statement.test/');
    assert.equal(requests, 0);
    for (const entry of ['lease-entry', 'invoice-entry']) {
        if (entry === 'invoice-entry') await page.setViewportSize({ width: 390, height: 844 });
        await page.evaluate(() => {
            window.dateFocuses = 0;
            window.trackDateFocus = event => {
                if (event.target === document.querySelector('#statement_from')?.nextElementSibling) window.dateFocuses++;
            };
            document.addEventListener('focusin', window.trackDateFocus);
        });
        await page.locator('#' + entry).click();
        const from = page.locator('#statement_from + input');
        const to = page.locator('#statement_to + input');
        await from.waitFor({ state: 'visible' });
        const originalForm = await page.locator('form').elementHandle();
        await page.waitForFunction(() => document.activeElement.id === 'statement-modal-title');
        await page.waitForTimeout(150);
        assert.equal(await page.evaluate(() => window.dateFocuses), 0);
        assert.equal(await page.locator('#statement_from').evaluate(el => el._flatpickr.isOpen), false);
        await page.evaluate(() => document.removeEventListener('focusin', window.trackDateFocus));
        await from.click();
        assert.equal(await page.locator('#statement_from').evaluate(el => el._flatpickr.isOpen), true);
        await from.fill('21/10/2026');
        await from.press('Enter');
        await page.waitForFunction(() => document.activeElement === document.querySelector('#statement_to').nextElementSibling);
        await to.fill('23/10/2026');
        await to.press('Enter');
        // Shared select exposes a visible search input and hidden submitted value.
        const selectInput = page.locator('form .relative.w-full input[type=text]');
        assert.equal(await selectInput.evaluate(el => el === document.activeElement), true);
        await selectInput.fill('Invoices');
        await page.locator('li').filter({ hasText: 'Invoices' }).click();
        await selectInput.press('Enter');
        assert.equal(await page.locator('#statement_invoice').evaluate(el => el === document.activeElement), true);
        await page.locator('#statement_invoice').fill('IA');
        const search = page.waitForRequest(r => r.url().includes('statement_invoice=IA'));
        await page.locator('#statement_invoice').press('Enter');
        const request = await search;
        assert.equal(new URL(request.url()).searchParams.get('statement_kind'), 'invoice');
        await page.getByText('Statement results').waitFor();
        assert.equal(await page.locator('#statement_invoice').evaluate(el => el === document.activeElement), true);
        await page.locator('#statement_invoice').press('Tab');
        assert.equal(await page.getByRole('button', { name: 'Search history' }).evaluate(el => el === document.activeElement), true);
        await page.keyboard.press('Shift+Tab');
        assert.equal(await page.locator('#statement_invoice').evaluate(el => el === document.activeElement), true);
        await page.getByRole('link', { name: 'Next' }).click();
        assert.equal(await originalForm.evaluate(el => el.isConnected), true);
        await page.getByRole('button', { name: 'Clear', exact: true }).click();
        assert.equal(await page.locator('#statement_invoice').inputValue(), '');
        await page.getByRole('button', { name: 'Close Invoice Statement' }).click();
        await page.waitForFunction(id => document.activeElement.id === id, entry);
    }
    await page.locator('#invoice-entry').click();
    await page.locator('#statement_from + input').waitFor({ state: 'visible' });
    await page.getByRole('button', { name: 'Close Invoice Statement' }).focus();
    await page.keyboard.press('Shift+Tab');
    assert.equal(await page.getByRole('link', { name: 'Next' }).evaluate(el => el === document.activeElement), true);
    await page.keyboard.press('Tab');
    assert.equal(await page.getByRole('button', { name: 'Close Invoice Statement' }).evaluate(el => el === document.activeElement), true);
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => document.activeElement.id === 'invoice-entry');
    delay = 200;
    await page.locator('#lease-entry').click();
    await page.getByRole('button', { name: 'Close Invoice Statement' }).click();
    await page.waitForTimeout(300);
    assert.equal(await page.locator('#lease-entry').evaluate(el => el === document.activeElement), true);
    assert.equal(await page.locator('#statement_from').count(), 0);
    assert.deepEqual(errors, []);
    console.log('PASS: both entries, title focus without date flash, explicit date click, Enter search, Tab/Shift+Tab, clear, paging, close and stale response.');
} finally {
    await browser.close();
}
