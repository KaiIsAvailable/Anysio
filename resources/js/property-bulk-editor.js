import { createBulkEditor } from './bulk-editor';

const hierarchyFields = {
    property: ['name', 'ownerId', 'propertyType', 'address', 'postcode', 'city', 'state'],
    unit: ['parentKey', 'unitNo', 'ownerId', 'unitType', 'block', 'floor', 'sqft', 'electricityNo', 'waterNo', 'indahWaterNo'],
    room: ['parentKey', 'roomNo', 'roomType'],
};
const hierarchyRequired = { property: ['name', 'propertyType', 'address', 'postcode', 'city', 'state'], unit: ['parentKey', 'unitNo'], room: ['parentKey', 'roomNo', 'roomType'] };

export function propertyBulkEditor(initialRows, canCreate = true, draftKey = null, saved = false, firstRow = 1, initialOperation = null, progressConfig = {}, serverErrors = {}, gridConfig = {}) {
    const hierarchical = !!gridConfig.hierarchy;
    const entities = hierarchical ? ['property', 'unit', 'room'] : ['property'];
    const permissions = gridConfig.permissions || { property: { view: true, create: canCreate, edit: true, delete: true } };
    const base = createBulkEditor(initialRows, Object.values(permissions).some(value => value.create), draftKey, saved, firstRow, initialOperation, progressConfig, serverErrors, {
        modalPrefix: 'property-bulk', recordSingular: 'record', preserveDraftKeys: hierarchical,
        removalConfirmation: 'Save all changes? Removing a property or unit also removes its children.',
        draftRestoredMessage: 'Draft restored from this browser tab. Changes have not been saved.', ...gridConfig,
    });
    return {
        ...base,
        activeEntity: 'property', nextCursors: { ...(gridConfig.cursors || {}) },
        makeRow(values = {}) {
            const entity = values.entity || 'property';
            const suffix = String(values.key || '').match(/^new-.*?(\d+)$/);
            if (suffix) this.nextKey = Math.max(this.nextKey, Number(suffix[1]) + 1);
            return {
                ...Object.fromEntries((hierarchyFields[entity] || []).map(field => [field, ''])),
                key: hierarchical ? `new-${entity}-${this.nextKey++}` : 'new-' + this.nextKey++,
                entity, isNew: false, changed: false, removed: false,
                uploadNames: {}, existingFiles: {}, ...values,
                ownerIdSelection: [values.ownerId || ''], propertyTypeSelection: values.propertyType ? [values.propertyType] : [], stateSelection: values.state ? [values.state] : [],
                parentKeySelection: values.parentKey ? [values.parentKey] : [], selectedUploads: {}, previewFiles: {},
            };
        },
        isDraftRow(row) {
            const entity = row?.entity || 'property';
            return !!row && entities.includes(entity) && !!permissions[entity]?.view && (!row.isNew || !!permissions[entity]?.create);
        },
        visibleRows(entity) { return this.rows.filter(row => row.entity === entity); },
        entityHasErrors(entity) {
            return this.visibleRows(entity).some(row => !this.ancestorRemoved(row) && this.rowErrors(row).length);
        },
        firstValidationError() {
            for (const entity of entities) {
                for (const row of this.visibleRows(entity)) {
                    if (this.ancestorRemoved(row)) continue;
                    const errors = this.rowErrors(row);
                    const error = hierarchyFields[entity].map(field => errors.find(error => this.errorCellField(error.field) === field)).find(Boolean) || errors[0];
                    if (error) return { row, error };
                }
            }
            return null;
        },
        applyServerErrors(errors) {
            base.applyServerErrors.call(this, errors);
            const first = this.firstValidationError();
            if (first) this.focusRowError(first.row, first.error);
        },
        showInvalidField(event) {
            if (!this.validatingRows) return base.showInvalidField.call(this, event);
            const key = event.target.closest('[data-bulk-row-body]')?.dataset.bulkRowBody;
            const row = this.rows.find(row => row.key === key);
            const field = event.target.closest('[data-error-field]')?.dataset.errorField;
            if (row && field && !row.removed && !this.ancestorRemoved(row)) this.setFieldError(row, field, event.target.validationMessage);
        },
        switchEntity(entity) {
            this.nextCursors[this.activeEntity] = this.nextCursor;
            this.activeEntity = entity;
            this.nextCursor = this.nextCursors[entity] || null;
            this.ensureEmptyRow();
            this.$nextTick(() => { if (this.$refs?.gridViewport) this.$refs.gridViewport.scrollTop = 0; this.updateErrorVisibility(); });
        },
        async fetchRows(parameters) {
            const entity = parameters.ids ? 'all' : this.activeEntity;
            const query = new URLSearchParams({ entity });
            if (parameters.after) query.set('after', parameters.after);
            for (const id of parameters.ids || []) query.append('ids[]', id);
            const response = await fetch(gridConfig.rowsUrl + '?' + query.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) throw new Error('Records unavailable');
            const data = await response.json();
            if (!Array.isArray(data.rows)) throw new Error('Records unavailable');
            data.rows = [...data.rows, ...(data.parents || [])].filter((row, index, all) => all.findIndex(candidate => candidate.key === row.key) === index);
            return data;
        },
        ensureEmptyRow() {
            const focused = document.activeElement;
            const editedKey = focused?.closest('[data-bulk-row-body]')?.dataset.bulkRowBody;
            let added = false;
            for (const entity of entities) {
                if (!permissions[entity]?.create) continue;
                const rows = this.visibleRows(entity);
                const last = rows[rows.length - 1];
                if (!last || !last.isNew || this.hasRowData(last)) { this.rows.push(this.makeRow({ entity, isNew: true })); added = true; }
            }
            if (added && editedKey) this.$nextTick(() => requestAnimationFrame(() => {
                if (document.activeElement === focused) focused.scrollIntoView({ block: 'center', inline: 'nearest' });
            }));
        },
        isEntryRow(row) { const rows = this.visibleRows(row.entity); return row.isNew && row === rows[rows.length - 1]; },
        revealNewRow(row) {
            if (!this.isEntryRow(row) || !this.$refs?.gridViewport) return;
            this.$refs.gridViewport.scrollTop = this.$refs.gridViewport.scrollHeight;
        },
        parentRow(row) { return this.rows.find(parent => parent.key === row.parentKey && parent.entity === (row.entity === 'unit' ? 'property' : 'unit')); },
        ancestorRemoved(row) {
            const parent = this.parentRow(row);
            return !!parent?.removed || (row.entity === 'room' && !!this.parentRow(parent || {})?.removed);
        },
        parentOptions(row, entity) {
            return this.rows.filter(parent => parent.entity === entity && !parent.removed && !this.ancestorRemoved(parent) && (!parent.isNew || this.hasRowData(parent)))
                .map(parent => ({ value: parent.key, label: entity === 'property' ? (parent.name || 'Unnamed property') : `${this.parentRow(parent)?.name || 'Property'} > ${parent.unitNo || 'Unnamed unit'}` }));
        },
        canRemove(row) { return !!permissions[row.entity]?.delete; },
        isPendingRow(row) { return !this.ancestorRemoved(row) && (row.isNew ? this.hasRowData(row) : row.changed || row.removed); },
        counts() {
            const rows = this.submissionRows();
            return { created: rows.filter(row => row.action === 'create').length, updated: rows.filter(row => row.action === 'update').length, removed: rows.filter(row => row.action === 'remove').length };
        },
        isCellDisabled(row, field) { return row.removed || this.ancestorRemoved(row) || !(permissions[row.entity]?.[row.isNew ? 'create' : 'edit']) || (field === 'parentKey' && !row.isNew); },
        hasRowData(row) { return (hierarchyFields[row.entity] || []).some(field => String(row[field] ?? '').trim()); },
        isFieldRequired(row, field) { return !row.removed && (row.isNew ? this.hasRowData(row) : row.changed) && hierarchyRequired[row.entity].includes(field); },
        markChanged(row, field = null) {
            if (field) this.clearCellErrors(row, field);
            row.changed = true; this.draftStored = false;
            this.$nextTick(() => {
                if (field === 'postcode') row.postcode = String(row.postcode || '').replace(/\D/g, '').slice(0, 5);
                this.ensureEmptyRow(); this.persistDraft();
            });
        },
        remove(row) {
            if (row.isNew) {
                const removed = new Set([row.key]);
                for (let level = 0; level < 2; level++) for (const candidate of this.rows) if (candidate.isNew && removed.has(candidate.parentKey)) removed.add(candidate.key);
                this.rows = this.rows.filter(candidate => !removed.has(candidate.key));
            } else if (this.canRemove(row)) row.removed = !row.removed;
            this.ensureEmptyRow(); this.persistDraft();
        },
        submissionRows() {
            return this.rows.flatMap((row, index) => {
                if (this.ancestorRemoved(row) || (row.isNew ? !this.hasRowData(row) : !row.changed && !row.removed)) return [];
                const action = row.isNew ? 'create' : (row.removed ? 'remove' : 'update');
                const payload = { action, entity: row.entity, clientKey: row.key, id: row.isNew ? null : row.key, version: row.version || null, rowNumber: this.visibleRows(row.entity).indexOf(row) + firstRow };
                if (action !== 'remove') for (const field of hierarchyFields[row.entity]) payload[field] = row[field];
                if (action === 'remove' && row.entity !== 'property') payload.parentKey = row.parentKey;
                return [payload];
            });
        },
        focusRowError(row, error) {
            this.switchEntity(row.entity);
            const field = this.errorCellField(error.field);
            const target = hierarchyFields[row.entity].includes(field) ? error : { ...error, field: hierarchyFields[row.entity][0] };
            base.focusRowError.call(this, row, target);
        },
        validateBeforeSave(form) {
            this.fieldErrors = {};
            const submitted = this.submissionRows();
            if (submitted.length > 300) { this.setFieldError(this.rows.find(row => this.hasRowData(row)), 'name', 'Save up to 300 changed records at a time.'); }
            for (const row of this.rows) {
                if (row.removed || this.ancestorRemoved(row) || (row.isNew ? !this.hasRowData(row) : !row.changed)) continue;
                for (const field of hierarchyRequired[row.entity]) if (!String(row[field] ?? '').trim()) this.setFieldError(row, field, 'This field is required.');
                if (row.entity === 'property' && row.postcode && !/^\d{5}$/.test(row.postcode)) this.setFieldError(row, 'postcode', 'Enter a five-digit postcode.');
                if (row.entity !== 'property' && !this.parentRow(row)) this.setFieldError(row, 'parentKey', 'Choose an available parent record.');
                if (row.entity === 'unit' && !row.ownerId && !this.parentRow(row)?.ownerId) this.setFieldError(row, 'ownerId', 'Choose an owner because this property has no owner.');
                if (row.entity === 'unit' && row.floor !== '' && row.floor != null && row.floor !== 'G'
                    && (!/^[+-]?\d+$/.test(String(row.floor)) || Number(row.floor) < -2147483648 || Number(row.floor) > 2147483647)) {
                    this.setFieldError(row, 'floor', 'Enter G (Ground Floor) or a whole floor number.');
                }
            }
            let nativeValid;
            this.validatingRows = true;
            try { nativeValid = form.checkValidity(); } finally { this.validatingRows = false; }
            const first = this.firstValidationError();
            if (first) { this.focusRowError(first.row, first.error); return false; }
            return nativeValid;
        },
    };
}
