import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const html = execFileSync('php', ['tests/Frontend/payment-modal-fixture.php'], { encoding: 'utf8' });
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = readFileSync('public/build/' + manifest['resources/css/app.css'].file, 'utf8');
const entries = ['resources/views/dashboard.blade.php', 'resources/views/adminSide/leases/show.blade.php', 'resources/views/adminSide/leases/invoices/index.blade.php'];
const browser = await chromium.launch({ channel: 'msedge', headless: true });
try {
    for (const entry of entries) {
        assert.match(readFileSync(entry, 'utf8'), /<x-modals.payment-modal/);
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        // No real requests, submissions, date-picker network calls, or database writes.
        await page.route('**/*', route => route.fulfill({ json: { amount: 0 } }));
        await page.setContent('<html><head><base href="http://payment.test"><style>[x-cloak]{display:none!important}' + css + '</style></head><body>' + html + '</body></html>');
        // Date picker rendering is outside this test; retain the real named date input.
        await page.evaluate(() => { window.flatpickr = () => ({}); });
        await page.addScriptTag({ path: 'node_modules/alpinejs/dist/cdn.js' });
        const open = async () => {
            await page.evaluate(() => window.dispatchEvent(new CustomEvent('open-payment', { detail: {
                id: 'fixture', invoiceNo: 'TEST', walletBalance: 500, totalAmount: 100,
                invoiceItems: [], dueDate: '2026-10-20', actionUrl: 'http://payment.test/submit',
            } })));
            await page.waitForFunction(() => document.querySelector('[name=amount_paid]')?.value === '100.00');
        };
        await open();
        const form = page.locator('form');
        const select = page.locator('select[name=payment_method]');
        const wallet = page.locator('[name=use_wallet]');
        const state = async (method, reference, amount, useWallet) => {
            await page.waitForFunction(expected => {
                const data = new FormData(document.querySelector('form'));
                return data.getAll('payment_method').length === 1 && data.get('payment_method') === expected;
            }, method);
            const values = await form.evaluate(el => [...new FormData(el)]);
            const data = new URLSearchParams(values);
            assert.deepEqual(data.getAll('payment_method'), [method]);
            assert.deepEqual(data.getAll('transaction_ref'), reference === null ? [] : [reference]);
            assert.equal(Number(data.get('amount_paid')), amount);
            assert.equal(data.get('use_wallet'), useWallet ? '1' : null);
        };
        const toggle = async checked => {
            if (await wallet.isChecked() !== checked) await wallet.locator('..').click();
            assert.equal(await wallet.isChecked(), checked);
        };
        await state('Cash', 'CASH', 100, false);
        await toggle(true);
        await state('Wallet', null, 0, true);
        assert.equal(await select.isDisabled(), true);
        await toggle(false);
        await state('Cash', 'CASH', 100, false);
        await select.selectOption('Bank Transfer');
        await page.locator('[name=transaction_ref]:enabled').fill('BANK-123');
        await state('Bank Transfer', 'BANK-123', 100, false);
        await toggle(true);
        await state('Wallet', null, 0, true);
        await toggle(false);
        await state('Bank Transfer', '', 100, false);
        await page.locator('[name=transaction_ref]:enabled').fill('BANK-NEW');
        await select.selectOption('Cash');
        await state('Cash', 'CASH', 100, false);
        await select.selectOption('Bank Transfer');
        await state('Bank Transfer', '', 100, false);
        await page.locator('[name=transaction_ref]:enabled').fill('SHOULD-CLEAR');
        await page.getByRole('button', {name:'Cancel', exact:true}).click();
        await open();
        await state('Cash', 'CASH', 100, false);
        await select.selectOption('Bank Transfer');
        await state('Bank Transfer', '', 100, false);
        assert.deepEqual(errors, []);
        console.log('PASS ' + entry + ': one method, Cash/Bank/Wallet, switches and reopen clear references.');
        await page.close();
    }
} finally { await browser.close(); }
