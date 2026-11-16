export function createBulkEditor(initialRows, canCreate = true, draftKey = null, saved = false, firstRow = 1, initialOperation = null, progressConfig = {}, serverErrors = {}, gridConfig = {}) {
    return {
        closeContactList() {},
        isDraftRow(row) { return row && typeof row === 'object'; },
        operationKey: initialOperation, progressTimer: null, progressStopped: false, progress: progressConfig.initialProgress || { status: 'submitting', processed: 0, total: 0, percent: 0 },
        displayedProcessed: 0, displayedPercent: 0, progressAnimationTimer: null, completionScheduled: false,
        nextCursor: gridConfig.nextCursor || null, loadingRows: false, restoringDraft: false, rowsLoadFailed: false, pendingDraftRows: [], skipLoadScroll: false,
        rows: [], nextKey: 1, saving: false, draftStored: false, draftMessage: '', discarding: false,
        fieldErrors: {}, errorSides: {}, invalidFocusPending: false, progressFailures: 0,
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
            const submitted = this.rows.filter(row => this.isPendingRow(row));
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
        finishProgress(progressUnavailable = false) {
            if (this.progressStopped) return;
            this.progressStopped = true;
            clearTimeout(this.progressTimer);
            clearTimeout(this.progressAnimationTimer);
            this.progressAnimationTimer = null;
            this.saving = false;
            this.$dispatch('close-' + (gridConfig.modalPrefix || 'tenant-bulk') + '-saving');
            const url = progressConfig.completeUrl.replace('__KEY__', encodeURIComponent(this.operationKey));
            window.location.assign(url + (progressUnavailable ? (url.includes('?') ? '&' : '?') + 'progress_unavailable=1' : ''));
        },
        async pollProgress() {
            if (this.progressStopped || !this.operationKey) return;
            const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            const timeout = controller ? setTimeout(() => controller.abort(), 15000) : null;
            try {
                const response = await fetch(progressConfig.statusUrl.replace('__KEY__', encodeURIComponent(this.operationKey)), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', ...(controller ? { signal: controller.signal } : {}) });
                if (response.status === 401 || response.status === 419) { window.location.reload(); return; }
                if (response.status === 403 || response.status === 404) { this.finishProgress(true); return; }
                if (!response.ok) throw new Error('Progress unavailable');
                const progress = await response.json();
                if (this.progressStopped) return;
                if (!['queued', 'processing', 'succeeded', 'failed'].includes(progress.status)
                    || !Number.isFinite(progress.processed) || !Number.isFinite(progress.total) || !Number.isFinite(progress.percent)) throw new Error('Invalid progress');
                this.progressFailures = 0;
                this.progress = progress;
                if (this.progress.status === 'failed') { this.finishProgress(); return; }
                this.animateProgress();
                if (this.progress.status === 'succeeded') return;
            } catch (error) {
                if (this.progressStopped) return;
                this.progress.status = 'reconnecting';
                if (++this.progressFailures >= 8) { this.finishProgress(true); return; }
            } finally {
                clearTimeout(timeout);
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
