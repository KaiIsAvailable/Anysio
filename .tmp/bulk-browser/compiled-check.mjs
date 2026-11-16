window.browserErrors=[];window.addEventListener('error',e=>browserErrors.push(e.message));function createBulkEditor(initialRows, canCreate = true, draftKey = null, saved = false, firstRow = 1, initialOperation = null, progressConfig = {}, serverErrors = {}, gridConfig = {}) {
    return {
        closeContactList() {},
        isDraftRow(row) { return row && typeof row === 'object'; },
        operationKey: initialOperation, progressTimer: null, progressStopped: false, progress: progressConfig.initialProgress || { status: 'submitting', processed: 0, total: 0, percent: 0 },
        displayedProcessed: 0, displayedPercent: 0, progressAnimationTimer: null, completionScheduled: false,
        nextCursor: gridConfig.nextCursor || null, loadingRows: false, restoringDraft: false, rowsLoadFailed: false, pendingDraftRows: [], skipLoadScroll: false,
        rows: [], nextKey: 1, saving: false, draftStored: false, draftMessage: '', discarding: false,
        fieldErrors: {}, errorSides: {}, invalidFocusPending: false,
        openReceipt: false, receiptImage: '', receiptUser: '', receiptDate: '', isPdf: false,
        contactPopover: { key: null, left: 0, top: 0, width: 360 }, contactHideTimer: null,
        init() {
            this.displayedProcessed = this.progress.status === 'succeeded' ? 0 : this.progress.processed;
            this.displayedPercent = this.progress.status === 'succeeded' ? 0 : Math.min(99, this.progress.percent);
            this.rows = initialRows.map(row => this.makeRow(row));
            if (saved && draftKey) {
                try { sessionStorage.removeItem(draftKey); } catch (error) { /* Editing remains available without browser storage. */ }
            }
            this.restoreDraft();
            this.ensureEmptyRow();
            this.applyServerErrors(serverErrors);
            if (this.pendingDraftRows.length) this.$nextTick(() => this.restorePendingDrafts());
            if (this.operationKey) {
                this.saving = true;
                this.$nextTick(() => this.progress.status === 'succeeded' ? this.animateProgress() : this.pollProgress());
            }
        },
        handleGridScroll() {
            this.closeContactList();
            this.updateErrorVisibility();
            if (this.skipLoadScroll) { this.skipLoadScroll = false; return; }
            this.loadRowsNearBottom(this.$refs.gridViewport);
        },
        loadRowsNearBottom(viewport) {
            if (viewport.scrollHeight - viewport.scrollTop - viewport.clientHeight < 200) this.loadMoreRows();
        },
        revealNewRow(row) {
            if (!row.isNew || row !== this.rows[this.rows.length - 1] || !this.$refs?.gridViewport) return;
            const viewport = this.$refs.gridViewport;
            if (viewport.scrollTop + viewport.clientHeight < viewport.scrollHeight - 1) {
                this.skipLoadScroll = true;
                viewport.scrollTop = viewport.scrollHeight;
            }
        },
        async fetchRows(parameters) {
            const query = new URLSearchParams();
            if (parameters.after) query.set('after', parameters.after);
            for (const id of parameters.ids || []) query.append('ids[]', id);
            const response = await fetch(gridConfig.rowsUrl + '?' + query.toString(), {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store',
            });
            if (!response.ok) throw new Error('Tenants unavailable');
            const data = await response.json();
            if (!Array.isArray(data.rows)) throw new Error('Tenants unavailable');
            return data;
        },
        appendRows(records, drafts = []) {
            const existing = new Set(this.rows.map(row => row.key));
            const saved = new Map(drafts.map(row => [row.key, row]));
            const added = records.filter(row => !existing.has(row.key)).map(row => this.makeRow({ ...row, ...saved.get(row.key), existingFiles: row.existingFiles || {} }));
            if (!added.length) return;
            const viewport = this.$refs?.gridViewport;
            const previousHeight = viewport?.scrollHeight;
            const editingNewRow = this.rows.some(row => row.isNew && row.key === document.activeElement?.closest('[data-bulk-row-body]')?.dataset.bulkRowBody);
            const persisted = [...this.rows.filter(row => !row.isNew), ...added].sort((left, right) => left.key < right.key ? -1 : (left.key > right.key ? 1 : 0));
            this.rows = [...persisted, ...this.rows.filter(row => row.isNew)];
            this.$nextTick(() => {
                if (viewport && editingNewRow) {
                    this.skipLoadScroll = true;
                    viewport.scrollTop += viewport.scrollHeight - previousHeight;
                }
                this.updateErrorVisibility();
            });
        },
        async loadMoreRows() {
            if (!this.nextCursor || !gridConfig.rowsUrl || this.loadingRows || this.restoringDraft || this.pendingDraftRows.length || this.saving || this.progressStopped || this.rowsLoadFailed) return;
            this.loadingRows = true;
            try {
                const data = await this.fetchRows({ after: this.nextCursor });
                if (this.progressStopped) return;
                this.appendRows(data.rows);
                this.nextCursor = data.nextCursor || null;
            } catch (error) {
                if (!this.progressStopped) this.rowsLoadFailed = true;
            } finally {
                this.loadingRows = false;
            }
        },
        async restorePendingDrafts() {
            if (this.restoringDraft || this.progressStopped) return;
            this.restoringDraft = true;
            try {
                while (this.pendingDraftRows.length && !this.progressStopped) {
                    const drafts = this.pendingDraftRows.slice(0, 50);
                    const data = await this.fetchRows({ ids: drafts.map(row => row.key) });
                    if (this.progressStopped) return;
                    this.appendRows(data.rows, drafts);
                    this.pendingDraftRows.splice(0, drafts.length);
                }
                this.applyServerErrors(serverErrors);
                this.persistDraft();
            } catch (error) {
                if (!this.progressStopped) this.rowsLoadFailed = true;
            } finally {
                this.restoringDraft = false;
            }
        },
        retryLoadingRows() {
            this.rowsLoadFailed = false;
            if (this.pendingDraftRows.length) this.restorePendingDrafts();
            else this.loadMoreRows();
        },
        restoreDraft() {
            if (!draftKey) return;
            try {
                const draft = JSON.parse(sessionStorage.getItem(draftKey) || 'null');
                if (!Array.isArray(draft)) return;
                const available = new Map(this.rows.map(row => [row.key, row]));
                let restored = 0;
                for (const saved of draft) {
                    if (!this.isDraftRow(saved)) continue;
                    const accountNumberPending = !!saved.accountNumber || !!saved.accountNumberPending;
                    saved.accountNumber = '';
                    saved.accountNumberPending = accountNumberPending;
                    if (saved.isNew === true && canCreate) {
                        // Generate a fresh temporary key so restored rows cannot collide with the next empty row.
                        this.rows.push(this.makeRow({ ...saved, key: gridConfig.preserveDraftKeys ? saved.key : 'new-' + this.nextKey }));
                        restored++;
                    } else if (saved.isNew !== true && !available.has(saved.key) && gridConfig.rowsUrl) {
                        this.pendingDraftRows.push(saved);
                    } else if (saved.isNew !== true && available.has(saved.key)) {
                        Object.assign(available.get(saved.key), this.makeRow({ ...saved, existingFiles: available.get(saved.key).existingFiles }));
                        restored++;
                    }
                }
                if (restored) {
                    this.draftStored = true;
                    this.draftMessage = gridConfig.draftRestoredMessage || 'Draft restored from this browser tab. Records have not been saved. Re-enter unsaved bank numbers and reselect files.';
                }
            } catch (error) {
                this.draftMessage = 'Draft recovery is unavailable. Keep this page open until your changes are saved.';
            }
        },
        applyServerErrors(errors) {
            const submitted = this.rows.filter(row => row.isNew ? this.hasRowData(row) : row.changed || row.removed);
            for (const [path, messages] of Object.entries(errors)) {
                const match = path.match(/^(rows|uploads)\.([^.]+)\.(.+)$/);
                if (!match) continue;
                const row = match[1] === 'rows'
                    ? (submitted.find(row => row.submittedIndex === Number(match[2])) || submitted[Number(match[2])])
                    : this.rows.find(row => (row.submittedUploadKey || row.key) === match[2]);
                if (row) {
                    const field = match[3].replace(/^contacts\.(\d+)\./, (prefix, index) => 'contacts.' + (row.submittedContactIndexes?.[Number(index)] ?? index) + '.');
                    this.setFieldError(row, field, messages);
                }
            }
            this.$nextTick(() => this.updateErrorVisibility());
        },
        setFieldError(row, field, messages) {
            this.fieldErrors[row.key] = { ...this.fieldErrors[row.key], [field]: Array.isArray(messages) ? messages : [messages] };
        },
        rowErrors(row) {
            return Object.entries(this.fieldErrors[row.key] || {}).map(([field, messages]) => ({ field, messages }));
        },
        errorCellField(field) {
            if (field === 'contacts') return 'contacts.name';
            if (['version', 'id', 'action', 'rowNumber', 'uploadKey'].includes(field)) return 'name';
            return field.replace(/^contacts\.\d+\./, 'contacts.');
        },
        fieldHasErrors(row, field) {
            return this.rowErrors(row).some(error => this.errorCellField(error.field) === field);
        },
        clearCellErrors(row, field) {
            if (!field || !this.fieldErrors[row.key]) return;
            for (const error of this.rowErrors(row)) {
                const contact = error.field.match(/^contacts\.(\d+)\./);
                if (this.errorCellField(error.field) === field && (!contact || Number(contact[1]) === row.activeContactIndex)) delete this.fieldErrors[row.key][error.field];
            }
            this.$nextTick(() => this.updateErrorVisibility());
        },
        errorBody(row) {
            return Array.from(this.$refs?.gridViewport?.querySelectorAll('[data-bulk-row-body]') || []).find(body => body.dataset.bulkRowBody === row.key);
        },
        errorCell(body, error) {
            return Array.from(body?.querySelectorAll('[data-error-field]') || []).find(cell => cell.dataset.errorField === this.errorCellField(error.field));
        },
        updateErrorVisibility() {
            const viewport = this.$refs?.gridViewport;
            if (!viewport) return;
            if (!Object.values(this.fieldErrors).some(fields => Object.keys(fields).length)) {
                if (Object.keys(this.errorSides).length) this.errorSides = {};
                return;
            }
            const bounds = viewport.getBoundingClientRect();
            const sides = {};
            const bodies = new Map(Array.from(viewport.querySelectorAll('[data-bulk-row-body]')).map(body => [body.dataset.bulkRowBody, body]));
            for (const row of this.rows) {
                for (const error of this.rowErrors(row)) {
                    const rect = this.errorCell(bodies.get(row.key), error)?.getBoundingClientRect();
                    if (!rect) continue;
                    const side = rect.left < bounds.left + 36 ? 'left' : (rect.right > bounds.right - 75 ? 'right' : null);
                    if (side) { sides[row.key] ||= {}; sides[row.key][side] ||= error; }
                }
            }
            this.errorSides = sides;
        },
        focusRowError(row, error) {
            const contact = error.field.match(/^contacts\.(\d+)\./);
            if (contact) this.selectContact(row, Number(contact[1]));
            this.$nextTick(() => {
                const cell = this.errorCell(this.errorBody(row), error);
                if (!cell) return;
                cell.scrollIntoView({ block: 'center', inline: 'center' });
                const input = cell.querySelector('input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), button:not(:disabled)');
                input?.focus({ preventScroll: true });
                this.updateErrorVisibility();
            });
        },
        showInvalidField(event) {
            const body = event.target.closest('[data-bulk-row-body]');
            const row = this.rows.find(row => row.key === body?.dataset.bulkRowBody);
            let field = event.target.closest('[data-error-field]')?.dataset.errorField;
            if (!row || !field) return;
            if (field.startsWith('contacts.')) field = 'contacts.' + row.activeContactIndex + '.' + field.split('.')[1];
            this.setFieldError(row, field, event.target.validationMessage);
            if (!this.invalidFocusPending) {
                this.invalidFocusPending = true;
                this.$nextTick(() => { this.focusRowError(row, { field }); this.invalidFocusPending = false; });
            }
        },
        persistDraft() {
            if (!draftKey || this.discarding) return;
            try {
                const pending = this.rows.filter(row => row.isNew ? this.hasRowData(row) : row.changed || row.removed).map(({ previewFiles, selectedUploads, existingFiles, accountNumber, ...row }) => ({ ...row, accountNumberPending: !!accountNumber || !!row.accountNumberPending }));
                if (pending.length || this.pendingDraftRows.length) sessionStorage.setItem(draftKey, JSON.stringify([...pending, ...this.pendingDraftRows.map(({ accountNumber, ...row }) => ({ ...row, accountNumberPending: !!accountNumber || !!row.accountNumberPending }))]));
                else sessionStorage.removeItem(draftKey);
                this.draftStored = true;
                this.draftMessage = pending.length ? 'Draft kept in this browser tab. Records have not been saved.' : '';
            } catch (error) {
                this.draftStored = false;
                this.draftMessage = 'Draft storage is unavailable. Keep this page open to retain your changes.';
            }
        },
        destroy() {
            this.progressStopped = true;
            clearTimeout(this.contactHideTimer);
            clearTimeout(this.progressTimer);
            clearTimeout(this.progressAnimationTimer);
            for (const row of this.rows) for (const preview of Object.values(row.previewFiles)) URL.revokeObjectURL(preview.url);
        },
        ensureEmptyRow() {
            if (!canCreate) return;
            const last = this.rows[this.rows.length - 1];
            if (last && last.isNew && !this.hasRowData(last)) return;
            const focused = document.activeElement;
            const editingEntry = last?.isNew && focused?.closest('[data-bulk-row-body]')?.dataset.bulkRowBody === last.key;
            this.rows.push(this.makeRow({ isNew: true }));
            if (editingEntry) {
                this.$nextTick(() => requestAnimationFrame(() => {
                    if (document.activeElement !== focused || !this.$refs?.gridViewport) return;
                    const viewport = this.$refs.gridViewport;
                    const previousTop = viewport.scrollTop;
                    focused.scrollIntoView({ block: 'center', inline: 'nearest' });
                    if (viewport.scrollTop !== previousTop) this.skipLoadScroll = true;
                }));
            }
        },
        isEntryRow(row) { return row.isNew && row === this.rows[this.rows.length - 1]; },
        isPendingRow(row) { return row.isNew ? this.hasRowData(row) : row.changed || row.removed; },
        uploadPreview(row, field) {
            return row.selectedUploads[field] ? (row.previewFiles[field] || null) : (row.existingFiles[field] || null);
        },
        previewUpload(row, field) {
            const preview = this.uploadPreview(row, field);
            if (!preview) return;
            this.receiptImage = preview.url;
            this.receiptUser = [row.name, preview.name].filter(Boolean).join(' - ');
            this.receiptDate = '';
            this.isPdf = preview.isPdf;
            this.openReceipt = true;
        },
        releaseUploadPreview(row, field) {
            if (row.previewFiles[field]) URL.revokeObjectURL(row.previewFiles[field].url);
            delete row.previewFiles[field];
        },
        setUpload(row, field, input) {
            const file = input.files?.[0];
            this.releaseUploadPreview(row, field);
            if (file) {
                row.uploadNames[field] = file.name;
                row.selectedUploads[field] = true;
                if (['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'].includes(file.type)) {
                    row.previewFiles[field] = { url: URL.createObjectURL(file), name: file.name, isPdf: file.type === 'application/pdf' };
                }
            } else {
                delete row.uploadNames[field];
                delete row.selectedUploads[field];
            }
            this.markChanged(row);
        },
        clearUpload(row, field, input) {
            input.value = '';
            this.releaseUploadPreview(row, field);
            delete row.uploadNames[field];
            delete row.selectedUploads[field];
            this.markChanged(row);
        },
        remove(row) {
            if (row.isNew) {
                for (const field of Object.keys(row.previewFiles)) this.releaseUploadPreview(row, field);
                this.rows = this.rows.filter(item => item.key !== row.key);
            }
            else row.removed = !row.removed;
            this.ensureEmptyRow();
            this.persistDraft();
        },
        counts() {
            return { created: this.rows.filter(row => row.isNew && this.hasRowData(row)).length, updated: this.rows.filter(row => !row.isNew && row.changed && !row.removed).length, removed: this.rows.filter(row => row.removed).length };
        },
        scrollHorizontally(event) {
            if (!event.shiftKey && !event.ctrlKey && event.deltaY > 0) this.loadRowsNearBottom(event.currentTarget);
            if (!event.shiftKey || event.ctrlKey) return;
            const container = event.currentTarget;
            if (container.scrollWidth <= container.clientWidth) return;
            const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
            const scale = event.deltaMode === 1 ? 16 : (event.deltaMode === 2 ? container.clientWidth : 1);
            event.preventDefault();
            container.scrollLeft += delta * scale;
        },
        prepareSubmission(event) {
            if (this.saving || this.loadingRows || this.restoringDraft || this.pendingDraftRows.length || !this.hasChanges()) { event.preventDefault(); return; }
            if (!this.validateBeforeSave(event.currentTarget)) { event.preventDefault(); return; }
            if (this.counts().removed && !confirm(gridConfig.removalConfirmation || ('Save changes and mark ' + this.counts().removed + ' ' + (gridConfig.recordSingular || 'tenant') + '(s) inactive?'))) { event.preventDefault(); return; }
            let submittedIndex = 0;
            for (const row of this.rows) {
                if (!this.isPendingRow(row)) continue;
                row.submittedIndex = submittedIndex++;
                row.submittedUploadKey = row.key;
                row.submittedContactIndexes = (row.contacts || []).flatMap((contact, index) => ['name', 'relationship', 'phone'].some(field => String(contact[field] || '').trim() !== '') ? [index] : []);
            }
            this.persistDraft();
            event.currentTarget.querySelector('[name=rows_json]').value = JSON.stringify(this.submissionRows());
            this.saving = true;
            this.progress = { status: 'submitting', processed: 0, total: this.submissionRows().length, percent: 0 };
        },
        animateProgress() {
            if (this.progressStopped || this.progressAnimationTimer !== null || this.completionScheduled) return;
            const remainingSteps = Math.max(this.progress.processed - this.displayedProcessed, this.progress.percent - this.displayedPercent, 1);
            const stepDelay = Math.max(4, Math.min(10, 800 / remainingSteps));
            const tick = () => {
                this.progressAnimationTimer = null;
                if (this.progressStopped) return;
                const targetCount = this.progress.processed;
                const targetPercent = this.progress.status === 'succeeded' ? 100 : Math.min(99, this.progress.percent);
                const reducedMotion = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
                if (reducedMotion) {
                    this.displayedProcessed = targetCount;
                    this.displayedPercent = targetPercent;
                } else {
                    if (this.displayedProcessed < targetCount) this.displayedProcessed++;
                    const countPercent = this.progress.total ? Math.floor(this.displayedProcessed * 100 / this.progress.total) : 0;
                    if (this.displayedPercent < Math.min(targetPercent, countPercent)) this.displayedPercent++;
                }
                const remaining = Math.max(targetCount - this.displayedProcessed, targetPercent - this.displayedPercent);
                if (remaining > 0) {
                    this.progressAnimationTimer = setTimeout(tick, stepDelay);
                } else if (this.progress.status === 'succeeded') {
                    this.completionScheduled = true;
                    this.progressAnimationTimer = setTimeout(() => this.finishProgress(), 240);
                }
            };
            tick();
        },
        finishProgress() {
            if (this.progressStopped) return;
            clearTimeout(this.progressAnimationTimer);
            this.progressAnimationTimer = null;
            this.saving = false;
            this.$dispatch('close-' + (gridConfig.modalPrefix || 'tenant-bulk') + '-saving');
            window.location.assign(progressConfig.completeUrl.replace('__KEY__', encodeURIComponent(this.operationKey)));
        },
        async pollProgress() {
            if (this.progressStopped || !this.operationKey) return;
            try {
                const response = await fetch(progressConfig.statusUrl.replace('__KEY__', encodeURIComponent(this.operationKey)), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                if (response.status === 401 || response.status === 419) { window.location.reload(); return; }
                if (!response.ok) throw new Error('Progress unavailable');
                const progress = await response.json();
                if (this.progressStopped) return;
                this.progress = progress;
                if (this.progress.status === 'failed') { this.finishProgress(); return; }
                this.animateProgress();
                if (this.progress.status === 'succeeded') return;
            } catch (error) {
                if (this.progressStopped) return;
                this.progress.status = 'reconnecting';
            }
            if (!this.progressStopped) this.progressTimer = setTimeout(() => this.pollProgress(), 1500);
        },
        resumeFromHistory(event) {
            if (!event.persisted) return;
            this.discarding = true;
            window.location.reload();
        },
        discardDraft() {
            try { if (draftKey) sessionStorage.removeItem(draftKey); } catch (error) { /* Storage may be disabled. */ }
            this.discarding = true;
            this.draftStored = true;
            window.location.reload();
        },
        hasChanges() { return !this.discarding && Object.values(this.counts()).some(count => count > 0); }
    };
}



function tenantBulkEditor(initialRows, canCreate = true, draftKey = null, saved = false, firstRow = 1, initialOperation = null, progressConfig = {}, serverErrors = {}, gridConfig = {}) {
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



const fields = ['name', 'email', 'phone', 'ic', 'gender', 'address', 'postcode', 'city', 'state',
    'companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress',
    'bankName', 'bankCode', 'accountHolder', 'accountNumber'];
const companyFields = ['companyName', 'ssmOld', 'ssmNew', 'companyPhone', 'companyEmail', 'companyAddress'];
const bankFields = ['bankName', 'bankCode', 'accountHolder', 'accountNumber'];

function ownerBulkEditor(initialRows, canCreate = true, draftKey = null, saved = false, firstRow = 1, initialOperation = null, progressConfig = {}, serverErrors = {}, gridConfig = {}) {
    return {
        ...createBulkEditor(initialRows, canCreate, draftKey, saved, firstRow, initialOperation, progressConfig, serverErrors, { modalPrefix: 'owner-bulk', recordSingular: 'owner', ...gridConfig }),
        makeRow(values = {}) {
            return { ...Object.fromEntries(fields.map(field => [field, ''])),
                key: 'new-' + this.nextKey++, isNew: false, changed: false, removed: false,
                accountLastFour: '', accountNumberPending: false, uploadNames: {}, existingFiles: {},
                ...values, genderSelection: values.gender ? [values.gender] : [], selectedUploads: {}, previewFiles: {},
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



const hierarchyFields = {
    property: ['name', 'ownerId', 'propertyType', 'address', 'postcode', 'city', 'state'],
    unit: ['parentKey', 'unitNo', 'ownerId', 'unitType', 'block', 'floor', 'sqft', 'electricityNo', 'waterNo', 'indahWaterNo'],
    room: ['parentKey', 'roomNo', 'roomType'],
};
const hierarchyRequired = { property: ['name', 'propertyType', 'address', 'postcode', 'city', 'state'], unit: ['parentKey', 'unitNo'], room: ['parentKey', 'roomNo', 'roomType'] };

function propertyBulkEditor(initialRows, canCreate = true, draftKey = null, saved = false, firstRow = 1, initialOperation = null, progressConfig = {}, serverErrors = {}, gridConfig = {}) {
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
                ownerIdSelection: [values.ownerId || ''], propertyTypeSelection: values.propertyType ? [values.propertyType] : [],
                parentKeySelection: values.parentKey ? [values.parentKey] : [], selectedUploads: {}, previewFiles: {},
            };
        },
        isDraftRow(row) {
            const entity = row?.entity || 'property';
            return !!row && entities.includes(entity) && !!permissions[entity]?.view && (!row.isNew || !!permissions[entity]?.create);
        },
        visibleRows(entity) { return this.rows.filter(row => row.entity === entity); },
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
        focusRowError(row, error) { this.switchEntity(row.entity); base.focusRowError.call(this, row, error); },
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
            }
            const nativeValid = form.checkValidity();
            const first = this.rows.find(row => this.rowErrors(row).length);
            if (first) { this.$nextTick(() => this.focusRowError(first, this.rowErrors(first)[0])); return false; }
            return nativeValid;
        },
    };
}

const fieldSelector = 'input:not([type="hidden"]), select, textarea';
const registeredRoots = new WeakSet();

function editableFields(container) {
    return Array.from(container.querySelectorAll(fieldSelector)).filter(field =>
        !field.disabled && !field.readOnly && field.getClientRects().length > 0 && field.tabIndex !== -1
    );
}

function bulkGridTarget(grid, field, { key }) {
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

function enableBulkGridNavigation(root = document) {
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

enableBulkGridNavigation();document.addEventListener('alpine:init',()=>{Alpine.data('ownerBulkEditor',ownerBulkEditor);Alpine.data('tenantBulkEditor',tenantBulkEditor);Alpine.data('propertyBulkEditor',propertyBulkEditor);});