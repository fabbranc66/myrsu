import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = fs.readFileSync('ui/documents.js', 'utf8');
const functionSource = (name) => {
  const start = source.indexOf(`function ${name}(`);
  const end = source.indexOf('\n}', start);
  assert.ok(start >= 0 && end > start, `Funzione ${name} non trovata`);
  return source.slice(source.slice(start - 6, start) === 'async ' ? start - 6 : start, end + 2);
};
let createdElement;
let modalOpens = 0;
let frameLoads = 0;
const frame = {
  get src() { return this.currentUrl || ''; },
  set src(value) { this.currentUrl = new URL(value, 'https://example.test/myrsu/ui/documents.html').href; frameLoads += 1; },
};
const modal = { open: false, showModal() { this.open = true; modalOpens += 1; } };
const verificationUrl = 'document-verify.html?id=7&sig=SIGNATURE';
const pdfPages = [20, 450].map((top) => ({
  getBoundingClientRect() { return { left: 10, top, width: 300, height: 420 }; },
}));
const context = vm.createContext({
  URL,
  activeVerifyUrl: verificationUrl,
  verifyFrame: frame,
  verifyFrameModal: modal,
  pdfCanvasViewer: { contains(page) { return pdfPages.includes(page); } },
  window: { location: { href: 'https://example.test/myrsu/ui/documents.html' } },
  document: {
    createElement(tagName) {
      createdElement = { tagName, dataset: {}, style: {}, setAttribute(name, value) { this[name] = value; } };
      return createdElement;
    },
  },
});
vm.runInContext(['addPdfLinks', 'addPdfLink', 'openPdfLink', 'openVerifyModal', 'isQrTap'].map(functionSource).join('\n'), context);
const viewport = { convertToViewportRectangle: () => [251, 31, 277, 57] };
const page = { appendChild(element) { assert.equal(element, createdElement); } };
context.addPdfLink(page, viewport, [502, 728, 554, 780], 'http://localhost/myrsu/ui/document-verify.html?id=7&sig=SIGNATURE');
assert.equal(createdElement.tagName, 'button');
assert.equal(createdElement.type, 'button');
assert.equal(createdElement['aria-label'], 'Verifica autenticità documento');
assert.equal(createdElement.dataset.pdfLink, verificationUrl);
assert.equal(createdElement.style.left, '251px');
assert.equal(createdElement.style.height, '26px');
const verificationLink = createdElement;
let prevented = 0;
const event = { preventDefault() { prevented += 1; }, stopPropagation() {} };
context.openPdfLink(event, verificationLink);
context.openPdfLink(event, verificationLink);
assert.equal(frame.src, 'https://example.test/myrsu/ui/document-verify.html?id=7&sig=SIGNATURE');
assert.equal(frameLoads, 1, 'pointerup e click non devono ricaricare la verifica due volte');
assert.equal(modalOpens, 1);
assert.equal(prevented, 4);
context.addPdfLink(page, viewport, [0, 0, 10, 10], 'https://example.org/reference');
assert.equal(createdElement.tagName, 'a');
assert.equal(createdElement.href, 'https://example.org/reference');
assert.equal(createdElement.target, '_blank');
context.openPdfLink(event, createdElement);
assert.equal(frameLoads, 1, 'Un link esterno non deve aprire la verifica');
modal.open = false;
context.openPdfLink(event, verificationLink);
assert.equal(modalOpens, 2, 'La verifica deve potersi riaprire');
for (const [pageIndex, pdfPage] of pdfPages.entries()) {
  const links = [];
  await context.addPdfLinks({ getAnnotations: async () => [] }, viewport, { appendChild(link) { links.push(link); } });
  assert.equal(links.length, 1, `QR presente sulla pagina ${pageIndex + 1} anche senza annotazioni PDF`);
  assert.equal(links[0].dataset.pdfLink, verificationUrl);
  const target = { closest() { return pdfPage; } };
  const top = pdfPage.getBoundingClientRect().top;
  assert.equal(context.isQrTap({ target, clientX: 280, clientY: top + 30 }), true);
  assert.equal(context.isQrTap({ target, clientX: 100, clientY: top + 200 }), false);
}
assert.equal(context.isQrTap({ target: { closest() { return null; } } }), false);
assert.equal(context.isQrTap({ target: { closest() { return {}; } } }), false);
console.log('OK: QR sulla prima e seconda pagina senza annotazioni, tocco locale, apertura singola e link esterni invariati.');
