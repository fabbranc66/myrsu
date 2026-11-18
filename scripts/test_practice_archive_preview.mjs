import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = fs.readFileSync('ui/practice-view.js', 'utf8');
function extract(name, text = source) {
  const start = text.indexOf(`function ${name}(`);
  const end = text.indexOf('\n}', start);
  assert.ok(start >= 0 && end > start, `Funzione ${name} non trovata`);
  return text.slice(text.slice(start - 6, start) === 'async ' ? start - 6 : start, end + 2);
}
const calls = [];
const storage = new Map();
const classes = () => ({ add() {}, remove() {} });
const modal = { open: false, showModal() { this.open = true; } };
const viewer = { classList: classes(), textContent: '', innerHTML: '' };
const location = { href: 'http://localhost/miodel/ui/practice-view.html?id=4' };
let blobs = 0;
class PreviewUrl extends URL {
  static createObjectURL() { return `blob:preview-${++blobs}`; }
  static revokeObjectURL() {}
}
const context = vm.createContext({
  URL: PreviewUrl, URLSearchParams, apiBase: '../api/v1', token: 'temporary-test-token',
  documentModal: modal, documentPreview: { src: '', classList: classes() }, pdfCanvasViewer: viewer,
  pdfToolbar: { classList: classes() }, documentPreviewUrl: null, activePdfUrl: null, activeVerifyUrl: null, activePdf: null, pdfScale: 1,
  window: { location }, localStorage: { setItem: (key, value) => storage.set(key, value) },
  api: async (path) => { calls.push(path); return path.includes('/archive/') ? { document: { signature: 'ARCHIVE' } } : { signature: 'LOCAL' }; },
  fetch: async (path, options) => {
    calls.push(path);
    assert.equal(options.headers.Authorization, 'Bearer temporary-test-token');
    return { ok: !path.includes('/sources/99/'), blob: async () => ({}), json: async () => ({ error: { message: 'Accesso negato' } }) };
  },
  renderPdfCanvas: async () => {},
  MyRsuIcons: { get: (name) => `<svg data-icon="${name}"></svg>` },
  document: { createElement: (tagName) => ({ tagName, dataset: {}, style: {}, setAttribute(name, value) { this[name] = value; } }) },
});
vm.runInContext(['documentPaths', 'fetchDocumentPreview', 'showDocument', 'itemAction', 'escapeHtml', 'addPdfLinks', 'addPdfLink'].map((name) => extract(name)).join('\n'), context);
const local = context.itemAction({ document_id: 1 });
const archived = context.itemAction({ document_id: 1, archive_source_configuration_id: 7, archive_origin_editable: true });
assert.ok(local.includes('class="icon-action" data-document-id="1"'));
assert.ok(archived.includes('class="icon-action" data-document-id="1" data-document-source="7"'));
assert.ok(archived.includes('title="Modifica"'));
assert.ok(!archived.includes('Consulta documento originale'));
assert.ok(!archived.includes('archive-item.html'));
assert.ok(!context.itemAction({ document_id: 1, archive_source_configuration_id: 7 }).includes('title="Modifica"'));
let touchHandler;
let handledTouches = 0;
let preventedTouches = 0;
const touchContext = vm.createContext({ timeline: { addEventListener: (type, handler) => { touchHandler = handler; } }, handleTimelineAction: async () => { handledTouches += 1; } });
const touchStart = source.indexOf("timeline.addEventListener('touchend'");
vm.runInContext(source.slice(touchStart, source.indexOf('\n});', touchStart) + 4), touchContext);
await touchHandler({ target: { closest: () => null }, preventDefault: () => { preventedTouches += 1; } });
assert.equal(preventedTouches, 0, 'I pulsanti modifica non devono perdere il click su mobile');
await touchHandler({ target: { closest: (selector) => { assert.ok(selector.includes('[data-document-id]')); return {}; } }, preventDefault: () => { preventedTouches += 1; } });
assert.equal(preventedTouches, 1);
assert.equal(handledTouches, 1);
await context.showDocument(1, '7');
assert.ok(calls.includes('../api/v1/archive/sources/7/documents/1/file'));
assert.ok(calls.includes('/archive/sources/7/document/1'));
assert.ok(!calls.includes('../api/v1/documents/1/preview'));
assert.equal(modal.open, true);
assert.equal(location.href, 'http://localhost/miodel/ui/practice-view.html?id=4');
assert.equal(context.activeVerifyUrl, 'document-verify.html?id=1&sig=ARCHIVE&source=7');
assert.equal(new URL(storage.get('myrsu_verify_return_url')).searchParams.get('preview_source'), '7');
const viewport = { convertToViewportRectangle: () => [1, 2, 30, 40] };
for (const pageNumber of [1, 2]) {
  const links = [];
  await context.addPdfLinks({ getAnnotations: async () => [] }, viewport, { appendChild: (link) => links.push(link) }, pageNumber);
  assert.equal(links.length, 1);
  assert.equal(links[0].dataset.pdfLink, context.activeVerifyUrl);
}
const annotations = [];
context.addPdfLink({ appendChild: (link) => annotations.push(link) }, viewport, [0, 0, 10, 10], 'http://localhost/miodel/ui/document-verify.html?id=1&sig=OLD');
assert.equal(annotations[0].dataset.pdfLink, context.activeVerifyUrl);
await assert.rejects(context.showDocument(1, '99'), /Accesso negato/);
assert.equal(context.activeVerifyUrl, null);
assert.equal(context.activePdfUrl, null);
assert.equal(viewer.textContent, 'Accesso negato');
await context.showDocument(1);
assert.ok(calls.includes('../api/v1/documents/1/preview'));
assert.ok(calls.includes('/documents/1'));
assert.equal(context.activeVerifyUrl, 'document-verify.html?id=1&sig=LOCAL');
assert.equal(new URL(storage.get('myrsu_verify_return_url')).searchParams.has('preview_source'), false);
assert.throws(() => context.documentPaths(1, '../'), /Origine/);
const verifySource = fs.readFileSync('ui/document-verify.js', 'utf8');
for (const [query, expected] of [['', '../api/v1/documents/1'], ['source=7', '../api/v1/archive/sources/7/documents/1']]) {
  const verify = vm.createContext({ params: new URLSearchParams(query) });
  vm.runInContext(extract('verificationPath', verifySource), verify);
  assert.equal(verify.verificationPath(1), expected);
}
console.log('OK: apertura normale dalla cronologia, stessa modale, origine corretta con ID uguali, QR su tutte le pagine e nessuna navigazione intermedia.');
