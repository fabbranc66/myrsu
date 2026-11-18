import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {existsSync} from 'node:fs';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';

const root = process.cwd();
const output = path.join(root, 'output', 'branding', 'tests');
const browserPath = [
  process.env.CHROME_PATH,
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].find((candidate) => candidate && existsSync(candidate));
assert.ok(browserPath, 'Browser Chromium locale non trovato.');
assert.equal(typeof WebSocket, 'function', 'Serve Node con WebSocket integrato.');

const pause = (duration) => new Promise((resolve) => setTimeout(resolve, duration));
const profile = await mkdtemp(path.join(os.tmpdir(), 'myrsu-branding-test-'));
const browserProcess = spawn(browserPath, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--disable-background-networking', '--disable-extensions', '--disable-sync',
  '--remote-debugging-address=127.0.0.1', '--remote-debugging-port=0',
  `--user-data-dir=${profile}`, 'about:blank',
], {windowsHide: true, stdio: 'ignore'});
browserProcess.on('error', (error) => console.error(error.message));

let socket;
let sequence = 0;
const pending = new Map();
const networkErrors = [];
let blockedApiCalls = 0;

function send(method, params = {}) {
  return new Promise((resolve, reject) => {
    const id = ++sequence;
    const timer = setTimeout(() => {
      pending.delete(id);
      reject(new Error(`Timeout CDP: ${method}`));
    }, 15000);
    pending.set(id, {resolve, reject, timer});
    socket.send(JSON.stringify({id, method, params}));
  });
}

async function evaluate(expression) {
  const result = await send('Runtime.evaluate', {expression, returnByValue: true});
  if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
  return result.result.value;
}

async function waitForDocument(selector = '.app-header') {
  for (let attempt = 0; attempt < 100; attempt++) {
    const ready = await evaluate(`document.readyState === 'complete'
      && !!document.querySelector(${JSON.stringify(selector)})
      && Array.from(document.images).every((image) => image.complete)
      && document.fonts.status === 'loaded'`);
    if (ready) return;
    await pause(100);
  }
  throw new Error('La pagina di prova non ha completato il caricamento.');
}

try {
  await mkdir(output, {recursive: true});
  let port;
  for (let attempt = 0; attempt < 100; attempt++) {
    try {
      port = Number((await readFile(path.join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0]);
      if (port) break;
    } catch {}
    await pause(100);
  }
  assert.ok(port, 'Avvio browser headless non riuscito.');
  const targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
  const target = targets.find((candidate) => candidate.type === 'page');
  assert.ok(target?.webSocketDebuggerUrl, 'Pagina di prova non disponibile.');
  socket = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve, reject) => {
    socket.addEventListener('open', resolve, {once: true});
    socket.addEventListener('error', reject, {once: true});
  });
  socket.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    if (message.id && pending.has(message.id)) {
      const request = pending.get(message.id);
      pending.delete(message.id);
      clearTimeout(request.timer);
      if (message.error) request.reject(new Error(JSON.stringify(message.error)));
      else request.resolve(message.result);
    }
    if (message.method !== 'Fetch.requestPaused') return;
    const {requestId, request} = message.params;
    let operation;
    if (request.url.includes('/api/v1/')) {
      blockedApiCalls++;
      operation = send('Fetch.fulfillRequest', {
        requestId, responseCode: 401,
        responseHeaders: [{name: 'Content-Type', value: 'application/json'}],
        body: Buffer.from('{"message":"Test grafico isolato"}').toString('base64'),
      });
    } else if (request.url.startsWith('http://localhost/miodel/') || /^(data:|blob:)/.test(request.url)) {
      operation = send('Fetch.continueRequest', {requestId});
    } else {
      operation = send('Fetch.failRequest', {requestId, errorReason: 'BlockedByClient'});
    }
    operation.catch((error) => networkErrors.push(error.message));
  });
  await send('Runtime.enable');
  await send('Page.enable');
  await send('Fetch.enable', {patterns: [{urlPattern: '*', requestStage: 'Request'}]});

  const results = [];
  const pages = ['login.html', 'app/index.html', 'pdr-calculator.html'];
  const widths = [320, 360, 390, 430, 768, 1024, 1366, 1920];
  for (const width of widths) {
    await send('Emulation.setDeviceMetricsOverride', {
      width, height: 900, deviceScaleFactor: 1, mobile: width <= 760,
    });
    for (const pageName of pages) {
      await send('Page.navigate', {url: `http://localhost/miodel/ui/${pageName}`});
      await waitForDocument();
      if (pageName === 'app/index.html') {
        await evaluate(`document.querySelectorAll('.app-header .app-nav > .hidden')
          .forEach((item) => {item.classList.remove('hidden'); item.hidden = false;})`);
      }
      const metrics = await evaluate(`(() => {
        const rectangle = (element) => {
          const rect = element.getBoundingClientRect();
          return {left: rect.left, right: rect.right, top: rect.top,
            bottom: rect.bottom, width: rect.width, height: rect.height};
        };
        const overlaps = (first, second) => first.left < second.right - 1
          && first.right > second.left + 1 && first.top < second.bottom - 1
          && first.bottom > second.top + 1;
        const header = document.querySelector('.app-header');
        const parent = header.querySelector(':scope > div');
        const parentRect = rectangle(parent);
        const image = header.querySelector('.pdr-dashboard-logo img');
        const style = getComputedStyle(parent, '::before');
        const logoWidth = parseFloat(style.width);
        const logoHeight = parseFloat(style.height);
        const logoTop = style.position === 'static' ? parentRect.top
          : parentRect.top + (parentRect.height - logoHeight) / 2;
        const logo = image ? rectangle(image) : {left: parentRect.left,
          right: parentRect.left + logoWidth, top: logoTop,
          bottom: logoTop + logoHeight, width: logoWidth, height: logoHeight};
        const title = rectangle(header.querySelector('h1'));
        const navigation = rectangle(header.querySelector('.app-nav'));
        return {viewport: document.documentElement.clientWidth, innerViewport: innerWidth, logo, title, navigation,
          header: rectangle(header), overflow: header.scrollWidth > header.clientWidth + 1,
          titleOverlap: overlaps(logo, title), navigationOverlap: overlaps(logo, navigation),
          source: image ? image.getAttribute('src') : style.backgroundImage,
          imageLoaded: image ? image.naturalWidth > 0 : true,
          dashboardLink: image ? image.closest('a').getAttribute('href') : null};
      })()`);
      const label = `${pageName} a ${width}px`;
      if (metrics.viewport > width || metrics.viewport < width - 20
          || metrics.titleOverlap || metrics.navigationOverlap || metrics.overflow) {
        console.log(JSON.stringify({label, metrics}));
      }
      assert.ok(metrics.viewport <= width && metrics.viewport >= width - 20, label);
      assert.ok(metrics.source.includes('miodelegato-logo-light.png'), label);
      assert.ok(metrics.imageLoaded, label);
      assert.ok(metrics.logo.width >= 150, `${label}: marchio troppo piccolo`);
      assert.ok(metrics.logo.left >= metrics.header.left - 1, label);
      assert.ok(metrics.logo.right <= metrics.header.right + 1, label);
      assert.ok(metrics.header.right <= width + 1, `${label}: testata fuori schermo`);
      assert.equal(metrics.titleOverlap, false, `${label}: sovrapposizione titolo`);
      assert.equal(metrics.navigationOverlap, false, `${label}: sovrapposizione menu`);
      assert.equal(metrics.overflow, false, `${label}: overflow testata`);
      if (pageName === 'pdr-calculator.html') assert.equal(metrics.dashboardLink, 'app/index.html');
      if (width <= 760 && pageName !== 'pdr-calculator.html') {
        const menu = await evaluate(`(() => {
          const navigation = document.querySelector('.app-header .app-nav');
          navigation.classList.add('menu-open');
          const rect = navigation.getBoundingClientRect();
          const result = {left: rect.left, right: rect.right};
          navigation.classList.remove('menu-open');
          return result;
        })()`);
        assert.ok(menu.left >= 0 && menu.right <= Math.max(width, metrics.innerViewport) + 1,
          `${label}: menu mobile fuori schermo`);
      }
      if ([390, 1366].includes(width)) {
        const screenshot = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
        await writeFile(path.join(output, `${pageName.replaceAll('/', '-').replace('.html', '')}-${width}.png`),
          Buffer.from(screenshot.data, 'base64'));
      }
      results.push({page: pageName, width, innerViewport: metrics.innerViewport,
        logoWidth: metrics.logo.width, headerHeight: metrics.header.height});
      console.log(`OK: ${label}`);
    }
  }
  for (const width of [320, 360, 390, 430, 768, 1366]) {
    await send('Emulation.setDeviceMetricsOverride', {
      width, height: 900, deviceScaleFactor: 1, mobile: width <= 760,
    });
    await send('Page.navigate', {url: 'http://localhost/miodel/ui/document-verify.html'});
    await waitForDocument('.verify-modal');
    const metrics = await evaluate(`(() => {
      const modal = document.querySelector('.verify-modal');
      const rect = modal.getBoundingClientRect();
      const heading = modal.querySelector('h1').getBoundingClientRect();
      const close = modal.querySelector('#closeVerifyModal').getBoundingClientRect();
      return {open: modal.open, left: rect.left, right: rect.right,
        overflow: modal.scrollWidth > modal.clientWidth + 1,
        overlap: heading.left < close.right && heading.right > close.left
          && heading.top < close.bottom && heading.bottom > close.top,
        viewport: innerWidth};
    })()`);
    assert.ok(metrics.open && metrics.left >= 0 && metrics.right <= width + 1);
    if (metrics.overflow || metrics.overlap) {
      console.log(JSON.stringify({width, metrics}));
      const screenshot = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
      await writeFile(path.join(output, 'document-verify-failure.png'), Buffer.from(screenshot.data, 'base64'));
    }
    assert.equal(metrics.overflow, false, `Verifica a ${width}px: overflow`);
    assert.equal(metrics.overlap, false, `Verifica a ${width}px: sovrapposizione`);
    if (width === 390) {
      const screenshot = await send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false});
      await writeFile(path.join(output, 'document-verify-390.png'), Buffer.from(screenshot.data, 'base64'));
    }
    results.push({page: 'document-verify.html', width});
    console.log(`OK: verifica documento a ${width}px`);
  }
  assert.deepEqual(networkErrors, []);
  await writeFile(path.join(output, 'layout-results.json'), JSON.stringify({results, blockedApiCalls}, null, 2));
  console.log(`OK: ${results.length} layout verificati. API bloccate: ${blockedApiCalls}. Nessun account o dato modificato.`);
} finally {
  if (socket?.readyState === WebSocket.OPEN) {
    await send('Browser.close').catch(() => {});
    socket.close();
  }
  if (browserProcess.exitCode === null) browserProcess.kill();
  for (const request of pending.values()) clearTimeout(request.timer);
  const relative = path.relative(path.resolve(os.tmpdir()), path.resolve(profile));
  if (relative && !relative.startsWith('..') && !path.isAbsolute(relative)
      && path.basename(profile).startsWith('myrsu-branding-test-')) {
    await rm(profile, {recursive: true, force: true, maxRetries: 10, retryDelay: 200}).catch(() => {});
  }
}
