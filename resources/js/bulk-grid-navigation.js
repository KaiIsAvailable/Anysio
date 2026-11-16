const fieldSelector = 'input:not([type="hidden"]), select, textarea';
const registeredRoots = new WeakSet();

function editableFields(container) {
    return Array.from(container.querySelectorAll(fieldSelector)).filter(field =>
        !field.disabled && !field.readOnly && field.getClientRects().length > 0 && field.tabIndex !== -1
    );
}

export function bulkGridTarget(grid, field, { key }) {
    const row = field.closest('[data-bulk-grid-row]');
    if (!row) return null;
    if (key === 'ArrowDown' || key === 'ArrowUp') {
        const cell = field.closest('td');
        if (!row || !cell) return null;
        const rows = Array.from(grid.querySelectorAll('[data-bulk-grid-row]'));
        const step = key === 'ArrowUp' ? -1 : 1;
        for (let index = rows.indexOf(row) + step; index >= 0 && index < rows.length; index += step) {
            const target = editableFields(rows[index]).find(candidate => candidate.closest('td').cellIndex === cell.cellIndex);
            if (target) return target;
        }
        return null;
    }

    const fields = Array.from(row.querySelectorAll(fieldSelector));
    const editable = new Set(editableFields(row));
    const index = fields.indexOf(field);
    if (index === -1) return null;
    const step = key === 'ArrowLeft' ? -1 : 1;
    for (let next = index + step; next >= 0 && next < fields.length; next += step) {
        if (editable.has(fields[next])) return fields[next];
    }
    return null;
}

export function enableBulkGridNavigation(root = document) {
    if (registeredRoots.has(root)) return;
    registeredRoots.add(root);
    // Capture keeps grid movement separate from individual field keyboard behavior.
    root.addEventListener('keydown', event => {
        if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key) || event.isComposing || event.keyCode === 229 || event.shiftKey || event.ctrlKey || event.altKey || event.metaKey) return;
        const field = event.target;
        const grid = field.closest?.('[data-bulk-grid]');
        if (!grid || !field.matches(fieldSelector) || field.disabled) return;

        const target = bulkGridTarget(grid, field, event);
        event.preventDefault();
        event.stopPropagation();
        target?.focus();
        target?.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    }, true);
}
