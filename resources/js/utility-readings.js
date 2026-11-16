import { tariffCalculation, scaledDecimal } from './utility-decimal.js';

const draftFields = ['previous', 'previousDate', 'current', 'rate', 'revision', 'eventId', 'reprice', 'billAmount', 'billReference'];
const draftFingerprint = row => JSON.stringify(draftFields.map(field => field === 'reprice' ? !!row[field] : String(row[field] ?? '')));

export function scaledReading(value, places) {
    const text = String(value ?? '').trim();
    if (!new RegExp(`^(?:\\d+(?:\\.\\d{0,${places}})?|\\.\\d{1,${places}})$`).test(text)) return null;
    const [whole, fraction = ''] = text.split('.');
    return BigInt(whole || '0') * 10n ** BigInt(places) + BigInt(fraction.padEnd(places, '0'));
}

export function resolveReadingTariff(plan, row, date) {
    if (!plan) return row.tariff;
    if (!row.reprice && row.savedTariff?.tariff_version_id) return row.savedTariff;
    const assignments = plan.assignments.filter(a => a.service_id === row.id);
    let id = plan.default;
    for (const assignment of assignments) if (assignment.effective_from <= date) id = assignment.tariff_id;
    const versions = plan.versions[id] || [];
    const tariff = versions.find(v => v.effective_date && v.effective_date <= date);
    if (!tariff) return { ...(versions[0] || {}), billable: false, review_reason: 'No tariff is effective for this reading date.' };
    if (row.previousDate && (tariff.effective_date > row.previousDate || assignments.some(a => a.effective_from > row.previousDate && a.effective_from <= date))) {
        return { ...tariff, billable: false, review_reason: 'The tariff changes inside this interval. Intermediate readings or an actual provider bill are required.' };
    }
    return tariff;
}

export function calculateWaterReading(row) {
    const previous = scaledReading(row.previous, 3), current = scaledReading(row.current, 3);
    const hasPrevious = row.previous !== '' && row.previous !== null;
    const hasCurrent = row.current !== '' && row.current !== null;
    let usage = null, formatted = null;
    if (hasPrevious && hasCurrent) {
        if (previous === null || current === null) return { usage: null, amount: null, error: 'Enter valid readings (up to 3 decimals).' };
        const event = (row.events || []).find(e => e.id === row.eventId);
        if (event) {
            if (event.type === 'rollover') {
                const capacity = scaledReading(event.capacity, 3);
                if (capacity === null || capacity <= previous || capacity <= current || current >= previous) return { usage: null, amount: null, error: 'Check rollover capacity and readings.' };
                usage = capacity - previous + current;
            } else {
                const closing = scaledReading(event.old_closing, 3), opening = scaledReading(event.new_opening, 3);
                if (closing === null || opening === null || closing < previous || current < opening) return { usage: null, amount: null, error: 'Check meter replacement/reset readings.' };
                usage = closing - previous + current - opening;
            }
        } else usage = current - previous;
        if (usage < 0n) return { usage: null, amount: null, error: 'This reading can be saved. It is lower than the previous reading and needs review before invoicing.' };
        formatted = `${usage / 1000n}.${String(usage % 1000n).padStart(3, '0')}`;
    }
    if (row.rate !== '' && row.rate !== null) {
        if (usage === null) return { usage: formatted, amount: null, error: '' };
        const rate = scaledReading(row.rate, 6);
        if (rate === null) return { usage: formatted, amount: null, error: 'Enter a valid rate (up to 6 decimals).' };
        const cents = (usage * rate + 5000000n) / 10000000n;
        if (cents > 2147483647n) return { usage: formatted, amount: null, error: 'Calculated amount exceeds the supported limit.' };
        return { usage: formatted, amount: Number(cents), error: '' };
    }
    if (!row.tariff) return { usage: formatted, amount: null, error: '' };
    if (row.tariff.billable === false) return { usage: formatted, amount: null, error: row.tariff.review_reason || 'Official reference is unverified; select a contractual landlord tariff or request verification.' };
    const result = tariffCalculation(row.tariff, formatted, row.billAmount);
    return { usage: formatted, amount: result?.amount ?? null, error: result || formatted === null ? '' : 'Check the assigned water tariff.' };
}

export function utilityReadings(config = {}) {
    return {
        rows: [], rowsById: {}, hierarchy: config.hierarchy || [], previousDate: '', selectedIds: [],
        selectedProperty: config.building || '', manageMeters: false,
        draftRetained: false,
        readingDate: config.readingDate || '', commonRate: '', flatOverrideConfirmed: false, submitting: false,
        init() {
            if (this.selectedProperty && !this.buildingOptions.some(option => option.value === this.selectedProperty)) this.selectedProperty = '';
            this.rows = (config.rows || []).map(row => ({ ...row }));
            this.rows.forEach(row => { row.original = this.signature(row); });
            this.rowsById = Object.fromEntries(this.rows.map(row => [row.id, row]));
            this.selectedIds = (config.selectedIds || []).filter(id => this.rowsById[id]);
            this.restoreDraft();
            this.restoreRows(config.oldRows);
            if (config.draftKey) this.persistDraft();
        },
        restoreRows(entries) {
            if (!Array.isArray(entries)) return;
            for (const old of entries) {
                const row = Object.hasOwn(this.rowsById, old?.id) ? this.rowsById[old.id] : null;
                // Retain the original revision: restored drafts cannot overwrite newer server data.
                if (row && !row.locked) for (const field of draftFields) {
                    if (Object.hasOwn(old, field)) row[field] = old[field] ?? '';
                }
            }
        },
        draftStorage() {
            return config.storage ?? (typeof window !== 'undefined' ? window.sessionStorage : null);
        },
        openInvoicePreview() {
            const url = new URL(window.location.href);
            if (!url.searchParams.has('preview_invoices')) return;
            url.searchParams.delete('preview_invoices');
            window.history.replaceState(window.history.state, '', url.toString());
            const navigation = window.performance?.getEntriesByType?.('navigation')?.[0]?.type;
            if (navigation === 'reload' || navigation === 'back_forward') return;
            this.$nextTick(() => this.$dispatch('open-water-invoice-preview'));
        },
        restoreDraft() {
            if (!config.draftKey) return;
            try {
                const cached = JSON.parse(this.draftStorage()?.getItem(config.draftKey) || 'null');
                if (cached?.version !== 1 || !Array.isArray(cached.rows)) return;
                const saved = config.savedDraft && config.savedDraft.scope === config.draftScope
                    && config.savedDraft.readingDate === cached.readingDate && Array.isArray(config.savedDraft.rows)
                    ? config.savedDraft.rows : [];
                const remaining = cached.rows.filter(row => !saved?.some(entry => entry.id === row.id && draftFingerprint(entry) === draftFingerprint(row)));
                if (!remaining.length && (cached.rows.length || saved.length)) { this.draftStorage()?.removeItem(config.draftKey); return; }
                this.restoreRows(remaining);
                if (!Array.isArray(config.oldRows) && typeof cached.readingDate === 'string') this.readingDate = cached.readingDate;
                if (!config.building && this.buildingOptions.some(option => option.value === cached.property)) this.selectedProperty = cached.property;
            } catch {
                this.draftRetained = false;
            }
        },
        persistDraft() {
            if (!config.draftKey) return false;
            try {
                const storage = this.draftStorage();
                if (!storage) return false;
                const rows = this.rows.filter(row => !row.locked && (this.isDirty(row) || this.signature(row) !== row.original)).map(row => Object.fromEntries([
                    ['id', row.id], ...draftFields.filter(field => Object.hasOwn(row, field)).map(field => [field, row[field] ?? '']),
                ]));
                const hasDraft = rows.length > 0 || this.readingDate !== (config.readingDate || '');
                if (hasDraft) storage.setItem(config.draftKey, JSON.stringify({ version: 1, rows,
                    readingDate: this.readingDate, property: this.selectedProperty }));
                else storage.removeItem(config.draftKey);
                this.draftRetained = hasDraft;
                return true;
            } catch {
                this.draftRetained = false;
                return false;
            }
        },
        discardDraft() {
            for (const original of config.rows || []) {
                const row = this.rowsById[original.id];
                if (row) for (const field of draftFields) {
                    if (Object.hasOwn(original, field)) row[field] = original[field];
                    else delete row[field];
                }
            }
            this.readingDate = config.readingDate || '';
            this.persistDraft();
        },
        signature(row) { return JSON.stringify([row.previous, row.previousDate, row.current, row.rate, row.eventId, row.reprice, row.billAmount, row.billReference]); },
        isDirty(row) {
            if (row.locked) return false;
            // Applying shared dates/rates must not create blank readings for every meter.
            const original = JSON.parse(row.original);
            if (row.status === 'Not entered' && row.current === '' && row.previous === original[0] && !row.billAmount && !row.eventId && !row.reprice) return false;
            return row.rebased || this.signature(row) !== row.original;
        },
        get hasChanges() { return this.rows.some(row => this.isDirty(row)); },
        readingRow(node) { return this.rowsById[node.rowId] || null; },
        get buildingOptions() {
            return this.hierarchy.filter(node => node.level === 0).map(node => ({ value: node.key.replace(/^property-/, ''), label: node.label }));
        },
        get visibleHierarchy() {
            if (!this.selectedProperty) return this.hierarchy;
            let building = '';
            return this.hierarchy.filter(node => {
                if (node.level === 0) building = node.key.replace(/^property-/, '');
                return building === this.selectedProperty;
            });
        },
        get visibleRows() {
            const ids = new Set(this.visibleHierarchy.map(node => node.rowId));
            return this.hierarchy.length ? this.rows.filter(row => ids.has(row.id)) : this.rows;
        },
        meterProgress(rows) {
            const meters = rows.filter(row => !['fixed', 'actual_bill'].includes(this.profile(row)?.calculation));
            const entered = meters.filter(row => row.current !== '' && row.current !== null).length;
            return { total: meters.length, entered, remaining: meters.length - entered,
                percent: meters.length ? Math.round(entered * 100 / meters.length) : 0 };
        },
        get progress() { return this.meterProgress(this.rows); },
        get propertyProgress() { return this.meterProgress(this.visibleRows); },
        get changedCount() { return this.rows.filter(row => this.isDirty(row)).length; },
        selectProperty(value) {
            if (value !== this.selectedProperty) { this.selectedProperty = value; this.selectedIds = []; }
        },
        get selectedMeters() { return this.rows.filter(row => this.selectedIds.includes(row.id)); },
        get allSelected() { return this.visibleRows.length > 0 && this.visibleRows.every(row => this.selectedIds.includes(row.id)); },
        toggleAllMeters(checked) { this.selectedIds = checked ? this.visibleRows.map(row => row.id) : []; },
        applyRate() { this.rows.filter(row => !row.locked && !['fixed', 'actual_bill'].includes(this.profile(row)?.calculation)).forEach(row => { row.rate = this.commonRate; }); },
        applyPreviousDate() {
            this.visibleRows.filter(row => !row.locked && !row.previousDateLocked).forEach(row => { row.previousDate = this.previousDate; });
        },
        nextField(event) {
            const fields = [...event.target.closest('table').querySelectorAll('[data-reading-field]')]
                .filter(field => !field.disabled && !field.readOnly);
            const index = fields.indexOf(event.target);
            if (index >= 0) fields[index + 1]?.focus();
        },
        profile(row) { return resolveReadingTariff(config.tariffPlan, row, this.readingDate); },
        needsRecalculation(row) {
            if (row.locked || !row.recordId) return false;
            if (row.reprice) return true;
            const assigned = resolveReadingTariff(config.tariffPlan, { ...row, reprice: true }, this.readingDate);
            if (!assigned?.tariff_version_id || !assigned.billable) return false;
            return !row.savedTariff?.billable || row.calculationNeedsRepair
                || row.savedTariff.tariff_version_id !== assigned.tariff_version_id;
        },
        calculation(row) { return calculateWaterReading({ ...row, tariff: this.profile(row) }); },
        error(row) {
            const calculation = this.calculation(row);
            if (calculation.error) return calculation.error;
            if (row.previousDate && row.previousDate > this.readingDate) return 'Check the previous reading date.';
            return '';
        },
        entryError(row) {
            if (this.manageMeters) return this.error(row);
            const reading = calculateWaterReading({ ...row, tariff: null, rate: '' });
            if (reading.error) return reading.error;
            return row.current !== '' && row.current !== null && row.previousDate && row.previousDate >= this.readingDate
                ? 'The current reading date must be after the previous reading date.' : '';
        },
        get ready() { return this.rows.filter(row => !row.locked && this.calculation(row).amount !== null && !this.error(row)).length; },
        get total() { return this.rows.reduce((sum, row) => sum + (this.calculation(row).amount ?? 0), 0); },
        money(cents) { return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(cents / 100); },
        formatReadingDate(value) { return value ? value.split('-').reverse().join('/') : '—'; },
        get payload() {
            const visibleIds = new Set(this.visibleRows.map(row => row.id));
            return JSON.stringify(this.rows.filter(row => {
                const method = this.profile(row)?.calculation;
                const entered = (row.current !== '' && row.current !== null) || (row.previous !== '' && row.previous !== null) || method === 'fixed' || (method === 'actual_bill' && row.billAmount !== '' && row.billAmount != null);
                const visible = visibleIds.has(row.id);
                const valuesChanged = this.isDirty(row) && (row.rebased || this.signature(row) !== row.original);
                const automaticFixedCharge = method === 'fixed' && row.status === 'Not entered';
                return !row.locked && entered && (valuesChanged || (visible && (this.isDirty(row) || row.needsSave || automaticFixedCharge)));
            }).map(({ id, previous, previousDate, current, rate, revision, eventId = '', reprice = false, billAmount = '', billReference = '' }) =>
                ({ id, previous, previousDate, current, rate, revision, eventId, reprice, billAmount, billReference })));

        },
    };
}

export function utilityAllocationEditor(sourceAmount, from, to, leases, submeters, initialRows = [], initialMethod = 'equal') {
    return {
        method: initialMethod, rows: initialRows.length ? initialRows : [], leases, submeters,
        addVacancy() { this.rows.push({ lease_id: '', weight: '1', amount: '', from_date: from, to_date: to, submeter_reading_id: '' }); },
        addLease() { this.rows.push({ lease_id: '', weight: '1', amount: '', from_date: from, to_date: to, submeter_reading_id: '' }); },
        useAllLeases() { this.rows = leases.map(lease => ({ lease_id: lease.id, weight: '1', amount: '', from_date: lease.from, to_date: lease.to, submeter_reading_id: '' })); },
        get result() {
            const keys = this.rows.map(row => row.lease_id || 'landlord');
            if (!keys.length || new Set(keys).size !== keys.length) return null;
            let weights = this.rows.map(row => {
                if (this.method === 'equal') return 1n;
                if (this.method === 'occupants') {
                    if (!/^\d+$/.test(String(row.weight))) return null;
                    const duration = Math.round((Date.parse(row.to_date + 'T00:00:00Z') - Date.parse(row.from_date + 'T00:00:00Z')) / 86400000);
                    if (!Number.isFinite(duration) || duration <= 0) return null;
                    const days = BigInt(duration);
                    return BigInt(row.weight) * days;
                }
                if (this.method === 'percentage') return scaledDecimal(row.weight, 6);
                if (this.method === 'submeter') return scaledDecimal(row.lease_id ? this.submeters[row.submeter_reading_id] : row.weight, 3);
                return 1n;
            });
            if (this.method === 'manual') {
                const amounts = this.rows.map(row => scaledDecimal(row.amount, 2));
                return amounts.some(a => a === null) || amounts.reduce((a, b) => a + b, 0n) !== BigInt(sourceAmount) ? null : amounts.map(Number);
            }
            if (weights.some(w => w === null || w <= 0n)) return null;
            const total = weights.reduce((a, b) => a + b, 0n);
            if (this.method === 'percentage' && total !== 100000000n) return null;
            const amounts = weights.map(w => BigInt(sourceAmount) * w / total);
            const remaining = Number(BigInt(sourceAmount) - amounts.reduce((a, b) => a + b, 0n));
            const indices = weights.map((w, index) => ({ index, remainder: BigInt(sourceAmount) * w % total, key: keys[index] }));
            indices.sort((a, b) => a.remainder === b.remainder ? (a.key < b.key ? -1 : 1) : (a.remainder > b.remainder ? -1 : 1));
            indices.slice(0, remaining).forEach(item => amounts[item.index]++);
            return amounts.map(Number);
        },
        money(cents) { return new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(cents / 100); },
    };
}
