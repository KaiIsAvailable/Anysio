import { test } from 'node:test';
import assert from 'node:assert/strict';
import { enableSessionDraft } from '../resources/js/editor/session-draft.js';

test('session canvas draft follows reload, navigation and validation lifecycle', () => {
    const storage = new Map();
    const listeners = new Map();
    let navigationType = 'navigate';
    Object.defineProperty(globalThis, 'performance', {
        configurable: true,
        value: { getEntriesByType: () => [{ type: navigationType }] }
    });
    globalThis.sessionStorage = {
        getItem: key => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
        removeItem: key => storage.delete(key)
    };
    globalThis.window = {
        addEventListener: (name, handler) => listeners.set(name, handler),
        removeEventListener: name => listeners.delete(name)
    };
    const setup = (validation = false) => {
        let project = { content: 'saved template' };
        const events = new Map();
        const editor = {
            getProjectData: () => project,
            loadProjectData: data => { project = data; },
            on: (name, handler) => events.set(name, handler),
            off: name => events.delete(name)
        };
        enableSessionDraft(editor, 'template:1', validation);
        return { editor, events };
    };

    let visit = setup();
    visit.editor.loadProjectData({ content: 'unsaved canvas' });
    visit.events.get('update')();
    assert.equal(JSON.parse(storage.get('template:1')).content, 'unsaved canvas');
    listeners.get('pagehide')();
    navigationType = 'reload';
    visit = setup();
    assert.equal(visit.editor.getProjectData().content, 'unsaved canvas');

    listeners.get('pagehide')();
    navigationType = 'navigate';
    visit = setup();
    assert.equal(visit.editor.getProjectData().content, 'saved template');
    assert.equal(storage.has('template:1'), false);

    visit.editor.loadProjectData({ content: 'discard on back' });
    visit.events.get('update')();
    listeners.get('pageshow')({ persisted: true });
    assert.equal(visit.editor.getProjectData().content, 'saved template');
    assert.equal(storage.has('template:1'), false);

    storage.set('template:1', JSON.stringify({ content: 'stale draft' }));
    navigationType = 'reload';
    visit = setup(true);
    assert.equal(visit.editor.getProjectData().content, 'saved template');

    globalThis.sessionStorage.setItem = () => { throw new Error('Quota exceeded'); };
    assert.doesNotThrow(() => visit.events.get('update')());
    visit.events.get('destroy')();
    assert.equal(listeners.size, 0);
});
