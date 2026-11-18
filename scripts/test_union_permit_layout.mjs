import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {existsSync} from 'node:fs';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const output = path.resolve('output/union-permits');
const browser = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
assert.ok(browser);
const profile = await mkdtemp(path.join(os.tmpdir(), 'myrsu-permits-layout-'));
const processHandle = spawn(browser, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-extensions', '--disable-sync', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], {windowsHide: true, stdio: 'ignore'});
const pause = (duration) => new Promise((resolve) => setTimeout(resolve, duration));
const pending = new Map();
const failures = [];
const mutations = [];
let socket;
let sequence = 0;
const year = new Date().getFullYear();
const requests = [
  {id: 11, user_id: 7, request_scope: 'internal', permit_type: 'rsu', union_name: 'FIM-CISL', subject: 'Riunione di prova con una descrizione sufficientemente lunga', request_date: `${year}-11-14`, start_at: `${year}-11-16 09:00:00`, end_at: `${year}-11-16 11:00:00`, hours: 2, status: 'issued', protocol_number: 'TEST/2026/001', document_id: null},
  {id: 12, user_id: 7, request_scope: 'external', permit_type: 'rls', subject: 'Richiesta annullata di prova', request_date: `${year}-10-10`, hours: 1, status: 'canceled', protocol_number: 'TEST/2026/002', document_id: null},
];

function send(method, params = {}) {
  return new Promise((resolve, reject) => {
    const id = ++sequence;
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Timeout ${method}`)); }, 15000);
    pending.set(id, {resolve, reject, timer});
    socket.send(JSON.stringify({id, method, params}));
  });
}
async function evaluate(expression) {
  const result = await send('Runtime.evaluate', {expression, returnByValue: true});
  if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
  return result.result.value;
}
async function waitFor(expression) {
  for (let attempt = 0; attempt < 100; attempt++) {
    if (await evaluate(expression)) return;
    await pause(50);
  }
  throw new Error(`Condizione non raggiunta: ${expression}`);
}

try {
  await mkdir(output, {recursive: true});
  let port;
  for (let attempt = 0; attempt < 100; attempt++) {
    try { port = Number((await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]); } catch {}
    if (port) break;
    await pause(100);
  }
  assert.ok(port, 'Browser di prova non avviato');
  const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  socket = new WebSocket(targets.find((target) => target.type === 'page').webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, {once: true}); socket.addEventListener('error', reject, {once: true}); });
  socket.addEventListener('message', (event) => {
    const value = JSON.parse(event.data);
    if (value.id && pending.has(value.id)) {
      const request = pending.get(value.id); pending.delete(value.id); clearTimeout(request.timer);
      if (value.error) request.reject(new Error(JSON.stringify(value.error))); else request.resolve(value.result);
    }
    if (value.method !== 'Fetch.requestPaused') return;
    const {requestId, request} = value.params;
    const url = new URL(request.url);
    let operation;
    if (url.pathname.endsWith('/auth-menu.js')) {
      operation = send('Fetch.fulfillRequest', {requestId, responseCode: 200, responseHeaders: [{name: 'Content-Type', value: 'text/javascript'}], body: ''});
    } else if (url.pathname.includes('/api/v1/')) {
      let data;
      if (request.method !== 'GET') {
        mutations.push({method: request.method, path: url.pathname, data: request.postData ? JSON.parse(request.postData) : null});
        data = request.method === 'DELETE' ? {hours_restored: true} : {document: null};
      } else if (url.pathname.endsWith('/me')) data = {id: 7, roles: ['admin']};
      else if (url.pathname.endsWith('/delegates')) data = [{id: 7, name: 'Delegato di prova', email: 'indirizzo.lungo.di.prova@example.invalid', mobile: '000000000', roles: 'delegato,rls', union_code: 'FIM-CISL'}, {id: 8, name: 'Secondo delegato', roles: 'delegato', union_code: 'FIM-CISL'}];
      else if (url.pathname.endsWith('/allocations')) data = [{year, permit_type: 'rsu', annual_hours: 128, used_hours: 48}, {year, permit_type: 'rls', annual_hours: 40, used_hours: 8}, {year: year + 1, permit_type: 'rsu', annual_hours: 128, used_hours: 0}];
      else if (url.pathname.endsWith('/requests')) data = requests;
      else { failures.push(`API inattesa: ${url.pathname}`); data = {}; }
      operation = send('Fetch.fulfillRequest', {requestId, responseCode: 200, responseHeaders: [{name: 'Content-Type', value: 'application/json'}], body: Buffer.from(JSON.stringify({data})).toString('base64')});
    } else if (request.url.startsWith('http://localhost/miodel/') || /^(data:|blob:)/.test(request.url)) operation = send('Fetch.continueRequest', {requestId});
    else operation = send('Fetch.failRequest', {requestId, errorReason: 'BlockedByClient'});
    operation.catch((error) => failures.push(error.message));
  });
  await send('Runtime.enable'); await send('Page.enable');
  await send('Page.addScriptToEvaluateOnNewDocument', {source: "localStorage.setItem('token','test-grafico-isolato'); window.confirm=()=>true;"});
  await send('Fetch.enable', {patterns: [{urlPattern: '*', requestStage: 'Request'}]});
  const results = [];
  for (const width of [320, 360, 390, 430, 768, 1024, 1366]) {
    await send('Emulation.setDeviceMetricsOverride', {width, height: 1000, deviceScaleFactor: 1, mobile: width <= 768});
    await send('Page.navigate', {url: 'http://localhost/miodel/ui/union-permit-requests.html'});
    await waitFor("document.readyState==='complete' && document.querySelectorAll('#balances .stat-card').length===2 && document.querySelectorAll('#requestsTable tr').length===2");
    const metrics = await evaluate(`(() => {
      const viewport = document.documentElement.clientWidth;
      const fields = [...document.querySelectorAll('.permit-requests-page input:not([type="hidden"]), .permit-requests-page select, .permit-requests-page textarea')];
      const outside = fields.filter((field) => { const rect=field.getBoundingClientRect(); return rect.left<0 || rect.right>viewport+1; }).map((field)=>field.name);
      const fieldRects=fields.map((field)=>field.getBoundingClientRect());
      const overlap=fieldRects.some((first,index)=>fieldRects.slice(index+1).some((second)=>first.left<second.right-1 && first.right>second.left+1 && first.top<second.bottom-1 && first.bottom>second.top+1));
      const form=document.querySelector('#requestForm');
      return {viewport, outside, overlap, scrollWidth:document.documentElement.scrollWidth,
        fieldCount:fields.length, formNames:[...form.elements].filter((field)=>field.name).map((field)=>field.name),
        delegateAssociated:document.querySelector('#delegateSelect').form===form,
        sections:[...document.querySelectorAll('.permit-section-heading h2')].map((heading)=>heading.textContent),
        fieldsetCount:form.querySelectorAll('fieldset').length,
        debugClosed:!document.querySelector('.permit-request-debug').open,
        futureVisible:document.querySelector('#balances').textContent.includes(String(new Date().getFullYear()+1)),
        smallestField:Math.min(...fieldRects.map((rect)=>rect.height))};
    })()`);
    assert.deepEqual(metrics.outside, [], JSON.stringify({width, metrics}));
    assert.equal(metrics.overlap, false, `${width}: campi sovrapposti`);
    assert.ok(metrics.scrollWidth <= width + 1, `${width}: pagina fuori schermo`);
    assert.equal(metrics.fieldCount, 10);
    assert.equal(metrics.formNames.length, 11);
    assert.equal(metrics.delegateAssociated, true);
    assert.equal(metrics.fieldsetCount, 2);
    assert.equal(metrics.debugClosed, true);
    assert.equal(metrics.futureVisible, false);
    assert.ok(metrics.smallestField >= 44);
    assert.deepEqual(metrics.sections, ['Delegato e ore disponibili', 'Richiesta di permesso', 'Storico richieste']);
    if ([390, 1366].includes(width)) {
      const shot = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
      await writeFile(path.join(output, `permit-requests-${width}.png`), Buffer.from(shot.data, 'base64'));
    }
    results.push({width, fields: metrics.fieldCount});
    console.log(`OK: pagina permessi a ${width}px`);
  }
  const edited = await evaluate(`(() => {document.querySelector('[data-edit="11"]').click(); const form=document.querySelector('#requestForm'); return {id:form.request_id.value,subject:form.subject.value,disabled:document.querySelector('#delegateSelect').disabled};})()`);
  assert.equal(edited.id, '11'); assert.equal(edited.subject, requests[0].subject); assert.equal(edited.disabled, true);
  await evaluate("document.querySelector('#requestForm').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))");
  await waitFor("document.querySelector('#message').textContent.includes('Permesso salvato')");
  assert.equal(mutations[0].method, 'PATCH'); assert.ok(mutations[0].path.endsWith('/requests/11'));
  await evaluate("document.querySelector('[data-delete=\"11\"]').click()");
  await waitFor("document.querySelector('#message').textContent.includes('ore ripristinate')");
  assert.equal(mutations[1].method, 'DELETE');
  const reset = await evaluate("({id:document.querySelector('#requestForm').request_id.value,button:document.querySelector('#submitButton').textContent,union:document.querySelector('#requestForm').union_name.value})");
  assert.equal(reset.id, ''); assert.equal(reset.button, 'Genera PDF richiesta'); assert.equal(reset.union, 'FIM-CISL');
  await evaluate(`(() => { const form=document.querySelector('#requestForm'); form.subject.value='Nuova richiesta di prova'; form.start_at.value='${year}-11-18T09:00'; form.end_at.value='${year}-11-18T11:00'; form.hours.value='2'; form.requestSubmit(); })()`);
  await waitFor("document.querySelector('#message').textContent==='PDF richiesta generato.'");
  assert.equal(mutations[2].method, 'POST');
  assert.ok(mutations[2].path.endsWith('/union-permits/requests'));
  assert.equal(mutations[2].data.user_id, '7');
  assert.equal(mutations[2].data.subject, 'Nuova richiesta di prova');
  assert.equal(mutations[2].data.hours, '2');
  assert.equal('request_id' in mutations[2].data, false);
  assert.deepEqual(failures, []);
  await writeFile(path.join(output, 'layout-results.json'), JSON.stringify({results, mockMutations: mutations.length}, null, 2));
  console.log('OK: 7 layout mobile-first; associazione delegato, creazione, modifica, salvataggio, annullamento e reset invariati. API simulate: nessun dato reale modificato.');
} finally {
  if (socket?.readyState === WebSocket.OPEN) { await send('Browser.close').catch(() => {}); socket.close(); }
  if (processHandle.exitCode === null) processHandle.kill();
  for (const request of pending.values()) clearTimeout(request.timer);
  const relative = path.relative(path.resolve(os.tmpdir()), path.resolve(profile));
  if (relative && !relative.startsWith('..') && !path.isAbsolute(relative) && path.basename(profile).startsWith('myrsu-permits-layout-')) await rm(profile, {recursive: true, force: true, maxRetries: 10, retryDelay: 200}).catch(() => {});
}
