import { createBulkEditor } from './bulk-editor';

export function tenantBulkEditor(initialRows, canCreate = true, draftKey = null, saved = false, firstRow = 1, initialOperation = null, progressConfig = {}, serverErrors = {}, gridConfig = {}) {
    return {
        ...createBulkEditor(initialRows, canCreate, draftKey, saved, firstRow, initialOperation, progressConfig, serverErrors, gridConfig),
        isDraftRow(row) { return row && typeof row === 'object' && Array.isArray(row.contacts); },
        makeRow(values = {}) {
            const row = { key: 'new-' + this.nextKey++, isNew: false, changed: false, removed: false, open: false, name: '', email: '', type: 'individual', phone: '', ic: '', passport: '', nationality: '', gender: '', occupation: '', companyName: '', ssmOld: '', ssmNew: '', companyPhone: '', companyEmail: '', companyAddress: '', bankName: '', bankCode: '', accountHolder: '', accountNumber: '', accountLastFour: '', contacts: [], uploadNames: {}, existingFiles: {}, activeContactIndex: 0, nationalityFromIc: false, ...values, typeSelection: [values.type || 'individual'], genderSelection: values.gender ? [values.gender.charAt(0).toUpperCase() + values.gender.slice(1).toLowerCase()] : [], selectedUploads: {}, previewFiles: {} };
            this.ensureContactRow(row);
            row.gender = row.genderSelection[0] || '';
            this.applyIdentityRules(row);
            return row;
        },
        applyIdentityRules(row, field = null) {
            if (row.type === 'corporate') {
                ['ic', 'passport', 'nationality', 'gender', 'occupation'].forEach(key => { row[key] = ''; });
                row.nationalityFromIc = false;
                if (row.genderSelection.length) row.genderSelection = [];
                return;
            }
            if (field === 'ic') row.ic = row.ic.replace(/\D/g, '').slice(0, 12);
            if (field === 'passport') row.passport = row.passport.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 30);
            if (row.ic) {
                row.passport = '';
                row.nationality = 'MALAYSIAN';
                row.nationalityFromIc = true;
            } else if (row.nationalityFromIc || (field === 'passport' && row.passport && row.nationality === 'MALAYSIAN')) {
                row.nationality = '';
                row.nationalityFromIc = false;
            }
        },
        isCellDisabled(row, field) {
            if (row.removed) return true;
            if (['companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress', 'companyLogo', 'ssmCertificate'].includes(field)) return row.type !== 'corporate';
            if (['ic', 'passport', 'nationality', 'gender', 'occupation', 'icPhoto'].includes(field) && row.type === 'corporate') return true;
            if (field === 'ic') return !!row.passport;
            if (field === 'passport') return !!row.ic;
            return false;
        },
        isFieldRequired(row, field) {
            if (!row.isNew && !row.changed) return false;
            if (this.isCellDisabled(row, field) || (row.isNew && !this.hasRowData(row))) return false;
            if (['name', 'phone'].includes(field)) return true;
            if (['bankName', 'accountHolder', 'accountNumber'].includes(field)) {
                const hasBankInput = ['bankName', 'bankCode', 'accountHolder', 'accountNumber'].some(key => String(row[key] || '').trim());
                return hasBankInput && (field !== 'accountNumber' || row.accountNumberPending || !row.accountLastFour);
            }
            if (row.type === 'corporate') return ['companyName', 'ssmNew', 'companyAddress'].includes(field);
            return ['nationality', 'gender'].includes(field) || (field === 'ic' && !row.passport) || (field === 'passport' && !row.ic);
        },
        hasRowData(row) {
            const fields = ['name', 'email', 'phone', 'ic', 'passport', 'nationality', 'gender', 'occupation', 'companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress', 'bankName', 'bankCode', 'accountHolder', 'accountNumber'];
            return Object.keys(row.uploadNames).some(field => !this.isCellDisabled(row, field) && row.uploadNames[field]) || row.type !== 'individual' || fields.some(field => String(row[field] || '').trim() !== '') || row.contacts.some(contact => Object.values(contact).some(value => String(value || '').trim() !== ''));
        },
        isContactFieldRequired(row, field) {
            if (row.removed || !['name', 'relationship', 'phone'].includes(field) || (row.isNew ? !this.hasRowData(row) : !row.changed)) return false;
            const entered = row.contacts.filter(contact => ['name', 'relationship', 'phone'].some(key => String(contact[key] || '').trim()));
            return entered.length === 0 || entered.includes(row.contacts[row.activeContactIndex]);
        },
        ensureContactRow(row) {
            const last = row.contacts[row.contacts.length - 1];
            if (!last || ['name', 'relationship', 'phone'].some(field => String(last[field] || '').trim() !== '')) {
                row.contacts.push({ name: '', relationship: '', phone: '' });
            }
            row.activeContactIndex = Math.max(0, Math.min(Number(row.activeContactIndex) || 0, row.contacts.length - 1));
        },
        selectContact(row, index) {
            row.activeContactIndex = Math.max(0, Math.min(index, row.contacts.length - 1));
            this.persistDraft();
        },
        contactPopoverRow() { return this.rows.find(row => row.key === this.contactPopover.key); },
        showContactList(row, anchor) {
            clearTimeout(this.contactHideTimer);
            const bounds = anchor.getBoundingClientRect();
            const width = Math.min(360, window.innerWidth - 16);
            this.contactPopover = { key: row.key, width, left: Math.max(8, Math.min(bounds.left, window.innerWidth - width - 8)), top: Math.max(8, Math.min(bounds.bottom, window.innerHeight - 264)) };
        },
        closeContactList() { clearTimeout(this.contactHideTimer); this.contactPopover.key = null; },
        queueCloseContactList() { this.contactHideTimer = setTimeout(() => this.closeContactList(), 180); },
        editContact(index) {
            const row = this.contactPopoverRow();
            if (!row) return;
            this.selectContact(row, index);
            this.closeContactList();
            this.$nextTick(() => {
                const input = Array.from(this.$refs.bulkSheet.querySelectorAll('[data-contact-field="name"]')).find(field => field.dataset.bulkRow === row.key);
                input?.focus();
            });
        },
        removeContact(row, index) {
            if (row.removed) return;
            row.contacts.splice(index, 1);
            if (index < row.activeContactIndex) row.activeContactIndex--;
            this.ensureContactRow(row);
            this.markChanged(row);
        },
        markChanged(row, field = null) {
            if (field) this.clearCellErrors(row, field === 'contactPhone' ? 'contacts.phone' : field);
            row.changed = true;
            this.draftStored = false;
            this.$nextTick(() => {
                if (field === 'phone' || field === 'companyPhone') row[field] = row[field].replace(/\D/g, '').slice(0, 20);
                if (field === 'accountNumber') row.accountNumber = row.accountNumber.replace(/\D/g, '').slice(0, 50);
                if (field === 'contactPhone') {
                    const contact = row.contacts[row.activeContactIndex];
                    contact.phone = contact.phone.replace(/\D/g, '').slice(0, 20);
                }
                this.applyIdentityRules(row, field);
                for (const error of this.rowErrors(row)) {
                    if (this.isCellDisabled(row, this.errorCellField(error.field))) delete this.fieldErrors[row.key][error.field];
                }
                if (field === 'ic' && row.ic) this.clearCellErrors(row, 'nationality');
                this.ensureContactRow(row);
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
                for (const field of ['name', 'email', 'type', 'phone', 'ic', 'passport', 'nationality', 'gender', 'occupation', 'companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress', 'bankName', 'bankCode', 'accountHolder', 'accountNumber']) payload[field] = row[field];
                payload.fileFields = Object.keys(row.uploadNames).filter(field => !this.isCellDisabled(row, field) && row.uploadNames[field]);
                payload.contacts = row.contacts.filter(contact => ['name', 'relationship', 'phone'].some(field => String(contact[field] || '').trim() !== ''));
                return [payload];
            });
        },
        validateBeforeSave(form) {
            this.fieldErrors = {};
            const requiredFields = ['name', 'phone', 'ic', 'passport', 'nationality', 'gender', 'companyName', 'ssmNew', 'companyAddress', 'bankName', 'accountHolder', 'accountNumber'];
            for (const row of this.rows) {
                if (row.removed || (row.isNew ? !this.hasRowData(row) : !row.changed)) continue;
                for (const field of requiredFields) {
                    if (this.isFieldRequired(row, field) && !String(row[field] || '').trim()) this.setFieldError(row, field, 'This field is required.');
                }
                let contactCount = 0;
                row.contacts.forEach((contact, index) => {
                    if (!['name', 'relationship', 'phone'].some(field => String(contact[field] || '').trim())) return;
                    contactCount++;
                    for (const field of ['name', 'relationship', 'phone']) {
                        if (!String(contact[field] || '').trim()) this.setFieldError(row, 'contacts.' + index + '.' + field, 'This field is required.');
                    }
                });
                if (!contactCount) {
                    for (const field of ['name', 'relationship', 'phone']) this.setFieldError(row, 'contacts.0.' + field, 'At least one emergency contact is required.');
                }
            }
            // Native checks still validate email, file reselection and other input constraints.
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
