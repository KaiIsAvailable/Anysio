import test from 'node:test';
import assert from 'node:assert/strict';
import { bulkGridTarget, enableBulkGridNavigation } from '../../resources/js/bulk-grid-navigation.js';

function fixture() {
    const grid = { rows: [], querySelectorAll(selector) { return selector === '[data-bulk-grid-row]' ? this.rows : this.rows.flatMap(row => row.fields); } };
    for (let i = 0; i < 3; i++) {
        const row = { fields: [], querySelectorAll() { return this.fields; } };
        for (let column = 0; column < 3; column++) {
            const cell = { cellIndex: column };
            row.fields.push({ disabled: false, readOnly: false, tabIndex: 0, getClientRects: () => [1], matches: () => true,
                closest(selector) { return selector === 'td' ? cell : selector === '[data-bulk-grid-row]' ? row : grid; },
                focus() { this.focused = true; }, scrollIntoView() { this.scrolled = true; },
            });
        }
        grid.rows.push(row);
    }
    return grid;
}

test('Left and Right move within the row, skipping disabled fields', () => {
    const grid = fixture();
    const [first, disabled, third] = grid.rows[0].fields;
    disabled.disabled = true;
    assert.equal(bulkGridTarget(grid, first, { key: 'ArrowRight' }), third);
    assert.equal(bulkGridTarget(grid, third, { key: 'ArrowLeft' }), first);
    assert.equal(bulkGridTarget(grid, third, { key: 'ArrowRight' }), null);
    first.readOnly = true;
    assert.equal(bulkGridTarget(grid, first, { key: 'ArrowRight' }), third);
});

test('Up and Down move to the same column in the next available row', () => {
    const grid = fixture();
    const first = grid.rows[0].fields[1];
    assert.equal(bulkGridTarget(grid, grid.rows[1].fields[1], { key: 'ArrowUp' }), first);
    assert.equal(bulkGridTarget(grid, first, { key: 'ArrowDown' }), grid.rows[1].fields[1]);
    grid.rows[1].fields.forEach(field => { field.disabled = true; });
    assert.equal(bulkGridTarget(grid, first, { key: 'ArrowDown' }), grid.rows[2].fields[1]);
    assert.equal(bulkGridTarget(grid, grid.rows[2].fields[1], { key: 'ArrowDown' }), null);
});

test('arrow navigation handles movement and edges, preserves composition and native Tab', () => {
    const grid = fixture();
    let handler;
    let registrations = 0;
    const root = { addEventListener(type, callback, capture) { assert.equal(capture, true); registrations++; handler = callback; } };
    enableBulkGridNavigation(root);
    enableBulkGridNavigation(root);
    assert.equal(registrations, 1);
    const event = { key: 'ArrowRight', target: grid.rows[0].fields[0], preventDefault() { this.prevented = true; }, stopPropagation() { this.stopped = true; } };
    handler(event);
    assert.equal(event.prevented, true);
    assert.equal(event.stopped, true);
    assert.equal(grid.rows[0].fields[1].focused, true);
    const composing = { ...event, prevented: false, isComposing: true };
    handler(composing);
    assert.equal(composing.prevented, false);
    const leaving = { ...event, key: 'Tab', prevented: false };
    handler(leaving);
    assert.equal(leaving.prevented, false);
    const lastEnter = { ...event, target: grid.rows[2].fields[2], prevented: false };
    handler(lastEnter);
    assert.equal(lastEnter.prevented, true);
});
