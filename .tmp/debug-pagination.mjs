import { getDocument } from '../node_modules/pdfjs-dist/legacy/build/pdf.mjs';
import { prepareDocumentFooter } from '../resources/js/editor/document-layout.js';
const tabs = await (await fetch('http://127.0.0.1:9224/json/list')).json();
const ws = new WebSocket(tabs.find(t => t.type === 'page').webSocketDebuggerUrl);
await new Promise(resolve => ws.addEventListener('open', resolve, { once: true }));
let id = 0;
const pending = new Map();
ws.addEventListener('message', event => { const m = JSON.parse(event.data); if (m.id) { pending.get(m.id)?.(m); pending.delete(m.id); } });
const call = (method, params = {}) => new Promise(resolve => { const next = ++id; pending.set(next, resolve); ws.send(JSON.stringify({ id: next, method, params })); });
await call('Page.enable');
const load = new Promise(resolve => ws.addEventListener('message', event => { if (JSON.parse(event.data).method === 'Page.loadEventFired') resolve(); }));
await call('Page.navigate', { url: 'file:///C:/xampp/htdocs/dashboard/Anysio/.tmp/print-debug.html' });
await load;
await call('Runtime.evaluate', { expression: 'document.fonts.ready.then(()=>Promise.all(Array.from(document.images,i=>i.decode().catch(()=>{}))))', awaitPromise: true });
const evaluate = async expression => (await call('Runtime.evaluate', { expression, returnByValue: true })).result?.result?.value;
const inspect = async label => {
    const result = await call('Page.printToPDF', { preferCSSPageSize: true, displayHeaderFooter: false, printBackground: true });
    const pdf = await getDocument({ data: new Uint8Array(Buffer.from(result.result.data, 'base64')) }).promise;
    console.log(label, pdf.numPages, 'last page operations', (await (await pdf.getPage(pdf.numPages)).getOperatorList()).fnArray.length);
};
await inspect('baseline');
await evaluate(`(${prepareDocumentFooter.toString()})(document)`);
await inspect('shared footer fix');
ws.send(JSON.stringify({ id: ++id, method: 'Browser.close' }));
ws.close();
process.exit(0);
console.log(await evaluate('JSON.stringify(Array.from(document.body.lastElementChild.children,e=>({tag:e.tagName,id:e.id,position:getComputedStyle(e).position,rect:e.getBoundingClientRect().toJSON(),marginTop:getComputedStyle(e).marginTop})))'));
console.log(await evaluate('JSON.stringify({body:document.body.getBoundingClientRect().toJSON(),last:document.body.lastElementChild.getBoundingClientRect().toJSON(),tail:document.body.lastElementChild.outerHTML.replace(/src="[^"]*"/g,"src=omitted").slice(-600)})'));
await evaluate('window.savedFooter=document.body.lastElementChild.outerHTML; window.footer=document.body.lastElementChild; footer.remove()');
await inspect('no footer');
await evaluate('document.body.append(footer); footer.style.setProperty("position","relative","important")');
await inspect('relative footer');
await evaluate('footer.style.setProperty("display","flow-root","important")');
await inspect('contained footer');
for (const [label, change] of [
    ['footer measured placement', '(()=>{const r=footer.getBoundingClientRect();const children=Array.from(footer.children,e=>({e,r:e.getBoundingClientRect()}));const height=Math.ceil(Math.max(...children.map(x=>x.r.bottom))-r.top);footer.style.setProperty("position","relative","important");footer.style.setProperty("height",height+"px","important");footer.style.setProperty("break-inside","avoid","important");children.forEach(({e,r:c})=>{for(const[k,v]of Object.entries({position:"absolute",float:"none",margin:"0",top:(c.top-r.top)+"px",left:(c.left-r.left)+"px",width:c.width+"px"}))e.style.setProperty(k,v,"important")})})()'],
    ['footer positioned inline blocks', 'footer.querySelectorAll(":scope > span").forEach(e=>{e.style.setProperty("position","static","important");e.style.setProperty("float","none","important");e.style.setProperty("display","block","important");e.style.setProperty("margin","0","important");e.style.setProperty("width","auto","important")});footer.style.setProperty("display","flow-root","important");'],
    ['footer full width no extra linebox', 'footer.style.setProperty("font-size","0","important");footer.querySelectorAll(":scope > span").forEach(e=>e.style.setProperty("line-height","normal","important"))'],
    ['address wrapper zero lineheight preserve paragraphs', 'footer.querySelectorAll("p").forEach(e=>e.style.setProperty("line-height",getComputedStyle(e).lineHeight,"important"));footer.querySelector("#i76oymr-2-2-3-3").style.setProperty("line-height","0","important")'],
    ['footer zero lineheight preserve text paragraphs', 'footer.querySelectorAll("p").forEach(e=>e.style.setProperty("line-height",getComputedStyle(e).lineHeight,"important"));footer.style.setProperty("line-height","0","important")'],
    ['footer zero lineheight preserving child lines', 'footer.querySelectorAll(":scope > *").forEach(e=>{e.style.setProperty("line-height",getComputedStyle(e).lineHeight,"important")});footer.style.setProperty("line-height","0","important")'],
    ['footer no trailing linebreak', 'footer.querySelectorAll("br").forEach(e=>e.remove())'],
    ['footer zero lineheight', 'footer.style.setProperty("line-height","0","important")'],
    ['footer paint containment', 'footer.style.setProperty("position","relative","important");footer.style.setProperty("contain","layout paint","important");footer.style.setProperty("height","200px","important");footer.querySelectorAll("span").forEach(e=>{if(getComputedStyle(e).position==="absolute"){e.style.setProperty("top","0","important");e.style.setProperty("left","0","important")}})'],
    ['footer anchor positioned children', 'footer.style.setProperty("position","relative","important");footer.style.setProperty("display","flow-root","important");footer.querySelectorAll("span").forEach(e=>{if(getComputedStyle(e).position==="absolute"){e.style.setProperty("top","0","important");e.style.setProperty("left","0","important")}})'],
    ['footer minheight', 'footer.style.setProperty("min-height","160px","important")'],
    ['image no float', 'footer.querySelectorAll("img").forEach(e=>e.style.setProperty("float","none","important"))'],
    ['no footer image', 'footer.querySelectorAll("img").forEach(e=>e.remove())'],
    ['footer no positioned children', 'footer.querySelectorAll("span").forEach(e=>{e.style.setProperty("position","static","important");e.style.setProperty("float","none","important");e.style.setProperty("margin","0","important")})'],
    ['footer avoid split', 'footer.style.setProperty("break-inside","avoid","important")'],
]) {
    await evaluate('footer.outerHTML=savedFooter;window.footer=document.body.lastElementChild;'+change);
    await inspect(label);
}
ws.close();
