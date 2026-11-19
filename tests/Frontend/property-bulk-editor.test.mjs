import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = ['bulk-editor.js', 'property-bulk-editor.js'].map(file => readFileSync(new URL('../../resources/js/' + file, import.meta.url), 'utf8')
    .replace(/^import .*;$/gm, '').replace(/export function /g, 'function ')).join('\n');

function factory(storage = new Map(), browser = {}) {
    const context = vm.createContext({ URLSearchParams, document: { activeElement: null }, clearTimeout, setTimeout,
        URL: { revokeObjectURL() {} }, fetch: browser.fetch,
        sessionStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) } });
    vm.runInContext(source, context);
    return (rows = [], canCreate = true, gridConfig = {}) => {
        const editor = context.propertyBulkEditor(rows, canCreate, 'property-draft', false, 1, null, {}, {}, gridConfig);
        editor.$nextTick = fn => fn(); editor.$dispatch = () => {}; editor.init(); return editor;
    };
}

const values = { name: 'Property', ownerId: '', propertyType: 'Condo', address: 'Main Street', postcode: '01234', city: 'Ipoh', state: 'Perak' };

test('property state selection survives drafts and keeps the existing required validation', () => {
    const storage = new Map(); const create = factory(storage);
    const editor = create([{ key: 'property-state', ...values }]);
    assert.deepEqual(Array.from(editor.rows[0].stateSelection), ['Perak']);
    Object.assign(editor.rows[0], { state: 'Selangor', stateSelection: ['Selangor'] }); editor.markChanged(editor.rows[0], 'state');
    const restored = create([{ key: 'property-state', ...values }]);
    assert.deepEqual(Array.from(restored.rows[0].stateSelection), ['Selangor']);
    assert.equal(restored.submissionRows()[0].state, 'Selangor');
    restored.rows[0].state = ''; restored.focusRowError = () => {};
    assert.equal(restored.isFieldRequired(restored.rows[0], 'state'), true);
    assert.equal(restored.validateBeforeSave({ checkValidity: () => true }), false);
});

test('grid starts with a single empty entry and requires no owner', () => {
    const editor = factory()();
    assert.equal(editor.rows.length, 1);
    assert.equal(editor.hasChanges(), false);
    Object.assign(editor.rows[0], values);
    editor.markChanged(editor.rows[0], 'name');
    assert.equal(editor.rows.length, 2);
    assert.equal(editor.isFieldRequired(editor.rows[0], 'ownerId'), false);
    assert.equal(editor.isFieldRequired(editor.rows[0], 'propertyType'), true);
});

test('select values initialize correctly and drafts recover each changed selection', () => {
    const storage = new Map(); const create = factory(storage);
    const editor = create([{ key: 'property-one', ...values, ownerId: 'owner-one', version: 'version' }]);
    assert.deepEqual(Array.from(editor.rows[0].ownerIdSelection), ['owner-one']);
    assert.deepEqual(Array.from(editor.rows[0].propertyTypeSelection), ['Condo']);
    editor.rows[0].ownerId = ''; editor.rows[0].propertyType = 'Landed'; editor.markChanged(editor.rows[0], 'propertyType');
    const restored = create([{ key: 'property-one', ...values, ownerId: 'owner-one', version: 'version' }]);
    assert.equal(restored.rows[0].ownerId, '');
    assert.deepEqual(Array.from(restored.rows[0].ownerIdSelection), ['']);
    assert.deepEqual(Array.from(restored.rows[0].propertyTypeSelection), ['Landed']);
});

test('saving only serializes changed fields and removal preserves version without field data', () => {
    const editor = factory()([{ key: 'property-one', ...values, version: 'revision' }]);
    assert.equal(editor.submissionRows().length, 0);
    editor.rows[0].name = 'Edited'; editor.markChanged(editor.rows[0]);
    let payload = editor.submissionRows()[0];
    assert.equal(payload.id, 'property-one');
    assert.equal(payload.action, 'update');
    assert.equal(payload.version, 'revision');
    assert.equal(payload.ownerId, '');
    assert.equal('status' in payload, false);
    assert.equal('created_by' in payload, false);
    editor.remove(editor.rows[0]); payload = editor.submissionRows()[0];
    assert.equal(payload.action, 'remove');
    assert.equal('name' in payload, false);
});

test('postcode preserves leading zeroes and validation blocks malformed or missing data', () => {
    const editor = factory()(); const row = editor.rows[0];
    Object.assign(row, values, { postcode: '01a23456' }); editor.markChanged(row, 'postcode');
    assert.equal(row.postcode, '01234');
    row.postcode = '123'; editor.focusRowError = () => {};
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), false);
    assert.equal(editor.rowErrors(row)[0].field, 'postcode');
    row.postcode = '01234'; row.propertyType = '';
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), false);
    row.propertyType = 'Condo';
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), true);
});

test('edit-only users have no blank create row and deleting a new row removes it locally', () => {
    const editor = factory()([{ key: 'property-one', ...values }], false);
    assert.equal(editor.rows.length, 1);
    const create = factory()(); const row = create.rows[0];
    Object.assign(row, values); create.markChanged(row); create.remove(row);
    assert.equal(create.submissionRows().length, 0);
});

const hierarchy = () => ({ hierarchy: true, rowsUrl: '/properties/bulk-edit', permissions: Object.fromEntries(['property', 'unit', 'room'].map(entity => [entity, { view: true, create: true, edit: true, delete: true }])) });

test('three sheets link new properties, units and rooms by stable draft keys', () => {
    const editor = factory()([], true, hierarchy());
    assert.equal(editor.rows.length, 3);
    assert.equal(editor.hasChanges(), false);
    const property = editor.visibleRows('property')[0];
    Object.assign(property, values, { ownerId: 'owner-one' }); editor.markChanged(property);
    const unit = editor.visibleRows('unit')[0];
    Object.assign(unit, { parentKey: property.key, unitNo: 'A-01' }); editor.markChanged(unit);
    const room = editor.visibleRows('room')[0];
    Object.assign(room, { parentKey: unit.key, roomNo: '101', roomType: 'Single' }); editor.markChanged(room);
    assert.equal(editor.parentOptions(unit, 'property')[0].value, property.key);
    assert.equal(editor.parentOptions(room, 'unit')[0].label, 'Property > A-01');
    assert.equal(editor.submissionRows().length, 3);
    assert.equal(editor.submissionRows().find(row => row.entity === 'room').parentKey, unit.key);
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), true);
});

test('restoring a hierarchy draft preserves parent links and generates noncolliding new keys', () => {
    const storage = new Map(); const create = factory(storage);
    const editor = create([], true, hierarchy());
    const property = editor.visibleRows('property')[0]; Object.assign(property, values, { ownerId: 'owner-one' }); editor.markChanged(property);
    const unit = editor.visibleRows('unit')[0]; Object.assign(unit, { parentKey: property.key, unitNo: 'A-01' }); editor.markChanged(unit);
    const room = editor.visibleRows('room')[0]; Object.assign(room, { parentKey: unit.key, roomNo: '101', roomType: 'Single' }); editor.markChanged(room);
    const restored = create([], true, hierarchy());
    assert.equal(restored.rows.find(row => row.key === unit.key).parentKey, property.key);
    assert.equal(restored.rows.find(row => row.key === room.key).parentKey, unit.key);
    assert.equal(new Set(restored.rows.map(row => row.key)).size, restored.rows.length);
    assert.equal(restored.submissionRows().length, 3);
});

test('existing parent removal suppresses child edits and undo restores them', () => {
    const editor = factory()([
        { key: 'property-one', entity: 'property', ...values },
        { key: 'unit-one', entity: 'unit', parentKey: 'property-one', ownerId: 'owner', unitNo: 'A-01' },
        { key: 'room-one', entity: 'room', parentKey: 'unit-one', roomNo: '101', roomType: 'Single' },
    ], true, hierarchy());
    editor.rows[2].roomType = 'Master'; editor.markChanged(editor.rows[2]);
    editor.remove(editor.rows[0]);
    assert.equal(editor.submissionRows().length, 1);
    assert.equal(editor.submissionRows()[0].entity, 'property');
    assert.equal(editor.isCellDisabled(editor.rows[2], 'roomType'), true);
    editor.remove(editor.rows[0]);
    assert.equal(editor.submissionRows()[0].entity, 'room');
});

test('removing a new parent clears its new descendants without submitting orphans', () => {
    const editor = factory()([], true, hierarchy());
    const property = editor.rows[0]; Object.assign(property, values); editor.markChanged(property);
    const unit = editor.visibleRows('unit')[0]; Object.assign(unit, { unitNo: 'A-01', parentKey: property.key }); editor.markChanged(unit);
    const room = editor.visibleRows('room')[0]; Object.assign(room, { roomNo: '101', parentKey: unit.key }); editor.markChanged(room);
    editor.remove(property);
    assert.equal(editor.submissionRows().length, 0);
    assert.equal(editor.rows.length, 3);
});

test('a child validation error opens its sheet and owner inheritance is required', () => {
    const editor = factory()([{ key: 'property-one', entity: 'property', ...values }], true, hierarchy());
    const unit = editor.visibleRows('unit')[0]; Object.assign(unit, { parentKey: 'property-one', unitNo: 'A-01' }); editor.markChanged(unit);
    editor.focusRowError = row => { editor.activeEntity = row.entity; };
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), false);
    assert.equal(editor.activeEntity, 'unit');
    assert.equal(editor.rowErrors(unit).some(error => error.field === 'ownerId'), true);
    unit.ownerId = 'owner-one';
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), true);
});

test('entity permissions prevent unauthorized blank rows and keep parent fields immutable', () => {
    const config = hierarchy(); config.permissions.property.create = false; config.permissions.property.edit = false; config.permissions.room.create = false;
    const editor = factory()([{ key: 'property-one', entity: 'property', ...values }], false, config);
    assert.equal(editor.visibleRows('property').length, 1);
    assert.equal(editor.visibleRows('unit').length, 1);
    assert.equal(editor.visibleRows('room').length, 0);
    assert.equal(editor.isCellDisabled(editor.rows[0], 'name'), true);
    assert.equal(editor.isCellDisabled({ entity: 'unit', isNew: false }, 'parentKey'), true);
});

test('loading rows includes the active entity and recovery fetches parents across sheets', async () => {
    const requests = [];
    const editor = factory(new Map(), { fetch: async url => {
        requests.push(url); return { ok: true, json: async () => ({ rows: [{ key: 'unit-one', entity: 'unit' }], parents: [{ key: 'property-one', entity: 'property' }], nextCursor: null }) };
    } })([], true, hierarchy());
    editor.switchEntity('unit');
    const data = await editor.fetchRows({ after: 'cursor-one' });
    assert.equal(requests[0].includes('entity=unit'), true);
    assert.equal(data.rows.length, 2);
    await editor.fetchRows({ ids: ['unit-one'] });
    assert.equal(requests[1].includes('entity=all'), true);
});

test('validation marks every failing sheet and opens the first sheet in tab order', () => {
    const editor = factory()([], true, hierarchy());
    const property = editor.visibleRows('property')[0]; Object.assign(property, values, { city: '', ownerId: 'owner-one' }); editor.markChanged(property);
    const unit = editor.visibleRows('unit')[0]; Object.assign(unit, { parentKey: property.key, unitNo: 'A-01', floor: '1.5' }); editor.markChanged(unit);
    const room = editor.visibleRows('room')[0]; Object.assign(room, { parentKey: unit.key, roomNo: '101' }); editor.markChanged(room);
    editor.rows.reverse(); editor.activeEntity = 'room';
    const focused = []; editor.focusRowError = (row, error) => { editor.activeEntity = row.entity; focused.push(error.field); };
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), false);
    for (const entity of ['property', 'unit', 'room']) assert.equal(editor.entityHasErrors(entity), true);
    assert.equal(editor.activeEntity, 'property'); assert.equal(focused[0], 'city');
    property.city = 'Ipoh'; editor.markChanged(property, 'city');
    assert.equal(editor.entityHasErrors('property'), false);
    for (const floor of ['G', '0', '-2', '12', '2147483647', '-2147483648', '']) {
        unit.floor = floor; editor.validateBeforeSave({ checkValidity: () => true });
        assert.equal(editor.fieldHasErrors(unit, 'floor'), false, floor);
    }
    for (const floor of ['g', 'Ground', '1e2', '2147483648']) {
        unit.floor = floor; editor.validateBeforeSave({ checkValidity: () => true });
        assert.equal(editor.fieldHasErrors(unit, 'floor'), true, floor);
    }
});

test('Laravel errors map restored submission indexes to their sheets and focus the first error', () => {
    const storage = new Map(); const create = factory(storage);
    const editor = create([], true, hierarchy());
    const property = editor.visibleRows('property')[0]; Object.assign(property, values, { ownerId: 'owner-one', submittedIndex: 0 }); editor.markChanged(property);
    const unit = editor.visibleRows('unit')[0]; Object.assign(unit, { parentKey: property.key, unitNo: 'A', submittedIndex: 1 }); editor.markChanged(unit);
    const room = editor.visibleRows('room')[0]; Object.assign(room, { parentKey: unit.key, roomNo: '101', roomType: 'Single', submittedIndex: 2 }); editor.markChanged(room);
    const restored = create([], true, hierarchy());
    let focused; restored.focusRowError = (row, error) => { restored.activeEntity = row.entity; focused = error.field; };
    restored.applyServerErrors({ 'rows.2.roomNo': ['Duplicate room'], 'rows.1.floor': ['Invalid floor'] });
    assert.equal(restored.activeEntity, 'unit'); assert.equal(focused, 'floor');
    assert.equal(restored.entityHasErrors('room'), true);
    assert.equal(restored.entityHasErrors('unit'), true);
    assert.equal(restored.entityHasErrors('property'), false);
    restored.clearCellErrors(restored.rows.find(row => row.key === unit.key), 'floor');
    assert.equal(restored.entityHasErrors('unit'), false);
});


test('water setup defaults to no meter and serializes only for permitted staff', () => {
    const editor = factory()([], true, { ...hierarchy(), canManageWaterMeters: true });
    const row = editor.visibleRows('property')[0];
    assert.equal(row.waterHasMeter, '0');
    assert.equal(editor.hasChanges(), false);
    assert.equal(editor.isCellDisabled(row, 'waterOpeningReading'), true);
    Object.assign(row, values, { waterHasMeter: '1', waterMeterNumber: 'MAIN-1', waterOpeningReading: '0', waterOpeningDate: '2026-01-01' });
    editor.markChanged(row, 'waterHasMeter');
    assert.equal(editor.isCellDisabled(row, 'waterOpeningReading'), false);
    assert.equal(editor.submissionRows()[0].waterOpeningReading, '0');
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), true);
    const restricted = factory()([{ key: 'existing-property', ...values, waterHasMeter: '1', waterMeterNumber: 'PRIVATE' }], false, hierarchy());
    restricted.markChanged(restricted.rows[0], 'name');
    assert.equal(restricted.isCellDisabled(restricted.rows[0], 'waterHasMeter'), true);
    assert.equal(Object.hasOwn(restricted.submissionRows()[0], 'waterMeterNumber'), false);
});

test('water opening reading needs its date and water errors focus their own column', () => {
    const editor = factory()([{ key: 'existing-property', ...values, waterHasMeter: '1', waterOpeningReading: '0' }], false, { ...hierarchy(), canManageWaterMeters: true });
    editor.markChanged(editor.rows[0], 'waterOpeningReading');
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), false);
    assert.equal(editor.rowErrors(editor.rows[0])[0].field, 'waterOpeningDate');
    editor.rows[0].waterOpeningDate = '2026-01-01';
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), true);
    editor.rows[0].waterSetupLocked = true;
    assert.equal(editor.isCellDisabled(editor.rows[0], 'waterHasMeter'), true);
    assert.equal(Object.hasOwn(editor.submissionRows()[0], 'waterHasMeter'), false);
});
