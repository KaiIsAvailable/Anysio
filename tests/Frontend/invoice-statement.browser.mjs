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
            const rows = dialog.locator('[data-statement-event]');
            assert.equal(await rows.first().locator('td').count(), entry === 'invoice-entry' ? 7 : 6);
            assert.equal(await rows.first().locator('td').first().locator('button svg').count(), 1);
            assert.equal(await rows.first().locator('td').last().locator('button').count(), 0);
            assert.equal(await dialog.getByRole('button', { name: /^Details/ }).count(), 0);
            for (const [index, amount, color] of [[0, 'Charged RM1,000.00', 'rgb(194, 65, 12)'], [1, 'Applied RM500.00', 'rgb(21, 128, 61)'], [2, 'Wallet credited RM200.00', 'rgb(29, 78, 216)'], [3, 'Wallet used RM100.00', 'rgb(126, 34, 206)']]) {
                assert.equal(await rows.nth(index).getByText(amount, { exact: true }).evaluate(el => getComputedStyle(el).color), color);
            }
            for (const [index, label, color] of [[0, 'unpaid', 'rgb(254, 249, 195)'], [1, 'recorded', 'rgb(241, 245, 249)'], [4, 'paid', 'rgb(220, 252, 231)'], [5, 'partial', 'rgb(219, 234, 254)'], [6, 'overdue', 'rgb(254, 226, 226)']]) {
                const badge = rows.nth(index).locator('td').last().locator('span');
                assert.equal((await badge.innerText()).trim().toLowerCase(), label);
                assert.equal(await badge.evaluate(el => getComputedStyle(el).backgroundColor), color);
            }
            const expand = dialog.locator('[data-statement-toggle]').first();
            const mainRow = rows.first();
            assert.equal((await mainRow.locator('td').first().innerText()).trim(), '27/10/2026');
            assert.equal(await dialog.getByRole('columnheader', { name: 'Date', exact: true }).count(), 1);
            assert.equal(await mainRow.evaluate(el => getComputedStyle(el).cursor), 'pointer');
            await mainRow.locator('td').nth(1).click();
            assert.equal(await expand.getAttribute('aria-expanded'), 'true');
            await dialog.locator('#statement-detail-0').getByText('Fixture monthly rental', { exact: true }).click();
            assert.equal(await expand.getAttribute('aria-expanded'), 'true');
            await mainRow.locator('td').nth(1).click({ position: { x: 3, y: 3 } });
            assert.equal(await expand.getAttribute('aria-expanded'), 'false');
            await mainRow.evaluate(el => {
                const cell = el.querySelectorAll('td')[1];
                cell.insertAdjacentHTML('beforeend', '<a id="row-link" href="#row-test">Test link</a><button id="row-other-button" type="button">Other action</button>');
                cell.querySelector('a').addEventListener('click', event => { event.preventDefault(); window.rowLinkClicked = true; });
            });
            await page.locator('#row-link').click();
            assert.equal(await page.evaluate(() => window.rowLinkClicked), true);
            assert.equal(await expand.getAttribute('aria-expanded'), 'false');
            await page.locator('#row-other-button').click();
            assert.equal(await expand.getAttribute('aria-expanded'), 'false');
            await mainRow.evaluate(el => {
                const target = el.querySelectorAll('td')[1];
                const range = document.createRange(); range.selectNodeContents(target);
                const selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
                target.dispatchEvent(new MouseEvent('click', { bubbles: true, detail: 1 }));
            });
            assert.equal(await expand.getAttribute('aria-expanded'), 'false');
            await page.evaluate(() => { window.getSelection().removeAllRanges(); document.querySelector('#row-link').remove(); document.querySelector('#row-other-button').remove(); });

            const details = dialog.locator('#statement-detail-0');
            assert.equal(await details.isVisible(), false);
            await expand.click();
            assert.equal(await expand.getAttribute('aria-expanded'), 'true');
            assert.equal(await details.isVisible(), true);
            await page.waitForTimeout(350);
            assert.equal(await expand.locator('svg').evaluate(el => getComputedStyle(el).transform), 'matrix(0, 1, -1, 0, 0, 0)');
            assert.ok((await details.innerText()).includes('Fixture monthly rental'));
            await expand.focus();
            await page.keyboard.press('Enter');
            assert.equal(await details.isVisible(), false);
            assert.equal(await expand.getAttribute('aria-expanded'), 'false');
            await expand.focus();
            await page.keyboard.press('Space');
            assert.equal(await expand.getAttribute('aria-expanded'), 'true');
            await page.keyboard.press('Space');
            assert.equal(await expand.getAttribute('aria-expanded'), 'false');
            await page.waitForTimeout(350);
            assert.equal(await expand.locator('svg').evaluate(el => getComputedStyle(el).transform), 'none');
            await scroll.evaluate(el => { el.scrollLeft = 0; });
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
            const nextToggle = dialog.locator('[data-statement-toggle]').first();
            assert.equal(await nextToggle.getAttribute('aria-expanded'), 'false');
            await dialog.locator('[data-statement-event]').first().locator('td').nth(1).click();
            assert.equal(await dialog.locator('#statement-detail-0').isVisible(), true);
            await nextToggle.focus();
            await page.keyboard.press('Enter');
            assert.equal(await dialog.locator('#statement-detail-0').isVisible(), false);
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
