import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import test from 'node:test';

const source = readFileSync('resources/views/components/lease-preview-actions.blade.php', 'utf8');
const modal = readFileSync('resources/views/components/preview-agreement-modal.blade.php', 'utf8');

function setup(fetch = async () => { throw new Error('Network unavailable'); }) {
    const events = [], frames = [];
    const context = {
        window: { dispatchEvent: event => events.push(event) },
        CustomEvent: class { constructor(type, { detail }) { Object.assign(this, { type, detail }); } },
        requestAnimationFrame: callback => frames.push(callback),
        FormData, fetch,
        document: { querySelector: () => ({ content: 'csrf' }) },
    };
    vm.createContext(context);
    const start = source.indexOf('window.viewAgreement = async function');
    vm.runInContext(source.slice(start, source.indexOf('</script>', start)).replace(/\{\{[\s\S]*?\}\}/g, 'fixture'), context);
    return { ...context, events, frames };
}

test('agreement opens before template processing and reports invalid data inside the modal', async () => {
    const { window, events, frames } = setup();
    const pending = window.viewAgreement({ dataset: { baseContent: '<p>{{ name }}</p>', replacements: '{invalid' } });
    assert.equal(events[0].type, 'open-lease-preview-loading');
    assert.equal(events.length, 1);
    frames.shift()();
    await Promise.resolve();
    frames.shift()();
    await pending;
    assert.equal(events[1].type, 'lease-preview-error');
    assert.equal(events[1].detail.requestId, events[0].detail.requestId);
});

test('agreement completes with escaped replacements and the same request identity', async () => {
    const { window, events, frames } = setup();
    const pending = window.viewAgreement({ dataset: { baseContent: '<p>{{ name }}</p>', replacements: '{"name":"A & B"}' } });
    frames.shift()();
    await Promise.resolve();
    frames.shift()();
    await pending;
    assert.equal(events[1].type, 'open-lease-preview');
    assert.equal(events[1].detail.content, '<p>A &amp; B</p>');
    assert.equal(events[1].detail.requestId, events[0].detail.requestId);
});

test('refund opens while the network is pending and reports network failures', async () => {
    let reject;
    const { window, events } = setup(() => new Promise((resolve, fail) => { reject = fail; }));
    const pending = window.viewRefund({ dataset: { templateId: 'template', leaseId: 'lease' } });
    assert.equal(events[0].type, 'open-lease-preview-loading');
    assert.equal(events.length, 1);
    reject(new Error('Network unavailable'));
    await pending;
    assert.equal(events[1].type, 'lease-preview-error');
    assert.equal(events[1].detail.message, 'Network unavailable');
});

test('refund completion keeps its request identity', async () => {
    const { window, events } = setup(async () => ({ ok: true, json: async () => ({ content: '<p>Refund</p>', title: 'Refund' }) }));
    await window.viewRefund({ dataset: { templateId: 'template', leaseId: 'lease' } });
    assert.equal(events[1].type, 'open-lease-preview');
    assert.equal(events[1].detail.requestId, events[0].detail.requestId);
    assert.equal(events[1].detail.content, '<p>Refund</p>');
});

test('closed or superseded preview ignores late success and error events', () => {
    const elements = { 'modal-content': { innerHTML: 'old' }, 'modal-title': { innerText: '' } };
    const context = { agreementBreakdown: () => ({ resetBreakdown() {} }), document: { getElementById: id => elements[id], body: { style: {} } } };
    vm.createContext(context);
    const data = modal.match(/<div x-data="([\s\S]*?)"\s/)[1];
    context.state = vm.runInContext(`(${data})`, context);
    context.state.$nextTick = callback => callback();
    context.state.refreshPaper = () => {};
    const dispatch = (name, detail) => {
        context.event = { detail };
        const handler = modal.match(new RegExp(`@${name}\\.window="([\\s\\S]*?)"`))[1];
        vm.runInContext(`(function() { with (state) { ${handler.replaceAll('$event', 'event')} } })()`, context);
    };
    dispatch('open-lease-preview-loading', { requestId: 'first', title: 'Agreement' });
    context.state.closeModal();
    dispatch('open-lease-preview', { requestId: 'first', content: 'stale' });
    assert.equal(context.state.openPreview, false);
    assert.equal(elements['modal-content'].innerHTML, '');
    dispatch('open-lease-preview-loading', { requestId: 'second', title: 'Refund' });
    dispatch('lease-preview-error', { requestId: 'first', message: 'stale error' });
    assert.equal(context.state.previewError, '');
    assert.equal(context.state.previewLoading, true);
    dispatch('open-lease-preview', { requestId: 'second', content: 'current' });
    assert.equal(elements['modal-content'].innerHTML, 'current');
});
