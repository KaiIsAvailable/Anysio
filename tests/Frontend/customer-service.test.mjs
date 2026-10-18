import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../resources/js/customer-service.js', import.meta.url), 'utf8')
    .replace('chats.set(root, api);', 'chats.set(root, api); root.testApi = api;')
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
        const key = { '[data-message-id]': 'messageId', '[data-unread-divider]': 'unreadDivider' }[selector];
        if (key) return this.children.flatMap(child => [...(child.dataset[key] !== undefined ? [child] : []), ...child.querySelectorAll(selector)]);
        if (selector === '[data-message-date]') return this.children.flatMap(child => [...(child.dataset.messageDate ? [child] : []), ...child.querySelectorAll(selector)]);
        return this.controls;
    }
    showModal() { this.open = true; }
    close() { this.open = false; this.fire('close'); }
    reset() { this.fields = this.fields.filter(([name]) => name === '_token'); }
}
class FormDataMock {
    constructor(form) { this.values = [...(form?.fields || [])]; }
    append(name, value) { this.values.push([name, value]); }
    get(name) { return this.values.find(([key]) => key === name)?.[1] ?? null; }
}
const settle = async () => { for (let i = 0; i < 4; i++) await new Promise(setImmediate); };

test('Subject selection stays committed across switching, failures and replaced search/page lists', async () => {
    for (const manager of [false, true]) {
        for (const mobile of [false, true]) {
            const { node, answers } = setup({ index: true, initial: 'first', manager, mobile });
            const list = node('[data-ticket-list]');
            const replaceList = () => {
                list.controls = ['first', 'middle', 'last'].map((id) => {
                    const link = new Element('a');
                    link.dataset.ticketId = id;
                    return link;
                });
            };
            const assertSelected = (id) => {
                for (const link of list.controls) {
                    assert.equal(link['aria-current'], String(link.dataset.ticketId === id));
                }
            };
            list.onHTML = replaceList;
            replaceList();
            for (const id of ['last', 'first', 'middle']) {
                answers.push({ data: { html: id } });
                await list.fire('click', { target: list.controls.find((link) => link.dataset.ticketId === id) });
                assertSelected(id);
            }
            let release;
            answers.push({ gate: new Promise((resolve) => { release = resolve; }), status: 500, data: {} });
            const failedSwitch = list.fire('click', { target: list.controls[2] });
            assertSelected('middle');
            release();
            await failedSwitch;
            assertSelected('middle');
            node('[data-ticket-search]').fields = [['search', 'leak']];
            answers.push({ data: { html: 'search results' } });
            await node('[data-ticket-search]').fire('submit');
            assertSelected('middle');
            const page = new Element('a');
            page.href = 'http://example.test/admin/tenant/customer-service?search=leak&page=2';
            answers.push({ data: { html: 'page results' } });
            await list.fire('click', { target: page });
            assertSelected('middle');
        }
    }
});

test('List replacement preserves the latest scroll position while keeping the selected subject', async () => {
    const { node, answers } = setup({ index: true, initial: 'first', manager: true });
    const list = node('[data-ticket-list]');
    list.scrollTop = 90;
    list.onHTML = () => {
        list.scrollTop = 0;
        const link = new Element('a');
        link.dataset.ticketId = 'first';
        list.controls = [link];
    };
    let release;
    answers.push({ gate: new Promise(resolve => { release = resolve; }), data: { html: 'updated' } });
    const pending = node('[data-ticket-search]').fire('submit');
    list.scrollTop = 170;
    release();
    await pending;
    assert.equal(list.scrollTop, 170);
    assert.equal(list.controls[0]['aria-current'], 'true');
});

function setup({ manager = false, multipleLeases = false, index = false, dashboard = false, chat = false, role = 'tenant', canSend = true, closed = false, messages = [], mobile = false, initial = '', now = null } = {}) {
    const nodes = new Map();
    const node = (selector) => {
        if (manager && ['[data-complaint-modal]', '[data-open-complaint]', '[data-creation-reason]'].includes(selector)) return null;
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
        canAttach: 'true',
        canSend: String(canSend),
        sendUrl: 'http://example.test/shared/send',
        readUrl: 'http://example.test/shared/read',
        limits: JSON.stringify({ max_files: 10, max_total_kb: 102400, types: { txt: { max_kb: 3072 }, png: { max_kb: 2048 }, mp4: { max_kb: 20480 }, pdf: { max_kb: 3072 } } }),
    };
    chatRoot.querySelector = node;
    const document = new Element();
    document.visibilityState = 'visible';
    document.hasFocus = () => true;
    document.querySelector = () => index ? indexRoot : null;
    const dashboardRoot = new Element();
    dashboardRoot.dataset = { redirectAfterCreate: 'true' };
    dashboardRoot.querySelector = node;
    document.querySelectorAll = (selector) => selector === '[data-complaint-create]' ? (dashboard ? [dashboardRoot] : []) : (chat ? [chatRoot] : index && initial ? [node('[data-conversation-host]').children[0]] : []);
    document.createElement = (tag) => new Element(tag);
    const navigations = [];
    const window = new Element();
    window.requestAnimationFrame = (callback) => callback();
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
    if (!manager) node('[data-complaint-modal]').dataset.limits = chatRoot.dataset.limits;
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
    class TestDate extends Date {
        constructor(...args) { super(...(args.length ? args : [currentTime])); }
        static now() { return currentTime; }
    }
    let timer;
    const clock = { advanceTo(value) { currentTime = new Date(value).getTime(); timer?.(); } };
    const resizeObservers = [];
    class ResizeObserverMock {
        constructor(callback) { this.callback = callback; this.targets = []; resizeObservers.push(this); }
        observe(target) { this.targets.push(target); }
        disconnect() { this.targets = []; }
    }
    vm.runInNewContext(source, { document, window, AbortController, CustomEvent, ResizeObserver: ResizeObserverMock, Date: TestDate, setTimeout: (callback) => { timer = callback; return 1; }, clearTimeout: () => {}, URL: TestURL, FormData: FormDataMock, fetch, console });
    document.fire('DOMContentLoaded');
    return { node, ticket, requests, answers, revoked, navigations, chatRoot, document, window, dynamicChats, clock, resizeObservers };
}

test('Read interactions require visibility and focus, deduplicate, and preserve the visit divider', async () => {
    for (const interaction of ['mouseenter', 'mousemove', 'pointerdown', 'touchstart']) {
        const { node, answers, requests, chatRoot, ticket, document } = setup({ chat: true });
        await settle();
        const incoming = { id: 'incoming', incoming: true, status: 'unread', text: 'Hello' };
        const own = { id: 'own', own: true, incoming: false, status: 'unread', text: 'Mine' };
        chatRoot.testApi.apply({ ticket: { ...ticket, mark_read_url: '/read-receipts' }, messages: [incoming, own] });
        const history = node('[data-chat-messages]');
        const rows = history.children;
        assert.equal(history.querySelectorAll('[data-unread-divider]').length, 1);
        assert.equal(requests.length, 1, 'loading alone never marks read');
        await history.fire('mouseenter', { movementX: 0, movementY: 0 });
        assert.equal(requests.length, 1, 'layout changes under a stationary pointer do not count');
        document.visibilityState = 'hidden';
        await history.fire(interaction);
        assert.equal(requests.length, 1);
        document.visibilityState = 'visible';
        document.hasFocus = () => false;
        await history.fire(interaction);
        assert.equal(requests.length, 1);
        document.hasFocus = () => true;
        let release;
        answers.push({ gate: new Promise(resolve => { release = resolve; }), data: { messages: [{ id: 'incoming', status: 'read' }] } });
        const pending = history.fire(interaction);
        await history.fire(interaction);
        assert.equal(requests.length, 2);
        assert.deepEqual(requests[1].body.values, [['_token', 'csrf-token'], ['message_ids[]', 'incoming']]);
        release();
        await pending;
        await settle();
        assert.equal(history.children, rows, 'receipt updates do not rerender messages or PDFs');
        assert.equal(history.querySelectorAll('[data-unread-divider]').length, 1);
        await history.fire(interaction);
        assert.equal(requests.length, 2, 'already read IDs are not submitted again');
    }
});

test('Reading updates only the subject badge without fetching lists or replacing chat, drafts or previews', async () => {
    for (const manager of [false, true]) {
        const { node, dynamicChats, answers, requests } = setup({ index: true, initial: 'one', manager });
        const chat = dynamicChats[0];
        chat.root.testApi.apply({ ticket: { id: 'one', status: 'pending', show_url: '/one', reply_url: '/send', mark_read_url: '/receipt' }, messages: [{ id: 'incoming', incoming: true, status: 'unread' }] });
        const list = node('[data-ticket-list]');
        const link = new Element('a');
        link.dataset.ticketId = 'one';
        link.setAttribute('aria-current', 'true');
        const badge = new Element();
        badge.textContent = '1';
        link.append(badge);
        list.controls = [link];
        list.scrollTop = 123;
        list.onHTML = () => assert.fail('Reading must not replace the subject list');
        chat.node('[data-chat-draft]').value = 'Keep draft';
        const picker = chat.node('[data-chat-files="document"]');
        picker.files = [{ name: 'keep.pdf', type: 'application/pdf', size: 20 }];
        await picker.fire('change');
        const previews = chat.node('[data-attachment-previews]').children;
        const history = chat.node('[data-chat-messages]');
        const rows = history.children;
        history.scrollTop = 29;
        answers.push({ data: { ticket_id: 'one', unread_count: 0, messages: [{ id: 'incoming', status: 'read' }] } });
        await history.fire('mousemove', { movementX: 1, movementY: 0 });
        assert.equal(requests.length, 1);
        assert.equal(requests[0].url, '/receipt');
        assert.equal(badge.hidden, true);
        assert.equal(badge.textContent, 0);
        assert.equal(list.scrollTop, 123);
        assert.equal(link['aria-current'], 'true');
        assert.equal(history.children, rows);
        assert.equal(history.scrollTop, 29);
        assert.equal(chat.node('[data-chat-draft]').value, 'Keep draft');
        assert.equal(chat.node('[data-attachment-previews]').children, previews);
        assert.equal(node('[data-page-feedback]').textContent, '');
    }
});

test('Subject requests have no loading notice but retain failure feedback and switch protection', async () => {
    const { node, answers } = setup({ index: true, initial: 'one', manager: true });
    const link = new Element('a');
    link.dataset.ticketId = 'two';
    let release;
    answers.push({ gate: new Promise(resolve => { release = resolve; }), status: 500, data: {} });
    const pending = node('[data-ticket-list]').fire('click', { target: link });
    assert.equal(node('[data-page-feedback]').textContent, '');
    assert.equal(node('[data-conversation-feedback]').hidden, true);
    release();
    await pending;
    assert.equal(node('[data-conversation-feedback]').hidden, false);
    assert.match(node('[data-conversation-feedback]').textContent, /Unable to open/);
});

test('Subject click marks only successfully displayed conversations, including repeated clicks', async () => {
    const { node, answers, requests, dynamicChats } = setup({ index: true, initial: 'one', manager: true });
    const link = new Element('a');
    link.dataset.ticketId = 'one';
    const ticket = { id: 'one', subject: 'One', status: 'pending', show_url: '/one', reply_url: '/one/send', mark_read_url: '/one/read' };
    for (const id of ['first', 'second']) {
        answers.push({ data: { ticket, messages: [{ id, incoming: true, status: 'unread', text: id }] } });
        answers.push({ data: { messages: [{ id, status: 'read' }] } });
        await node('[data-ticket-list]').fire('click', { target: link });
        await settle();
        const posted = requests.filter(request => request.method === 'POST').at(-1);
        assert.deepEqual(posted.body.values.filter(([key]) => key === 'message_ids[]'), [['message_ids[]', id]]);
        assert.equal(dynamicChats[0].node('[data-chat-messages]').querySelectorAll('[data-unread-divider]').length, 1);
    }
    const before = requests.filter(request => request.method === 'POST').length;
    answers.push({ status: 500, data: {} });
    await node('[data-ticket-list]').fire('click', { target: link });
    await settle();
    assert.equal(requests.filter(request => request.method === 'POST').length, before);
});

test('Later arrivals need a new interaction; successful sends do not imply reading', async () => {
    const { node, answers, requests, chatRoot, ticket, clock } = setup({ chat: true });
    await settle();
    const active = { ...ticket, mark_read_url: '/read-receipts' };
    const first = { id: 'first', incoming: true, status: 'unread', text: 'First' };
    chatRoot.testApi.apply({ ticket: active, messages: [first] });
    answers.push({ data: { messages: [{ id: 'first', status: 'read' }] } });
    await chatRoot.testApi.confirmRead();
    const later = { id: 'later', incoming: true, status: 'unread', text: 'Arrived during sending' };
    answers.push({ data: { ticket: active, messages: [{ ...first, status: 'read' }, later] } });
    node('[data-chat-draft]').value = 'Reply';
    await node('[data-chat-form]').fire('submit');
    await settle();
    assert.equal(requests.filter(request => request.url === '/read-receipts').length, 1);
    const history = node('[data-chat-messages]');
    assert.equal(history.querySelectorAll('[data-unread-divider]')[0].children[1].textContent, '1 unread messages');
    clock.advanceTo(new Date(Date.now() + 2000));
    answers.push({ data: { messages: [{ id: 'later', status: 'read' }] } });
    await history.fire('mousemove');
    await settle();
    assert.deepEqual(requests.at(-1).body.values.filter(([key]) => key === 'message_ids[]'), [['message_ids[]', 'later']]);
});

test('Read failure retries only after a new interaction and stale receipts cannot change a new ticket', async () => {
    const { node, answers, requests, chatRoot, ticket, clock } = setup({ chat: true });
    await settle();
    const active = { ...ticket, mark_read_url: '/read-receipts' };
    chatRoot.testApi.apply({ ticket: active, messages: [{ id: 'old', incoming: true, status: 'unread' }] });
    answers.push({ status: 500, data: {} });
    await chatRoot.testApi.confirmRead();
    await node('[data-chat-messages]').fire('mousemove');
    assert.equal(requests.length, 2, 'failed requests are throttled');
    clock.advanceTo(new Date(Date.now() + 2000));
    let release;
    answers.push({ gate: new Promise(resolve => { release = resolve; }), data: { messages: [{ id: 'old', status: 'read' }] } });
    const pending = chatRoot.testApi.confirmRead();
    chatRoot.testApi.apply({ ticket: { ...active, id: 'new' }, messages: [{ id: 'new', incoming: true, status: 'unread' }] });
    const rows = node('[data-chat-messages]').children;
    release();
    await pending;
    assert.equal(node('[data-chat-messages]').children, rows);
    assert.equal(node('[data-chat-messages]').querySelectorAll('[data-message-id]')[0].dataset.messageStatus, 'unread');
});

test('Unread and date boundaries share one separator and recalculate only on reentry', async () => {
    const { node, chatRoot, ticket } = setup({ chat: true });
    await settle();
    const message = { id: 'first', incoming: true, status: 'unread', date_key: '2026-10-18', text: 'Hi' };
    chatRoot.testApi.apply({ ticket, messages: [message] });
    const history = node('[data-chat-messages]');
    assert.equal(history.children.length, 2, 'one shared separator and one message');
    assert.equal(history.querySelectorAll('[data-unread-divider]').length, 1);
    assert.equal(history.querySelectorAll('[data-message-date]').length, 1);
    chatRoot.testApi.apply({ ticket, messages: [{ ...message, status: 'read' }] });
    assert.equal(history.querySelectorAll('[data-unread-divider]').length, 0);
    assert.equal(history.querySelectorAll('[data-message-date]').length, 1);
});

test('Large read snapshots are bounded and do not absorb messages from a later response', async () => {
    const { node, answers, requests, chatRoot, ticket } = setup({ chat: true });
    await settle();
    const messages = Array.from({ length: 501 }, (_, i) => ({ id: String(i), incoming: true, status: 'unread', text: 'Hi' }));
    chatRoot.testApi.apply({ ticket: { ...ticket, mark_read_url: '/receipt' }, messages });
    answers.push({ data: { messages: messages.slice(0, 500).map(m => ({ id: m.id, status: 'read' })) } });
    answers.push({ data: { messages: [{ id: '500', status: 'read' }] } });
    await chatRoot.testApi.confirmRead();
    assert.equal(requests.length, 3);
    assert.equal(requests[1].body.values.filter(([key]) => key === 'message_ids[]').length, 500);
    assert.deepEqual(requests[2].body.values.filter(([key]) => key === 'message_ids[]'), [['message_ids[]', '500']]);
    assert.equal(node('[data-chat-messages]').querySelectorAll('[data-message-id]').filter(row => row.dataset.messageStatus === 'read').length, 501);
});

test('A delayed Subject confirmation keeps its original IDs even if more messages are now displayed', async () => {
    const { chatRoot, ticket, answers, requests } = setup({ chat: true });
    await settle();
    chatRoot.testApi.apply({ ticket: { ...ticket, mark_read_url: '/receipt' }, messages: [
        { id: 'loaded-at-click', incoming: true, status: 'unread' },
        { id: 'arrived-after-click', incoming: true, status: 'unread' },
    ] });
    answers.push({ data: { messages: [{ id: 'loaded-at-click', status: 'read' }] } });
    await chatRoot.testApi.confirmRead(['loaded-at-click']);
    assert.deepEqual(requests.at(-1).body.values.filter(([key]) => key === 'message_ids[]'), [['message_ids[]', 'loaded-at-click']]);
});

test('Subject clicks in a background page do not mark read or schedule a later automatic read', async () => {
    const { node, document, answers, requests } = setup({ index: true, initial: 'one', manager: true });
    document.visibilityState = 'hidden';
    const link = new Element('a');
    link.dataset.ticketId = 'one';
    answers.push({ data: { ticket: { id: 'one', status: 'pending', show_url: '/one', reply_url: '/send', mark_read_url: '/receipt' }, messages: [{ id: 'incoming', incoming: true, status: 'unread' }] } });
    await node('[data-ticket-list]').fire('click', { target: link });
    await settle();
    document.visibilityState = 'visible';
    await document.fire('visibilitychange');
    assert.equal(requests.filter(request => request.method === 'POST').length, 0);
});

test('Own text, attachment and mixed sends restart bottom following on desktop and mobile for both sides', async () => {
    for (const role of ['tenant', 'agentAdmin']) for (const mobile of [false, true]) {
        for (const kind of ['text', 'attachment', 'mixed']) {
            const { node, answers, ticket, resizeObservers } = setup({ chat: true, role, mobile });
            await settle();
            const history = node('[data-chat-messages]');
            await history.fire(mobile ? 'touchmove' : 'wheel', { deltaY: -100 });
            history.scrollTop = 10;
            if (kind !== 'attachment') node('[data-chat-draft]').value = 'New message';
            if (kind !== 'text') {
                const picker = node('[data-chat-files="media"]');
                picker.files = [{ name: 'image.png', type: 'image/png', size: 100 }];
                await picker.fire('change');
            }
            history.scrollHeight = 500;
            answers.push({ data: { ticket, messages: [{ id: 'sent', own: true, text: 'Sent' }] } });
            await node('[data-chat-form]').fire('submit');
            assert.equal(history.scrollTop, 500, `${role}/${mobile}/${kind}`);
            history.scrollHeight = 700;
            await history.fire('load');
            assert.equal(history.scrollTop, 700);
            history.scrollHeight = 800;
            await history.fire('loadedmetadata');
            assert.equal(history.scrollTop, 800);
            history.scrollHeight = 900;
            resizeObservers[0].callback();
            assert.equal(history.scrollTop, 900);
            await history.fire(mobile ? 'touchmove' : 'wheel', { deltaY: -100 });
            history.scrollTop = 200;
            history.scrollHeight = 1100;
            await history.fire('load');
            resizeObservers[0].callback();
            assert.equal(history.scrollTop, 200);
        }
    }
});

test('Failed sends preserve the scroll position, draft, attachments and existing message nodes', async () => {
    for (const status of [403, 422, 500]) {
        const { node, answers, ticket } = setup({ chat: true });
        await settle();
        const history = node('[data-chat-messages]');
        const children = history.children;
        history.scrollTop = 12;
        node('[data-chat-draft]').value = 'Keep';
        const picker = node('[data-chat-files="document"]');
        picker.files = [{ name: 'keep.pdf', type: 'application/pdf', size: 100 }];
        await picker.fire('change');
        answers.push({ status, data: { message: 'Rejected' } });
        if (status === 403) answers.push({ data: { ticket: { ...ticket, closed: true }, messages: [] } });
        await node('[data-chat-form]').fire('submit');
        assert.equal(history.scrollTop, 12);
        assert.equal(history.children, children);
        assert.equal(node('[data-chat-draft]').value, 'Keep');
        assert.equal(node('[data-attachment-previews]').children.length, 1);
    }
});

test('A send response from a previous conversation cannot scroll, replace or clear the new conversation', async () => {
    for (const status of [200, 500]) {
        const { node, answers, ticket, chatRoot } = setup({ chat: true });
        await settle();
        node('[data-chat-draft]').value = 'Old request';
        let release;
        answers.push({ gate: new Promise(resolve => { release = resolve; }), status, data: { ticket, messages: [] } });
        const pending = node('[data-chat-form]').fire('submit');
        chatRoot.testApi.apply({ ticket: { ...ticket, id: 'new', subject: 'New conversation' }, messages: [] });
        node('[data-chat-draft]').value = 'New draft';
        const history = node('[data-chat-messages]');
        history.scrollTop = 17;
        const children = history.children;
        release();
        await pending;
        assert.equal(history.scrollTop, 17);
        assert.equal(history.children, children);
        assert.equal(node('[data-chat-subject]').textContent, 'New conversation');
        assert.equal(node('[data-chat-draft]').value, 'New draft');
    }
});

test('Delayed media and PDF height follow the bottom only until the user scrolls up', async () => {
    const { node, resizeObservers } = setup({ chat: true });
    await settle();
    const history = node('[data-chat-messages]');
    history.scrollHeight = 500;
    await history.fire('load');
    assert.equal(history.scrollTop, 500);
    history.scrollHeight = 700;
    resizeObservers[0].callback();
    assert.equal(history.scrollTop, 700);
    await history.fire('wheel', { deltaY: -100 });
    history.scrollTop = 200;
    history.scrollHeight = 900;
    await history.fire('loadedmetadata');
    resizeObservers[0].callback();
    assert.equal(history.scrollTop, 200);
    assert.equal(node('[data-chat-status]').textContent, 'PENDING');
});

test('Clicking the current subject reloads messages while retaining the draft and attachments', async () => {
    for (const mobile of [false, true]) {
        const { node, answers, requests, dynamicChats } = setup({ index: true, initial: 'first', mobile });
        const chat = dynamicChats[0].node;
        chat('[data-chat-draft]').value = 'Keep this draft';
        const picker = chat('[data-chat-files="document"]');
        picker.files = [{ name: 'note.txt', size: 10, type: 'text/plain' }];
        await picker.fire('change');
        const link = new Element('a'); link.dataset.ticketId = 'first';
        answers.push({ data: { ticket: { id: 'first', subject: 'Refreshed', status: 'pending', closed: false, reply_url: '/reply', show_url: '/show' }, messages: [] } });
        await node('[data-ticket-list]').fire('click', { target: link });
        assert.equal(requests.length, 1);
        assert.equal(chat('[data-chat-subject]').textContent, 'Refreshed');
        assert.equal(chat('[data-chat-draft]').value, 'Keep this draft');
        assert.equal(chat('[data-attachment-previews]').children.length, 1);
        const history = chat('[data-chat-messages]');
        await history.fire('touchmove');
        history.scrollTop = 5;
        history.scrollHeight = 100;
        await history.fire('load');
        assert.equal(history.scrollTop, 5);
    }
});

test('A successful send refreshes the subject list without losing search, page or selection', async () => {
    const { node, answers, requests, window, dynamicChats } = setup({ index: true, initial: 'first' });
    const page = new Element('a'); page.href = 'http://example.test/admin/tenant/customer-service?search=leak&page=2';
    answers.push({ data: { html: 'page' } });
    await node('[data-ticket-list]').fire('click', { target: page });
    const chat = dynamicChats[0].node;
    chat('[data-chat-draft]').value = 'Reply';
    await chat('[data-chat-draft]').fire('input');
    answers.push({ data: { ticket: { id: 'first', subject: 'First', status: 'pending', closed: false }, messages: [] } }, { data: { html: 'reordered page' } });
    await chat('[data-chat-form]').fire('submit');
    assert.equal(requests.length, 3);
    assert.equal(requests[1].method, 'POST');
    assert.match(requests[2].url, /search=leak/);
    assert.match(requests[2].url, /page=2/);
    assert.match(window.location.href, /ticket=first/);
    assert.equal(node('[data-ticket-list]').innerHTML, 'reordered page');
});

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
        { id: 'new', own: true, sender_type: 'agentAdmin', text: 'Agent message', created_at: '09 Oct 2026 09:01' },
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
test('Maintenance reuses the persistent desktop/mobile shell without a create modal and preserves drafts', async () => {
    for (const mobile of [false, true]) {
        const { node, requests, answers, window } = setup({ index: true, manager: true, mobile });
        const link = new Element('a'); link.dataset.ticketId = 'managed';
        answers.push({ data: { ticket: { id: 'managed', subject: 'Leak', context: 'Tenant · Room A', closed: false, reply_url: '/maintenance/managed/messages', show_url: '/maintenance/managed' },
            messages: [{ id: 'a', sender_type: 'agentAdmin', own: false, text: 'Another agent' }, { id: 'b', sender_type: 'agentAdmin', own: true, text: 'My reply' }] } });
        await node('[data-ticket-list]').fire('click', { target: link });
        const chat = node('[data-conversation-host]').children[0];
        const field = selector => chat.querySelector(selector);
        assert.equal(field('[data-chat-context]').textContent, 'Tenant · Room A');
        assert.equal(field('[data-chat-attach]').hidden, false);
        assert.equal(field('[data-chat-messages]').children[0].className, 'flex justify-start');
        assert.equal(field('[data-chat-messages]').children[1].className, 'flex justify-end');
        field('[data-chat-draft]').value = 'Keep my draft';
        await field('[data-chat-draft]').fire('input');
        window.confirm = () => false;
        const back = new Element('button'); back.dataset.backToList = '';
        await node('[data-conversation-host]').fire('click', { target: back });
        assert.equal(field('[data-chat-draft]').value, 'Keep my draft');
        assert.equal(node('[data-conversation-host]').children[0], chat);
        answers.push({ status: 422, data: { errors: { items: ['Try again'] } } });
        await field('[data-chat-form]').fire('submit');
        assert.equal(requests.at(-1).url, '/maintenance/managed/messages');
        assert.equal(field('[data-chat-draft]').value, 'Keep my draft');
        answers.push({ data: { html: 'filtered list' } });
        await node('[data-ticket-search]').fire('submit');
        assert.equal(node('[data-page-feedback]').textContent, '');
    }
});
test('Maintenance roles reuse attachment previews, removal, batch submission and failure recovery', async () => {
    for (const role of ['agentAdmin', 'ownerAdmin', 'owner']) {
        const { node, ticket, answers, requests } = setup({ chat: true, manager: true, role });
        await settle();
        assert.equal(node('[data-chat-attach]').hidden, false);
        const picker = node('[data-chat-files="document"]');
        picker.files = [{ name: 'proof.txt', type: 'text/plain', size: 100 }, { name: 'remove.txt', type: 'text/plain', size: 100 }];
        await picker.fire('change');
        const previews = node('[data-attachment-previews]');
        assert.equal(previews.children.length, 2);
        await previews.children[1].children.at(-1).fire('click');
        assert.equal(previews.children.length, 1);
        const draft = node('[data-chat-draft]');
        draft.value = 'Manager with attachment';
        let release;
        answers.push({ gate: new Promise(resolve => { release = resolve; }), status: 500, data: { message: 'Storage failed' } });
        const pending = node('[data-chat-form]').fire('submit');
        assert.equal(node('[data-chat-loading]').hidden, false);
        await node('[data-chat-form]').fire('submit');
        assert.equal(requests.length, 2);
        assert.equal(requests[1].body.get('items[0][text]'), 'Manager with attachment');
        assert.equal(requests[1].body.get('items[1][file]').name, 'proof.txt');
        release(); await pending;
        assert.equal(draft.value, 'Manager with attachment');
        assert.equal(previews.children.length, 1);
        assert.equal(node('[data-chat-loading]').hidden, true);
        draft.value = '';
        answers.push({ data: { ticket, messages: [{ own: true, sender_type: role, attachment_url: '/private/proof', extension: 'txt' }] } });
        await node('[data-chat-form]').fire('submit');
        assert.equal(requests[2].body.get('items[0][type]'), 'file');
        assert.equal(requests[2].body.get('items[0][file]').name, 'proof.txt');
        assert.equal(previews.children.length, 0);
    }
});
