import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const source = readFileSync(new URL('../../resources/js/service-request-navigation.js', import.meta.url), 'utf8').replace('export function', 'function');

function setup(url = '/admin/maintenance/unread') {
    const events = {};
    const timers = [];
    const requests = [];
    const answers = [];
    const document = { visibilityState: 'visible', addEventListener: (name, handler) => { events[name] = handler; }, removeEventListener: (name) => { delete events[name]; } };
    const context = vm.createContext({
        document, window: document, AbortController,
        setTimeout: (handler, delay) => { timers.push({ handler, delay }); return timers.length; },
        clearTimeout() {},
        fetch: async (url, options) => {
            requests.push({ url, options });
            const answer = await (answers.shift() ?? { unread_count: 0 });
            if (answer instanceof Error) throw answer;
            return { ok: !answer.status, status: answer.status || 200, json: async () => answer };
        },
    });
    vm.runInContext(source, context);
    return { component: context.serviceRequestNavigation(url), events, timers, requests, answers, document };
}

test('Unread badge shows the message count, decreases after reading and clears at zero', async () => {
    const { component, answers, events, timers, requests } = setup();
    component.init();
    await new Promise(setImmediate);
    assert.equal(component.unreadCount, 0);
    answers.push({ unread_count: 12 });
    await timers.at(-1).handler();
    assert.equal(component.unreadCount, 12);
    assert.equal(timers.at(-1).delay, 1000);
    answers.push({ unread_count: 5 });
    await events['service-request-read']();
    assert.equal(component.unreadCount, 5);
    answers.push({ unread_count: 0 });
    await events['service-request-read']();
    assert.equal(component.unreadCount, 0);
    assert.equal(requests[0].options.cache, 'no-store');
    component.destroy();
    assert.equal(events['service-request-read'], undefined);
});

test('Hidden pages and users without navigation access do not poll', async () => {
    const missing = setup('');
    missing.component.init();
    assert.equal(missing.requests.length, 0);
    const page = setup();
    page.document.visibilityState = 'hidden';
    page.component.init();
    assert.equal(page.requests.length, 0);
    page.document.visibilityState = 'visible';
    await page.events.visibilitychange();
    assert.equal(page.requests.length, 1);
    page.component.destroy();
});

test('Network failures preserve the count and access denial stops refreshing', async () => {
    const { component, answers, timers } = setup();
    component.unreadCount = 7;
    answers.push(new Error('Offline'));
    component.init();
    await new Promise(setImmediate);
    assert.equal(component.unreadCount, 7);
    const scheduled = timers.length;
    answers.push({ status: 403 });
    await component.refreshUnread();
    assert.equal(component.unreadCount, 0);
    assert.equal(timers.length, scheduled);
    component.destroy();
});

test('Slow responses never overlap requests, including read events', async () => {
    const { component, answers, requests, events, timers } = setup();
    let resolve;
    answers.push(new Promise(done => { resolve = done; }));
    component.init();
    await events['service-request-read']();
    await component.refreshUnread();
    assert.equal(requests.length, 1);
    resolve({ unread_count: 4 });
    await new Promise(setImmediate);
    assert.equal(component.unreadCount, 4);
    assert.equal(timers.at(-1).delay, 0);
    await timers.at(-1).handler();
    assert.equal(requests.length, 2);
    assert.equal(timers.at(-1).delay, 1000);
    component.destroy();
});

test('Failures back off to thirty seconds and success restores one-second refreshes', async () => {
    const { component, answers, timers } = setup();
    component.init();
    await new Promise(setImmediate);
    for (const delay of [2000, 4000, 8000, 16000, 30000, 30000]) {
        answers.push({ status: 500 });
        await timers.at(-1).handler();
        assert.equal(timers.at(-1).delay, delay);
    }
    answers.push({ unread_count: 3 });
    await timers.at(-1).handler();
    assert.equal(timers.at(-1).delay, 1000);
    component.destroy();
});
