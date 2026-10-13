import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/customer-service.js', import.meta.url), 'utf8');
class Element {
    constructor(tag = 'div') {
        this.tagName = tag.toUpperCase();
        this.children = [];
        this.listeners = {};
        this.dataset = {};
        this.value = '';
        this.textContent = '';
        this.hidden = false;
        this.open = false;
        this.controls = [];
        this.fields = [];
        this.style = {};
        this.scrollHeight = 44;
    }
    contains(target) { return this === target || this.children.includes(target); }
    focus() { this.focused = true; }
    click() { this.clicked = true; }
    requestSubmit() { return this.fire('submit'); }
    setAttribute(name, value) { this[name] = value; }
    addEventListener(event, callback) { (this.listeners[event] ??= []).push(callback); }
    async fire(event, extra = {}) {
        for (const callback of this.listeners[event] ?? []) await callback({ target: this, currentTarget: this, preventDefault() {}, ...extra });
    }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; this.textContent = ''; }
    closest(selector) { return selector === '[data-ticket-url]' && this.dataset.ticketUrl ? this : null; }
    querySelectorAll() { return this.controls; }
    showModal() { this.open = true; }
    close() { this.open = false; this.fire('close'); }
    reset() { this.fields = this.fields.filter(([name]) => name === '_token'); }
}
class FormDataMock {
    constructor(form) { this.values = [...form.fields]; }
    append(name, value) { this.values.push([name, value]); }
    get(name) { return this.values.find(([key]) => key === name)?.[1] ?? null; }
}
const settle = async () => { for (let i = 0; i < 4; i++) await new Promise(setImmediate); };
function setup({ index = false, dashboard = false, chat = false, role = 'tenant', canSend = true, closed = false, messages = [], mobile = false } = {}) {
    const nodes = new Map();
    const node = (selector) => {
        if (!nodes.has(selector)) nodes.set(selector, new Element());
        return nodes.get(selector);
    };
    const indexRoot = new Element();
    indexRoot.dataset = { indexUrl: 'http://example.test/admin/tenant/customer-service' };
    indexRoot.querySelector = node;
    const chatRoot = new Element();
    chatRoot.dataset = {
        currentRole: role,
        canSend: String(canSend),
        sendUrl: 'http://example.test/shared/send',
        readUrl: 'http://example.test/shared/read',
        limits: JSON.stringify({ max_files: 10, max_total_kb: 102400, types: { txt: { max_kb: 10240 }, png: { max_kb: 10240 } } }),
    };
    chatRoot.querySelector = node;
    const document = new Element();
    document.querySelector = () => index ? indexRoot : null;
    const dashboardRoot = new Element();
    dashboardRoot.dataset = { redirectAfterCreate: 'true' };
    dashboardRoot.querySelector = node;
    document.querySelectorAll = (selector) => selector === '[data-complaint-create]' ? (dashboard ? [dashboardRoot] : []) : (chat ? [chatRoot] : []);
    document.createElement = (tag) => new Element(tag);
    const navigations = [];
    const window = new Element();
    window.matchMedia = () => ({ matches: !mobile });
    window.location = { href: indexRoot.dataset.indexUrl, assign(url) { navigations.push(url); this.href = url; } };
    window.history = { replaceState(_state, _title, url) { window.location.href = String(url); } };
    const requests = [];
    const answers = [];
    const revoked = [];
    class TestURL extends URL {
        static createObjectURL() { return 'blob:preview'; }
        static revokeObjectURL(url) { revoked.push(url); }
    }
    const fetch = async (url, options = {}) => {
        requests.push({ url: String(url), ...options });
        const answer = answers.shift();
        assert.ok(answer, 'Every frontend request must have a test response');
        if (answer.gate) await answer.gate;
        return { ok: answer.status === undefined || answer.status < 400, status: answer.status ?? 200, json: async () => answer.data };
    };
    const ticket = { id: 'one', subject: 'Leaking tap', status: closed ? 'done' : 'pending', closed, reply_url: '/ignored/reply', show_url: '/ignored/show' };
    if (chat) answers.push({ data: { ticket, messages } });
    node('[data-chat-attachment-menu]').hidden = true;
    node('[data-chat-form]').fields = [['_token', 'csrf-token']];
    node('[data-complaint-modal]').dataset.limits = chatRoot.dataset.limits;
    node('[data-complaint-form]').action = indexRoot.dataset.indexUrl;
    vm.runInNewContext(source, { document, window, URL: TestURL, FormData: FormDataMock, fetch, console });
    document.fire('DOMContentLoaded');
    return { node, ticket, requests, answers, revoked, navigations, chatRoot, document };
}

test('Index component submits details and CSRF, closes modal, and redirects to show', async () => {
    const { node, requests, answers, navigations } = setup({ index: true });
    await node('[data-open-complaint]').fire('click');
    assert.equal(node('[data-complaint-modal]').open, true);
    node('[data-complaint-form]').fields = [['_token', 'token'], ['subject', 'Leaking tap'], ['details', 'Text']];
    answers.push({ data: { ticket: { id: 'one', show_url: '/ticket/one' }, messages: [] } });
    await node('[data-complaint-form]').fire('submit');
    assert.equal(requests.length, 1);
    assert.deepEqual(requests[0].body.values.map(([name]) => name), ['_token', 'subject', 'details']);
    assert.equal(node('[data-complaint-modal]').open, false);
    assert.deepEqual(navigations, ['/ticket/one']);
});

test('Dashboard creates through the shared modal and redirects without a ticket table', async () => {
    const { node, requests, answers, navigations } = setup({ dashboard: true });
    await node('[data-open-complaint]').fire('click');
    assert.equal(node('[data-complaint-modal]').open, true);
    await node('[data-cancel-complaint]').fire('click');
    assert.equal(node('[data-complaint-modal]').open, false);
    await node('[data-open-complaint]').fire('click');
    node('[data-complaint-form]').fields = [['_token', 'token'], ['subject', 'Dashboard complaint'], ['details', 'Text']];
    answers.push({ status: 422, data: { errors: { subject: ['Please enter a subject.'] } } });
    await node('[data-complaint-form]').fire('submit');
    assert.equal(node('[data-complaint-modal]').open, true);
    assert.equal(node('[data-create-error]').textContent, 'Please enter a subject.');
    answers.push({ data: { ticket: { show_url: '/tenant/ticket/new' } } });
    await node('[data-complaint-form]').fire('submit');
    assert.equal(requests.length, 2);
    assert.deepEqual(navigations, ['/tenant/ticket/new']);
    assert.deepEqual(requests[1].body.values.map(([name]) => name), ['_token', 'subject', 'details']);
});

test('Clicking a ticket row navigates to show without requesting a chat modal', async () => {
    const { node, requests, navigations } = setup({ index: true });
    const row = new Element('tr');
    row.dataset.ticketUrl = 'http://example.test/ticket/one';
    await node('[data-ticket-list]').fire('click', { target: row });
    assert.deepEqual(navigations, [row.dataset.ticketUrl]);
    assert.equal(requests.length, 0);
    await node('[data-ticket-list]').fire('keydown', { target: row, key: 'Enter' });
    assert.equal(navigations.length, 2);
});

test('Chat reads and sends through component endpoints, previews/removes files, and clears successful sends', async () => {
    const history = [
        { id: 'old', sender_type: 'owner', text: 'Earlier response', created_at: '09 Oct 2026 09:00' },
        { id: 'new', sender_type: 'agentAdmin', text: 'Agent message', created_at: '09 Oct 2026 09:01' },
    ];
    const { node, ticket, requests, answers, revoked, chatRoot } = setup({ chat: true, role: 'agentAdmin', messages: history });
    await settle();
    assert.equal(requests[0].url, chatRoot.dataset.readUrl);
    assert.equal(node('[data-chat-subject]').textContent, 'Leaking tap');
    assert.equal(node('[data-chat-form]').hidden, false);
    assert.equal(node('[data-chat-messages]').children[0].className, 'flex justify-start');
    assert.equal(node('[data-chat-messages]').children[1].className, 'flex justify-end');
    node('[data-chat-files="document"]').files = [{ name: 'image.png', type: 'image/png', size: 100 }, { name: 'note.txt', type: 'text/plain', size: 100 }];
    await node('[data-chat-files="document"]').fire('change');
    assert.equal(node('[data-attachment-previews]').children.length, 2);
    assert.equal(node('[data-attachment-previews]').children[0].children[1].tagName, 'IMG');
    await node('[data-attachment-previews]').children[0].children.at(-1).fire('click');
    assert.equal(node('[data-attachment-previews]').children.length, 1);
    node('[data-chat-draft]').value = 'Direct reply';
    answers.push({ data: { ticket, messages: [...history, { sender_type: 'agentAdmin', text: 'Direct reply' }, { sender_type: 'agentAdmin', extension: 'txt', attachment_url: '/private/file' }] } });
    await node('[data-chat-form]').fire('submit');
    assert.equal(requests[1].url, chatRoot.dataset.sendUrl);
    assert.deepEqual(requests[1].body.values.map(([name]) => name), ['_token', 'items[0][type]', 'items[0][text]', 'items[1][type]', 'items[1][file]']);
    assert.equal(node('[data-chat-messages]').children.length, 4);
    assert.equal(node('[data-chat-draft]').value, '');
    assert.equal(node('[data-attachment-previews]').children.length, 0);
    assert.ok(revoked.includes('blob:preview'));
});

test('Read-only prop remains read-only even if a ticket is open', async () => {
    const { node, requests } = setup({ chat: true, canSend: false });
    await settle();
    assert.equal(node('[data-chat-form]').hidden, true);
    assert.equal(node('[data-chat-closed]').hidden, false);
    await node('[data-chat-form]').fire('submit');
    assert.equal(requests.length, 1);
});

test('Closed tickets hide Send and a rejected send preserves the draft', async () => {
    const closed = setup({ chat: true, closed: true });
    await settle();
    assert.equal(closed.node('[data-chat-form]').hidden, true);
    await closed.node('[data-chat-form]').fire('submit');
    assert.equal(closed.requests.length, 1);
    const { node, answers } = setup({ chat: true });
    await settle();
    node('[data-chat-draft]').value = 'Keep this draft';
    answers.push({ status: 422, data: { errors: { items: ['Validation failed'] } } });
    await node('[data-chat-form]').fire('submit');
    assert.equal(node('[data-chat-draft]').value, 'Keep this draft');
    assert.equal(node('[data-chat-error]').textContent, 'Validation failed');
});

test('Create previews and removes files, preserves them on failure, and locks close and repeat submit while pending', async () => {
    const { node, requests, answers } = setup({ dashboard: true });
    await node('[data-open-complaint]').fire('click');
    node('[data-complaint-form]').fields = [['_token', 'token'], ['subject', 'Complaint'], ['details', 'Text']];
    const picker = node('[data-create-files="media"]');
    picker.files = [{ name: 'image.png', type: 'image/png', size: 100 }, { name: 'other.png', type: 'image/png', size: 100 }, { name: 'third.png', type: 'image/png', size: 100 }];
    await picker.fire('change');
    assert.equal(node('[data-create-previews]').children.length, 3);
    await node('[data-create-previews]').children[0].children.at(-1).fire('click');
    assert.equal(node('[data-create-previews]').children.length, 2);
    let release;
    const gate = new Promise((resolve) => { release = resolve; });
    answers.push({ gate, status: 422, data: { errors: { attachments: ['Manager conflict'] } } });
    const pending = node('[data-complaint-form]').fire('submit');
    assert.equal(node('[data-create-submit]').textContent, 'Creating...');
    await node('[data-close-complaint]').fire('click');
    await node('[data-cancel-complaint]').fire('click');
    let prevented = false;
    await node('[data-complaint-modal]').fire('cancel', { preventDefault() { prevented = true; } });
    await node('[data-complaint-form]').fire('submit');
    assert.equal(requests.length, 1);
    assert.equal(prevented, true);
    assert.equal(node('[data-complaint-modal]').open, true);
    release();
    await pending;
    assert.equal(node('[data-complaint-modal]').open, true);
    assert.equal(node('[data-create-previews]').children.length, 2);
    assert.equal(node('[data-complaint-form]').fields[2][1], 'Text');
    assert.equal(node('[data-create-submit]').textContent, 'Create');
    assert.deepEqual(requests[0].body.values.map(([name]) => name), ['_token', 'subject', 'details', 'attachments[]', 'attachments[]']);
    await node('[data-close-complaint]').fire('click');
    assert.equal(node('[data-complaint-modal]').open, false);
});

test('Create rejects empty content and oversized selections before requesting', async () => {
    const { node, requests } = setup({ index: true });
    node('[data-complaint-form]').fields = [['subject', 'Complaint']];
    await node('[data-complaint-form]').fire('submit');
    assert.equal(requests.length, 0);
    assert.match(node('[data-create-error]').textContent, /details/);
    const picker = node('[data-create-files="document"]');
    picker.files = [{ name: 'large.txt', size: 10241 * 1024, type: 'text/plain' }];
    await picker.fire('change');
    assert.equal(node('[data-create-previews]').children.length, 0);
    assert.match(node('[data-create-error]').textContent, /size limit/);
});

test('Chat menu closes on selection, outside click and Escape; file selections append across categories', async () => {
    const { node, document } = setup({ chat: true });
    await settle();
    const menu = node('[data-chat-attachment-menu]');
    const button = node('[data-chat-attach]');
    await button.fire('click');
    assert.equal(menu.hidden, false);
    await node('[data-chat-pick="document"]').fire('click');
    assert.equal(menu.hidden, true);
    assert.equal(node('[data-chat-files="document"]').clicked, true);
    for (const kind of ['document', 'media', 'camera']) {
        const picker = node('[data-chat-files="' + kind + '"]');
        picker.files = [{ name: kind + '.png', type: 'image/png', size: 100 }];
        await picker.fire('change');
    }
    assert.equal(node('[data-attachment-previews]').children.length, 3);
    assert.equal(node('[data-chat-send]').disabled, false);
    await button.fire('click');
    await document.fire('pointerdown', { target: new Element() });
    assert.equal(menu.hidden, true);
    await button.fire('click');
    await document.fire('keydown', { key: 'Escape' });
    assert.equal(menu.hidden, true);
    assert.equal(button.focused, true);
    while (node('[data-attachment-previews]').children.length) await node('[data-attachment-previews]').children[0].children.at(-1).fire('click');
    assert.equal(node('[data-chat-send]').disabled, true);
});

test('Desktop Enter sends, Shift+Enter and IME do not send; mobile Enter stays a newline', async () => {
    const { node, ticket, requests, answers } = setup({ chat: true });
    await settle();
    const draft = node('[data-chat-draft]');
    draft.value = '????';
    draft.scrollHeight = 250;
    await draft.fire('input');
    assert.equal(draft.style.height, '160px');
    await draft.fire('compositionstart');
    await draft.fire('keydown', { key: 'Enter' });
    await draft.fire('compositionend');
    await draft.fire('keydown', { key: 'Enter', isComposing: true });
    await draft.fire('keydown', { key: 'Enter', keyCode: 229 });
    await draft.fire('keydown', { key: 'Enter', shiftKey: true });
    assert.equal(requests.length, 1);
    answers.push({ data: { ticket, messages: [] } });
    await draft.fire('keydown', { key: 'Enter' });
    await settle();
    assert.equal(requests.length, 2);
    assert.equal(draft.value, '');
    assert.equal(node('[data-chat-send]').disabled, true);
    const mobile = setup({ chat: true, mobile: true });
    await settle();
    mobile.node('[data-chat-draft]').value = 'Mobile';
    await mobile.node('[data-chat-draft]').fire('input');
    let prevented = false;
    await mobile.node('[data-chat-draft]').fire('keydown', { key: 'Enter', preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
    assert.equal(mobile.requests.length, 1);
});

test('Chat loading prevents duplicate requests and preserves text and attachments after failure', async () => {
    const { node, answers, requests } = setup({ chat: true });
    await settle();
    const draft = node('[data-chat-draft]');
    draft.value = 'Keep text';
    const picker = node('[data-chat-files="document"]');
    picker.files = [{ name: 'note.txt', type: 'text/plain', size: 100 }];
    await picker.fire('change');
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    answers.push({ gate, status: 422, data: { message: 'Upload failed' } });
    const pending = node('[data-chat-form]').fire('submit');
    assert.equal(node('[data-chat-send]').disabled, true);
    assert.equal(node('[data-chat-loading]').hidden, false);
    await node('[data-chat-form]').fire('submit');
    assert.equal(requests.length, 2);
    release();
    await pending;
    assert.equal(draft.value, 'Keep text');
    assert.equal(node('[data-attachment-previews]').children.length, 1);
    assert.equal(node('[data-chat-loading]').hidden, true);
    assert.equal(node('[data-chat-send]').disabled, false);
    assert.equal(node('[data-chat-error]').textContent, 'Upload failed');
});

test('Chat file limits reject a new selection without losing queued files, and a server close disables sending', async () => {
    const { node, ticket, answers } = setup({ chat: true });
    await settle();
    const picker = node('[data-chat-files="document"]');
    picker.files = [{ name: 'valid.txt', type: 'text/plain', size: 100 }];
    await picker.fire('change');
    for (const files of [
        [{ name: 'large.txt', type: 'text/plain', size: 10241 * 1024 }],
        [{ name: 'bad.exe', type: 'application/octet-stream', size: 1 }],
        Array.from({ length: 10 }, () => ({ name: 'note.txt', type: 'text/plain', size: 1 })),
    ]) {
        picker.files = files;
        await picker.fire('change');
        assert.equal(node('[data-attachment-previews]').children.length, 1);
        assert.notEqual(node('[data-chat-error]').textContent, '');
    }
    answers.push({ status: 403, data: { message: 'Closed' } }, { data: { ticket: { ...ticket, closed: true }, messages: [] } });
    await node('[data-chat-form]').fire('submit');
    assert.equal(node('[data-chat-form]').hidden, true);
    assert.equal(node('[data-chat-send]').disabled, true);
    assert.equal(node('[data-attachment-previews]').children.length, 1);
});
