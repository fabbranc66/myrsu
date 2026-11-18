import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {existsSync} from 'node:fs';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const pdf = await readFile('storage/private/branding/fixtures/comunicato.pdf');
const browser = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
assert.ok(browser);
const profile = await mkdtemp(path.join(os.tmpdir(), 'miodel-pointer-'));
const processHandle = spawn(browser, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-extensions', '--disable-sync', '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank'], {windowsHide: true, stdio: 'ignore'});
const pause = (duration) => new Promise((resolve) => setTimeout(resolve, duration));
const pending = new Map();
const failures = [];
let socket;
let model = {document_id: 99, revision: 1, scroll_ratio: 0, pointer_page: 1, pointer_x: .5, pointer_y: .1, highlight_x: null, highlight_y: null, highlight_page: null, highlight_end_x: null, highlight_end_y: null, closed: false};
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
            for (const field of ['scroll_ratio', 'pointer_page', 'pointer_x', 'pointer_y', 'highlight_x', 'highlight_y', 'highlight_page', 'highlight_end_x', 'highlight_end_y']) model[field] = body[field];
            model.revision++;
            positions++;
          }
        }
        const data = {
          meeting_id: 1, meeting_title: 'Proiezione di prova', document_id: model.document_id, document_name: 'Documento ' + model.document_id,
          revision: model.revision, scroll_ratio: model.scroll_ratio, pointer_page: model.pointer_page, pointer_x: model.pointer_x, pointer_y: model.pointer_y,
          highlight_x: model.highlight_x, highlight_y: model.highlight_y,
          highlight_page: model.highlight_page, highlight_end_x: model.highlight_end_x, highlight_end_y: model.highlight_end_y,
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
        inVerticalView: rect.top >= bounds.top && rect.bottom <= bounds.bottom,
        events: getComputedStyle(marker).pointerEvents,
      };
    };
    window.testHighlightMetrics = (page = 1) => {
      const view = document.querySelector('#pdfViewer');
      const highlight = view?.querySelector('.projection-highlight');
      const canvas = view?.querySelector('.pdf-page[data-page-number="' + page + '"]');
      if (!highlight || !canvas) return {visible: false};
      return {
        visible: !highlight.hidden,
        x: (parseFloat(highlight.style.left) - canvas.offsetLeft) / canvas.offsetWidth,
        y: (parseFloat(highlight.style.top) - canvas.offsetTop) / canvas.offsetHeight,
        width: parseFloat(highlight.style.width) / canvas.offsetWidth,
        height: parseFloat(highlight.style.height) / canvas.offsetHeight,
        events: getComputedStyle(highlight).pointerEvents,
      };
    };
    window.testHighlightGesture = (page = 1) => {
      const canvas = document.querySelector('.pdf-page[data-page-number="' + page + '"]');
      const rect = canvas.getBoundingClientRect();
      for (const [type, button, buttons, x, y] of [['pointerdown',0,1,.6,.22],['pointermove',-1,1,.2,.15],['pointerup',0,0,.2,.15]]) {
        canvas.dispatchEvent(new PointerEvent(type, {bubbles:true,cancelable:true,isPrimary:true,pointerType:'mouse',button,buttons,clientX:rect.left+rect.width*x,clientY:rect.top+rect.height*y}));
      }
    };
`});
  for (const fixture of [{operator: 320, guest: 1366, type: 'touch'}, {operator: 1366, guest: 320, type: 'mouse'}]) {
    model = {document_id: 99, revision: 1, scroll_ratio: 0, pointer_page: 1, pointer_x: .5, pointer_y: .1, highlight_x: null, highlight_y: null, highlight_page: null, highlight_end_x: null, highlight_end_y: null, closed: false};
    await send('Emulation.setDeviceMetricsOverride', {width: fixture.operator, height: 740, deviceScaleFactor: fixture.operator === 320 ? 2 : 1, mobile: fixture.operator === 320});
    await send('Page.navigate', {url: 'http://localhost/video/?token=test-public&operator=1&control=test-control'});
    await waitFor("document.querySelectorAll('#pdfViewer canvas').length===2&&window.testPointerMetrics().visible");
    await evaluate("(() => {const frame=document.createElement('iframe');frame.id='testGuest';frame.style.cssText='position:fixed;right:0;top:0;height:700px;border:0;z-index:100;width:" + fixture.guest + "px';frame.src='http://localhost/video/?token=test-public';document.body.append(frame);})()");
    const guest = "document.querySelector('#testGuest').contentWindow";
    await waitFor(guest + ".document.querySelectorAll('#pdfViewer canvas').length===2&&" + guest + ".testPointerMetrics().visible");
    const previous = positions;
    await evaluate("(() => {const canvas=document.querySelector('#pdfViewer .pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('" + (fixture.type === 'touch' ? 'pointerdown' : 'pointermove') + "',{bubbles:true,isPrimary:true,pointerType:'" + fixture.type + "',clientX:rect.left+rect.width*.3,clientY:rect.top+rect.height*.2}));})()");
    for (let attempt = 0; attempt < 50 && positions === previous; attempt++) await pause(50);
    assert.ok(positions > previous, 'Il movimento della regia viene inviato');
    await waitFor(guest + ".testPointerMetrics().visible&&Math.abs(" + guest + ".testPointerMetrics().x-.3)<.001&&Math.abs(" + guest + ".testPointerMetrics().y-.2)<.001");
    assert.equal((await evaluate(guest + '.testPointerMetrics()')).inView, true);
    await pause(800);
    assert.equal((await evaluate(guest + '.testPointerMetrics()')).visible, true, 'Puntatore persistente anche da fermo');
    assert.equal((await evaluate(guest + '.testPointerMetrics()')).events, 'none');
    const beforeGuest = positions;
    await evaluate("(() => {const guestWindow=" + guest + ",canvas=guestWindow.document.querySelector('#pdfViewer .pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new guestWindow.PointerEvent('pointerdown',{bubbles:true,isPrimary:true,pointerType:'touch',clientX:rect.left+20,clientY:rect.top+20}));})()");
    await pause(350);
    assert.equal(positions, beforeGuest, 'Lo spettatore non cambia il puntatore');
    const manualScroll = await evaluate("(() => {const view=" + guest + ".document.querySelector('#pdfViewer');view.scrollTop=view.scrollHeight;return view.scrollTop;})()");
    assert.ok(manualScroll > 0, 'Scorrimento locale disponibile');
    await pause(700);
    assert.ok(Math.abs(await evaluate(guest + ".document.querySelector('#pdfViewer').scrollTop") - manualScroll) < 1, 'Puntatore fermo: nessun riposizionamento durante la consultazione');
    await evaluate("(() => {const canvas=document.querySelector('#pdfViewer .pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,isPrimary:true,pointerType:'mouse',clientX:rect.left+rect.width*.35,clientY:rect.top+rect.height*.21}));})()");
    await waitFor(guest + ".testPointerMetrics().inView&&Math.abs(" + guest + ".testPointerMetrics().x-.35)<.001&&Math.abs(" + guest + ".testPointerMetrics().y-.21)<.001");
    assert.ok(Math.abs(await evaluate(guest + ".document.querySelector('#pdfViewer').scrollTop") - manualScroll) > 1, 'Nuovo movimento regia: ritorno al puntatore');
    await evaluate('window.testHighlightGesture()');
    const highlightReleasedAt = Date.now();
    await waitFor(guest + '.testHighlightMetrics().visible');
    for (const context of ['window', guest]) {
      const metrics = await evaluate(context + '.testHighlightMetrics()');
      for (const [field, expected] of Object.entries({x: .2, y: .15, width: .4, height: .07})) {
        assert.ok(Math.abs(metrics[field] - expected) < .001, 'Evidenziazione proporzionata: ' + field);
      }
      assert.equal(metrics.events, 'none');
    }
    await pause(650);
    assert.equal(await evaluate(guest + '.testHighlightMetrics().visible'), true, 'Rilascio del sinistro conserva evidenziazione');
    assert.equal(await evaluate("document.querySelector('.pdf-page').dispatchEvent(new MouseEvent('contextmenu',{bubbles:true,cancelable:true}))"), true, 'Menu contestuale regia invariato');
    assert.equal(await evaluate(guest + ".document.querySelector('.pdf-page').dispatchEvent(new MouseEvent('contextmenu',{bubbles:true,cancelable:true}))"), true, 'Menu contestuale ospite invariato');
    const beforeGuestHighlight = positions;
    await evaluate(guest + '.testHighlightGesture()');
    await pause(350);
    assert.equal(positions, beforeGuestHighlight, 'Ospite non crea o cancella evidenziazioni condivise');
    assert.equal(await evaluate(guest + '.testHighlightMetrics().visible'), true);
    await evaluate("(() => {const canvas=document.querySelector('.pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,isPrimary:true,pointerType:'mouse',buttons:0,clientX:rect.left+rect.width*.2,clientY:rect.top+rect.height*.15}));})()");
    await pause(350);
    assert.equal(await evaluate(guest + '.testHighlightMetrics().visible'), true, 'Movimento dopo rilascio non cancella entro cinque secondi');
    await evaluate("(() => {const canvas=document.querySelector('.pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,isPrimary:true,pointerType:'mouse',buttons:0,clientX:rect.left+rect.width*.35,clientY:rect.top+rect.height*.21}));})()");
    await waitFor(guest + '.testPointerMetrics().x>.349&&'+guest+'.testPointerMetrics().x<.351');
    for (const context of ['window', guest]) {
      const metrics = await evaluate(context + '.testHighlightMetrics()');
      assert.equal(metrics.visible, true);
      for (const [field, expected] of Object.entries({x: .2, y: .15, width: .4, height: .07})) {
        assert.ok(Math.abs(metrics[field] - expected) < .001, 'Puntatore libero senza deformare evidenziazione: ' + field);
      }
    }
    await pause(Math.max(0, 4200 - (Date.now() - highlightReleasedAt)));
    await evaluate("(() => {const canvas=document.querySelector('.pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,isPrimary:true,pointerType:'mouse',buttons:0,clientX:rect.left+rect.width*.2,clientY:rect.top+rect.height*.15}));})()");
    await pause(250);
    assert.equal(await evaluate(guest + '.testHighlightMetrics().visible'), true, 'Area ancora visibile dopo quattro secondi e piu movimenti');
    await pause(Math.max(0, 5300 - (Date.now() - highlightReleasedAt)));
    assert.equal(await evaluate(guest + '.testHighlightMetrics().visible'), true, 'Dopo cinque secondi resta finche non si muove il puntatore');
    await evaluate("(() => {const canvas=document.querySelector('.pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,isPrimary:true,pointerType:'mouse',buttons:0,clientX:rect.left+rect.width*.2,clientY:rect.top+rect.height*.15}));})()");
    await waitFor('!window.testHighlightMetrics().visible&&!'+guest+'.testHighlightMetrics().visible');
    assert.equal(model.highlight_x, null, 'Primo movimento dopo cinque secondi cancella anche a coordinate identiche');
    await evaluate("(() => {const canvas=document.querySelector('.pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,isPrimary:true,pointerType:'mouse',clientX:rect.left+rect.width*.35,clientY:rect.top+rect.height*.21}));})()");
    await waitFor(guest + '.testPointerMetrics().x>.349&&'+guest+'.testPointerMetrics().x<.351');
    await evaluate("(() => {const guestWindow=" + guest + ",view=guestWindow.document.querySelector('#pdfViewer'),bounds=view.getBoundingClientRect();view.dispatchEvent(new guestWindow.WheelEvent('wheel',{bubbles:true,cancelable:true,ctrlKey:true,deltaY:-Math.log(2)/.002,clientX:bounds.left+bounds.width/2,clientY:bounds.top+bounds.height/2}));})()");
    await waitFor(guest + ".document.querySelector('#zoomValue').textContent==='200%'");
    const horizontalPan = await evaluate("(() => {const view=" + guest + ".document.querySelector('#pdfViewer');view.scrollLeft=view.scrollWidth;view.scrollTop=view.scrollHeight;return view.scrollLeft;})()");
    assert.ok(horizontalPan > 0, 'Spettatore ingrandito e traslato manualmente in orizzontale');
    await evaluate("(() => {const canvas=document.querySelector('.pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,isPrimary:true,pointerType:'mouse',clientX:rect.left+rect.width*.05,clientY:rect.top+rect.height*.15}));})()");
    await waitFor(guest + '.testPointerMetrics().inVerticalView&&Math.abs('+guest+'.testPointerMetrics().x-.05)<.001');
    await pause(500);
    assert.ok(Math.abs(await evaluate(guest + ".document.querySelector('#pdfViewer').scrollLeft") - horizontalPan) < 1, 'Il ritorno verticale alla regia conserva la traslazione orizzontale dello spettatore');
    assert.equal((await evaluate(guest + '.testPointerMetrics()')).inView, false, 'Nessun inseguimento X anche se il puntatore e fuori campo orizzontale');
    await evaluate("(() => {const guestWindow=" + guest + ",view=guestWindow.document.querySelector('#pdfViewer'),bounds=view.getBoundingClientRect();view.dispatchEvent(new guestWindow.WheelEvent('wheel',{bubbles:true,cancelable:true,ctrlKey:true,deltaY:Math.log(2)/.002,clientX:bounds.left+bounds.width/2,clientY:bounds.top+bounds.height/2}));view.scrollLeft=0;view.scrollTop=0;})()");
    await waitFor(guest + ".document.querySelector('#zoomValue').textContent==='100%'");
    await evaluate("(() => {const canvas=document.querySelector('.pdf-page'),rect=canvas.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,isPrimary:true,pointerType:'mouse',clientX:rect.left+rect.width*.35,clientY:rect.top+rect.height*.21}));})()");
    await waitFor(guest + '.testPointerMetrics().x>.349&&'+guest+'.testPointerMetrics().x<.351');
    await evaluate("(() => {const view=document.querySelector('#pdfViewer');for(const [type,touches] of [['touchstart',[{clientX:50,clientY:50},{clientX:150,clientY:50}]],['touchmove',[{clientX:50,clientY:50},{clientX:210,clientY:50}]],['touchend',[]]]){const event=new Event(type,{bubbles:true,cancelable:true});Object.defineProperty(event,'touches',{value:touches});view.dispatchEvent(event);}})()");
    await pause(500);
    assert.ok(Math.abs(model.pointer_x - .35) < .001 && Math.abs(model.pointer_y - .21) < .001, 'Zoom conserva il punto sul documento');
    await evaluate("(() => {const view=document.querySelector('#pdfViewer'),canvas=view.querySelectorAll('.pdf-page')[1];view.scrollTop=canvas.offsetTop+20;})()");
    await pause(250);
    await evaluate("(() => {const view=document.querySelector('#pdfViewer'),canvas=view.querySelectorAll('.pdf-page')[1],rect=canvas.getBoundingClientRect(),bounds=view.getBoundingClientRect();canvas.dispatchEvent(new PointerEvent('pointermove',{bubbles:true,isPrimary:true,pointerType:'mouse',clientX:Math.max(bounds.left+24,Math.min(bounds.right-24,rect.left+rect.width*.6)),clientY:Math.max(bounds.top+24,Math.min(bounds.bottom-24,rect.top+rect.height*.2))}));})()");
    for (let attempt = 0; attempt < 50 && model.pointer_page !== 2; attempt++) await pause(50);
    assert.equal(model.pointer_page, 2);
    await waitFor(guest + ".testPointerMetrics(2).visible&&" + guest + ".testPointerMetrics(2).inView&&Math.abs(" + guest + ".testPointerMetrics(2).x-" + model.pointer_x + ")<.001&&Math.abs(" + guest + ".testPointerMetrics(2).y-" + model.pointer_y + ")<.001");
    model = {document_id: 100, revision: model.revision + 1, scroll_ratio: 0, pointer_page: 1, pointer_x: .5, pointer_y: .1, highlight_x: null, highlight_y: null, highlight_page: null, highlight_end_x: null, highlight_end_y: null, closed: false};
    await waitFor("document.querySelector('#documentTitle').textContent==='Documento 100'&&" + guest + ".document.querySelector('#documentTitle').textContent==='Documento 100'&&window.testPointerMetrics().visible&&" + guest + ".testPointerMetrics().visible");
    model.closed = true;
    await waitFor("document.querySelector('#connectionState').textContent==='Terminata'&&" + guest + ".document.querySelector('#connectionState').textContent==='Terminata'");
    console.log('OK regia ' + fixture.operator + 'px / spettatore ' + fixture.guest + 'px: ' + fixture.type + ', inseguimento solo verticale con posizione X manuale conservata, sinistro, evidenziazione per cinque secondi, scroll, zoom, cambio documento e chiusura.');
  }
  assert.equal(oldPaths, 0);
  assert.deepEqual(failures, []);
  console.log('Tutte le API simulate: nessuna modifica alla proiezione reale.');
} finally {
  if (socket?.readyState === WebSocket.OPEN) {await send('Browser.close').catch(() => {}); socket.close();}
  if (processHandle.exitCode === null) processHandle.kill();
  for (const request of pending.values()) clearTimeout(request.timer);
  const relative = path.relative(path.resolve(os.tmpdir()), path.resolve(profile));
  if (relative && !relative.startsWith('..') && !path.isAbsolute(relative) && path.basename(profile).startsWith('miodel-pointer-')) await rm(profile, {recursive: true, force: true, maxRetries: 10, retryDelay: 200}).catch(() => {});
}
