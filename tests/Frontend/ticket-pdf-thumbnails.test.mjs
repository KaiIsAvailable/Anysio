import test from 'node:test';
import assert from 'node:assert/strict';
import { createPdfThumbnails } from '../../resources/js/ticket-pdf-thumbnails.js';

const tick = () => new Promise(resolve => setImmediate(resolve));
function fixture(count = 1, failure = false, pendingRender = false) {
    const cards = Array.from({ length: count }, () => {
        const canvas = { hidden: true, style: {}, getContext: () => ({}) };
        const fallback = { hidden: false };
        return { dataset: { pdfUrl: '/tenant/tickets/1/attachments/2' }, canvas, fallback,
            querySelector: selector => selector.includes('canvas') ? canvas : fallback };
    });
    let callback, options, loads = 0, destroyed = 0, cancelled = 0;
    const pages = [], requests = [], completions = [];
    class Observer {
        constructor(cb, opts) { callback = cb; options = opts; }
        observe() {}
        disconnect() {}
    }
    const load = async () => {
        loads++;
        return { getDocument(request) {
            requests.push(request);
            return { destroy: async () => { destroyed++; }, promise: failure ? Promise.reject(new Error('403')) : Promise.resolve({
                getPage: async number => {
                    pages.push(number);
                    return { getViewport: ({ scale }) => ({ width: 600 * scale, height: 800 * scale }),
                        render: () => {
                            let reject;
                            const promise = pendingRender ? new Promise((resolve, fail) => { completions.push(resolve); reject = fail; }) : Promise.resolve();
                            return { promise, cancel: () => { cancelled++; reject?.(new Error('cancelled')); } };
                        } };
                },
            }) };
        } };
    };
    const root = { querySelectorAll: () => cards };
    const manager = createPdfThumbnails(root, { load, Observer, baseUrl: 'https://example.test/chat', pixelRatio: 3 });
    manager.scan();
    return { cards, manager, pages, requests, completions, options,
        enter: () => callback(cards.map(target => ({ target, isIntersecting: true }))),
        stats: () => ({ loads, destroyed, cancelled }) };
}

test('lazy authorized first-page rendering is bounded and releases PDF resources', async () => {
    const f = fixture();
    assert.equal(f.stats().loads, 0);
    assert.equal(f.options.rootMargin, '200px 0px');
    f.enter(); await tick();
    assert.deepEqual(f.pages, [1]);
    assert.equal(f.requests[0].url, 'https://example.test/tenant/tickets/1/attachments/2');
    assert.equal(f.requests[0].withCredentials, true);
    assert.equal(f.requests[0].disableAutoFetch, true);
    assert.equal(f.cards[0].canvas.hidden, false);
    assert.equal(f.cards[0].fallback.hidden, true);
    assert.ok(f.cards[0].canvas.width <= 288 && f.cards[0].canvas.height <= 320);
    assert.equal(f.stats().destroyed, 1);
    f.enter(); await tick(); assert.equal(f.stats().loads, 1);
    f.manager.destroy(); assert.equal(f.cards[0].canvas.width, 0);
});

test('failed preview preserves fallback without rejecting chat work', async () => {
    const f = fixture(1, true);
    f.enter(); await tick();
    assert.equal(f.cards[0].canvas.hidden, true);
    assert.equal(f.cards[0].fallback.hidden, false);
    assert.equal(f.stats().destroyed, 1);
    f.manager.destroy();
});

test('only two PDFs run at once and switching cancels renders and queued jobs', async () => {
    const f = fixture(5, false, true);
    f.enter(); await tick();
    assert.equal(f.stats().loads, 2);
    f.manager.reset(); await tick();
    assert.deepEqual(f.stats(), { loads: 2, destroyed: 2, cancelled: 2 });
    assert.ok(f.cards.every(card => card.canvas.hidden && !card.fallback.hidden));
    f.manager.destroy();
});

test('switch during engine import never starts an obsolete attachment request', async () => {
    let resolve, callback, requests = 0;
    const card = { dataset: { pdfUrl: '/attachment' }, querySelector: () => ({ style: {} }) };
    class Observer { constructor(cb) { callback = cb; } observe() {} disconnect() {} }
    const manager = createPdfThumbnails({ querySelectorAll: () => [card] }, {
        Observer, baseUrl: 'https://example.test', load: () => new Promise(r => { resolve = r; }),
    });
    manager.scan(); callback([{ target: card, isIntersecting: true }]);
    manager.destroy(); resolve({ getDocument() { requests++; } }); await tick();
    assert.equal(requests, 0);
});

test('cross-origin PDF URLs are never fetched', async () => {
    const f = fixture(); f.cards[0].dataset.pdfUrl = 'https://other.test/file.pdf';
    f.enter(); await tick(); assert.equal(f.stats().loads, 0);
    assert.equal(f.cards[0].fallback.hidden, false); f.manager.destroy();
});

test('installed PDF.js and its worker render a real PDF first page', async (t) => {
    let native;
    try { native = await import('@napi-rs/canvas'); }
    catch { t.skip('Optional Node canvas unavailable; browser rendering still requires manual verification.'); return; }
    Object.assign(globalThis, { DOMMatrix: native.DOMMatrix, ImageData: native.ImageData, Path2D: native.Path2D });
    const { getDocument } = await import('pdfjs-dist/legacy/build/pdf.mjs');
    const stream = '0.4 0.2 0.8 rg 10 10 80 80 re f';
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] /Resources << >> /Contents 4 0 R >>',
        `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`,
    ];
    let pdf = '%PDF-1.4\n';
    const offsets = [0];
    objects.forEach((object, i) => { offsets.push(pdf.length); pdf += `${i + 1} 0 obj\n${object}\nendobj\n`; });
    const xref = pdf.length;
    pdf += `xref\n0 5\n0000000000 65535 f \n${offsets.slice(1).map(offset => String(offset).padStart(10, '0') + ' 00000 n \n').join('')}trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF`;
    const task = getDocument({ data: new TextEncoder().encode(pdf), isEvalSupported: false });
    try {
        const document = await task.promise;
        const page = await document.getPage(1);
        const canvas = native.createCanvas(100, 100);
        await page.render({ canvasContext: canvas.getContext('2d'), viewport: page.getViewport({ scale: 1 }) }).promise;
        const pixel = canvas.getContext('2d').getImageData(50, 50, 1, 1).data;
        assert.ok(pixel[2] > pixel[0] && pixel[0] > pixel[1], 'Rendered PDF has the expected purple center');
    } finally { await task.destroy(); }
});
