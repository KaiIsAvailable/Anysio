import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
import test from 'node:test';

test('auto-height does not resize the frame model during content edits', () => {
    const source = readFileSync('resources/js/editor/init.js', 'utf8');
    const start = source.indexOf('    const resizePaper =');
    const end = source.indexOf("    editor.on('canvas:frame:load:body'", start);
    const attributes = {};
    let updates = 0;
    let refreshes = 0;
    let canvasWidth = 1000;
    const context = {
        paperUpdate: undefined,
        cancelAnimationFrame() {},
        requestAnimationFrame(callback) { callback(); return 1; },
        editor: { Canvas: {
            getFrame: () => ({
                get: key => attributes[key],
                set(values) { updates++; Object.assign(attributes, values); }
            }),
            getElement: () => ({ clientWidth: canvasWidth }),
            refresh() { refreshes++; }
        } }
    };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end) + '\nglobalThis.runResize = resizePaper;', context);
    context.runResize();
    assert.equal(updates, 1);
    for (let i = 0; i < 10; i++) context.runResize();
    assert.equal(updates, 1);
    assert.equal(refreshes, 1);
    assert.equal(attributes.height, 'auto');
    assert.equal(attributes.minHeight, 1123);
    canvasWidth = 1200;
    context.runResize();
    assert.equal(updates, 2);
    context.runResize();
    assert.equal(updates, 2);
    assert(!source.includes("editor.on('update', resizePaper)"));
    assert(!source.includes("editor.on('block:drag:start', resizePaper)"));
});

test('canvas scrolling stops at the paper end and adjusts when content shrinks', () => {
    const source = readFileSync('resources/js/editor/init.js', 'utf8');
    const start = source.indexOf('    const limitPaperScroll =');
    const end = source.indexOf('    const observePaperBounds =', start);
    let paperEnd = 2000;
    const scrollCanvas = {
        scrollTop: 1900,
        clientHeight: 600,
        getBoundingClientRect: () => ({ top: 100 })
    };
    const context = {
        scrollCanvas,
        editor: { Canvas: { getFrameEl: () => ({ parentElement: {
            getBoundingClientRect: () => ({ bottom: 100 + paperEnd - scrollCanvas.scrollTop })
        } }) } }
    };
    vm.createContext(context);
    vm.runInContext(source.slice(start, end) + '\nglobalThis.limit = limitPaperScroll;', context);
    context.limit();
    assert.equal(scrollCanvas.scrollTop, 1424);
    scrollCanvas.scrollTop = 500;
    context.limit();
    assert.equal(scrollCanvas.scrollTop, 500);
    paperEnd = 800;
    context.limit();
    assert.equal(scrollCanvas.scrollTop, 224);
    paperEnd = 300;
    context.limit();
    assert.equal(scrollCanvas.scrollTop, 0);
});
