import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/customer-service.js', import.meta.url), 'utf8')
    .replace("import { createPdfThumbnails } from './ticket-pdf-thumbnails';", 'const createPdfThumbnails = () => ({ scan() {}, reset() {}, destroy() {} });');
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
        this.classList = { toggle() {} };
    }
    set innerHTML(value) { this.html = value; if (this.onHTML) this.onHTML(value); }
    get innerHTML() { return this.html; }
    querySelector() { return this.children[0] || null; }
    contains(target) { return this === target || this.children.includes(target); }
    focus() { this.focused = true; }
    click() { this.clicked = true; }
    requestSubmit() { return this.fire('submit'); }
    dispatchEvent(event) { if (event.type === 'form-loading') this.loading = event.detail; }
    setAttribute(name, value) { this[name] = value; }
    addEventListener(event, callback, options = {}) {
        (this.listeners[event] ??= []).push(callback);
        options.signal?.addEventListener('abort', () => { this.listeners[event] = this.listeners[event].filter((item) => item !== callback); });
    }
    async fire(event, extra = {}) {
        for (const callback of this.listeners[event] ?? []) await callback({ target: this, currentTarget: this, preventDefault() {}, ...extra });
    }
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; this.textContent = ''; }
    closest(selector) {
        if (selector === '[data-back-to-list]' && this.dataset.backToList !== undefined) return this;
        return selector === 'a' && this.tagName === 'A' ? this : null;
    }
    querySelectorAll(selector) {
        if (selector === '[data-message-date]') return this.children.flatMap(child => [...(child.dataset.messageDate ? [child] : []), ...child.querySelectorAll(selector)]);
        return this.controls;
    }
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
function setup({ multipleLeases = false, index = false, dashboard = false, chat = false, role = 'tenant', canSend = true, closed = false, messages = [], mobile = false, initial = '', now = null } = {}) {
    const nodes = new Map();
    const node = (selector) => {
        if (selector === '[data-lease-selection]' && !multipleLeases) return null;
        if (!nodes.has(selector)) nodes.set(selector, new Element());
        return nodes.get(selector);
    };
    const indexRoot = new Element();
    indexRoot.dataset = { indexUrl: 'http://example.test/admin/tenant/customer-service', ticketUrlTemplate: 'http://example.test/ticket/__TICKET__'  };
    indexRoot.dataset.selectedTicket = initial;
    indexRoot.querySelector = node;
    const chatRoot = new Element();
    chatRoot.dataset = {
        currentRole: role,
        canSend: String(canSend),
        sendUrl: 'http://example.test/shared/send',
        readUrl: 'http://example.test/shared/read',
        limits: JSON.stringify({ max_files: 10, max_total_kb: 102400, types: { txt: { max_kb: 3072 }, png: { max_kb: 2048 }, mp4: { max_kb: 20480 }, pdf: { max_kb: 3072 } } }),
    };
    chatRoot.querySelector = node;
    const document = new Element();
    document.querySelector = () => index ? indexRoot : null;
    const dashboardRoot = new Element();
    dashboardRoot.dataset = { redirectAfterCreate: 'true' };
    dashboardRoot.querySelector = node;
    document.querySelectorAll = (selector) => selector === '[data-complaint-create]' ? (dashboard ? [dashboardRoot] : []) : (chat ? [chatRoot] : index && initial ? [node('[data-conversation-host]').children[0]] : []);
    document.createElement = (tag) => new Element(tag);
    const navigations = [];
    const window = new Element();
    window.matchMedia = () => ({ matches: !mobile });
    window.location = { href: indexRoot.dataset.indexUrl, assign(url) { navigations.push(url); this.href = url; } };
    window.confirm = () => true;
    window.history = { pushState(_state, _title, url) { window.location.href = String(url); }, replaceState(_state, _title, url) { window.location.href = String(url); } };
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
        if (String(url).includes('/ticket/') && answer.data?.html && !answer.data.ticket) {
            const id = answer.data.html;
            answer.data = { ticket: { id, subject: id, status: 'pending', closed: false, reply_url: '/ticket/' + id + '/reply', show_url: '/ticket/' + id }, messages: [] };
        }
        return { ok: answer.status === undefined || answer.status < 400, status: answer.status ?? 200, json: async () => answer.data };
    };
    const ticket = { id: 'one', subject: 'Leaking tap', status: closed ? 'done' : 'pending', closed, reply_url: '/ignored/reply', show_url: '/ignored/show' };
    if (chat) answers.push({ data: { ticket, messages } });
    node('[data-chat-attachment-menu]').hidden = true;
    node('[data-chat-form]').fields = [['_token', 'csrf-token']];
    node('[data-complaint-modal]').dataset.limits = chatRoot.dataset.limits;
    node('[data-complaint-form]').action = indexRoot.dataset.indexUrl;
    const dynamicChats = [];
    node('[data-conversation-host]').onHTML = (id) => {
        const nodes = new Map();
        const chatNode = (selector) => { if (!nodes.has(selector)) nodes.set(selector, new Element()); return nodes.get(selector); };
        const dynamic = new Element();
        dynamic.dataset = { ...chatRoot.dataset, empty: String(!id), canSend: String(!!id), sendUrl: '/ticket/' + id + '/reply', readUrl: '/ticket/' + id };
        dynamic.querySelector = chatNode;
        chatNode('[data-chat-subject]').textContent = id || 'Customer Service';
        chatNode('[data-chat-form]').controls = [chatNode('[data-chat-draft]'), chatNode('[data-chat-attach]'), chatNode('[data-chat-send]')];
        chatNode('[data-chat-form]').fields = [['_token', 'token']];
        node('[data-conversation-host]').replaceChildren(dynamic);
        dynamicChats.push({ id, node: chatNode, root: dynamic });
    };
    if (index) node('[data-conversation-host]').innerHTML = initial;
    if (initial) window.location.href += '?ticket=' + initial;
    let currentTime = now ? new Date(now).getTime() : Date.now();
    class TestDate extends Date { constructor(...args) { super(...(args.length ? args : [currentTime])); } }
    let timer;
    const clock = { advanceTo(value) { currentTime = new Date(value).getTime(); timer?.(); } };
    vm.runInNewContext(source, { document, window, AbortController, CustomEvent, Date: TestDate, setTimeout: (callback) => { timer = callback; return 1; }, clearTimeout: () => {}, URL: TestURL, FormData: FormDataMock, fetch, console });
    document.fire('DOMContentLoaded');
    return { node, ticket, requests, answers, revoked, navigations, chatRoot, document, window, dynamicChats, clock };
}

test('Index creates a complaint, refreshes list and selects its conversation without navigation', async () => {
    const { node, requests, answers, navigations } = setup({ index: true });
    await node('[data-open-complaint]').fire('click');
    assert.equal(node('[data-complaint-modal]').open, true);
    node('[data-complaint-form]').fields = [['_token', 'token'], ['subject', 'Leaking tap'], ['details', 'Text']];
    answers.push({ data: { ticket: { id: 'one' }, messages: [] } }, { data: { html: 'one' } }, { data: { html: 'list', creation_reason: null } });
    await node('[data-complaint-form]').fire('submit');
    assert.equal(requests.length, 3);
    assert.deepEqual(requests[0].body.values.map(([name]) => name), ['_token', 'subject', 'details']);
    assert.equal(node('[data-complaint-modal]').open, false);
    assert.equal(node('[data-complaint-form]').loading, false);
    assert.deepEqual(navigations, []);
    assert.equal(node('[data-conversation-host]').children[0].querySelector('[data-chat-subject]').textContent, 'one');
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
    assert.equal(node('[data-complaint-form]').loading, false);
    answers.push({ data: { ticket: { index_url: '/tenant/customer-service?ticket=new' } } });
    await node('[data-complaint-form]').fire('submit');
    assert.equal(requests.length, 2);
    assert.deepEqual(navigations, ['/tenant/customer-service?ticket=new']);
    assert.deepEqual(requests[1].body.values.map(([name]) => name), ['_token', 'subject', 'details']);
});

test('Selecting subjects renders shared chats; a stale response cannot replace a newer selection', async () => {
    const { node, requests, answers, window, dynamicChats } = setup({ index: true });
    const link = (id) => { const link = new Element('a'); link.dataset.ticketId = id; return link; };
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    answers.push({ gate, data: { html: 'old' } }, { data: { html: 'new' } });
    const pending = node('[data-ticket-list]').fire('click', { target: link('old') });
    await node('[data-ticket-list]').fire('click', { target: link('new') });
    release(); await pending;
    assert.equal(node('[data-conversation-host]').children[0].querySelector('[data-chat-subject]').textContent, 'new');
    assert.equal(dynamicChats.length, 1);
    assert.match(window.location.href, /ticket=new/);
    assert.equal(requests.length, 2);
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
    assert.equal(node('[data-complaint-form]').loading, true);
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
    assert.equal(node('[data-complaint-form]').loading, false);
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
    assert.match(node('[data-create-error]').textContent, /maximum/);
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

test('Switch confirmation protects drafts, reuses one listener, and sends only to the active ticket', async () => {
    const { node, answers, requests, window, dynamicChats } = setup({ index: true });
    const link = (id) => { const link = new Element('a'); link.dataset.ticketId = id; return link; };
    answers.push({ data: { html: 'first' } });
    await node('[data-ticket-list]').fire('click', { target: link('first') });
    const first = dynamicChats[0].node;
    first('[data-chat-draft]').value = 'Draft';
    window.confirm = () => false;
    await node('[data-ticket-list]').fire('click', { target: link('second') });
    assert.equal(requests.length, 1);
    assert.equal(first('[data-chat-draft]').value, 'Draft');
    window.confirm = () => true;
    answers.push({ data: { html: 'second' } });
    await node('[data-ticket-list]').fire('click', { target: link('second') });
    const second = dynamicChats[0].node;
    assert.equal(second('[data-chat-draft]').value, '');
    await first('[data-chat-form]').fire('submit');
    assert.equal(requests.length, 2);
    second('[data-chat-draft]').value = 'New message';
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    answers.push({ gate, data: { ticket: { subject: 'second', status: 'pending', closed: false }, messages: [] } });
    const sending = second('[data-chat-form]').fire('submit');
    await node('[data-ticket-list]').fire('click', { target: link('third') });
    assert.equal(requests.length, 3);
    assert.equal(requests[2].url, '/ticket/second/reply');
    await node('[data-conversation-host]').fire('click', { target: Object.assign(new Element('button'), { dataset: { backToList: '' } }) });
    assert.match(window.location.href, /ticket=second/);
    await second('[data-chat-form]').fire('submit');
    assert.equal(requests.length, 3);
    release(); await sending;
    await node('[data-conversation-host]').fire('click', { target: Object.assign(new Element('button'), { dataset: { backToList: '' } }) });
    assert.equal(new URL(window.location.href).searchParams.has('ticket'), false);
});

test('Search and pagination keep the selected conversation; failed loads can retry', async () => {
    const { node, answers, requests, window } = setup({ index: true });
    const link = new Element('a'); link.dataset.ticketId = 'one';
    answers.push({ status: 500, data: { message: 'Failure' } });
    await node('[data-ticket-list]').fire('click', { target: link });
    assert.equal(node('[data-retry-conversation]').hidden, false);
    answers.push({ data: { html: 'one' } });
    await node('[data-retry-conversation]').fire('click');
    await settle();
    node('[data-ticket-search]').fields = [['search', 'leak']];
    answers.push({ data: { html: 'matches' } });
    await node('[data-ticket-search]').fire('submit');
    assert.match(window.location.href, /search=leak/);
    assert.match(window.location.href, /ticket=one/);
    const page = new Element('a'); page.href = 'http://example.test/admin/tenant/customer-service?search=leak&page=2';
    answers.push({ data: { html: 'page 2' } });
    await node('[data-ticket-list]').fire('click', { target: page });
    assert.match(requests.at(-1).url, /page=2/);
    assert.equal(node('[data-conversation-host]').children[0].querySelector('[data-chat-subject]').textContent, 'one');
});

test('Hydrated deep link initializes once; browser history and mobile back protect attachment drafts', async () => {
    const { node, dynamicChats, answers, requests, window } = setup({ index: true, initial: 'first' });
    assert.equal(requests.length, 0);
    const first = dynamicChats[0].node;
    assert.equal(first('[data-chat-form]').listeners.submit.length, 1);
    const picker = first('[data-chat-files="document"]');
    picker.files = [{ name: 'draft.txt', type: 'text/plain', size: 100 }];
    await picker.fire('change');
    window.confirm = () => false;
    await node('[data-conversation-host]').fire('click', { target: Object.assign(new Element('button'), { dataset: { backToList: '' } }) });
    assert.match(window.location.href, /ticket=first/);
    assert.equal(first('[data-attachment-previews]').children.length, 1);
    window.location.href = 'http://example.test/admin/tenant/customer-service?ticket=second';
    await window.fire('popstate');
    assert.match(window.location.href, /ticket=first/);
    window.confirm = () => true;
    window.location.href = 'http://example.test/admin/tenant/customer-service?ticket=second';
    answers.push({ data: { html: 'second' } }, { data: { html: 'list' } });
    await window.fire('popstate');
    assert.equal(node('[data-conversation-host]').children[0].querySelector('[data-chat-subject]').textContent, 'second');
    assert.equal(first('[data-chat-form]').listeners.submit.length, 1);
});

test('Persistent shell stays empty and disabled through the first load, then changes atomically', async () => {
    const { node, answers, requests, dynamicChats } = setup({ index: true });
    const shell = dynamicChats[0].root;
    const chat = dynamicChats[0].node;
    assert.equal(chat('[data-chat-subject]').textContent, 'Customer Service');
    assert.equal(chat('[data-chat-draft]').disabled, true);
    assert.equal(chat('[data-chat-attach]').disabled, true);
    assert.equal(chat('[data-chat-send]').disabled, true);
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    answers.push({ gate, data: { html: 'first' } });
    const link = new Element('a'); link.dataset.ticketId = 'first';
    const pending = node('[data-ticket-list]').fire('click', { target: link });
    assert.equal(node('[data-conversation-host]').children[0], shell);
    assert.equal(chat('[data-chat-subject]').textContent, 'Customer Service');
    assert.equal(node('[data-conversation-feedback]').hidden, true);
    await chat('[data-chat-form]').fire('submit');
    assert.equal(requests.length, 1);
    release(); await pending;
    assert.equal(node('[data-conversation-host]').children[0], shell);
    assert.equal(chat('[data-chat-subject]').textContent, 'first');
    assert.equal(chat('[data-chat-form]').action, '/ticket/first/reply');
    assert.equal(chat('[data-chat-draft]').disabled, false);
    assert.equal(chat('[data-chat-form]').listeners.submit.length, 1);
});

test('A failed switch preserves history, endpoints and drafts; retry commits the newest conversation', async () => {
    const { node, answers, requests, dynamicChats, window } = setup({ index: true });
    const chat = dynamicChats[0].node;
    const shell = dynamicChats[0].root;
    const link = id => Object.assign(new Element('a'), { dataset: { ticketId: id } });
    answers.push({ data: { ticket: { id: 'first', subject: 'First', status: 'pending', closed: false, reply_url: '/first/send', show_url: '/first' }, messages: [{ sender_type: 'tenant', text: 'Original history' }] } });
    await node('[data-ticket-list]').fire('click', { target: link('first') });
    const history = chat('[data-chat-messages]').children[0];
    chat('[data-chat-draft]').value = 'Unsent text';
    const picker = chat('[data-chat-files="document"]');
    picker.files = [{ name: 'draft.txt', type: 'text/plain', size: 100 }];
    await picker.fire('change');
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    answers.push({ gate, status: 403, data: {} });
    const pending = node('[data-ticket-list]').fire('click', { target: link('second') });
    assert.equal(chat('[data-chat-draft]').disabled, true);
    assert.equal(chat('[data-chat-subject]').textContent, 'First');
    assert.equal(chat('[data-chat-messages]').children[0], history);
    assert.equal(chat('[data-chat-form]').action, '/first/send');
    await chat('[data-chat-form]').fire('submit');
    assert.equal(requests.length, 2);
    release(); await pending;
    assert.equal(node('[data-conversation-host]').children[0], shell);
    assert.equal(chat('[data-chat-messages]').children[0], history);
    assert.equal(chat('[data-chat-draft]').value, 'Unsent text');
    assert.equal(chat('[data-attachment-previews]').children.length, 1);
    assert.equal(chat('[data-chat-draft]').disabled, false);
    assert.match(window.location.href, /ticket=first/);
    assert.equal(node('[data-retry-conversation]').hidden, false);
    answers.push({ data: { ticket: { id: 'second', subject: 'Closed second', status: 'closed', closed: true, reply_url: '/second/send', show_url: '/second' }, messages: [] } });
    await node('[data-retry-conversation]').fire('click'); await settle();
    assert.equal(chat('[data-chat-subject]').textContent, 'Closed second');
    assert.equal(chat('[data-chat-form]').action, '/second/send');
    assert.equal(chat('[data-chat-draft]').value, '');
    assert.equal(chat('[data-attachment-previews]').children.length, 0);
    assert.equal(chat('[data-chat-send]').disabled, true);
    answers.push({ data: { html: 'third' } });
    await node('[data-ticket-list]').fire('click', { target: link('third') });
    assert.equal(chat('[data-chat-draft]').disabled, false);
    assert.equal(chat('[data-chat-form]').listeners.submit.length, 1);
});

test('Message day groups use Malaysia dates, update at midnight and do not duplicate on refresh', async () => {
    const messages = [
        { id: '1', sender_type: 'tenant', text: 'Yesterday', sent_at: '2026-10-12T15:59:00Z' },
        { id: '2', sender_type: 'tenant', text: 'Today', sent_at: '2026-10-12T16:00:00Z' },
        { id: '3', sender_type: 'tenant', extension: 'pdf', filename: 'file.pdf', attachment_url: '/private/pdf', sent_at: '2026-10-12T16:01:00Z' },
    ];
    const { node, clock, answers, ticket } = setup({ chat: true, messages, now: '2026-10-13T23:59:59+08:00' });
    await settle();
    const labels = () => node('[data-chat-messages]').querySelectorAll('[data-message-date]').map(el => el.textContent);
    assert.deepEqual(labels(), ['Yesterday', 'Today']);
    clock.advanceTo('2026-10-14T00:00:00+08:00');
    assert.deepEqual(labels(), ['12 Oct 2026', 'Yesterday']);
    answers.push({ data: { ticket, messages } });
    node('[data-chat-draft]').value = 'Update';
    await node('[data-chat-form]').fire('submit');
    assert.deepEqual(labels(), ['12 Oct 2026', 'Yesterday']);
    const pdfBubble = node('[data-chat-messages]').children.at(-1).children[0];
    assert.equal(pdfBubble.children[0].textContent.endsWith('00:01'), true);
    assert.equal(pdfBubble.children[1].children[1].textContent, 'file.pdf');
    assert.equal(pdfBubble.children[1].children[0].href, '/private/pdf');
    assert.equal(pdfBubble.children.at(-1).href, '/private/pdf?download=1');
});

test('Create and reply enforce the same per-file boundaries with filename feedback', async () => {
    for (const create of [true, false]) {
        const { node } = setup(create ? { dashboard: true } : { chat: true });
        await settle();
        const picker = node(create ? '[data-create-files="document"]' : '[data-chat-files="document"]');
        const previews = node(create ? '[data-create-previews]' : '[data-attachment-previews]');
        const error = node(create ? '[data-create-error]' : '[data-chat-error]');
        for (const [extension, mb] of [['png', 2], ['mp4', 20], ['pdf', 3]]) {
            const count = previews.children.length;
            picker.files = [{ name: 'limit.' + extension, type: 'application/octet-stream', size: mb * 1024 * 1024 }];
            await picker.fire('change');
            assert.equal(previews.children.length, count + 1);
            picker.files = [{ name: 'over.' + extension, type: 'application/octet-stream', size: mb * 1024 * 1024 + 1 }];
            await picker.fire('change');
            assert.equal(previews.children.length, count + 1);
            assert.equal(error.textContent, 'over.' + extension + ': maximum ' + mb + ' MB per file.');
        }
    }
});

test('Lease selection is required, submitted and preserved with all inputs after validation failure', async () => {
    for (const surface of [{ dashboard: true }, { index: true }]) {
        const { node, requests, answers } = setup({ ...surface, multipleLeases: true });
        await node('[data-open-complaint]').fire('click');
        const form = node('[data-complaint-form]');
        form.fields = [['_token', 'token'], ['subject', 'Draft'], ['details', 'Keep details'], ['lease_id', '']];
        await form.fire('submit');
        assert.equal(requests.length, 0);
        assert.equal(node('[data-create-error]').textContent, 'Please select a lease.');
        form.fields.at(-1)[1] = 'chosen-lease';
        const picker = node('[data-create-files="document"]');
        picker.files = [{ name: 'note.txt', type: 'text/plain', size: 12 }];
        await picker.fire('change');
        let release;
        const gate = new Promise(resolve => { release = resolve; });
        answers.push({ gate, status: 422, data: { errors: { lease_id: ['Lease unavailable'] } } });
        const pending = form.fire('submit');
        await form.fire('submit');
        assert.equal(requests.length, 1);
        assert.equal(requests[0].body.get('lease_id'), 'chosen-lease');
        release();
        await pending;
        assert.deepEqual(form.fields, [['_token', 'token'], ['subject', 'Draft'], ['details', 'Keep details'], ['lease_id', 'chosen-lease']]);
        assert.equal(node('[data-create-previews]').children.length, 1);
        assert.equal(node('[data-complaint-modal]').open, true);
        assert.equal(form.loading, false);
    }
});