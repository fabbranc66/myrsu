import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {existsSync} from 'node:fs';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const pdf = await readFile('storage/private/branding/fixtures/comunicato.pdf');
const browser = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
assert.ok(browser);
const profile = await mkdtemp(path.join(os.tmpdir(), 'miodel-projection-zoom-'));
const processHandle = spawn(browser, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-extensions', '--disable-sync', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], {windowsHide: true, stdio: 'ignore'});
const pause = (duration) => new Promise((resolve) => setTimeout(resolve, duration));
const pending = new Map();
const failures = [];
let socket;
let model = {document_id: 99, revision: 1, scroll_ratio: 0, pointer_page: 1, pointer_x: .5, pointer_y: .1, closed: false};
let positions = 0;
let oldPaths = 0;
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

    if (url.pathname.includes('/myrsu/')) oldPaths++;
    if (url.pathname.includes('/api/v1/')) {
      if (url.pathname.endsWith('/projection-access/document')) operation = fulfill(requestId, pdf, 'application/pdf');
      else {
        let status = model.closed ? 410 : 200;
        if (request.method === 'PATCH') {
          const body = JSON.parse(request.postData);
          if (body.control_token !== 'test-control') status = 403;
          else if (body.document_id !== model.document_id) status = 409;
          else if (!model.closed) {
            for (const field of ['scroll_ratio', 'pointer_page', 'pointer_x', 'pointer_y']) model[field] = body[field];
            model.revision++;
            positions++;
          }
        }
        const data = {
          meeting_id: 1, meeting_title: 'Proiezione di prova', document_id: model.document_id, document_name: 'Documento ' + model.document_id,
          revision: model.revision, scroll_ratio: model.scroll_ratio, pointer_page: model.pointer_page, pointer_x: model.pointer_x, pointer_y: model.pointer_y,
          public_url: 'http://localhost/video/?token=test-public',
          qr_data_url: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLbtAAAAABJRU5ErkJggg==',
        };
        operation = send('Fetch.fulfillRequest', {
          requestId, responseCode: status, responseHeaders: [{name: 'Content-Type', value: 'application/json'}],
          body: Buffer.from(JSON.stringify(status === 200 ? {data} : {error: {message: 'Proiezione di prova non disponibile'}})).toString('base64'),
        });
      }
    } else if (request.url.startsWith('http://localhost/video/') || request.url.startsWith('http://localhost/miodel/') || /^(data:|blob:)/.test(request.url)) operation = send('Fetch.continueRequest', {requestId});
    else operation = send('Fetch.failRequest', {requestId, errorReason: 'BlockedByClient'});
    operation.catch((error) => failures.push(error.message));
  });
  await send('Runtime.enable'); await send('Page.enable');
  await send('Page.addScriptToEvaluateOnNewDocument', {source: "if(location.pathname.endsWith('/ui/app/index.html')){localStorage.clear();sessionStorage.clear();if(location.search.includes('preview_test=user'))localStorage.setItem('token','prova-isolata');}"});
  await send('Fetch.enable', {patterns: [{urlPattern: '*', requestStage: 'Request'}]});

  await send('Page.addScriptToEvaluateOnNewDocument', {source: `
    window.testPointerMetrics = (page = 1) => {
      const view = document.querySelector('#pdfViewer');
      const marker = view?.querySelector('.projection-pointer');
      const canvas = view?.querySelector('.pdf-page[data-page-number="' + page + '"]');
      if (!marker || !canvas) return {visible: false};
      const rect = marker.getBoundingClientRect(), bounds = view.getBoundingClientRect();
      return {
        visible: !marker.hidden,
        x: (parseFloat(marker.style.left) - canvas.offsetLeft) / canvas.offsetWidth,
        y: (parseFloat(marker.style.top) - canvas.offsetTop) / canvas.offsetHeight,
        inView: rect.top >= bounds.top && rect.bottom <= bounds.bottom && rect.left >= bounds.left && rect.right <= bounds.right,
        events: getComputedStyle(marker).pointerEvents,
      };
    };
`});

  for (const fixture of [{width: 320, operator: false, ratio: 2}, {width: 1366, operator: true, ratio: 1}]) {
    model = {document_id: 99, revision: 1, scroll_ratio: 0, pointer_page: 1, pointer_x: .5, pointer_y: .1, closed: false};
    await send('Emulation.setDeviceMetricsOverride', {width: fixture.width, height: 740, deviceScaleFactor: fixture.ratio, mobile: fixture.width === 320});
    await send('Page.navigate', {url: 'http://localhost/video/?token=test-public' + (fixture.operator ? '&operator=1&control=test-control' : '')});
    await waitFor("!!document.querySelector('.pdf-page-surface[data-render-ready=\"true\"]:not([hidden])')");
    const before = await evaluate("(() => {const view=document.querySelector('#pdfViewer'),bounds=view.getBoundingClientRect(),page=view.querySelector('.pdf-page'),rect=page.getBoundingClientRect(),x=bounds.left+bounds.width*.55,y=bounds.top+Math.min(150,bounds.height*.4);return {x,y,pageX:(x-rect.left)/rect.width,pageY:(y-rect.top)/rect.height,innerWidth,ratio:devicePixelRatio,scale:visualViewport.scale,font:getComputedStyle(document.querySelector('.projection-header h1')).fontSize};})()");
    await send('Input.dispatchMouseEvent', {type: 'mouseMoved', x: before.x, y: before.y});
    await send('Input.dispatchMouseEvent', {type: 'mouseWheel', x: before.x, y: before.y, deltaX: 0, deltaY: -2500, modifiers: 2});
    await waitFor("document.querySelector('#zoomValue').textContent==='1000%'&&!!document.querySelector('.pdf-page-surface[data-render-ready=\"true\"][data-render-zoom=\"10\"]:not([hidden])')");
    const after = await evaluate("(() => {const rect=document.querySelector('#pdfViewer .pdf-page').getBoundingClientRect();return {x:rect.left+rect.width*" + before.pageX + ",y:rect.top+rect.height*" + before.pageY + ",innerWidth,ratio:devicePixelRatio,scale:visualViewport.scale,font:getComputedStyle(document.querySelector('.projection-header h1')).fontSize};})()");
    assert.equal(after.innerWidth, before.innerWidth, 'Nessuno zoom del browser');
    assert.equal(after.ratio, before.ratio, 'Densità dello schermo invariata');
    assert.equal(after.scale, before.scale, 'Pagina del browser ferma');
    assert.equal(after.font, before.font, 'Interfaccia non ingrandita');
    assert.ok(Math.abs(after.x - before.x) < 4 && Math.abs(after.y - before.y) < 4, 'Fulcro sul puntatore');
    const buffers = await evaluate("[...document.querySelectorAll('.pdf-page-surface:not([hidden])')].map(canvas=>({pixels:canvas.width*canvas.height,width:canvas.width,height:canvas.height,density:Number(canvas.dataset.renderDensity),tile:parseFloat(canvas.style.width),page:canvas.parentElement.offsetWidth}))");
    assert.ok(buffers.length > 0);
    for (const buffer of buffers) {
      assert.ok(buffer.pixels <= 8000000 && buffer.width <= 8192 && buffer.height <= 8192, 'Memoria dei canvas limitata');
      assert.ok(buffer.density >= fixture.ratio * .95, 'Alta definizione effettiva alla scala richiesta');
      assert.ok(buffer.page > buffer.tile * 2, 'Ridisegno della porzione visibile, non bitmap ingrandita');
    }
    const scrollBefore = await evaluate("document.querySelector('#pdfViewer').scrollTop");
    await send('Input.dispatchMouseEvent', {type: 'mouseWheel', x: before.x, y: before.y, deltaX: 0, deltaY: 350, modifiers: 0});
    await waitFor("document.querySelector('#pdfViewer').scrollTop>" + (scrollBefore + 20));
    assert.equal(await evaluate("document.querySelector('#zoomValue').textContent"), '1000%', 'Rotella normale scorre senza zoom');
    const tileBefore = await evaluate("parseFloat(document.querySelector('.pdf-page-surface:not([hidden])').style.left)");
    await evaluate("document.querySelector('#pdfViewer').scrollLeft+=250");
    await waitFor("!![...document.querySelectorAll('.pdf-page-surface:not([hidden])')].find(canvas=>canvas.dataset.renderReady==='true'&&parseFloat(canvas.style.left)>" + (tileBefore + 100) + ")");
    await send('Input.dispatchMouseEvent', {type: 'mouseWheel', x: before.x, y: before.y, deltaX: 0, deltaY: 5000, modifiers: 2});
    await waitFor("document.querySelector('#zoomValue').textContent==='75%'");
    const pinch = await evaluate("(() => {const view=document.querySelector('#pdfViewer'),bounds=view.getBoundingClientRect(),page=view.querySelector('.pdf-page'),rect=page.getBoundingClientRect(),x=Math.max(rect.left+20,Math.min(bounds.right-20,rect.left+rect.width*.5)),y=Math.max(rect.top+20,Math.min(bounds.bottom-20,rect.top+100));const start=new Event('touchstart',{bubbles:true,cancelable:true});Object.defineProperty(start,'touches',{value:[{clientX:x-50,clientY:y},{clientX:x+50,clientY:y}]});view.dispatchEvent(start);const move=new Event('touchmove',{bubbles:true,cancelable:true});Object.defineProperty(move,'touches',{value:[{clientX:x-1000,clientY:y},{clientX:x+1000,clientY:y}]});view.dispatchEvent(move);const end=new Event('touchend',{bubbles:true});Object.defineProperty(end,'touches',{value:[]});view.dispatchEvent(end);return {x,y,pageX:(x-rect.left)/rect.width,pageY:(y-rect.top)/rect.height};})()");
    await waitFor("document.querySelector('#zoomValue').textContent==='1000%'&&!!document.querySelector('.pdf-page-surface[data-render-ready=\"true\"][data-render-zoom=\"10\"]:not([hidden])')");
    const pinchAfter = await evaluate("(() => {const rect=document.querySelector('#pdfViewer .pdf-page').getBoundingClientRect();return {x:rect.left+rect.width*" + pinch.pageX + ",y:rect.top+rect.height*" + pinch.pageY + "};})()");
    assert.ok(Math.abs(pinchAfter.x - pinch.x) < 4 && Math.abs(pinchAfter.y - pinch.y) < 4, 'Fulcro tra le dita');
    console.log('OK ' + fixture.width + 'px: solo PDF fino al 1000%, fulcro mouse/dita, scorrimento e ridisegno nitido con memoria limitata.');
  }
  assert.equal(oldPaths, 0);
  assert.deepEqual(failures, []);
  console.log('API simulate: nessun documento, incontro o dato reale modificato.');
} finally {
  if (socket?.readyState === WebSocket.OPEN) {await send('Browser.close').catch(() => {}); socket.close();}
  if (processHandle.exitCode === null) processHandle.kill();
  for (const request of pending.values()) clearTimeout(request.timer);
  const relative = path.relative(path.resolve(os.tmpdir()), path.resolve(profile));
  if (relative && !relative.startsWith('..') && !path.isAbsolute(relative) && path.basename(profile).startsWith('myrsu-dashboard-pdf-')) await rm(profile, {recursive: true, force: true, maxRetries: 10, retryDelay: 200}).catch(() => {});
}
