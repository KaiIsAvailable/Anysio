import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { replaceTemplateVariables } from '../../resources/js/template-variable-replacement.js';

test('invoice and receipt values replace every token while preserving formatting', () => {
    const html = '<span data-variable="tenant_name" style="font-size:18pt">{{ tenant_name }}</span>'
        + '<b>{{ tenant_name }}</b> {tenant_name} &#123;&#123; tenant_name &#125;&#125;';
    const rendered = replaceTemplateVariables(html, { tenant_name: 'A <B> & $&' });
    const value = 'A &lt;B&gt; &amp; $&amp;';
    assert.equal(rendered, `<span data-variable="tenant_name" style="font-size:18pt">${value}</span><b>${value}</b> ${value} ${value}`);
    assert.equal(replaceTemplateVariables('{{ receipt_no }} / {{ total }}', { receipt_no: 'R001', total: '100.00' }), 'R001 / 100.00');
});

test('lease invoice preview returns to the loading modal with its request ID', () => {
    const source = readFileSync('resources/views/layouts/app.blade.php', 'utf8');
    const start = source.indexOf('            window.dispatchEvent(new CustomEvent(', source.indexOf('// 7.'));
    const end = source.indexOf('            document.body.style.overflow', start);
    for (const target of ['agreement', undefined]) {
        let event;
        const context = {
            extra: { previewTarget: target, previewRequestId: 'invoice-document-1' },
            title: 'Invoice', content: 'Rendered data',
            CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
            window: { dispatchEvent(value) { event = value; } }
        };
        vm.runInNewContext(source.slice(start, end), context);
        assert.equal(event.type, target ? 'open-lease-preview' : 'open-invoice-preview');
        assert.equal(event.detail.requestId, 'invoice-document-1');
        assert.equal(event.detail.content, 'Rendered data');
    }
});

test('lease invoice and receipt previews populate dynamic items before opening once', () => {
    const source = readFileSync('resources/views/layouts/app.blade.php', 'utf8');
    const start = source.indexOf('window.openDocumentPreview = function(');
    const end = source.indexOf('</script>', start);
    for (const type of ['invoice', 'receipt']) {
        const events = [];
        const tbody = { innerHTML: '' };
        const container = {
            querySelectorAll() { return []; },
            querySelector() { return tbody; },
            get innerHTML() { return `<table><tbody>${tbody.innerHTML}</tbody></table>`; },
            set innerHTML(value) {}
        };
        const context = {
            console: { log() {} },
            document: { createElement() { return container; }, body: { style: {} } },
            CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
            window: { replaceDocumentTemplateVariables: replaceTemplateVariables, dispatchEvent(event) { events.push(event); } }
        };
        vm.runInNewContext(source.slice(start, end), context);
        context.window.openDocumentPreview(type, '<table></table>', {},
            [{ name: 'Monthly Rental', description: 'Room A & B', amount: '200.00' }], 'Document',
            { previewTarget: 'agreement', previewRequestId: 'document-1' });
        assert.match(tbody.innerHTML, /Monthly Rental/);
        assert.match(tbody.innerHTML, /Room A &amp; B/);
        assert.match(tbody.innerHTML, /RM 200\.00/);
        assert.equal(events.length, 1);
        assert.equal(events[0].type, 'open-lease-preview');
        assert.equal(events[0].detail.requestId, 'document-1');
        assert.match(events[0].detail.content, /Monthly Rental/);
    }
});
