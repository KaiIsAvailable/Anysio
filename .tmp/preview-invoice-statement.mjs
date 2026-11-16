import { readFileSync, writeFileSync } from 'node:fs';
import { execFileSync, spawn } from 'node:child_process';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
import assert from 'node:assert/strict';
const endpoint = 'http://statement.test/lease-entry/statement';
const fixture = execFileSync('php', ['tests/Frontend/invoice-statement-fixture.php', 'modal', endpoint], { encoding: 'utf8' });
const results = execFileSync('php', ['tests/Frontend/invoice-statement-fixture.php', 'results', endpoint], { encoding: 'utf8' });
const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const css = readFileSync('public/build/' + manifest['resources/css/app.css'].file, 'utf8');
const logic = readFileSync('resources/js/invoice-statement.js', 'utf8').replace('export function', 'function');
const alpine = pathToFileURL(resolve('node_modules/alpinejs/dist/cdn.js')).href;
const html = `<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>[x-cloak]{display:none!important}${css}</style><script>${logic}; window.fetch=async()=>({ok:true,json:async()=>({html:${JSON.stringify(results).replaceAll('<', '\\u003c')}})});document.addEventListener('alpine:init',()=>Alpine.data('invoiceStatement',invoiceStatement));</script><script defer src="${alpine}"></script></head><body class="bg-slate-100 p-6">${fixture}</body></html>`;
const path = resolve('.tmp/invoice-statement-preview.html');
writeFileSync(path, html);
const edge = spawn('C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', ['--headless', '--disable-gpu', '--no-first-run', '--remote-debugging-port=9237', '--user-data-dir=' + resolve('.tmp/edge-statement-preview'), 'about:blank'], { windowsHide: true, stdio: 'ignore' });
let ws;
try {
    let tabs;
    for(let i=0;i<40;i++) { try { tabs=await (await fetch('http://127.0.0.1:9237/json/list')).json(); break; } catch { await new Promise(r=>setTimeout(r,200)); } }
    ws=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise(r=>ws.addEventListener('open',r,{once:true}));
    let seq=0; const pending=new Map();
    ws.addEventListener('message',e=>{const msg=JSON.parse(e.data);if(msg.id){pending.get(msg.id)?.(msg);pending.delete(msg.id);}});
    const call=(method,params={})=>new Promise(r=>{const id=++seq;pending.set(id,r);ws.send(JSON.stringify({id,method,params}));});
    const evaluate=async expression=>(await call('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true})).result?.result?.value;
    await call('Page.enable');
    await call('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await call('Page.navigate',{url:pathToFileURL(path).href});
    for(let i=0;i<40;i++){if(await evaluate("!!window.Alpine && !!document.querySelector('#lease-entry')?._x_dataStack"))break;await new Promise(r=>setTimeout(r,100));}
    await evaluate("document.querySelector('#lease-entry').click()");
    for(let i=0;i<40;i++){if(await evaluate("!!document.querySelector('[data-statement-event]')"))break;await new Promise(r=>setTimeout(r,100));}
    await new Promise(r=>setTimeout(r,400));
    assert.equal(await evaluate("document.querySelectorAll('#invoice-statement thead th').length"),5);
    assert.equal(await evaluate("document.documentElement.scrollWidth <= innerWidth"),true);
    writeFileSync('.tmp/invoice-statement-desktop.png',Buffer.from((await call('Page.captureScreenshot',{format:'png'})).result.data,'base64'));
    await evaluate("document.querySelector('[data-statement-toggle]').click()");
    assert.equal(await evaluate("document.querySelector('#statement-detail-0').hidden"),false);
    writeFileSync('.tmp/invoice-statement-details.png',Buffer.from((await call('Page.captureScreenshot',{format:'png'})).result.data,'base64'));
    await evaluate("document.querySelector('[data-statement-toggle]').click()");
    await call('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    await new Promise(r=>setTimeout(r,150));
    assert.equal(await evaluate("document.documentElement.scrollWidth <= innerWidth"),true);
    writeFileSync('.tmp/invoice-statement-mobile.png',Buffer.from((await call('Page.captureScreenshot',{format:'png'})).result.data,'base64'));
    console.log('PASS: rendered statement at desktop and mobile sizes, independent money columns, expandable details, no page overflow.');
    await call('Browser.close');
} finally { ws?.close(); edge.kill(); }
