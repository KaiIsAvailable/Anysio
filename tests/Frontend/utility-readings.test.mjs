import test from 'node:test';
import assert from 'node:assert/strict';
import { calculateWaterReading, utilityReadings, utilityAllocationEditor } from '../../resources/js/utility-readings.js';

const meter = (overrides = {}) => ({ id: 'meter-a', previous: '10.125', previousDate: '2026-11-01',
    current: '', rate: '', revision: 'revision-a', locked: false, status: 'Not entered', ...overrides });
function entry(rows, oldRows = null) {
    const state = utilityReadings({ rows, readingDate: '2026-11-13', oldRows });
    state.init();
    return state;
}

test('invoice preview opens once and removes its URL flag so refresh does not repeat it', () => {
    const previousWindow = globalThis.window;
    const url = new URL('https://example.test/admin/utilities/readings?month=2026-11&building=property-a&management_user_id=manager-a&preview_invoices=1#readings');
    const historyState = { existing: true };
    let events = 0;
    try {
        globalThis.window = { location: { href: url.href }, history: { state: historyState,
            replaceState(state, title, value) {
                assert.equal(state, historyState);
                window.location.href = value;
            } } };
        const state = entry([]);
        state.$nextTick = callback => callback();
        state.$dispatch = event => {
            assert.equal(event, 'open-water-invoice-preview');
            assert.equal(new URL(window.location.href).searchParams.has('preview_invoices'), false);
            events++;
        };
        state.openInvoicePreview();
        const refreshed = new URL(window.location.href);
        assert.equal(refreshed.searchParams.get('month'), '2026-11');
        assert.equal(refreshed.searchParams.get('building'), 'property-a');
        assert.equal(refreshed.searchParams.get('management_user_id'), 'manager-a');
        assert.equal(refreshed.hash, '#readings');
        state.openInvoicePreview();
        assert.equal(events, 1);
        for (const type of ['reload', 'back_forward']) {
            window.location.href = url.href;
            window.performance = { getEntriesByType: () => [{ type }] };
            state.openInvoicePreview();
            assert.equal(new URL(window.location.href).searchParams.has('preview_invoices'), false);
            assert.equal(events, 1, 'Reloading an old preview URL must not reopen the modal');
        }
    } finally { globalThis.window = previousWindow; }
});

test('recalculation is offered only for an unapplied effective tariff or a repairable saved calculation', () => {
    const tariff = { tariff_version_id: 'lap-v1', effective_date: '2024-05-01', billable: true };
    const later = { ...tariff, tariff_version_id: 'lap-v2', effective_date: '2026-12-01' };
    const plan = { default: 'lap', versions: { lap: [later, tariff] }, assignments: [] };
    const state = utilityReadings({ rows: [meter({ recordId: 'reading-a', savedTariff: tariff })],
        readingDate: '2026-11-13', tariffPlan: plan });
    state.init();
    const row = state.rows[0];
    assert.equal(state.needsRecalculation(row), false, 'The assigned version has already been applied');
    row.savedTariff = { ...tariff, tariff_version_id: 'older-version' };
    assert.equal(state.needsRecalculation(row), true);
    row.reprice = true;
    assert.equal(state.needsRecalculation(row), true, 'Keep the selected option visible until saving');
    row.reprice = false;
    row.savedTariff = tariff;
    assert.equal(state.needsRecalculation(row), false, 'After saving the assigned tariff, hide the checkbox');
    row.calculationNeedsRepair = true;
    assert.equal(state.needsRecalculation(row), true);
    row.calculationNeedsRepair = false;
    row.savedTariff = null;
    assert.equal(state.needsRecalculation(row), true, 'Legacy records without a tariff can be repaired');
    row.locked = true;
    assert.equal(state.needsRecalculation(row), false);
    row.locked = false;
    row.recordId = null;
    assert.equal(state.needsRecalculation(row), false, 'New readings use the assigned tariff automatically');
    row.recordId = 'reading-a';
    row.savedTariff = tariff;
    state.readingDate = '2026-12-13';
    assert.equal(state.needsRecalculation(row), false, 'An interval spanning a rate change is not repairable by recalculation alone');
    row.previousDate = '2026-12-01';
    assert.equal(state.needsRecalculation(row), true);
});

test('water calculations round once per meter and match server fixed-point arithmetic', () => {
    assert.deepEqual(calculateWaterReading(meter({ current: '12.470', rate: '2.50' })), { usage: '2.345', amount: 586, error: '' });
    assert.deepEqual(calculateWaterReading(meter({ previous: '0', current: '0.001', rate: '5' })), { usage: '0.001', amount: 1, error: '' });
    assert.deepEqual(calculateWaterReading(meter({ current: '10.125', rate: '0' })), { usage: '0.000', amount: 0, error: '' });
    assert.equal(calculateWaterReading(meter({ current: '9', rate: '2' })).amount, null);
    assert.match(calculateWaterReading(meter({ current: '9', rate: '2' })).error, /can be saved.*needs review/);
    assert.equal(calculateWaterReading(meter({ current: '12.470' })).usage, '2.345');
    assert.equal(calculateWaterReading(meter()).usage, null);
    assert.match(calculateWaterReading(meter({ current: '999999999999.999', rate: '999999999.999999' })).error, /limit/);
    assert.match(calculateWaterReading(meter({ current: 'NaN', rate: '2' })).error, /valid/);
});

test('applying a common rate preserves locked rows and does not create blank readings', () => {
    const state = entry([meter(), meter({ id: 'locked', locked: true, current: '12', rate: '1' })]);
    state.commonRate = '2.50';
    state.applyRate();
    assert.equal(state.rows[1].rate, '1');
    assert.deepEqual(JSON.parse(state.payload), []);
    state.rows[0].current = '12.470';
    assert.equal(state.ready, 1);
    assert.equal(state.hasChanges, true);
    const payload = JSON.parse(state.payload);
    assert.equal(payload.length, 1);
    assert.equal(payload[0].rate, '2.50');
    assert.equal(payload[0].revision, 'revision-a');
    assert.equal(Object.hasOwn(payload[0], 'amount'), false);
});

test('single save skips blank rows and includes legacy drafts without a confirmation step', () => {
    const state = entry([meter({ current: '12.470', rate: '2.50', status: 'Saved', needsSave: true, readingDate: '2026-11-13' }),
        meter({ id: 'locked', locked: true, current: '15', rate: '2' }), meter({ id: 'blank' })]);
    assert.deepEqual(JSON.parse(state.payload).map(row => row.id), ['meter-a']);
    state.rows[0].needsSave = false;
    assert.equal(JSON.parse(state.payload).length, 0);
    state.readingDate = '2026-11-14';
    assert.equal(JSON.parse(state.payload).length, 0);
    state.rows[0].current = '12.471';
    assert.equal(JSON.parse(state.payload).length, 1);
});

test('validation redirects restore edits without replacing the submitted stale revision', () => {
    const state = entry([meter()], [{ id: 'meter-a', previous: '10.125', previousDate: '2026-11-01',
        current: '11.000', rate: '2', revision: 'stale-revision' }]);
    assert.equal(state.rows[0].current, '11.000');
    assert.equal(JSON.parse(state.payload)[0].revision, 'stale-revision');
    state.rows[0].previousDate = '2026-11-14';
    assert.match(state.error(state.rows[0]), /date/);
});

test('hundreds of edited meters serialize into one complete payload', () => {
    const state = entry(Array.from({ length: 500 }, (_, index) => meter({ id: `meter-${index}` })));
    state.commonRate = '2'; state.applyRate();
    state.rows.forEach(row => { row.current = '11.125'; });
    assert.equal(JSON.parse(state.payload).length, 500);
    assert.equal(state.ready, 500);
    assert.equal(state.total, 100000);
});

test('hierarchy reading fields edit the original meter row used for calculations and saving', () => {
    const state = entry([meter()]);
    const node = { key: 'room-r1', label: 'R1', rowId: 'meter-a', level: 2 };
    const row = state.readingRow(node);
    row.current = '11.125'; row.rate = '2';
    assert.equal(state.rows[0].current, '11.125');
    assert.equal(state.total, 200);
    assert.equal(JSON.parse(state.payload)[0].id, 'meter-a');
    assert.equal(state.readingRow({ rowId: null }), null);
});

test('common previous dates fill only editable baselines without submitting untouched empty rows', () => {
    const state = entry([meter({ id: 'new', previous: '', previousDate: '', previousDateLocked: false }),
        meter({ id: 'established', previousDateLocked: true })]);
    state.previousDate = '2026-11-02'; state.applyPreviousDate();
    assert.equal(state.rows[0].previousDate, '2026-11-02');
    assert.equal(state.rows[1].previousDate, '2026-11-01');
    assert.equal(JSON.parse(state.payload).length, 0);
    state.rows[0].previous = '0'; state.rows[0].current = '2';
    assert.equal(JSON.parse(state.payload).length, 1);
});

test('Enter moves to the next editable reading and skips locked previous fields', () => {
    const state = entry([]);
    let focused = false;
    const fields = [{ disabled: false, readOnly: false }, { disabled: false, readOnly: true },
        { disabled: true, readOnly: false }, { disabled: false, readOnly: false, focus() { focused = true; } }];
    fields[0].closest = () => ({ querySelectorAll: () => fields });
    state.nextField({ target: fields[0] });
    assert.equal(focused, true);
});

test('returning after meter deletion restores other edited readings and skips the removed meter', () => {
    const state = entry([meter({ id: 'remaining' })], [
        { id: 'removed', current: '50', rate: '2', revision: 'removed-revision' },
        { id: 'remaining', current: '12.125', rate: '2', revision: 'revision-a' },
    ]);
    assert.equal(state.rows.length, 1);
    assert.equal(state.rows[0].current, '12.125');
    assert.deepEqual(JSON.parse(state.payload).map(row => row.id), ['remaining']);
});

test('bulk selection includes only meters and select all can be cleared without changing readings', () => {
    const state = entry([meter(), meter({ id: 'locked', locked: true, current: '12' })]);
    assert.equal(state.allSelected, false);
    state.selectedIds = ['meter-a'];
    assert.deepEqual(state.selectedMeters.map(row => row.id), ['meter-a']);
    state.toggleAllMeters(true);
    assert.equal(state.allSelected, true);
    assert.equal(state.selectedMeters.length, 2);
    assert.equal(state.hasChanges, false);
    state.toggleAllMeters(false);
    assert.deepEqual(state.selectedIds, []);
    assert.equal(state.allSelected, false);
    assert.equal(entry([]).allSelected, false);
});

test('nullable readings serialize independently and never calculate missing values as zero', () => {
    const state = entry([meter({ id: 'previous-only', previous: '', previousDate: '', current: '', rate: '2' }),
        meter({ id: 'current-only', previous: null, previousDate: null, current: null, rate: '2' }),
        meter({ id: 'blank', previous: '', previousDate: '', current: '' })]);
    state.rows[0].previous = '0';
    state.rows[1].current = '5';
    assert.deepEqual(JSON.parse(state.payload).map(row => row.id), ['previous-only', 'current-only']);
    for (const row of state.rows) {
        assert.deepEqual(state.calculation(row), { usage: null, amount: null, error: '' });
        assert.equal(state.error(row), '');
    }
});

test('blank flat rate uses default Perak blocks and minimum while explicit rates override it', () => {
    const tariff = { calculation: 'blocks', tiers: [{ limit: 20, rate: 0.75 }, { limit: 35, rate: 1.08 }, { limit: null, rate: 1.35 }],
        minimum_charge: 7.5, capacity_rate: 0, network_rate: 0, fixed_charge: 0, fixed_waiver_up_to: null };
    const row = meter({ previous: '0', current: '30', rate: '', tariff });
    assert.equal(calculateWaterReading(row).amount, 2580);
    assert.equal(calculateWaterReading({ ...row, current: '40' }).amount, 3795);
    assert.equal(calculateWaterReading({ ...row, current: '0' }).amount, 750);
    assert.equal(calculateWaterReading({ ...row, rate: '2' }).amount, 6000);
    assert.equal(calculateWaterReading({ ...row, previous: null }).amount, null);
});

test('saved meters with different reading dates do not block invoice preview as unsaved edits', () => {
    const state = entry([meter({ current: '12', rate: '2', status: 'Saved', readingDate: '2026-11-10' }),
        meter({ id: 'other', current: '13', rate: '2', status: 'Saved', readingDate: '2026-11-11' })]);
    assert.equal(state.hasChanges, false);
    assert.deepEqual(JSON.parse(state.payload), []);
    state.readingDate = '2026-11-14';
    assert.equal(state.hasChanges, false);
    assert.equal(JSON.parse(state.payload).length, 0);
    state.rows[0].current = '14';
    assert.deepEqual(JSON.parse(state.payload).map(row => row.id), ['meter-a']);
});


test('shared allocation previews conserve cents across equal, occupant, percentage and manual policies', () => {
    const state = utilityAllocationEditor(100, '2026-11-01', '2026-11-13', [], {});
    state.rows = ['b', 'a', 'c'].map(lease_id => ({ lease_id, weight: '1', amount: '', from_date: '2026-11-01', to_date: '2026-11-13' }));
    assert.deepEqual(state.result, [33, 34, 33]);
    state.method = 'occupants'; state.rows[0].weight = '2';
    assert.equal(state.result.reduce((a, b) => a + b), 100);
    state.rows[0].to_date = '';
    assert.equal(state.result, null);
    state.method = 'percentage'; state.rows.forEach((row, i) => row.weight = ['50', '25', '25'][i]);
    assert.deepEqual(state.result, [50, 25, 25]);
    state.rows[0].weight = '51'; assert.equal(state.result, null);
    state.method = 'manual'; state.rows.forEach((row, i) => row.amount = ['0.50', '0.25', '0.25'][i]);
    assert.deepEqual(state.result, [50, 25, 25]);
    state.rows[0].amount = '0.51'; assert.equal(state.result, null);
});


test('building workspaces retain edits and scope date changes and bulk selection to visible meters', () => {
    const state = utilityReadings({ building: 'a', readingDate: '2026-11-13', rows: [
        meter({ id: 'a1', propertyId: 'a', current: '12', status: 'Saved', readingDate: '2026-11-13' }),
        meter({ id: 'b1', propertyId: 'b', current: '20', status: 'Saved', readingDate: '2026-11-13' })],
        hierarchy: [{ key: 'property-a', label: 'A', level: 0, rowId: null }, { key: 'unit-a', level: 1, rowId: 'a1' },
            { key: 'property-b', label: 'B', level: 0, rowId: null }, { key: 'unit-b', level: 1, rowId: 'b1' }] });
    state.init();
    assert.equal(state.progress.total, 2);
    assert.equal(state.propertyProgress.total, 1);
    assert.deepEqual(state.visibleHierarchy.map(node => node.key), ['property-a', 'unit-a']);
    state.readingDate = '2026-11-14';
    assert.deepEqual(JSON.parse(state.payload).map(row => row.id), []);
    state.toggleAllMeters(true); assert.deepEqual(state.selectedIds, ['a1']);
    state.rows[0].current = '13'; state.selectProperty('b');
    assert.deepEqual(state.selectedIds, []);
    assert.equal(state.rows[0].current, '13');
    assert.deepEqual(JSON.parse(state.payload).map(row => row.id), ['a1']);
    state.selectProperty('a'); assert.equal(state.rows[0].current, '13');
});

test('field recording surfaces physical reading errors without requiring staff to manage pricing', () => {
    const state = entry([]);
    const row = meter({ current: '12', tariff: { billable: false, review_reason: 'Unverified tariff' } });
    assert.equal(state.entryError(row), '');
    row.current = '9'; assert.match(state.entryError(row), /previous/);
    row.current = '12'; state.manageMeters = true;
    assert.equal(state.entryError(row), 'Unverified tariff');
});


test('overall progress stays account-wide while selected property progress changes independently', () => {
    const state = utilityReadings({ readingDate: '2026-11-14', building: 'a', rows: [
        meter({ id: 'a1', propertyId: 'a', current: '0', status: 'Saved' }),
        meter({ id: 'a2', propertyId: 'a', current: null }),
        meter({ id: 'b1', propertyId: 'b', current: '20', status: 'Saved' })],
        hierarchy: [{ key: 'property-a', level: 0 }, { key: 'a1', rowId: 'a1', level: 1 }, { key: 'a2', rowId: 'a2', level: 1 },
            { key: 'property-b', level: 0 }, { key: 'b1', rowId: 'b1', level: 1 }] });
    state.init();
    assert.deepEqual(state.progress, { total: 3, entered: 2, remaining: 1, percent: 67 });
    assert.equal(state.propertyProgress.percent, 50);
    state.selectProperty('b');
    assert.equal(state.propertyProgress.percent, 100);
    assert.equal(state.progress.percent, 67);
    state.selectProperty('');
    assert.deepEqual(state.propertyProgress, state.progress);
});

test('a partial save includes edited meters without requiring every current reading or resubmitting saved meters', () => {
    const state = entry([meter({ id: 'saved', current: '20', status: 'Saved', readingDate: '2026-11-10' }),
        meter({ id: 'new-a' }), meter({ id: 'new-b' }), meter({ id: 'blank' })]);
    state.readingDate = '2026-11-12';
    state.rowsById['new-a'].current = '12';
    state.rowsById['new-b'].current = '13';
    assert.deepEqual(JSON.parse(state.payload).map(row => row.id), ['new-a', 'new-b']);
    assert.equal(state.progress.total, 4);
    assert.equal(state.progress.entered, 3);
});


function draftStorage() {
    const entries = new Map();
    return { getItem: key => entries.get(key) ?? null, setItem: (key, value) => entries.set(key, value), removeItem: key => entries.delete(key) };
}
function draftEntry(storage, extra = {}) {
    const state = utilityReadings({ rows: [meter()], readingDate: '2026-11-13', storage,
        draftKey: 'readings:user-a:account-a:2026-11', draftScope: 'account-a:2026-11', ...extra });
    state.init();
    return state;
}

test('session drafts restore readings and the chosen date after refresh and can be discarded', () => {
    const storage = draftStorage();
    const first = draftEntry(storage);
    first.rows[0].current = '12.500'; first.readingDate = '2026-11-14';
    assert.equal(first.persistDraft(), true);
    const refreshed = draftEntry(storage);
    assert.equal(refreshed.rows[0].current, '12.500');
    assert.equal(refreshed.readingDate, '2026-11-14');
    assert.equal(refreshed.hasChanges, true);
    refreshed.discardDraft();
    assert.equal(refreshed.rows[0].current, '');
    assert.equal(refreshed.hasChanges, false);
    assert.equal(storage.getItem('readings:user-a:account-a:2026-11'), null);
});

test('session drafts isolate users, accounts and months', () => {
    const storage = draftStorage(); const first = draftEntry(storage);
    first.rows[0].current = '12'; first.persistDraft();
    for (const key of ['readings:user-b:account-a:2026-11', 'readings:user-a:account-b:2026-11', 'readings:user-a:account-a:2026-12']) {
        assert.equal(draftEntry(storage, { draftKey: key }).rows[0].current, '');
    }
    assert.equal(draftEntry(storage).rows[0].current, '12');
});

test('successful saves remove acknowledged draft values but preserve newer local edits', () => {
    const storage = draftStorage(); const first = draftEntry(storage);
    first.rows[0].current = '12'; first.persistDraft();
    const submitted = JSON.parse(first.payload);
    const saved = { scope: 'account-a:2026-11', readingDate: '2026-11-13', rows: submitted };
    const serverRows = [meter({ current: '12', status: 'Saved', revision: 'revision-new' })];
    const afterSave = draftEntry(storage, { savedDraft: saved, rows: serverRows });
    assert.equal(afterSave.hasChanges, false);
    assert.equal(storage.getItem('readings:user-a:account-a:2026-11'), null);
    const next = draftEntry(storage); next.rows[0].current = '13'; next.persistDraft();
    const changedDuringSave = draftEntry(storage, { savedDraft: saved, rows: serverRows });
    assert.equal(changedDuringSave.rows[0].current, '13');
    assert.equal(changedDuringSave.rows[0].revision, 'revision-a');
});

test('restored drafts retain stale revisions and server validation input takes priority', () => {
    const storage = draftStorage(); const first = draftEntry(storage);
    first.rows[0].current = '12'; first.persistDraft();
    const refreshed = draftEntry(storage, { rows: [meter({ revision: 'changed-by-other-staff' })] });
    assert.equal(refreshed.rows[0].revision, 'revision-a');
    const validation = draftEntry(storage, { oldRows: [{ id: 'meter-a', current: '9', revision: 'submitted-revision' }] });
    assert.equal(validation.rows[0].current, '9');
    assert.equal(validation.rows[0].revision, 'submitted-revision');
});

test('a date-only edit is retained and unavailable storage is reported to the unload fallback', () => {
    const storage = draftStorage(); const first = draftEntry(storage);
    first.readingDate = '2026-11-12'; first.persistDraft();
    assert.equal(draftEntry(storage).readingDate, '2026-11-12');
    const blocked = draftEntry({ getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); }, removeItem() { throw new Error('blocked'); } });
    blocked.rows[0].current = '12';
    assert.equal(blocked.persistDraft(), false);
    assert.equal(blocked.draftRetained, false);
});


test('restoring draft input ignores identifiers outside the loaded meter map', () => {
    const state = draftEntry(draftStorage(), { oldRows: [{ id: '__proto__', current: '999' }, { id: 'another-account', current: '888' }] });
    assert.equal(Object.prototype.current, undefined);
    assert.equal(state.rows[0].current, '');
});
