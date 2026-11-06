import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { documentBaseCss, documentHtml, printDocument, applyDocumentImageFallbacks } from '../../resources/js/editor/document-layout.js';

test('shared preview and print preserve template styles and wait for assets', async () => {
    let removed = false;
    globalThis.DOMParser = class {
        parseFromString() {
            return {
                querySelectorAll: () => [{ textContent: '#address { font-size:18pt; }', remove() { removed = true; } }],
                body: { get innerHTML() { return removed ? '<p id="address">Address</p>' : '<style>duplicate</style>'; } }
            };
        }
    };
    const html = documentHtml('template');
    assert(html.includes(documentBaseCss));
    assert(html.includes('@page { size: A4 portrait; margin: 20mm; }'));
    assert(html.includes('#address { font-size:18pt; }'));
    assert(html.includes('<body><p id="address">Address</p></body>'));
    assert(html.includes('.pdf-page-break > *'));
    const events = [];
    let output;
    const popup = {
        document: {
            readyState: 'complete',
            write(value) { output = value; }, close() {},
            fonts: { ready: Promise.resolve().then(() => events.push('fonts')) },
            images: [{ complete: true, naturalWidth: 100, addEventListener() {}, decode: async () => { events.push('image'); } }]
        },
        focus() {}, print() { events.push('print'); }, close() { events.push('close'); }
    };
    globalThis.window = { open: () => popup };
    await printDocument('template', 'Agreement');
    assert.equal(output, html);
    assert.equal(popup.document.title, 'Agreement');
    assert(events.indexOf('fonts') < events.indexOf('print'));
    assert(events.indexOf('image') < events.indexOf('print'));
    for (const file of [
        'resources/views/components/preview-agreement-modal.blade.php',
        'resources/views/adminSide/setting/document_template/index.blade.php'
    ]) {
        const view = readFileSync(file, 'utf8');
        assert(view.includes('window.documentLayout.documentHtml'));
        assert(view.includes('window.documentLayout.printDocument'));
        assert(!view.includes('doc.write('));
    }
});

test('broken preview images use the canvas placeholder without changing valid logos', () => {
    const image = (valid) => ({
        src: '/image/logo.png', complete: true, naturalWidth: valid ? 100 : 0,
        getAttribute() { return this.src; },
        removeAttribute(name) { assert.equal(name, 'srcset'); },
        addEventListener() {}
    });
    const broken = image(false);
    const valid = image(true);
    applyDocumentImageFallbacks({ images: [broken, valid] });
    assert(broken.src.startsWith('data:image/svg+xml'));
    assert.equal(valid.src, '/image/logo.png');
});
