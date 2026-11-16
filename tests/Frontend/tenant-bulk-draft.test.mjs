import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = ['bulk-editor.js', 'tenant-bulk-editor.js'].map(file => readFileSync(new URL('../../resources/js/' + file, import.meta.url), 'utf8').replace(/^import .*;$/gm, '').replace(/export function /g, 'function ')).join('\n');

test('Shift wheel moves the grid horizontally while ordinary wheel and zoom stay native', () => {
    const editor = editorFactory()();
    const container = { scrollWidth: 2000, clientWidth: 600, scrollLeft: 100 };
    let prevented = false;
    const event = { shiftKey: true, ctrlKey: false, deltaX: 0, deltaY: 40, deltaMode: 0, currentTarget: container, preventDefault() { prevented = true; } };
    editor.scrollHorizontally(event);
    assert.equal(container.scrollLeft, 140);
    assert.equal(prevented, true);
    editor.scrollHorizontally({ ...event, deltaX: -30, deltaY: 0 });
    assert.equal(container.scrollLeft, 110);
    editor.scrollHorizontally({ ...event, deltaY: 2, deltaMode: 1 });
    assert.equal(container.scrollLeft, 142);
    prevented = false;
    editor.scrollHorizontally({ ...event, shiftKey: false });
    editor.scrollHorizontally({ ...event, ctrlKey: true });
    assert.equal(container.scrollLeft, 142);
    assert.equal(prevented, false);
});

function editorFactory(storage = new Map(), browser = {}) {
    const context = vm.createContext({ URLSearchParams, AbortController: browser.AbortController, document: browser.document || { activeElement: null }, clearTimeout: browser.clearTimeout || clearTimeout, setTimeout: browser.setTimeout || setTimeout, fetch: browser.fetch, window: browser.window, URL: { createObjectURL: file => 'blob:preview-' + file.name, revokeObjectURL() {} }, sessionStorage: {
        getItem: key => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
        removeItem: key => storage.delete(key),
    } });
    vm.runInContext(source, context);
    return (rows = [], canCreate = true, key = 'account-1:page-1', saved = false, operation = null, config = {}, errors = {}, gridConfig = {}) => {
        const editor = context.tenantBulkEditor(rows, canCreate, key, saved, 1, operation, config, errors, gridConfig);
        editor.$nextTick = callback => callback();
        editor.$dispatch = browser.dispatch || (() => {});
        editor.init();
        return editor;
    };
}

function animationClock() {
    let next = 1;
    const pending = new Map();
    return {
        setTimeout(callback) { const id = next++; pending.set(id, callback); return id; },
        clearTimeout(id) { pending.delete(id); },
        step() { const [id, callback] = pending.entries().next().value; pending.delete(id); callback(); },
        drain() { let steps = 0; while (pending.size && steps++ < 1000) this.step(); assert.equal(pending.size, 0); },
        pending,
    };
}

test('loading another tenant batch preserves edits, deduplicates records and keeps new rows last', async () => {
    const editor = editorFactory(new Map(), {
        fetch: async () => ({ ok: true, json: async () => ({ rows: [{ key: 'tenant-1', name: 'Server original' }, { key: 'tenant-2', name: 'Next' }], nextCursor: null }) }),
    })([{ key: 'tenant-1', name: 'Original' }], true, 'draft', false, null, {}, {}, { rowsUrl: '/tenants/bulk-edit', nextCursor: 'tenant-1' });
    editor.rows[0].name = 'Edited';
    editor.markChanged(editor.rows[0]);
    editor.rows[1].name = 'New tenant';
    editor.markChanged(editor.rows[1]);
    await editor.loadMoreRows();
    assert.equal(editor.rows[0].name, 'Edited');
    assert.equal(editor.rows[1].key, 'tenant-2');
    assert.equal(editor.rows[2].name, 'New tenant');
    assert.equal(editor.hasRowData(editor.rows.at(-1)), false);
    assert.equal(editor.nextCursor, null);
    assert.equal(editor.counts().updated, 1);
    assert.equal(editor.counts().created, 1);
});

test('concurrent scroll events make one request and failed loads preserve the cursor for retry', async () => {
    let release;
    let requests = 0;
    const editor = editorFactory(new Map(), {
        fetch: () => { requests++; return new Promise(resolve => { release = resolve; }); },
    })([], true, 'draft', false, null, {}, {}, { rowsUrl: '/rows', nextCursor: 'cursor-1' });
    const first = editor.loadMoreRows();
    await editor.loadMoreRows();
    assert.equal(requests, 1);
    release({ ok: false });
    await first;
    assert.equal(editor.nextCursor, 'cursor-1');
    assert.equal(editor.rowsLoadFailed, true);
    assert.equal(editor.loadingRows, false);
    editor.rowsLoadFailed = false;
    const retry = editor.loadMoreRows();
    release({ ok: true, json: async () => ({ rows: [{ key: 'tenant-1', name: 'Loaded' }], nextCursor: null }) });
    await retry;
    assert.equal(editor.rows[0].key, 'tenant-1');
    assert.equal(requests, 2);
});

test('downward wheel at the bottom requests more tenants without blocking native scrolling', () => {
    const editor = editorFactory()();
    let requested = 0;
    editor.loadMoreRows = () => { requested++; };
    const event = { shiftKey: false, ctrlKey: false, deltaY: 20, currentTarget: { scrollHeight: 2000, scrollTop: 1400, clientHeight: 600 }, preventDefault() { assert.fail('Normal scrolling must remain native'); } };
    editor.scrollHorizontally(event);
    assert.equal(requested, 1);
    editor.scrollHorizontally({ ...event, ctrlKey: true });
    editor.scrollHorizontally({ ...event, deltaY: -20 });
    editor.scrollHorizontally({ ...event, currentTarget: { scrollHeight: 2000, scrollTop: 100, clientHeight: 600 } });
    assert.equal(requested, 1);
});

test('drafts from later batches are restored from authorized server records before saving', async () => {
    const storage = new Map([['draft', JSON.stringify([{ key: 'tenant-80', name: 'Draft edit', changed: true, version: 'original-version', isNew: false, contacts: [] }])]]);
    const requests = [];
    const editor = editorFactory(storage, {
        fetch: async url => { requests.push(url); return { ok: true, json: async () => ({ rows: [{ key: 'tenant-80', name: 'Saved name', existingFiles: { icPhoto: { name: 'old.pdf', url: '/preview' } } }], nextCursor: null }) }; },
    })([{ key: 'tenant-1', name: 'First' }], true, 'draft', false, null, {}, {}, { rowsUrl: '/rows', nextCursor: 'tenant-50' });
    assert.equal(editor.restoringDraft, true);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(editor.restoringDraft, false);
    assert.equal(editor.pendingDraftRows.length, 0);
    assert.equal(editor.rows[1].name, 'Draft edit');
    assert.equal(editor.rows[1].version, 'original-version');
    assert.equal(editor.rows[1].existingFiles.icPhoto.name, 'old.pdf');
    assert.equal(editor.nextCursor, 'tenant-50');
    assert.equal(requests[0].includes('ids%5B%5D=tenant-80'), true);
    assert.equal(editor.submissionRows()[0].id, 'tenant-80');
});

test('unrestored drafts survive network errors and records no longer accessible are excluded', async () => {
    const storage = new Map([['draft', JSON.stringify([{ key: 'tenant-80', name: 'Pending', changed: true, isNew: false, contacts: [] }])]]);
    let fail = true;
    const editor = editorFactory(storage, {
        fetch: async () => { if (fail) throw new Error('Offline'); return { ok: true, json: async () => ({ rows: [], nextCursor: null }) }; },
    })([], true, 'draft', false, null, {}, {}, { rowsUrl: '/rows' });
    await new Promise(resolve => setImmediate(resolve));
    editor.persistDraft();
    assert.equal(JSON.parse(storage.get('draft'))[0].key, 'tenant-80');
    assert.equal(editor.pendingDraftRows.length, 1);
    assert.equal(editor.rowsLoadFailed, true);
    fail = false;
    editor.retryLoadingRows();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(editor.pendingDraftRows.length, 0);
    assert.equal(editor.rows.some(row => row.key === 'tenant-80'), false);
    assert.equal(storage.has('draft'), false);
});

test('returned errors match submitted rows and uploads after draft keys are regenerated', () => {
    const storage = new Map();
    const create = editorFactory(storage);
    const editor = create([{ key: 'unchanged', name: 'Existing' }]);
    const row = editor.rows[1];
    Object.assign(row, { name: 'New', submittedIndex: 0, submittedUploadKey: 'previous-key', submittedContactIndexes: [1] });
    row.contacts = [{ name: '', phone: '', relationship: '' }, { name: 'Parent', phone: '' }];
    editor.markChanged(row);
    const restored = create([{ key: 'unchanged', name: 'Existing' }], true, 'account-1:page-1', false, null, {}, {
        'rows.0.gender': ['Choose gender.'],
        'rows.0.contacts.0.phone': ['Enter contact phone.'],
        'uploads.previous-key.icPhoto': ['Invalid image.'],
    });
    assert.equal(restored.rowErrors(restored.rows[0]).length, 0);
    const draft = restored.rows[1];
    assert.equal(restored.fieldHasErrors(draft, 'gender'), true);
    assert.equal(restored.fieldHasErrors(draft, 'contacts.phone'), true);
    assert.equal(restored.fieldHasErrors(draft, 'icPhoto'), true);
    assert.equal(restored.rowErrors(draft)[1].field, 'contacts.1.phone');
    restored.clearCellErrors(draft, 'gender');
    assert.equal(restored.fieldHasErrors(draft, 'gender'), false);
    assert.equal(restored.rowErrors(draft).length, 2);
});

test('off-screen error indicators switch sides and clicking selects the contact and focuses its field', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    row.contacts = [{ name: 'First', phone: '1' }, { name: 'Second', phone: '2' }, { name: '', phone: '' }];
    editor.setFieldError(row, 'contacts.1.phone', ['Invalid phone.']);
    let rect = { left: 1100, right: 1300 };
    let focused = false;
    let scrolled = false;
    const cell = { dataset: { errorField: 'contacts.phone' }, getBoundingClientRect: () => rect,
        scrollIntoView: options => { scrolled = options.inline === 'center'; },
        querySelector: () => ({ focus: () => { focused = true; } }) };
    const body = { dataset: { bulkRowBody: row.key }, querySelectorAll: () => [cell] };
    editor.$refs = { gridViewport: { clientWidth: 900, getBoundingClientRect: () => ({ left: 0, right: 900 }), querySelectorAll: () => [body] } };
    editor.updateErrorVisibility();
    assert.equal(editor.errorSides[row.key].right.field, 'contacts.1.phone');
    editor.focusRowError(row, editor.errorSides[row.key].right);
    assert.equal(row.activeContactIndex, 1);
    assert.equal(focused && scrolled, true);
    rect = { left: -100, right: 60 };
    editor.updateErrorVisibility();
    assert.equal(editor.errorSides[row.key].left.field, 'contacts.1.phone');
    rect = { left: 200, right: 400 };
    editor.updateErrorVisibility();
    assert.equal(editor.errorSides[row.key], undefined);
});

test('editing one contact does not hide errors belonging to another contact', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    editor.setFieldError(row, 'contacts.0.phone', ['First phone.']);
    editor.setFieldError(row, 'contacts.1.phone', ['Second phone.']);
    editor.clearCellErrors(row, 'contacts.phone');
    assert.equal(editor.rowErrors(row).length, 1);
    assert.equal(editor.rowErrors(row)[0].field, 'contacts.1.phone');
});

test('changing identity and tenant type clears errors for fields that no longer apply', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    for (const field of ['ic', 'passport', 'nationality', 'name']) editor.setFieldError(row, field, ['Required.']);
    row.ic = '900101145678';
    editor.markChanged(row, 'ic');
    assert.equal(editor.fieldHasErrors(row, 'passport'), false);
    assert.equal(editor.fieldHasErrors(row, 'nationality'), false);
    editor.setFieldError(row, 'gender', ['Required.']);
    row.type = 'corporate';
    editor.markChanged(row, 'type');
    assert.equal(editor.fieldHasErrors(row, 'gender'), false);
    assert.equal(editor.fieldHasErrors(row, 'name'), true);
});

test('required checks block saving before queuing and catch all missing fields including gender and inactive contacts', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    Object.assign(row, { name: 'Tenant', phone: '0123456789', ic: '900101145678' });
    row.contacts = [{ name: 'Parent', phone: '' }, { name: '', phone: '0199999999' }, { name: '', phone: '' }];
    row.activeContactIndex = 2;
    editor.markChanged(row, 'ic');
    let prevented = false;
    editor.prepareSubmission({ currentTarget: { checkValidity: () => true }, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(editor.saving, false);
    assert.equal(editor.fieldHasErrors(row, 'gender'), true);
    assert.equal(editor.rowErrors(row).some(error => error.field === 'contacts.0.phone'), true);
    assert.equal(editor.rowErrors(row).some(error => error.field === 'contacts.1.name'), true);
    assert.equal(editor.rowErrors(row).some(error => error.field === 'contacts.0.relationship'), true);
    assert.equal(editor.rowErrors(editor.rows.at(-1)).length, 0);
});

test('valid required fields allow blank login email, bank details and the trailing empty contact', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    Object.assign(row, { name: 'Tenant', phone: '0123456789', ic: '900101145678', gender: 'Male' });
    row.contacts = [{ name: 'Parent', phone: '0199999999', relationship: 'Parent' }, { name: '', phone: '', relationship: '' }];
    editor.markChanged(row, 'ic');
    row.activeContactIndex = 1;
    assert.equal(editor.isContactFieldRequired(row, 'name'), false);
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), true);
    row.activeContactIndex = 0;
    assert.equal(editor.isContactFieldRequired(row, 'phone'), true);
    assert.equal(editor.isContactFieldRequired(row, 'relationship'), true);
    row.contacts[0].relationship = '';
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), false);
    assert.equal(editor.fieldHasErrors(row, 'contacts.relationship'), true);
});

test('submitting valid rows keeps the saving modal closed until server validation succeeds', () => {
    const events = [];
    const editor = editorFactory(new Map(), { dispatch: name => events.push(name) })();
    const row = editor.rows[0];
    Object.assign(row, { name: 'Tenant', phone: '0123456789', ic: '900101145678', gender: 'Male' });
    Object.assign(row.contacts[0], { name: 'Contact', relationship: 'Parent', phone: '0199999999' });
    editor.markChanged(row, 'ic');
    const payload = { value: '' };
    let prevented = false;
    editor.prepareSubmission({ currentTarget: { checkValidity: () => true, querySelector: () => payload }, preventDefault() { prevented = true; } });
    assert.equal(prevented, false);
    assert.equal(editor.saving, true);
    assert.equal(JSON.parse(payload.value).length, 1);
    assert.equal(events.includes('open-tenant-bulk-saving'), false);
});

test('corporate required checks skip individual fields, require emergency contacts and allow removals', () => {
    const editor = editorFactory()([{ key: 'remove-me', name: 'Existing', removed: true }]);
    const row = editor.rows[1];
    Object.assign(row, { type: 'corporate', name: 'Representative', phone: '0123456789' });
    editor.markChanged(row, 'type');
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), false);
    assert.equal(editor.fieldHasErrors(row, 'companyName'), true);
    assert.equal(editor.fieldHasErrors(row, 'ssmNew'), true);
    assert.equal(editor.fieldHasErrors(row, 'companyAddress'), true);
    assert.equal(editor.fieldHasErrors(row, 'gender'), false);
    assert.equal(editor.fieldHasErrors(row, 'contacts.name'), true);
    assert.equal(editor.rowErrors(editor.rows[0]).length, 0);
    Object.assign(row, { companyName: 'Company', ssmNew: '123', companyAddress: 'Address' });
    Object.assign(row.contacts[0], { name: 'Contact', phone: '0199999999', relationship: 'Parent' });
    assert.equal(editor.validateBeforeSave({ checkValidity: () => true }), true);
});

test('partial bank details require the complete group and existing accounts retain their number', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    row.bankName = 'Bank';
    editor.markChanged(row);
    assert.equal(editor.isFieldRequired(row, 'accountHolder'), true);
    assert.equal(editor.isFieldRequired(row, 'accountNumber'), true);
    row.accountLastFour = '1234';
    assert.equal(editor.isFieldRequired(row, 'accountNumber'), false);
});

test('reload restores edits, new rows, contacts, and pending removals', () => {
    const storage = new Map();
    const create = editorFactory(storage);
    const rows = [{ key: 'tenant-1', name: 'Original' }, { key: 'tenant-2', name: 'Other' }];
    const first = create(rows);
    first.rows[0].name = 'Updated';
    Object.assign(first.rows[0].contacts[0], { name: 'Contact', phone: '123', relationship: 'Sibling' });
    first.markChanged(first.rows[0]);
    first.rows[2].name = 'New tenant';
    first.markChanged(first.rows[2]);
    first.remove(first.rows[1]);

    const restored = create(rows);
    assert.equal(restored.rows[0].name, 'Updated');
    assert.equal(restored.rows[0].contacts[0].name, 'Contact');
    assert.equal(restored.rows[1].removed, true);
    assert.equal(restored.rows[2].name, 'New tenant');
    assert.equal(restored.rows.length, 4);
    assert.equal(restored.hasRowData(restored.rows[3]), false);
    assert.equal(new Set(restored.rows.map(row => row.key)).size, restored.rows.length);
    assert.equal(restored.draftStored, true);
});

test('drafts remain separated by account/page and inaccessible records are not restored', () => {
    const storage = new Map();
    const create = editorFactory(storage);
    const first = create([{ key: 'tenant-1', name: 'Original' }]);
    first.rows[0].name = 'Edited';
    first.markChanged(first.rows[0]);
    assert.equal(create([], true, 'account-2:page-1').counts().created, 0);
    assert.equal(create([]).rows.some(row => row.key === 'tenant-1'), false);
    first.rows[1].name = 'Draft tenant';
    first.markChanged(first.rows[1]);
    assert.equal(create([], false).rows.length, 0);
});

test('empty rows are excluded and removing a new draft clears its saved entry', () => {
    const storage = new Map();
    const create = editorFactory(storage);
    const editor = create();
    editor.persistDraft();
    assert.equal(storage.size, 0);
    editor.rows[0].name = 'Draft tenant';
    editor.markChanged(editor.rows[0]);
    assert.equal(JSON.parse(storage.values().next().value).length, 1);
    editor.remove(editor.rows[0]);
    assert.equal(storage.size, 0);
});

test('unavailable storage preserves editing and indicates the draft is not stored', () => {
    const storage = { get() { throw new Error('Blocked'); }, set() { throw new Error('Blocked'); } };
    const editor = editorFactory(storage)();
    editor.rows[0].name = 'Unsaved';
    editor.markChanged(editor.rows[0]);
    assert.equal(editor.rows[0].name, 'Unsaved');
    assert.equal(editor.rows.length, 2);
    assert.equal(editor.draftStored, false);
});

test('IC disables passport, sets Malaysian nationality, and unlocking permits passport entry', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    assert.equal(row.nationality, '');
    row.ic = '900101-14-5678';
    editor.markChanged(row, 'ic');
    assert.equal(row.ic, '900101145678');
    assert.equal(row.nationality, 'MALAYSIAN');
    assert.equal(editor.isCellDisabled(row, 'passport'), true);
    row.ic = '';
    editor.markChanged(row, 'ic');
    assert.equal(row.nationality, '');
    assert.equal(editor.isCellDisabled(row, 'passport'), false);
    row.passport = 'ab-123';
    editor.markChanged(row, 'passport');
    assert.equal(row.passport, 'AB123');
    assert.equal(editor.isCellDisabled(row, 'ic'), true);
    row.nationality = 'SINGAPOREAN';
    editor.markChanged(row, 'nationality');
    assert.equal(row.nationality, 'SINGAPOREAN');
    row.passport = '';
    editor.markChanged(row, 'passport');
    assert.equal(editor.isCellDisabled(row, 'ic'), false);
});

test('corporate rows disable personal fields and retain general contact and bank fields', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    assert.equal(editor.isCellDisabled(row, 'companyName'), true);
    assert.equal(editor.isFieldRequired(row, 'name'), false);
    row.ic = '900101145678';
    editor.markChanged(row, 'ic');
    row.type = 'corporate';
    editor.markChanged(row, 'type');
    for (const field of ['ic', 'passport', 'nationality', 'gender', 'occupation']) {
        assert.equal(row[field], '');
        assert.equal(editor.isCellDisabled(row, field), true);
        assert.equal(editor.isFieldRequired(row, field), false);
    }
    assert.equal(editor.isCellDisabled(row, 'companyName'), false);
    assert.equal(editor.isFieldRequired(row, 'companyName'), true);
    assert.equal(editor.isFieldRequired(row, 'ssmNew'), true);
    assert.equal(editor.isFieldRequired(row, 'ssmOld'), false);
    assert.equal(editor.isCellDisabled(row, 'phone'), false);
    assert.equal(editor.isCellDisabled(row, 'bankName'), false);
});

test('restored IC drafts keep automation and one empty row', () => {
    const create = editorFactory();
    const editor = create();
    editor.rows[0].ic = '900101145678';
    editor.markChanged(editor.rows[0], 'ic');
    const restored = create();
    assert.equal(restored.rows[0].nationality, 'MALAYSIAN');
    assert.equal(restored.isCellDisabled(restored.rows[0], 'passport'), true);
    assert.equal(restored.rows.length, 2);
    assert.equal(restored.rows[1].nationality, '');
    assert.equal(restored.counts().created, 1);
});

test('empty contact fields are available immediately without creating a pending tenant', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    assert.equal(row.contacts.length, 1);
    assert.equal(editor.hasRowData(row), false);
    row.contacts[0].name = 'Emergency contact';
    editor.markChanged(row);
    assert.equal(editor.rows.length, 2);
    assert.equal(editor.counts().created, 1);
});

test('contact entry appends one empty contact and removal preserves remaining contacts and draft', () => {
    const create = editorFactory();
    const editor = create([{ key: 'tenant-1', name: 'Tenant', contacts: [{ name: 'First', phone: '123', relationship: 'Parent' }] }]);
    const row = editor.rows[0];
    assert.equal(row.contacts.length, 2);
    row.contacts[1].phone = '456';
    editor.markChanged(row);
    assert.equal(row.contacts.length, 3);
    editor.markChanged(row);
    assert.equal(row.contacts.length, 3);
    editor.removeContact(row, 0);
    assert.equal(row.contacts.length, 2);
    assert.equal(row.contacts[0].phone, '456');
    const restored = create([{ key: 'tenant-1', name: 'Tenant' }]);
    assert.equal(restored.rows[0].contacts[0].phone, '456');
    assert.equal(restored.rows[0].contacts.length, 2);
    editor.removeContact(row, 0);
    assert.equal(row.contacts.length, 1);
    assert.equal(row.contacts[0].phone, '');
    editor.removeContact(row, 0);
    assert.equal(row.contacts.length, 1);
});

test('contact navigation keeps the active editor on one contact and restores its selection', () => {
    const create = editorFactory();
    const editor = create();
    const row = editor.rows[0];
    row.contacts[0].name = 'First';
    editor.markChanged(row);
    assert.equal(row.activeContactIndex, 0);
    editor.selectContact(row, 1);
    row.contacts[1].name = 'Second';
    editor.markChanged(row);
    assert.equal(row.contacts.length, 3);
    assert.equal(row.activeContactIndex, 1);
    const restored = create();
    assert.equal(restored.rows[0].activeContactIndex, 1);
    assert.equal(restored.rows[0].contacts[1].name, 'Second');
    editor.removeContact(row, 0);
    assert.equal(row.activeContactIndex, 0);
    assert.equal(row.contacts[0].name, 'Second');
    editor.selectContact(row, 99);
    assert.equal(row.activeContactIndex, row.contacts.length - 1);
    editor.removeContact(row, row.activeContactIndex);
    assert.ok(row.activeContactIndex < row.contacts.length);
});

test('choosing a contact from the overview selects it, closes the overview, and focuses its name', () => {
    const editor = editorFactory()([{ key: 'tenant-1', contacts: [{ name: 'First' }, { name: 'Second' }] }]);
    let focused = false;
    editor.$refs = { bulkSheet: { querySelectorAll: () => [{ dataset: { bulkRow: 'tenant-1' }, focus() { focused = true; } }] } };
    editor.contactPopover.key = 'tenant-1';
    editor.editContact(1);
    assert.equal(editor.rows[0].activeContactIndex, 1);
    assert.equal(editor.rows[0].contacts[1].name, 'Second');
    assert.equal(editor.contactPopover.key, null);
    assert.equal(focused, true);
    assert.equal(editor.rows[0].changed, false);
});

test('numeric phone fields strip non-digits, preserve leading zeroes, and respect length limits', () => {
    const editor = editorFactory()();
    const row = editor.rows[0];
    row.phone = '012-345 abc6789';
    editor.markChanged(row, 'phone');
    assert.equal(row.phone, '0123456789');
    row.contacts[0].phone = '+012 (345) abc6789';
    editor.markChanged(row, 'contactPhone');
    assert.equal(row.contacts[0].phone, '0123456789');
    row.phone = '0'.repeat(25);
    editor.markChanged(row, 'phone');
    assert.equal(row.phone.length, 20);
    row.contacts[0].phone = '1'.repeat(25);
    editor.markChanged(row, 'contactPhone');
    assert.equal(row.contacts[0].phone.length, 20);
    row.companyPhone = '+60 12-345';
    editor.markChanged(row, 'companyPhone');
    assert.equal(row.companyPhone, '6012345');
    row.accountNumber = '0012 ABC-345';
    editor.markChanged(row, 'accountNumber');
    assert.equal(row.accountNumber, '0012345');
});

test('gender selection initializes existing values and bank fields remain optional', () => {
    const editor = editorFactory()([{ key: 'tenant-1', gender: 'MALE' }]);
    const row = editor.rows[0];
    assert.equal(row.gender, 'Male');
    assert.equal(row.genderSelection[0], 'Male');
    assert.equal(row.changed, false);
    for (const field of ['bankName', 'bankCode', 'accountHolder', 'accountNumber']) {
        assert.equal(editor.isFieldRequired(row, field), false);
    }
    row.gender = 'Female';
    editor.markChanged(row, 'gender');
    row.type = 'corporate';
    editor.markChanged(row, 'type');
    assert.equal(row.gender, '');
    assert.equal(row.genderSelection.length, 0);
});

test('save payload includes only pending rows and excludes placeholder contacts', () => {
    const editor = editorFactory()([{ key: 'existing-1', name: 'Existing', version: 'version' }]);
    assert.equal(editor.submissionRows().length, 0);
    editor.rows[1].name = 'New tenant';
    editor.rows[1].contacts[0].name = 'Contact';
    editor.markChanged(editor.rows[1]);
    const payload = editor.submissionRows();
    assert.equal(payload.length, 1);
    assert.equal(payload[0].action, 'create');
    assert.equal(payload[0].contacts.length, 1);
    assert.equal('typeSelection' in payload[0], false);
    editor.remove(editor.rows[0]);
    assert.equal(editor.submissionRows()[0].action, 'remove');
    assert.equal('name' in editor.submissionRows()[0], false);
});

test('draft clears only after successful save, while failures keep unsaved input', () => {
    const storage = new Map();
    const create = editorFactory(storage);
    const editor = create();
    editor.rows[0].name = 'New tenant';
    editor.markChanged(editor.rows[0]);
    assert.equal(create().rows[0].name, 'New tenant');
    const afterSuccess = create([], true, 'account-1:page-1', true);
    assert.equal(afterSuccess.rows[0].name, '');
    assert.equal(afterSuccess.hasChanges(), false);
    assert.equal(storage.size, 0);
});

test('optional upload selection creates a pending row and draft recovery requests reselection', () => {
    const create = editorFactory();
    const editor = create();
    const row = editor.rows[0];
    const input = { files: [{ name: 'identity.pdf' }], value: 'selected' };
    editor.setUpload(row, 'icPhoto', input);
    assert.equal(editor.rows.length, 2);
    assert.equal(row.selectedUploads.icPhoto, true);
    assert.equal(editor.submissionRows()[0].fileFields[0], 'icPhoto');
    const restored = create();
    assert.equal(restored.rows[0].uploadNames.icPhoto, 'identity.pdf');
    assert.equal(restored.rows[0].selectedUploads.icPhoto, undefined);
    assert.equal(restored.submissionRows()[0].fileFields[0], 'icPhoto');
    editor.clearUpload(row, 'icPhoto', input);
    assert.equal(input.value, '');
    assert.equal(editor.counts().created, 0);
    assert.equal(editor.isCellDisabled(row, 'companyLogo'), true);
    row.type = 'corporate';
    editor.markChanged(row, 'type');
    assert.equal(editor.isCellDisabled(row, 'icPhoto'), true);
    assert.equal(editor.isCellDisabled(row, 'ssmCertificate'), false);
});

test('the shared popup previews stored files and replacements without persisting blob URLs', () => {
    const storage = new Map();
    const create = editorFactory(storage);
    const original = { key: 'tenant-1', name: 'Tenant', existingFiles: { icPhoto: { url: '/stored/document', name: 'original.pdf', isPdf: true } } };
    const editor = create([original]);
    const row = editor.rows[0];
    editor.previewUpload(row, 'icPhoto');
    assert.equal(editor.openReceipt, true);
    assert.equal(editor.receiptImage, '/stored/document');
    assert.equal(editor.isPdf, true);
    const input = { files: [{ name: 'replacement.png', type: 'image/png' }], value: 'selected' };
    editor.setUpload(row, 'icPhoto', input);
    editor.previewUpload(row, 'icPhoto');
    assert.equal(editor.receiptImage, 'blob:preview-replacement.png');
    assert.equal(editor.isPdf, false);
    assert.equal(storage.values().next().value.includes('blob:'), false);
    const refreshed = create([{ ...original, existingFiles: { icPhoto: { url: '/stored/current', name: 'current.pdf', isPdf: true } } }]);
    assert.equal(refreshed.uploadPreview(refreshed.rows[0], 'icPhoto').url, '/stored/current');
    editor.clearUpload(row, 'icPhoto', input);
    assert.equal(editor.uploadPreview(row, 'icPhoto').url, '/stored/document');
});

test('saving modal receives progress and redirects to session completion after success', async () => {
    const states = [{ status: 'processing', processed: 150, total: 300, percent: 50 }, { status: 'succeeded', processed: 300, total: 300, percent: 100 }];
    const navigations = [];
    const events = [];
    const clock = animationClock();
    const create = editorFactory(new Map(), {
        setTimeout: callback => clock.setTimeout(callback), clearTimeout: id => clock.clearTimeout(id),
        fetch: async () => ({ ok: true, status: 200, json: async () => states.shift() }),
        window: { location: { assign: url => navigations.push(url) } }, dispatch: name => events.push(name),
    });
    const editor = create([], true, 'draft', false, null, { statusUrl: '/progress/__KEY__', completeUrl: '/complete/__KEY__' });
    editor.operationKey = 'operation-1';
    editor.saving = true;
    await editor.pollProgress();
    assert.equal(editor.progress.processed, 150);
    assert.equal(editor.progress.percent, 50);
    assert.equal(editor.saving, true);
    clock.clearTimeout(editor.progressTimer);
    await editor.pollProgress();
    assert.equal(editor.saving, true);
    assert.equal(navigations.length, 0);
    clock.drain();
    assert.equal(editor.displayedProcessed, 300);
    assert.equal(editor.displayedPercent, 100);
    assert.equal(editor.saving, false);
    assert.equal(events[0], 'close-tenant-bulk-saving');
    assert.equal(navigations[0], '/complete/operation-1');
    editor.destroy();
});

test('progress counts increase one at a time and stop below 100 until the server confirms success', () => {
    const clock = animationClock();
    const navigations = [];
    const editor = editorFactory(new Map(), {
        setTimeout: callback => clock.setTimeout(callback), clearTimeout: id => clock.clearTimeout(id),
        window: { location: { assign: url => navigations.push(url) } },
    })();
    editor.progress = { status: 'processing', processed: 60, total: 60, percent: 99 };
    editor.animateProgress();
    assert.equal(editor.displayedProcessed, 1);
    clock.step();
    assert.equal(editor.displayedProcessed, 2);
    assert.equal(editor.displayedPercent, 2);
    clock.drain();
    assert.equal(editor.displayedProcessed, 60);
    assert.equal(editor.displayedPercent, 99);
    assert.equal(navigations.length, 0);
    editor.destroy();
});

test('a save completed before the modal opens still counts from one to one hundred at a steady pace', () => {
    const clock = animationClock();
    const delays = [];
    const navigations = [];
    const editor = editorFactory(new Map(), {
        setTimeout: (callback, delay) => { delays.push(delay); return clock.setTimeout(callback); },
        clearTimeout: id => clock.clearTimeout(id),
        window: { location: { assign: url => navigations.push(url) } },
        fetch: () => assert.fail('An already confirmed success does not need another status request'),
    })([], true, 'draft', false, 'fast-save', {
        completeUrl: '/complete/__KEY__', initialProgress: { status: 'succeeded', processed: 10, total: 10, percent: 100 },
    });
    assert.equal(editor.displayedPercent, 1);
    assert.equal(editor.saving, true);
    assert.equal(navigations.length, 0);
    for (let percent = 2; percent <= 100; percent++) {
        clock.step();
        assert.equal(editor.displayedPercent, percent);
    }
    assert.equal(navigations.length, 0);
    assert.equal(delays.slice(0, -1).every(delay => delay === 8), true);
    clock.step();
    assert.equal(navigations[0], '/complete/fast-save');
    assert.equal(editor.displayedProcessed, 10);
    editor.destroy();
});

test('a completed 300-row save animates all rows without slowing down at the end', () => {
    const clock = animationClock();
    const delays = [];
    const editor = editorFactory(new Map(), {
        setTimeout: (callback, delay) => { delays.push(delay); return clock.setTimeout(callback); },
        clearTimeout: id => clock.clearTimeout(id),
        window: { location: { assign() {} } },
    })([], true, 'draft', false, 'large-save', {
        completeUrl: '/complete/__KEY__', initialProgress: { status: 'succeeded', processed: 300, total: 300, percent: 100 },
    });
    clock.drain();
    assert.equal(editor.displayedProcessed, 300);
    assert.equal(editor.displayedPercent, 100);
    assert.equal(delays.reduce((sum, delay) => sum + delay, 0) < 1500, true);
    editor.destroy();
});

test('animation pauses at reported progress and leaving the page cancels pending animation', () => {
    const clock = animationClock();
    const editor = editorFactory(new Map(), {
        setTimeout: callback => clock.setTimeout(callback), clearTimeout: id => clock.clearTimeout(id),
    })();
    editor.progress = { status: 'processing', processed: 5, total: 60, percent: 8 };
    editor.animateProgress();
    clock.drain();
    assert.equal(editor.displayedProcessed, 5);
    assert.equal(editor.displayedPercent, 8);
    editor.progress.processed = 10;
    editor.progress.percent = 16;
    editor.animateProgress();
    assert.equal(clock.pending.size, 1);
    editor.destroy();
    assert.equal(clock.pending.size, 0);
});

test('reduced-motion preference displays confirmed progress without counting animation', () => {
    const clock = animationClock();
    const editor = editorFactory(new Map(), {
        setTimeout: callback => clock.setTimeout(callback), clearTimeout: id => clock.clearTimeout(id),
        window: { matchMedia: () => ({ matches: true }) },
    })();
    editor.progress = { status: 'processing', processed: 60, total: 60, percent: 99 };
    editor.animateProgress();
    assert.equal(editor.displayedProcessed, 60);
    assert.equal(editor.displayedPercent, 99);
    assert.equal(clock.pending.size, 0);
    editor.destroy();
});

test('failed saves cancel the animation and redirect without showing 100 percent', async () => {
    const clock = animationClock();
    const navigations = [];
    const editor = editorFactory(new Map(), {
        setTimeout: callback => clock.setTimeout(callback), clearTimeout: id => clock.clearTimeout(id),
        fetch: async () => ({ ok: true, status: 200, json: async () => ({ status: 'failed', processed: 0, total: 60, percent: 0 }) }),
        window: { location: { assign: url => navigations.push(url) } },
    })([], true, 'draft', false, null, { statusUrl: '/progress/__KEY__', completeUrl: '/complete/__KEY__' });
    editor.operationKey = 'operation-1';
    editor.progress = { status: 'processing', processed: 60, total: 60, percent: 99 };
    editor.animateProgress();
    await editor.pollProgress();
    assert.equal(clock.pending.size, 0);
    assert.equal(editor.displayedPercent < 100, true);
    assert.equal(navigations[0], '/complete/operation-1');
    editor.destroy();
});

test('temporary polling failures retain drafts and stop retrying after leaving the page', async () => {
    const storage = new Map();
    const create = editorFactory(storage, { fetch: async () => { throw new Error('Network unavailable'); } });
    const editor = create([], true, 'draft', false, null, { statusUrl: '/progress/__KEY__' });
    editor.rows[0].name = 'Unsaved tenant';
    editor.markChanged(editor.rows[0]);
    editor.operationKey = 'operation-1';
    editor.saving = true;
    await editor.pollProgress();
    assert.equal(editor.progress.status, 'reconnecting');
    assert.equal(editor.saving, true);
    assert.equal(storage.has('draft'), true);
    editor.destroy();
    assert.equal(editor.progressStopped, true);
});

test('repeated polling failures stop loading and route to a session message without declaring the save failed', async () => {
    const storage = new Map(); const clock = animationClock(); const navigations = [];
    const create = editorFactory(storage, { fetch: async () => { throw new Error('Offline'); },
        setTimeout: callback => clock.setTimeout(callback), clearTimeout: id => clock.clearTimeout(id),
        window: { location: { assign: url => navigations.push(url) } } });
    const editor = create([], true, 'draft', false, null, { statusUrl: '/progress/__KEY__', completeUrl: '/complete/__KEY__' });
    editor.rows[0].name = 'Unsaved'; editor.markChanged(editor.rows[0]); editor.operationKey = 'operation-1'; editor.saving = true;
    for (let i = 0; i < 8; i++) await editor.pollProgress();
    assert.equal(editor.progressStopped, true); assert.equal(editor.saving, false);
    assert.equal(editor.progress.status, 'reconnecting'); assert.equal(storage.has('draft'), true);
    assert.deepEqual(navigations, ['/complete/operation-1?progress_unavailable=1']);
});

test('hung progress requests abort after the request timeout and retry safely', async () => {
    const clock = animationClock();
    const create = editorFactory(new Map(), { AbortController,
        setTimeout: callback => clock.setTimeout(callback), clearTimeout: id => clock.clearTimeout(id),
        fetch: (_url, options) => new Promise((_resolve, reject) => options.signal.addEventListener('abort', () => reject(new Error('Aborted')))) });
    const editor = create([], true, 'draft', false, null, { statusUrl: '/progress/__KEY__' });
    editor.operationKey = 'operation-1'; editor.saving = true;
    const pending = editor.pollProgress(); clock.step(); await pending;
    assert.equal(editor.progress.status, 'reconnecting'); assert.equal(editor.progressFailures, 1);
    assert.equal(editor.saving, true); editor.destroy();
});

test('reloading an active operation starts with the server progress instead of zero', async () => {
    const create = editorFactory(new Map(), { fetch: async () => ({ ok: true, status: 200, json: async () => ({ status: 'processing', processed: 150, total: 300, percent: 50 }) }) });
    const editor = create([], true, 'draft', false, 'operation-1', {
        statusUrl: '/progress/__KEY__', initialProgress: { status: 'processing', processed: 150, total: 300, percent: 50 },
    });
    assert.equal(editor.saving, true);
    assert.equal(editor.progress.total, 300);
    assert.equal(editor.progress.percent, 50);
    editor.destroy();
    await Promise.resolve();
});

test('tenant browser drafts never retain full bank account numbers', () => {
    const storage = new Map(); const create = editorFactory(storage);
    const editor = create([{ key: 'tenant-1', name: 'Tenant', contacts: [], bankName: 'Bank', accountHolder: 'Tenant', accountLastFour: '5678' }]);
    editor.rows[0].accountNumber = '0012345678'; editor.markChanged(editor.rows[0], 'accountNumber');
    assert.equal(storage.get('account-1:page-1').includes('0012345678'), false);
    const restored = create([{ key: 'tenant-1', name: 'Tenant', contacts: [], bankName: 'Bank', accountHolder: 'Tenant', accountLastFour: '5678' }]);
    assert.equal(restored.rows[0].accountNumber, '');
    assert.equal(restored.isFieldRequired(restored.rows[0], 'accountNumber'), true);
});
