import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const browser = await chromium.launch({ channel: 'msedge', headless: true });
try {
    const logic = readFileSync('resources/js/invoice-statement.js', 'utf8').replace('export function', 'function');
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const css = readFileSync('public/build/' + manifest['resources/css/app.css'].file, 'utf8');
    for (const entry of ['lease-entry', 'invoice-entry']) {
        for (const width of [1440, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            const endpoint = `http://statement.test/${entry}/statement`;
            const fixture = execFileSync('php', ['tests/Frontend/invoice-statement-fixture.php', 'modal', endpoint + '?statement_kind=payment&statement_page=3'], { encoding: 'utf8' });
            const results = execFileSync('php', ['tests/Frontend/invoice-statement-fixture.php', 'results', endpoint], { encoding: 'utf8' });
            const errors = [], requests = [];
            let fail = false, delay = 800;
            page.on('pageerror', error => errors.push(error.message));
            await page.route('http://statement.test/**', async route => {
                const url = new URL(route.request().url());
                if (url.pathname.endsWith('/statement')) {
                    requests.push(url.href);
                    if (delay) await new Promise(resolve => setTimeout(resolve, delay));
                    return route.fulfill(fail ? { status: 500, json: {} } : { json: { html: results + `<p id="page-marker">Page ${url.searchParams.get('statement_page') || 1}</p>` } });
                }
                if (url.pathname === '/alpine.js') return route.fulfill({ contentType: 'text/javascript', body: readFileSync('node_modules/alpinejs/dist/cdn.js') });
                return route.fulfill({ contentType: 'text/html', body: `<html><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}</style><script>${logic}; document.addEventListener('alpine:init', () => Alpine.data('invoiceStatement', invoiceStatement));</script><script defer src="/alpine.js"></script></head><body>${fixture}</body></html>` });
            });
            await page.goto('http://statement.test/?statement_kind=payment');
            assert.equal(requests.length, 0);
            await page.locator('#' + entry).click();
            const dialog = page.getByRole('dialog');
            const close = page.getByRole('button', { name: 'Close Invoice Statement' });
            await page.waitForFunction(() => document.querySelector('[aria-busy="true"]'));
            assert.equal(await close.isVisible(), true);
            assert.equal(await dialog.locator('table, [role="status"]').count(), 0);
            assert.equal(await dialog.getByText('Loading statement', { exact: false }).count(), 0);
            await page.locator('#page-marker').waitFor();
            delay = 0;
            assert.equal(await dialog.getByText('Fixture records', { exact: true }).count(), 0);
            await page.waitForFunction(() => document.activeElement.id === 'statement-modal-title');
            await page.waitForTimeout(350);
            assert.equal(requests.at(-1), endpoint);
            assert.equal(await dialog.locator('form, input, select').count(), 0);
            assert.equal(await close.locator('svg').count(), 1);
            assert.equal((await close.innerText()).trim(), '');
            const bounds = await dialog.boundingBox();
            assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width);
            const scroll = dialog.locator('#invoice-statement > .overflow-x-auto');
            const box = await scroll.boundingBox();
            assert.ok(box.x - bounds.x >= (width < 640 ? 16 : 32));
            assert.ok(bounds.x + bounds.width - box.x - box.width >= (width < 640 ? 16 : 32) - 1);
            if (width < 640) {
                assert.ok(await scroll.evaluate(el => el.scrollWidth > el.clientWidth));
                await scroll.evaluate(el => { el.scrollLeft = 100; });
                assert.ok(await scroll.evaluate(el => el.scrollLeft > 0));
            }
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            await page.screenshot({ path: join(tmpdir(), `statement-${entry}-${width}.png`) });
            await page.mouse.click(2, 2);
            await page.locator('.animate-shake').waitFor();
            assert.equal(await dialog.isVisible(), true);
            assert.equal(await page.locator('.animate-shake').evaluate(el => getComputedStyle(el).animationName), 'shake');
            await page.waitForTimeout(450);
            assert.equal(await page.locator('.animate-shake').count(), 0);
            delay = 800;
            await dialog.locator('a[href*="statement_page=2"]:visible').first().click();
            await page.waitForFunction(() => document.querySelector('[aria-busy="true"]'));
            assert.equal(await page.locator('#page-marker').textContent(), 'Page 1');
            assert.equal(await dialog.locator('table').count(), 1);
            assert.equal(await dialog.getByText('Loading statement', { exact: false }).count(), 0);
            await page.waitForFunction(() => document.querySelector('#page-marker')?.textContent === 'Page 2');
            await close.click();
            await page.waitForFunction(id => document.activeElement.id === id, entry);
            await page.locator('#' + entry).click();
            await page.waitForFunction(() => document.querySelector('[aria-busy="true"]'));
            assert.equal(await dialog.locator('table').count(), 0);
            await page.waitForFunction(() => document.querySelector('#page-marker')?.textContent === 'Page 1');
            delay = 0;
            assert.equal(requests.at(-1), endpoint);
            await page.keyboard.press('Escape');
            await page.waitForFunction(id => document.activeElement.id === id, entry);
            fail = true;
            await page.locator('#' + entry).click();
            await page.getByRole('alert').waitFor();
            fail = false;
            await page.getByRole('button', { name: 'Retry', exact: true }).click();
            await page.locator('#page-marker').waitFor();
            await close.click();
            delay = 250;
            await page.locator('#' + entry).click();
            await close.click();
            await page.waitForTimeout(350);
            assert.equal(await dialog.isVisible(), false);
            assert.deepEqual(errors, []);
            console.log(`PASS ${entry} ${width}px: SVG close, shake, Escape, no filters, page reset, pagination, gutters, scrolling, retry, stale response.`);
            await page.close();
        }
    }
} finally { await browser.close(); }
