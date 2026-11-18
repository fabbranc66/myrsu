import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {existsSync} from 'node:fs';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const output = path.resolve('output/dashboard-pdf');
const pdf = await readFile('storage/private/branding/fixtures/comunicato.pdf');
const browser = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
assert.ok(browser);
const profile = await mkdtemp(path.join(os.tmpdir(), 'myrsu-dashboard-pdf-'));
const processHandle = spawn(browser, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-extensions', '--disable-sync', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], {windowsHide: true, stdio: 'ignore'});
const pause = (duration) => new Promise((resolve) => setTimeout(resolve, duration));
const pending = new Map();
const failures = [];
let socket;
let sequence = 0;
let pdfRequests = 0;
let mutations = 0;
function send(method, params = {}) {
  return new Promise((resolve, reject) => {
    const id = ++sequence;
    const timer = setTimeout(() => {pending.delete(id); reject(new Error(`Timeout ${method}`));}, 20000);
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
  for (let attempt = 0; attempt < 200; attempt++) {
    if (await evaluate(expression)) return;
    await pause(50);
  }
  throw new Error(`Condizione non raggiunta: ${expression}`);
}
function fulfill(requestId, body, type = 'application/json') {
  return send('Fetch.fulfillRequest', {requestId, responseCode: 200, responseHeaders: [{name: 'Content-Type', value: type}], body: Buffer.from(body).toString('base64')});
}
try {
  await mkdir(output, {recursive: true});
  let port;
  for (let attempt = 0; attempt < 100; attempt++) {
    try {port = Number((await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]);} catch {}
    if (port) break;
    await pause(100);
  }
  assert.ok(port, 'Browser di prova non avviato');
  const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  socket = new WebSocket(targets.find((target) => target.type === 'page').webSocketDebuggerUrl);
  await new Promise((resolve, reject) => {socket.addEventListener('open', resolve, {once: true}); socket.addEventListener('error', reject, {once: true});});
  socket.addEventListener('message', (event) => {
    const value = JSON.parse(event.data);
    if (value.id && pending.has(value.id)) {
      const request = pending.get(value.id); pending.delete(value.id); clearTimeout(request.timer);
      if (value.error) request.reject(new Error(JSON.stringify(value.error))); else request.resolve(value.result);
    }
    if (value.method === 'Runtime.exceptionThrown') failures.push(value.params.exceptionDetails.text);
    if (value.method !== 'Fetch.requestPaused') return;
    const {requestId, request} = value.params;
    const url = new URL(request.url);
    let operation;
    if (url.pathname.includes('/api/v1/')) {
      if (request.method !== 'GET') mutations++;
      if (url.pathname.endsWith('/preview')) {
        pdfRequests++;
        operation = fulfill(requestId, pdf, 'application/pdf');
      } else if (url.pathname.endsWith('/thumbnail')) {
        operation = fulfill(requestId, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbtAAAAABJRU5ErkJggg==', 'base64'), 'image/png');
      } else {
        let data = [];
        if (url.pathname.endsWith('/me')) data = {id: 7, name: 'Utente di prova', roles: ['delegato'], permissions: []};
        else if (url.pathname.endsWith('/public/documents')) data = {sections: {comunicati: [], documenti: [{id: 99, original_name: 'PDF di prova nitidezza', category: 'documenti', signature: 'TEST', mime_type: 'application/pdf', conversion_status: 'ready'}]}};
        else if (url.pathname.endsWith('/verify')) data = {valid: true, checksum_ok: true};
        operation = fulfill(requestId, JSON.stringify({data}));
      }
    } else if (request.url.startsWith('http://localhost/miodel/') || /^(data:|blob:)/.test(request.url)) operation = send('Fetch.continueRequest', {requestId});
    else operation = send('Fetch.failRequest', {requestId, errorReason: 'BlockedByClient'});
    operation.catch((error) => failures.push(error.message));
  });
  await send('Runtime.enable'); await send('Page.enable');
  await send('Page.addScriptToEvaluateOnNewDocument', {source: "if(location.pathname.endsWith('/ui/app/index.html')){localStorage.clear();sessionStorage.clear();if(location.search.includes('preview_test=user'))localStorage.setItem('token','prova-isolata');}"});
  await send('Fetch.enable', {patterns: [{urlPattern: '*', requestStage: 'Request'}]});
  const results = [];
  for (const fixture of [{role: 'guest', width: 320, ratio: 2}, {role: 'user', width: 390, ratio: 3}]) {
    await send('Emulation.setDeviceMetricsOverride', {width: fixture.width, height: 1000, deviceScaleFactor: fixture.ratio, mobile: true});
    await send('Emulation.setPageScaleFactor', {pageScaleFactor: 1});
    await send('Page.navigate', {url: `http://localhost/miodel/ui/app/index.html?preview_test=${fixture.role}`});
    const suffix = fixture.role === 'guest' ? 'Guest' : 'User';
    const viewer = `document.querySelector('#documentModalPdf${suffix}')`;
    await waitFor(`!!document.querySelector('#publicBoard${suffix}Content [data-document="99"]')`);
    const requestsBefore = pdfRequests;
    await evaluate(`document.querySelector('#publicBoard${suffix}Content [data-document="99"]').click()`);
    await waitFor(`${viewer}.querySelectorAll('canvas').length===2 && ${viewer}.querySelector('canvas').width>100`);
    async function metrics() {
      return evaluate(`(() => {const viewer=${viewer}; const wrap=viewer.querySelector('.dashboard-pdf-page-wrap'); const canvas=wrap.querySelector('canvas'); return {pixels:canvas.width,cssWidth:parseFloat(canvas.style.width),cssHeight:parseFloat(canvas.style.height),wrapWidth:parseFloat(wrap.style.width),zoom:visualViewport.scale,ratio:devicePixelRatio,scroll:viewer.scrollTop,pages:viewer.querySelectorAll('.dashboard-pdf-page-wrap').length};})()`);
    }
    const initial = await metrics();
    assert.equal(initial.pages, 2);
    assert.equal(initial.pixels, Math.ceil(initial.cssWidth * fixture.ratio));
    assert.equal(initial.wrapWidth, initial.cssWidth);
    let previous = initial;
    for (const zoom of [2, 4]) {
      await send('Emulation.setPageScaleFactor', {pageScaleFactor: zoom});
      await waitFor(`${viewer}.querySelector('canvas').width>${previous.pixels}`);
      const current = await metrics();
      const scale = Math.min(fixture.ratio * current.zoom, 8, Math.sqrt(8_388_608 / (current.cssWidth * current.cssHeight)), 8192 / Math.max(current.cssWidth, current.cssHeight));
      assert.equal(current.pixels, Math.ceil(current.cssWidth * scale));
      assert.equal(current.cssWidth, initial.cssWidth);
      assert.equal(current.scroll, initial.scroll);
      assert.equal(pdfRequests, requestsBefore + 1, 'Il PDF non deve essere riscaricato a ogni zoom');
      results.push({role: fixture.role, width: fixture.width, zoom, pixels: current.pixels, cssWidth: current.cssWidth});
      previous = current;
    }
    const shot = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
    await writeFile(path.join(output, `${fixture.role}-zoom4.png`), Buffer.from(shot.data, 'base64'));
    await send('Emulation.setPageScaleFactor', {pageScaleFactor: 1});
    await waitFor(`${viewer}.querySelector('canvas').width===${initial.pixels}`);
    await evaluate(`${viewer}.scrollTop=${viewer}.querySelector('.dashboard-pdf-page-wrap').offsetHeight+8`);
    await waitFor(`${viewer}.querySelectorAll('canvas')[1].width>100`);
    const qr = await evaluate(`(() => {const wrap=${viewer}.querySelectorAll('.dashboard-pdf-page-wrap')[1];const link=wrap.querySelector('.dashboard-pdf-link');const box=link.getBoundingClientRect();link.click();return {width:box.width,height:box.height,url:document.querySelector('#dashboardVerifyFrame').src,open:!document.querySelector('#dashboardVerifyModal').classList.contains('hidden')};})()`);
    assert.ok(qr.width > 0 && qr.height > 0 && qr.open);
    assert.ok(qr.url.includes('document-verify.html'));
    await evaluate("document.querySelector('#closeDashboardVerifyModal').click()");
    await evaluate(`document.querySelector('#closeDocumentModal${suffix}').click()`);
    assert.equal(await evaluate(`${viewer}.children.length`), 0);
    await send('Emulation.setPageScaleFactor', {pageScaleFactor: 2});
    await pause(250);
    assert.equal(await evaluate(`${viewer}.children.length`), 0, 'Rendering riavviato dopo chiusura');
    assert.equal(pdfRequests, requestsBefore + 1);
    await evaluate(`document.querySelector('#publicBoard${suffix}Content [data-document="99"]').click();document.querySelector('#closeDocumentModal${suffix}').click()`);
    await pause(250);
    assert.equal(await evaluate(`${viewer}.children.length`), 0, 'Anteprima riapparsa dopo chiusura immediata');
    console.log(`OK: dashboard ${fixture.role}, ${fixture.width}px DPR ${fixture.ratio}, zoom 1x/2x/4x, QR seconda pagina e chiusura`);
  }
  await send('Emulation.setDeviceMetricsOverride', {width: 1366, height: 1000, deviceScaleFactor: 1, mobile: false});
  await send('Emulation.setPageScaleFactor', {pageScaleFactor: 1});
  await send('Page.navigate', {url: 'http://localhost/miodel/ui/app/index.html?preview_test=user'});
  await waitFor("!!document.querySelector('#publicBoardUserContent [data-document=\"99\"]')");
  const desktop = await evaluate(`(() => {document.querySelector('#publicBoardUserContent [data-document="99"]').click();return {url:document.querySelector('#documentModalFrameUser').src,frameHidden:document.querySelector('#documentModalFrameUser').classList.contains('hidden'),canvasHidden:document.querySelector('#documentModalPdfUser').classList.contains('hidden')};})()`);
  assert.ok(desktop.url.includes('/documents/99/preview'));
  assert.equal(desktop.frameHidden, false);
  assert.equal(desktop.canvasHidden, true);
  await evaluate("document.querySelector('#closeDocumentModalUser').click()");
  console.log('OK: visualizzatore desktop invariato');
  assert.equal(mutations, 0);
  assert.deepEqual(failures, []);
  await writeFile(path.join(output, 'results.json'), JSON.stringify(results, null, 2));
  console.log('OK: PDF ridisegnato alla risoluzione dello zoom; dimensioni CSS e scorrimento invariati. API simulate, nessun account o documento modificato.');
} finally {
  if (socket?.readyState === WebSocket.OPEN) {await send('Browser.close').catch(() => {}); socket.close();}
  if (processHandle.exitCode === null) processHandle.kill();
  for (const request of pending.values()) clearTimeout(request.timer);
  const relative = path.relative(path.resolve(os.tmpdir()), path.resolve(profile));
  if (relative && !relative.startsWith('..') && !path.isAbsolute(relative) && path.basename(profile).startsWith('myrsu-dashboard-pdf-')) await rm(profile, {recursive: true, force: true, maxRetries: 10, retryDelay: 200}).catch(() => {});
}
