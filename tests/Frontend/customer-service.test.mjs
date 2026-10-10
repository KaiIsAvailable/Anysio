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
    }
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
function setup({ index = false, chat = false, role = 'tenant', canSend = true, closed = false, messages = [] } = {}) {
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
    document.querySelectorAll = () => chat ? [chatRoot] : [];
    document.createElement = (tag) => new Element(tag);
    const navigations = [];
    const window = new Element();
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
        return { ok: answer.status === undefined || answer.status < 400, status: answer.status ?? 200, json: async () => answer.data };
    };
    const ticket = { id: 'one', subject: 'Leaking tap', status: closed ? 'done' : 'pending', closed, reply_url: '/ignored/reply', show_url: '/ignored/show' };
    if (chat) answers.push({ data: { ticket, messages } });
    node('[data-chat-form]').fields = [['_token', 'csrf-token']];
    node('[data-complaint-form]').action = indexRoot.dataset.indexUrl;
    vm.runInNewContext(source, { document, window, URL: TestURL, FormData: FormDataMock, fetch, console });
    document.fire('DOMContentLoaded');
    return { node, ticket, requests, answers, revoked, navigations, chatRoot };
}

test('Create component submits subject and CSRF, closes modal, and refreshes the index', async () => {
    const { node, requests, answers } = setup({ index: true });
    await node('[data-open-complaint]').fire('click');
    assert.equal(node('[data-complaint-modal]').open, true);
    node('[data-complaint-form]').fields = [['_token', 'token'], ['subject', 'Leaking tap']];
    answers.push({ data: { ticket: { id: 'one' }, messages: [] } }, { data: { html: '<table>New complaint</table>', creation_reason: null } });
    await node('[data-complaint-form]').fire('submit');
    assert.equal(requests.length, 2);
    assert.deepEqual(requests[0].body.values.map(([name]) => name), ['_token', 'subject']);
    assert.equal(node('[data-complaint-modal]').open, false);
    assert.equal(node('[data-ticket-list]').innerHTML, '<table>New complaint</table>');
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
    node('[data-chat-files]').files = [{ name: 'image.png', type: 'image/png', size: 100 }, { name: 'note.txt', type: 'text/plain', size: 100 }];
    await node('[data-chat-files]').fire('change');
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
