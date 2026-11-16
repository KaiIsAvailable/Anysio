import { createBulkEditor } from './bulk-editor';

const fields = ['name', 'email', 'phone', 'ic', 'gender', 'address', 'postcode', 'city', 'state',
    'companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress',
    'bankName', 'bankCode', 'accountHolder', 'accountNumber'];
const companyFields = ['companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress'];
const bankFields = ['bankName', 'bankCode', 'accountHolder', 'accountNumber'];

export function ownerBulkEditor(initialRows, canCreate = true, draftKey = null, saved = false, firstRow = 1, initialOperation = null, progressConfig = {}, serverErrors = {}, gridConfig = {}) {
    return {
        ...createBulkEditor(initialRows, canCreate, draftKey, saved, firstRow, initialOperation, progressConfig, serverErrors, { modalPrefix: 'owner-bulk', recordSingular: 'owner', ...gridConfig }),
        makeRow(values = {}) {
            return { ...Object.fromEntries(fields.map(field => [field, ''])),
                key: 'new-' + this.nextKey++, isNew: false, changed: false, removed: false,
                accountLastFour: '', accountNumberPending: false, uploadNames: {}, existingFiles: {},
                ...values, genderSelection: values.gender ? [values.gender] : [], stateSelection: values.state ? [values.state] : [], selectedUploads: {}, previewFiles: {},
            };
        },
        isCellDisabled(row) { return row.removed; },
        hasRowData(row) {
            return fields.some(field => String(row[field] || '').trim()) || Object.values(row.uploadNames).some(Boolean);
        },
        isFieldRequired(row, field) {
            if (row.removed || (row.isNew ? !this.hasRowData(row) : !row.changed)) return false;
            if (['name', 'phone', 'gender', 'ic'].includes(field)) return true;
            if (field === 'companyName') return companyFields.some(key => String(row[key] || '').trim()) || Object.values(row.uploadNames).some(Boolean);
            if (['bankName', 'accountHolder', 'accountNumber'].includes(field)) {
                return bankFields.some(key => String(row[key] || '').trim()) && (field !== 'accountNumber' || row.accountNumberPending || !row.accountLastFour);
            }
            return false;
        },
        markChanged(row, field = null) {
            if (field) this.clearCellErrors(row, field);
            row.changed = true;
            this.draftStored = false;
            this.$nextTick(() => {
                if (['phone', 'companyPhone', 'ic', 'postcode', 'accountNumber'].includes(field)) {
                    const length = { phone: 20, companyPhone: 20, ic: 12, postcode: 10, accountNumber: 50 }[field];
                    row[field] = String(row[field] || '').replace(/\D/g, '').slice(0, length);
                }
                this.ensureEmptyRow();
                this.persistDraft();
            });
        },
        submissionRows() {
            return this.rows.flatMap((row, index) => {
                if (row.isNew ? !this.hasRowData(row) : !row.changed && !row.removed) return [];
                const action = row.isNew ? 'create' : (row.removed ? 'remove' : 'update');
                const payload = { action, id: row.isNew ? null : row.key, version: row.version || null, rowNumber: index + firstRow, uploadKey: row.key };
                if (action === 'remove') return [payload];
                for (const field of fields) payload[field] = row[field];
                payload.fileFields = Object.keys(row.uploadNames).filter(field => row.uploadNames[field]);
                return [payload];
            });
        },
        validateBeforeSave(form) {
            this.fieldErrors = {};
            for (const row of this.rows) {
                if (row.removed || (row.isNew ? !this.hasRowData(row) : !row.changed)) continue;
                for (const field of fields) {
                    if (this.isFieldRequired(row, field) && !String(row[field] || '').trim()) this.setFieldError(row, field, 'This field is required.');
                }
            }
            const nativeValid = form.checkValidity();
            const first = this.rows.find(row => this.rowErrors(row).length);
            if (first) {
                this.$nextTick(() => this.focusRowError(first, this.rowErrors(first)[0]));
                return false;
            }
            return nativeValid;
        },
    };
}
