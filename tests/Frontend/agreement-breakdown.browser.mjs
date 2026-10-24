import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';
const require = createRequire(import.meta.url);
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const browser = await chromium.launch({ channel: 'msedge', headless: true });
try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const fixture = execFileSync('php', ['tests/Frontend/agreement-breakdown-fixture.php'], { encoding: 'utf8' });
    const logic = readFileSync('resources/js/agreement-breakdown.js', 'utf8').replaceAll('export function', 'function');
    const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
    const css = readFileSync('public/build/' + manifest['resources/css/app.css'].file, 'utf8');
    await page.route('http://agreement.test/**', route => route.fulfill({ contentType: 'text/html', body: `<style>${css}</style><script>${logic}</script>${fixture}<script>${readFileSync('node_modules/alpinejs/dist/cdn.js', 'utf8')}</script>` }));
    await page.goto('http://agreement.test/');
    const original = '<p>Other rent: 1,234.56</p><table><tbody><tr><td>5</td><td>Rental per Month<br>Ringgit Malaysia</td><td><strong>1,234.56</strong></td></tr><tr><td>Deposit</td><td>2,469.12</td></tr></tbody></table>';
    const open = async (allowBreakdown = true, cents = 123456, frequency = 'monthly') => {
        await page.evaluate(async detail => {
            window.dispatchEvent(new CustomEvent('open-lease-preview', { detail }));
            await Alpine.nextTick();
        }, { content: original, title: 'Agreement', allowBreakdown, rentalBasis: { cents, frequency } });
        await page.locator('#invoice-modal-content').waitFor({ state: 'visible' });
    };
    const content = () => page.locator('#invoice-modal-content').innerHTML();
    const button = label => page.getByRole('button', { name: label, exact: true });
    const edit = async (basic, service) => {
        await button('Set Rental Breakdown').click();
        await page.locator('#basic-lease').fill(basic);
        await page.locator('#service-charge').fill(service);
    };
    await open();
    assert.equal(await content(), original);
    await page.context().addInitScript(() => { window.print = () => {}; window.close = () => {}; });
    const originalPopupPromise = page.waitForEvent('popup');
    await button('Print').click();
    const originalPopup = await originalPopupPromise;
    await originalPopup.waitForLoadState();
    assert.equal(await originalPopup.locator('body').innerHTML().then(s => s.trim()), original);
    await originalPopup.pdf({ path: 'storage/app/agreement-original-test.pdf', format: 'A4' });
    await edit('1000', '1');
    await button('Apply').click();
    assert.match(await page.getByRole('alert').innerText(), /must equal/);
    assert.equal(await page.locator('#basic-lease').inputValue(), '1000');
    assert.equal(await content(), original);
    for (const bad of ['-1', '1.001', '1e3', '']) {
        await page.locator('#basic-lease').fill(bad);
        await button('Apply').click();
        assert.equal(await content(), original);
    }
    await page.locator('#basic-lease').fill('1000');
    await page.locator('#service-charge').fill('234.56');
    await button('Apply').click();
    const applied = await content();
    assert.match(applied, /BASIC LEASE \(1000\)/);
    assert.match(applied, /SERVICE CHARGE \(234.56\)/);
    assert.match(applied, /Other rent: 1,234.56/);
    await edit('999', '235.56');
    await button('Cancel').click();
    assert.equal(await content(), applied);
    await button('Set Rental Breakdown').click();
    assert.equal(await page.locator('#basic-lease').inputValue(), '1000');
    await page.locator('#basic-lease').fill('1234.56');
    await page.locator('#service-charge').fill('0');
    await button('Apply').click();
    assert.match(await content(), /SERVICE CHARGE \(0\)/);
    // Capture the real Print popup, keeping it open for Chromium PDF export.
    const popupPromise = page.waitForEvent('popup');
    await button('Print').click();
    const popup = await popupPromise;
    await popup.waitForLoadState();
    assert.equal(await popup.locator('body').innerHTML().then(s => s.trim()), (await content()).trim());
    assert.equal(await popup.getByRole('button').count(), 0);
    await popup.pdf({ path: 'storage/app/agreement-breakdown-test.pdf', format: 'A4' });
    await popup.screenshot({ path: 'storage/app/agreement-breakdown-test.png', fullPage: true });
    await button('Set Rental Breakdown').click();
    await button('Remove Breakdown').click();
    assert.equal(await content(), original);
    await edit('1000', '234.56'); await button('Apply').click();
    await button('Close').click(); await open();
    assert.equal(await content(), original);
    await edit('1000', '234.56'); await button('Apply').click();
    await open(true, 50000);
    assert.equal(await content(), original);
    await button('Set Rental Breakdown').click();
    assert.equal(await page.locator('#rental-total').inputValue(), '500');
    assert.equal(await page.locator('#basic-lease').inputValue(), '');
    await button('Cancel').click();
    await open(false);
    await button('Set Rental Breakdown').waitFor({ state: 'hidden' });
    assert.equal(await button('Set Rental Breakdown').isVisible(), false);
    for (const frequency of ['daily', 'monthly', 'yearly']) {
        await open(true, 50000, frequency);
        await edit('300', '200');
        await page.locator('#service-charge').blur();
        assert.equal(await page.locator('#basic-lease').inputValue(), '300');
        assert.equal(await page.locator('#service-charge').inputValue(), '200');
        assert.equal(await page.locator('label[for="rental-total"]').innerText(), `Original ${frequency} rental (RM)`);
        await button('Apply').click();
        const cell = page.locator('#invoice-modal-content tr').first().locator('td').last();
        assert.equal(await cell.innerText(), '500\nBASIC LEASE (300)\nSERVICE CHARGE (200)');
        assert.equal(await cell.locator('strong').count(), 1);
        const printPromise = page.waitForEvent('popup');
        await button('Print').click();
        const print = await printPromise;
        await print.waitForLoadState();
        assert.equal(await print.locator('tr').first().locator('td').last().innerText(), '500\nBASIC LEASE (300)\nSERVICE CHARGE (200)');
        const path = `storage/app/agreement-${frequency}-format-test.pdf`;
        await print.pdf({ path, format: 'A4' });
        const { getDocument } = await import('../../node_modules/pdfjs-dist/legacy/build/pdf.mjs');
        const pdf = await getDocument({ data: new Uint8Array(readFileSync(path)) }).promise;
        const text = (await (await pdf.getPage(1)).getTextContent()).items.map(item => item.str).join(' ');
        assert.match(text, /500\s+BASIC LEASE \(300\)\s+SERVICE CHARGE \(200\)/);
        assert(!text.includes('Set Rental Breakdown'));
        await edit('500', '0'); await button('Apply').click();
        assert.match(await cell.innerText(), /SERVICE CHARGE \(0\)/);
        await edit('499', '0'); await button('Apply').click();
        assert.match(await page.getByRole('alert').innerText(), /must equal/);
        await button('Cancel').click();
    }
    await open(true, null, null);
    await edit('300', '200'); await button('Apply').click();
    assert.match(await page.getByRole('alert').innerText(), /missing/);
    assert.equal(await content(), original);
    await button('Cancel').click();
    // Exercise the repository's full template through the actual index replacement function.
    const seed = readFileSync('database/seeders/AntzCustomTemplate.php', 'utf8');
    const template = seed.match(/\$agreementHtml = <<<'HTML'\s*([\s\S]*?)\s*HTML;/)[1];
    const index = readFileSync('resources/views/adminSide/leases/index.blade.php', 'utf8');
    const start = index.indexOf('window.viewAgreement = function');
    await page.addScriptTag({ content: index.slice(start, index.indexOf('</script>', start)) });
    await page.evaluate(async template => {
        const replacements = Object.fromEntries([...template.matchAll(/{{\s*(\w+)\s*}}/g)].map(m => [m[1], 'Sample']));
        replacements.rent_price = '1,234.56';
        window.viewAgreement({ dataset: { baseContent: template, replacements: JSON.stringify(replacements), title: 'Template test', rentalBreakdown: 'true', rentalBasis: JSON.stringify({ cents: 123456, frequency: 'monthly' }) } });
        await Alpine.nextTick();
    }, template);
    const amountStyle = () => page.locator('#igw53k strong').evaluate(el => {
        const s = getComputedStyle(el);
        return [s.fontFamily, s.fontSize, s.fontWeight, s.textAlign];
    });
    const originalStyle = await amountStyle();
    await edit('1000', '234.56'); await button('Apply').click();
    assert.deepEqual(await amountStyle(), originalStyle);
    assert.equal((await content()).split('BASIC LEASE (').length - 1, 1);
    const fullPopupPromise = page.waitForEvent('popup');
    await button('Print').click();
    const fullPopup = await fullPopupPromise;
    await fullPopup.waitForLoadState();
    await fullPopup.pdf({ path: 'storage/app/agreement-breakdown-full-test.pdf', format: 'A4' });
    await fullPopup.locator('#igw53k').screenshot({ path: 'storage/app/agreement-breakdown-full-cell.png' });
    const descriptionCases = JSON.parse(execFileSync('php', ['tests/Frontend/agreement-breakdown-fixture.php', 'descriptions'], { encoding: 'utf8' }));
    for (const item of descriptionCases.filter(item => ['WIFI UNIT', 'STANDARD UNIT', 'Other'].includes(item.type))) {
        await page.evaluate(async ({ template, variables }) => {
            variables.rent_price = '500.00';
            window.viewAgreement({ dataset: { baseContent: template, replacements: JSON.stringify(variables), title: 'Description test', rentalBreakdown: 'true', rentalBasis: JSON.stringify({ cents: 50000, frequency: 'monthly' }) } });
            await Alpine.nextTick();
        }, { template, variables: item.variables });
        const description = page.locator('#i75opp');
        assert.equal(await description.innerText(), item.variables.service_package_description);
        const originalDescription = await description.innerHTML();
        await edit('300', '200'); await button('Apply').click();
        assert.equal(await description.innerHTML(), originalDescription);
        await button('Set Rental Breakdown').click(); await button('Remove Breakdown').click();
        assert.equal(await description.innerHTML(), originalDescription);
        const printPromise = page.waitForEvent('popup');
        await button('Print').click();
        const print = await printPromise;
        await print.waitForLoadState();
        assert.equal(await print.locator('#i75opp').innerText(), item.variables.service_package_description);
        const geometry = await print.locator('#i75opp').evaluate(cell => {
            const range = document.createRange(); range.selectNodeContents(cell);
            const text = range.getBoundingClientRect(), bounds = cell.getBoundingClientRect();
            return { fits: text.top >= bounds.top && text.bottom <= bounds.bottom && text.left >= bounds.left && text.right <= bounds.right, height: bounds.height };
        });
        assert(geometry.fits, `${item.type} Description must fit inside its table cell`);
        const file = `storage/app/agreement-description-${item.type.replaceAll(' ', '-').toLowerCase()}.pdf`;
        await print.pdf({ path: file, format: 'A4' });
        const { getDocument } = await import('../../node_modules/pdfjs-dist/legacy/build/pdf.mjs');
        const pdf = await getDocument({ data: new Uint8Array(readFileSync(file)) }).promise;
        let found = false;
        for (let number = 1; number <= pdf.numPages; number++) {
            const text = (await (await pdf.getPage(number)).getTextContent()).items.map(part => part.str).join(' ').replace(/\s+/g, ' ');
            if (text.includes(item.variables.service_package_description)) found = true;
            assert(!text.includes('service_package_description'));
        }
        assert(found, `${item.type} exact Description must appear in PDF`);
        console.log(`${item.type}: preview/Print/PDF Description passed; table row height ${geometry.height}px`);
    }
    assert.deepEqual(errors, []);
    console.log('PASS: original, validation, apply/edit/remove/cancel, zero service, reopen, switch, protected documents, Print popup and PDF export');
} finally { await browser.close(); }
