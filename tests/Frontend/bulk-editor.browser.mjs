import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { execFileSync, spawn } from 'node:child_process';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import assert from 'node:assert/strict';

mkdirSync('.tmp/bulk-browser', { recursive: true });
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = readFileSync('public/build/' + manifest['resources/css/app.css'].file, 'utf8');
const scripts = ['bulk-editor.js', 'tenant-bulk-editor.js', 'owner-bulk-editor.js', 'property-bulk-editor.js', 'bulk-grid-navigation.js', 'form-validation.js']
    .map(file => readFileSync('resources/js/' + file, 'utf8').replace(/^import .*;$/gm, '').replace(/export function /g, 'function ')).join('\n');
const alpine = readFileSync('node_modules/alpinejs/dist/cdn.js', 'utf8');
const paths = {};
for (const module of ['owner', 'tenant', 'property', 'form']) {
    const fixture = execFileSync('php', ['tests/Frontend/bulk-editor-fixture.php', module], { encoding: 'utf8' });
    paths[module] = resolve('.tmp/bulk-browser/' + module + '.html');
    writeFileSync(paths[module], `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>${css}\n[x-cloak]{display:none!important}</style><script>window.browserErrors=[];window.addEventListener('error',e=>browserErrors.push(e.message));${scripts}\nwindow.formValidation={focusFormErrors,focusInvalidForm};enableBulkGridNavigation();document.addEventListener('alpine:init',()=>{Alpine.data('ownerBulkEditor',ownerBulkEditor);Alpine.data('tenantBulkEditor',tenantBulkEditor);Alpine.data('propertyBulkEditor',propertyBulkEditor);});</script></head><body>${fixture}<script>${alpine}</script></body></html>`);
}
const browser = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--no-first-run', '--remote-debugging-port=9242', '--user-data-dir=' + resolve('.tmp/bulk-browser/edge'), 'about:blank'], { windowsHide: true, stdio: 'ignore' });
let ws;
try {
    let tabs;
    for (let i=0;i<50;i++) { try { tabs=await (await fetch('http://127.0.0.1:9242/json/list')).json(); break; } catch { await new Promise(r=>setTimeout(r,100)); } }
    assert.ok(tabs, 'Browser did not start');
    ws=new WebSocket(tabs.find(tab=>tab.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>ws.addEventListener('open',r,{once:true}));
    let next=1; const pending=new Map();
    ws.addEventListener('message',event=>{const result=JSON.parse(event.data);if(result.id){pending.get(result.id)?.(result);pending.delete(result.id);}});
    const call=(method,params={})=>new Promise(r=>{const id=next++;pending.set(id,r);ws.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>{const response=await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});assert.equal(response.result?.exceptionDetails,undefined,JSON.stringify(response));return response.result?.result?.value;};
    await call('Page.enable');
    for (const module of ['owner', 'tenant', 'property', 'form']) {
        await call('Emulation.setDeviceMetricsOverride',{width:1440,height:950,deviceScaleFactor:1,mobile:false});
        await call('Page.navigate',{url:pathToFileURL(paths[module]).href});
        for(let i=0;i<50;i++){if(await evaluate("!!window.Alpine && !!document.querySelector('[x-ref=bulkSheet], #validation-fixture')?._x_dataStack"))break;await new Promise(r=>setTimeout(r,100));}
        await new Promise(r=>setTimeout(r,150));
        assert.deepEqual(await evaluate('browserErrors'), [], module);
        if (module === 'form') {
            assert.equal(await evaluate('document.activeElement.name'), 'floor', 'Laravel errors receive initial focus');
            for (const floor of ['G', '0', '-2', '12']) {
                assert.equal(await evaluate(`(()=>{const field=document.querySelector('[name=floor]');field.value=${JSON.stringify(floor)};return field.checkValidity();})()`), true);
            }
            await evaluate("document.querySelector('[name=floor]').value='1.5';document.querySelector('button[type=submit]').click()");
            await evaluate('new Promise(resolve => queueMicrotask(resolve))');
            assert.equal(await evaluate('document.activeElement.name'), 'floor');
            await evaluate("document.querySelector('[name=floor]').value='G';document.querySelector('button[type=submit]').click()");
            await evaluate('new Promise(resolve => queueMicrotask(resolve))');
            assert.equal(await evaluate('document.activeElement.name'), 'room_no');
            assert.equal(await evaluate("Alpine.$data(document.querySelector('form')).loading"), false);
            await evaluate("formValidation.focusFormErrors(document.querySelector('form'),['owner_id'])");
            assert.equal(await evaluate("document.activeElement.closest('[x-data]').querySelector('[name=owner_id]').value"), 'owner-choice');
            assert.deepEqual(await evaluate('browserErrors'), []);
            continue;
        }
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'), true);
        assert.equal(await evaluate("document.querySelectorAll('[data-bulk-grid-row]').length"),module === 'property' ? 4 : 2);
        if (module !== 'tenant') {
            await evaluate("window.editor=Alpine.$data(document.querySelector('[x-ref=bulkSheet]'));document.querySelector('[data-error-field=state] input[type=text]').focus()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate("document.querySelector('[data-error-field=state] input[type=text]').value"), 'Perak');
            await evaluate("Array.from(document.querySelectorAll('[data-error-field=state] li')).find(el=>el.textContent.trim()==='Selangor').click()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate('editor.rows[0].state'), 'Selangor');
            assert.equal(await evaluate("document.querySelector('[data-error-field=state] input[type=text]').value"), 'Selangor');
            assert.equal(await evaluate('editor.submissionRows()[0].state'), 'Selangor');
            await evaluate("Array.from(document.querySelectorAll('[data-error-field=state] li')).find(el=>el.textContent.trim()==='Perak').click()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            await evaluate('editor.rows[0].changed = false; editor.persistDraft()');
            if (module === 'owner') {
                await evaluate("Array.from(document.querySelectorAll('[data-error-field=state] li')).find(el=>el.textContent.trim()==='Not specified').click()");
                await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
                assert.equal(await evaluate('editor.rows[0].state'), '');
                assert.equal(await evaluate('editor.submissionRows()[0].state'), '');
                await evaluate("Array.from(document.querySelectorAll('[data-error-field=state] li')).find(el=>el.textContent.trim()==='Perak').click()");
                await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
                await evaluate('editor.rows[0].changed = false; editor.persistDraft()');
            }
        }
        if (module === 'property') {
            await evaluate("window.editor=Alpine.$data(document.querySelector('[x-ref=bulkSheet]'))");
            assert.equal(await evaluate('editor.hasChanges()'), false);
            assert.equal(await evaluate("document.querySelector('[data-error-field=propertyType] input[type=text]').value"), 'Condo / Apartment');
            await evaluate("document.querySelector('[data-error-field=ownerId] input[type=text]').focus()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            await evaluate("Array.from(document.querySelectorAll('[data-error-field=ownerId] li')).find(el=>el.textContent.trim()==='No owner').click()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate('editor.rows[0].ownerId'), '');
            await evaluate("document.querySelector('[data-error-field=propertyType] input[type=text]').focus()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            await evaluate("Array.from(document.querySelectorAll('[data-error-field=propertyType] li')).find(el=>el.textContent.trim()==='Landed House').click()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate('editor.rows[0].propertyType'), 'Landed');
            assert.equal(await evaluate("document.querySelector('[data-error-field=propertyType] input[type=text]').value"), 'Landed House');
            assert.equal(await evaluate("editor.submissionRows()[0].ownerId === '' && editor.submissionRows()[0].propertyType === 'Landed'"), true);
            await evaluate("window.newProperty=editor.visibleRows('property').find(row=>row.isNew);Object.assign(newProperty,{name:'New Property',ownerId:'owner-choice',ownerIdSelection:['owner-choice'],propertyType:'Condo',propertyTypeSelection:['Condo'],address:'Main Street',postcode:'01234',city:'Ipoh',state:'Perak',stateSelection:['Perak']});editor.markChanged(newProperty);editor.switchEntity('unit')");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            await evaluate("document.querySelector('[role=tabpanel][aria-label=Units] [data-error-field=parentKey] input[type=text]').focus()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            await evaluate("Array.from(document.querySelectorAll('[role=tabpanel][aria-label=Units] [data-error-field=parentKey] li')).find(el=>el.textContent.trim()==='New Property').click()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate("editor.visibleRows('unit')[0].parentKey"),await evaluate('newProperty.key'));
            await evaluate("window.newUnit=editor.visibleRows('unit')[0];newUnit.unitNo='A-02';editor.markChanged(newUnit,'unitNo')");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            writeFileSync('.tmp/bulk-browser/units-desktop.png', Buffer.from((await call('Page.captureScreenshot',{format:'png'})).result.data,'base64'));
            await evaluate("editor.switchEntity('room')");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            await evaluate("document.querySelector('[role=tabpanel][aria-label=Rooms] [data-error-field=parentKey] input[type=text]').focus()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            await evaluate("Array.from(document.querySelectorAll('[role=tabpanel][aria-label=Rooms] [data-error-field=parentKey] li')).find(el=>el.textContent.trim()==='New Property > A-02').click()");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate("editor.visibleRows('room')[0].parentKey"),await evaluate('newUnit.key'));
            await evaluate("Object.assign(editor.visibleRows('room')[0],{roomNo:'101',roomType:'Single'});editor.markChanged(editor.visibleRows('room')[0])");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            writeFileSync('.tmp/bulk-browser/rooms-desktop.png', Buffer.from((await call('Page.captureScreenshot',{format:'png'})).result.data,'base64'));
            await evaluate("editor.switchEntity('property')");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate("editor.submissionRows().filter(row=>row.action==='create').length"),3);
            await evaluate("newProperty.city='';newUnit.floor='1.5';editor.visibleRows('room')[0].roomType='';editor.switchEntity('room');editor.validateBeforeSave(document.querySelector('form'))");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate('editor.activeEntity'), 'property');
            assert.equal(await evaluate("document.activeElement.closest('[data-error-field]').dataset.errorField"), 'city');
            assert.equal(await evaluate("Array.from(document.querySelectorAll('[role=tab] [aria-label=\"Validation errors\"]')).filter(el=>el.getClientRects().length).length"), 3);
            await evaluate("newProperty.city='Ipoh';newUnit.floor='G';editor.visibleRows('room')[0].roomType='Single';editor.validateBeforeSave(document.querySelector('form'))");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate("Array.from(document.querySelectorAll('[role=tab] [aria-label=\"Validation errors\"]')).filter(el=>el.getClientRects().length).length"), 0);
            await evaluate("newProperty.submittedIndex=0;newUnit.submittedIndex=1;editor.visibleRows('room')[0].submittedIndex=2;editor.applyServerErrors({'rows.2.roomNo':['Duplicate room number']})");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate('editor.activeEntity'), 'room');
            assert.equal(await evaluate("document.activeElement.closest('[data-error-field]').dataset.errorField"), 'roomNo');
            await evaluate('editor.fieldErrors={}');
            for (const entity of ['property', 'unit', 'room']) {
                await evaluate(`editor.switchEntity('${entity}');for(let i=0;i<70;i++){editor.rows.splice(editor.rows.length-1,0,editor.makeRow({entity:'${entity}',key:'sticky-${entity}-'+i,name:'Existing',unitNo:'A-'+i,roomNo:'R-'+i}));}`);
                await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
                await evaluate("document.querySelector('[x-ref=gridViewport]').scrollTop=250;document.querySelector('[x-ref=gridViewport]').scrollLeft=90");
                await evaluate('new Promise(resolve => requestAnimationFrame(resolve))');
                assert.equal(await evaluate("(()=>{const viewport=document.querySelector('[x-ref=gridViewport]').getBoundingClientRect();const table=Array.from(document.querySelectorAll('[data-bulk-grid]')).find(el=>el.getClientRects().length);const header=table.querySelector('thead').getBoundingClientRect();const column=table.querySelector('thead tr:nth-child(2) th').getBoundingClientRect();const cell=table.querySelector('[data-error-field]').getBoundingClientRect();return Math.abs(header.top-viewport.top)<2&&Math.abs(column.left-cell.left)<2&&Math.abs(column.width-cell.width)<2;})()"), true, entity + ' sticky header stays aligned');
                assert.equal(await evaluate("document.querySelector('[role=tablist]').getBoundingClientRect().bottom<=document.querySelector('[x-ref=gridViewport]').getBoundingClientRect().top+1"), true);
            }
            await evaluate("editor.switchEntity('property')");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            writeFileSync('.tmp/bulk-browser/properties-desktop.png', Buffer.from((await call('Page.captureScreenshot',{format:'png'})).result.data,'base64'));
        } else {
        assert.equal(await evaluate("!!document.querySelector('[data-error-field=accountNumber] input')"),true);
        await evaluate("document.querySelector('[data-field=name]').focus()");
        await call('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowRight',code:'ArrowRight'});
        assert.equal(await evaluate('document.activeElement.dataset.field'),'email');
        await evaluate("window.editor=Alpine.$data(document.querySelector('[x-ref=bulkSheet]'));editor.rows[0].phone='0012-345';editor.markChanged(editor.rows[0],'phone')");
        await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
        assert.equal(await evaluate('editor.rows[0].phone'),'0012345');
        if(module==='owner') {
            await evaluate("editor.rows[0].bankName='Bank';editor.rows[0].accountHolder='Owner';editor.rows[0].accountNumber='00123456789';editor.markChanged(editor.rows[0],'accountNumber')");
            await evaluate('new Promise(resolve => Alpine.nextTick(resolve))');
            assert.equal(await evaluate("sessionStorage.getItem('owner-fixture').includes('00123456789')"),false);
            assert.equal(await evaluate("document.body.textContent.includes('Emergency Contacts')"),false);
            writeFileSync('.tmp/bulk-browser/owners-desktop.png', Buffer.from((await call('Page.captureScreenshot',{format:'png'})).result.data,'base64'));
        }
        }
        // A long sheet reproduces the transition from the pinned entry to a regular row.
        await evaluate("editor=Alpine.$data(document.querySelector('[x-ref=bulkSheet]'));for(let i=0;i<70;i++){const entry=editor.rows.findIndex(row=>row.isNew&&(!row.entity||row.entity==='property')&&editor.isEntryRow(row));editor.rows.splice(entry,0,editor.makeRow({key:'existing-'+i,name:'Existing '+i}));}document.querySelector('[x-ref=gridViewport]').scrollTop=0");
        await new Promise(r=>setTimeout(r,80));
        await evaluate("window.entryField=Array.from(document.querySelectorAll('[data-field=name]')).at(-1);entryField.focus();window.enteredKey=entryField.closest('[data-bulk-row-body]').dataset.bulkRowBody");
        await call('Input.insertText',{text:'New record'});
        await new Promise(r=>setTimeout(r,120));
        assert.equal(await evaluate('document.activeElement === entryField'),true);
        assert.equal(await evaluate("(()=>{const rows=editor.visibleRows?editor.visibleRows('property'):editor.rows;return rows.at(-2).key===enteredKey&&rows.at(-1).name==='';})()"),true);
        assert.equal(await evaluate("(()=>{const field=entryField.getBoundingClientRect();const viewport=document.querySelector('[x-ref=gridViewport]').getBoundingClientRect();const blank=Array.from(document.querySelectorAll('[data-bulk-row-body]')).filter(row=>row.getClientRects().length).at(-1).getBoundingClientRect();return field.top>=viewport.top && field.bottom<=blank.top;})()"),true);
        await call('Input.insertText',{text:' continued'});
        assert.equal(await evaluate('entryField.value'),'New record continued');
        await call('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowRight',code:'ArrowRight'});
        assert.equal(await evaluate("document.activeElement.closest('[data-bulk-row-body]').dataset.bulkRowBody"),await evaluate('enteredKey'));
        await call('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
        assert.equal(await evaluate('document.documentElement.scrollWidth <= innerWidth'),true);
        assert.equal(await evaluate("document.querySelector('[x-ref=gridViewport]').scrollWidth > document.querySelector('[x-ref=gridViewport]').clientWidth"),true);
        assert.deepEqual(await evaluate('browserErrors'),[]);
    }
    console.log('Form validation and owner, tenant, property, unit and room bulk browser checks passed at desktop and mobile widths.');
} finally { ws?.close();browser.kill(); }
