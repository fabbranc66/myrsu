import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('ui/documents.js', 'utf8');
function extract(name) {
  const start = source.indexOf(`function ${name}(`);
  const end = source.indexOf('\n}', start);
  assert.ok(start >= 0 && end > start, `Funzione ${name} non trovata`);
  return source.slice(source.slice(start - 6, start) === 'async ' ? start - 6 : start, end + 2);
}

const cases = [
  {ratio: 3, width: 340, zoom: 1},
  {ratio: 2, width: 340, zoom: 1},
  {ratio: 1, width: 340, zoom: 1},
  {ratio: 1.25, width: 340, zoom: 1},
  {ratio: 6, width: 340, zoom: 1},
  {ratio: undefined, width: 340, zoom: 1},
  {ratio: 3, width: 340, zoom: 3},
  {ratio: 3, width: 840, zoom: 5},
];
let checks = 0;
for (const test of cases) {
  const rendered = [];
  const linked = [];
  const viewer = {innerHTML: '', pages: [], appendChild(page) { this.pages.push(page); }};
  const pdf = {numPages: 2, async getPage(number) {
    return {number, getViewport: ({scale}) => ({width: 595.28 * scale, height: 841.89 * scale}),
      render(options) { rendered.push(options); return {promise: Promise.resolve()}; }};
  }};
  const context = vm.createContext({
    window: {devicePixelRatio: test.ratio}, activePdfUrl: 'blob:local-test', activePdf: null,
    pdfScale: test.zoom, pdfZoomValue: {textContent: ''}, pdfCanvasViewer: viewer,
    pdfjsLib: {getDocument(options) { assert.equal(options.url, 'blob:local-test'); return {promise: Promise.resolve(pdf)}; }},
    documentModal: {clientWidth: test.width + 36},
    document: {createElement(tag) { return {tagName: tag, width: 0, height: 0, style: {}, children: [],
      getContext() { return {canvas: this}; }, appendChild(child) { this.children.push(child); }}; }},
    addPdfLinks: async (page, viewport, wrap) => linked.push({page, viewport, wrap}),
  });
  vm.runInContext([extract('renderPdfCanvas'), extract('pageScale')].join('\n'), context);
  await context.renderPdfCanvas();
  assert.equal(rendered.length, 2);
  assert.equal(viewer.pages.length, 2);
  assert.equal(context.pdfZoomValue.textContent, `${test.zoom * 100}%`);
  for (const [index, options] of rendered.entries()) {
    const viewport = options.viewport;
    const scale = Math.min(Math.max(1, test.ratio || 1), 3,
      Math.sqrt(8_388_608 / (viewport.width * viewport.height)),
      8192 / Math.max(viewport.width, viewport.height));
    const canvas = options.canvasContext.canvas;
    const label = `DPR ${test.ratio ?? 'predefinito'}, zoom ${test.zoom}, pagina ${index + 1}`;
    assert.equal(canvas.width, Math.ceil(viewport.width * scale), label);
    assert.equal(canvas.height, Math.ceil(viewport.height * scale), label);
    assert.equal(canvas.style.width, `${viewport.width}px`, label);
    assert.equal(canvas.style.height, `${viewport.height}px`, label);
    assert.equal(viewer.pages[index].style.width, `${viewport.width}px`, label);
    assert.equal(viewer.pages[index].style.height, `${viewport.height}px`, label);
    assert.deepEqual(Array.from(options.transform), [scale, 0, 0, scale, 0, 0], label);
    assert.equal(linked[index].viewport, viewport, `${label}: coordinate link in pixel CSS`);
    assert.equal(linked[index].wrap, viewer.pages[index]);
    assert.ok(canvas.width * canvas.height <= 8_410_000, `${label}: limite memoria`);
    assert.ok(Math.max(canvas.width, canvas.height) <= 8192, `${label}: limite dimensioni`);
    checks += 11;
  }
}

const context = vm.createContext({
  activeVerifyUrl: 'document-verify.html?id=1&sig=TEST',
  document: {createElement: (tag) => ({tag, style: {}, dataset: {}, setAttribute(name, value) { this[name] = value; }})},
});
vm.runInContext([extract('addPdfLinks'), extract('addPdfLink')].join('\n'), context);
for (const page of [1, 2]) {
  const links = [];
  const viewport = {convertToViewportRectangle: () => [250, 30, 278, 58]};
  await context.addPdfLinks({getAnnotations: async () => []}, viewport, {appendChild: (link) => links.push(link)});
  assert.equal(links.length, 1);
  assert.equal(links[0].dataset.pdfLink, context.activeVerifyUrl);
  assert.equal(links[0].style.width, '28px');
  assert.equal(links[0].style.height, '28px');
  checks += 4;
}
assert.match(source, /documentPreview\.src = previewUrl;/);
assert.match(readFileSync('ui/documents.html', 'utf8'), /documents\.js\?v=20261114-mobile-pdf-hidpi-1/);
console.log(`OK: ${checks + 2} controlli: anteprima HiDPI, zoom, limiti memoria, QR su entrambe le pagine e desktop invariato. Nessuna richiesta reale.`);
