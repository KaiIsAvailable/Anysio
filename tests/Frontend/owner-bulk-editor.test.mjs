import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = ['bulk-editor.js', 'owner-bulk-editor.js'].map(file => readFileSync(new URL('../../resources/js/' + file, import.meta.url), 'utf8')
    .replace(/^import .*;$/gm, '').replace(/export function /g, 'function ')).join('\n');

function factory(storage = new Map(), browser = {}) {
    const context = vm.createContext({ URLSearchParams, document: { activeElement: null }, clearTimeout, setTimeout,
        fetch: browser.fetch, window: browser.window, confirm: () => true,
        URL: { createObjectURL: file => 'blob:' + file.name, revokeObjectURL() {} },
        sessionStorage: { getItem: key => storage.get(key) ?? null, setItem: (key, value) => storage.set(key, value), removeItem: key => storage.delete(key) } });
    vm.runInContext(source, context);
    return (rows = [], key = 'owner-draft', canCreate = true) => {
        const editor = context.ownerBulkEditor(rows, canCreate, key, false, 1, null, {}, {}, { rowsUrl: '/owners/bulk-edit' });
        editor.$nextTick = fn => fn(); editor.$dispatch = () => {}; editor.init(); return editor;
    };
}
const values = { name: 'Owner', phone: '0123456789', ic: '900101145678', gender: 'Male' };

test('state selection preserves existing values, draft changes, and blank optional states', () => {
    const storage = new Map(); const create = factory(storage);
    const editor = create([{ key: 'owner-state', ...values, state: 'Perak' }]);
    assert.deepEqual(Array.from(editor.rows[0].stateSelection), ['Perak']);
    Object.assign(editor.rows[0], { state: 'Selangor', stateSelection: ['Selangor'] }); editor.markChanged(editor.rows[0], 'state');
    assert.equal(editor.submissionRows()[0].state, 'Selangor');
    const restored = create([{ key: 'owner-state', ...values, state: 'Perak' }]);
    assert.deepEqual(Array.from(restored.rows[0].stateSelection), ['Selangor']);
    assert.equal(restored.rows[0].state, 'Selangor');
    restored.rows[0].state = ''; restored.rows[0].stateSelection = []; restored.markChanged(restored.rows[0], 'state');
    assert.equal(restored.submissionRows()[0].state, '');
    assert.equal(restored.isFieldRequired(restored.rows[0], 'state'), false);
});

test('owner grid starts with one blank row and has optional company and bank groups', () => {
    const editor = factory()();
    assert.equal(editor.rows.length, 1);
    assert.equal(editor.hasChanges(), false);
    Object.assign(editor.rows[0], values);
    editor.markChanged(editor.rows[0]);
    assert.equal(editor.rows.length, 2);
    assert.equal(editor.isFieldRequired(editor.rows[0], 'companyName'), false);
    assert.equal(editor.isFieldRequired(editor.rows[0], 'bankName'), false);
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), true);
    assert.equal(editor.submissionRows().length, 1);
});

test('partial company or bank requires identifying details and replacement numbers remain optional', () => {
    const editor = factory()(); const row = editor.rows[0];
    Object.assign(row, values, { companyPhone: '0123', bankCode: 'CODE' }); editor.markChanged(row);
    assert.equal(editor.isFieldRequired(row, 'companyName'), true);
    assert.equal(editor.isFieldRequired(row, 'bankName'), true);
    assert.equal(editor.isFieldRequired(row, 'accountHolder'), true);
    assert.equal(editor.isFieldRequired(row, 'accountNumber'), true);
    row.accountLastFour = '1234';
    assert.equal(editor.isFieldRequired(row, 'accountNumber'), false);
});

test('drafts exclude full bank numbers and restore a mandatory replacement warning', () => {
    const storage = new Map(); const create = factory(storage);
    const editor = create([{ key: 'owner-1', ...values, accountLastFour: '5678', bankName: 'Bank', accountHolder: 'Owner' }]);
    editor.rows[0].accountNumber = '00123456789'; editor.markChanged(editor.rows[0]);
    assert.equal(storage.get('owner-draft').includes('00123456789'), false);
    const restored = create([{ key: 'owner-1', ...values, accountLastFour: '5678', bankName: 'Bank', accountHolder: 'Owner' }]);
    assert.equal(restored.rows[0].accountNumber, '');
    assert.equal(restored.rows[0].accountNumberPending, true);
    assert.equal(restored.isFieldRequired(restored.rows[0], 'accountNumber'), true);
});

test('old drafts containing bank numbers are sanitized during restoration', () => {
    const storage = new Map([['owner-draft', JSON.stringify([{ key: 'owner-1', ...values, changed: true, accountNumber: '0099999' }])]]);
    const editor = factory(storage)([{ key: 'owner-1', ...values }]);
    assert.equal(editor.rows[0].accountNumber, '');
    editor.persistDraft();
    assert.equal(storage.get('owner-draft').includes('0099999'), false);
});

test('payload keeps owner and company addresses separate and excludes untouched rows', () => {
    const editor = factory()([{ key: 'owner-1', ...values }]);
    Object.assign(editor.rows[0], { address: 'Owner Street', companyName: 'Company', companyAddress: 'Company Street' });
    editor.markChanged(editor.rows[0]); const row = editor.submissionRows()[0];
    assert.equal(row.address, 'Owner Street'); assert.equal(row.companyAddress, 'Company Street');
    assert.equal(row.action, 'update'); assert.equal(row.id, 'owner-1');
    assert.equal('contacts' in row, false);
    editor.remove(editor.rows[0]);
    assert.equal(editor.submissionRows()[0].action, 'remove');
    assert.equal('name' in editor.submissionRows()[0], false);
});

test('numeric fields preserve leading zeroes and uploads require reselection after draft recovery', () => {
    const storage = new Map(); const create = factory(storage); const editor = create();
    const row = editor.rows[0]; row.phone = '0012 +345'; editor.markChanged(row, 'phone');
    assert.equal(row.phone, '0012345');
    editor.setUpload(row, 'ssmCertificate', { files: [{ name: 'ssm.pdf', type: 'application/pdf' }] });
    assert.equal(editor.isFieldRequired(row, 'companyName'), true);
    const restored = create();
    assert.equal(restored.rows[0].uploadNames.ssmCertificate, 'ssm.pdf');
    assert.equal(restored.rows[0].selectedUploads.ssmCertificate, undefined);
    assert.equal(restored.submissionRows()[0].fileFields[0], 'ssmCertificate');
});

test('valid submissions publish only changed owner payloads', () => {
    const editor = factory()(); Object.assign(editor.rows[0], values); editor.markChanged(editor.rows[0]);
    let payload = '';
    const form = { checkValidity: () => true, querySelector: () => ({ set value(value) { payload = value; } }) };
    editor.prepareSubmission({ currentTarget: form, preventDefault() { assert.fail('Valid submission was blocked'); } });
    assert.equal(editor.saving, true);
    assert.equal(JSON.parse(payload).length, 1);
    assert.equal(JSON.parse(payload)[0].name, 'Owner');
});

test('permissions can disable new entry rows while existing owner rows remain editable', () => {
    const editor = factory()([{ key: 'owner-1', ...values }], 'owner-draft', false);
    assert.equal(editor.rows.length, 1);
    editor.rows[0].name = 'Updated'; editor.markChanged(editor.rows[0]);
    assert.equal(editor.rows.length, 1); assert.equal(editor.counts().updated, 1);
});
