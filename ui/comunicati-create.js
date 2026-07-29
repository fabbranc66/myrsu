import * as pdfjsLib from './vendor/pdfjs/pdf.mjs';

pdfjsLib.GlobalWorkerOptions.workerSrc = './vendor/pdfjs/pdf.worker.mjs';

const apiBase = '../api/v1';
const token = sessionStorage.getItem('token') || localStorage.getItem('token');
const table = document.querySelector('#comunicatiTable');
const jsonOutput = document.querySelector('#jsonOutput');
const message = document.querySelector('#message');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const pdfCanvasViewer = document.querySelector('#pdfCanvasViewer');
const pdfToolbar = document.querySelector('#pdfToolbar');
const pdfZoomValue = document.querySelector('#pdfZoomValue');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const verifyFrameModal = document.querySelector('#verifyFrameModal');
const verifyFrame = document.querySelector('#verifyFrame');
const closeVerifyFrameModal = document.querySelector('#closeVerifyFrameModal');
let documentPreviewUrl = null;
let activePdfUrl = null;
let activeVerifyUrl = null;
let activePdf = null;
let pdfScale = 1;
let pinchStartDistance = 0;
let pinchStartScale = 1;
let comunicati = [];

async function api(path, options = {}) {
  const headers = options.headers || {};
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

function row(document) {
  const draft = document.conversion_status === 'pending';
  const editUrl = draft ? `comunicati-editor.html?id=${document.id}` : `document-edit.html?id=${document.id}`;
  const title = document.protocol_number || document.protocol_subject || document.original_name;
  const subject = document.protocol_subject || document.original_name || '';
  const preview = document.pdf_available
    ? `<button class="icon-action" data-view="${document.id}" title="Anteprima">${MyRsuIcons.get('eye')}</button>`
    : `<span class="doc-type-tag draft" title="PDF fisico mancante">NO FILE</span>`;
  return `<tr>
    <td data-label="Documento"><span class="table-value-title">${draft ? '<span class="doc-type-tag draft">DRAFT</span>' : '<span class="doc-type-tag">PDF</span>'} <span class="truncate-title" title="${escapeHtml(subject)}">${escapeHtml(title)}</span></span></td>
    <td data-label="Visibilita">${document.visibility}</td>
    <td data-label="Stato">${draft ? 'bozza' : 'ufficiale'}</td>
    <td data-label="Data"><span class="truncate-title" title="${escapeHtml(document.created_at || '-')}">${document.created_at || '-'}</span></td>
    <td data-label="Azioni" class="actions-cell">${draft ? '' : preview}<a class="icon-action" href="${editUrl}" title="Modifica">${MyRsuIcons.get('edit')}</a>${draft ? `<button class="draft-generate-button" data-generate="${document.id}">${MyRsuIcons.get('document')} Genera ufficiale</button>` : ''}</td>
  </tr>`;
}

function escapeHtml(value) {
  return String(value || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

async function load() {
  const documents = await api('/documents');
  comunicati = documents.filter((document) => document.category === 'comunicati');
  table.innerHTML = comunicati.map(row).join('');
}

document.addEventListener('click', async (event) => {
  const view = event.target.closest('[data-view]');
  if (view) {
    await showDocument(view.dataset.view);
    return;
  }

  const button = event.target.closest('[data-generate]');
  if (!button) return;
  try {
    const data = await api(`/comunicati/${button.dataset.generate}/generate`, { method: 'POST' });
    message.textContent = `Generato ${data.protocol.protocol_number}`;
    await load();
  } catch (error) {
    message.textContent = error.message;
  }
});

async function showDocument(id) {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  const previewUrl = `${apiBase}/documents/${id}/preview?token=${encodeURIComponent(token || '')}`;
  const row = comunicati.find((item) => Number(item.id) === Number(id));
  activeVerifyUrl = row?.signature ? `document-verify.html?id=${encodeURIComponent(id)}&sig=${encodeURIComponent(row.signature)}` : null;
  if (isMobileViewport()) {
    documentPreview.src = '';
    documentPreview.classList.add('hidden');
    pdfCanvasViewer.classList.remove('hidden');
    pdfToolbar.classList.remove('hidden');
    activePdfUrl = previewUrl;
    activePdf = null;
    pdfScale = 1;
    await renderPdfCanvas();
  } else {
    pdfCanvasViewer.classList.add('hidden');
    pdfToolbar.classList.add('hidden');
    documentPreview.src = previewUrl;
    documentPreview.classList.remove('hidden');
  }
  documentModal.showModal();
}

async function renderPdfCanvas() {
  if (!activePdfUrl) return;
  pdfCanvasViewer.innerHTML = '<p class="muted">Caricamento PDF...</p>';
  activePdf = activePdf || await pdfjsLib.getDocument({
    url: activePdfUrl,
    cMapUrl: './vendor/pdfjs/cmaps/',
    cMapPacked: true,
    standardFontDataUrl: './vendor/pdfjs/standard_fonts/',
    wasmUrl: './vendor/pdfjs/wasm/',
  }).promise;
  pdfCanvasViewer.innerHTML = '';
  pdfZoomValue.textContent = `${Math.round(pdfScale * 100)}%`;
  for (let pageNumber = 1; pageNumber <= activePdf.numPages; pageNumber += 1) {
    const page = await activePdf.getPage(pageNumber);
    const viewport = page.getViewport({ scale: pageScale(page) * pdfScale });
    const canvas = document.createElement('canvas');
    const pageWrap = document.createElement('div');
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    canvas.className = 'pdf-page-canvas';
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    pageWrap.className = 'pdf-page-wrap';
    pageWrap.style.width = `${canvas.width}px`;
    pageWrap.style.height = `${canvas.height}px`;
    pageWrap.appendChild(canvas);
    await addPdfLinks(page, viewport, pageWrap, pageNumber);
    pdfCanvasViewer.appendChild(pageWrap);
  }
}

async function addPdfLinks(page, viewport, pageWrap, pageNumber) {
  const annotations = await page.getAnnotations({ intent: 'display' });
  annotations.filter((item) => (item.url || item.unsafeUrl) && item.rect).forEach((item) => {
    addPdfLink(pageWrap, viewport, item.rect, item.url || item.unsafeUrl);
  });
  if (pageNumber === 1 && activeVerifyUrl) addPdfLink(pageWrap, viewport, [502, 728, 554, 780], activeVerifyUrl);
}

function addPdfLink(pageWrap, viewport, rect, url) {
  const [x1, y1, x2, y2] = viewport.convertToViewportRectangle(rect);
  const link = document.createElement('a');
  link.href = url;
  link.dataset.pdfLink = url;
  link.target = '_blank';
  link.rel = 'noopener';
  link.className = 'pdf-page-link';
  link.style.left = `${Math.min(x1, x2)}px`;
  link.style.top = `${Math.min(y1, y2)}px`;
  link.style.width = `${Math.abs(x2 - x1)}px`;
  link.style.height = `${Math.abs(y2 - y1)}px`;
  pageWrap.appendChild(link);
}

function pageScale(page) {
  const viewport = page.getViewport({ scale: 1 });
  const availableWidth = Math.max(260, documentModal.clientWidth - 36);
  return Math.min(2, availableWidth / viewport.width);
}

function isMobileViewport() {
  return window.matchMedia('(max-width: 760px)').matches;
}

function pinchDistance(touches) {
  return Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);
}

closeDocumentModal.addEventListener('click', () => {
  closePreviewModal();
});

window.addEventListener('message', (event) => {
  if (typeof event.data !== 'object' || event.data?.type !== 'myrsu:verify-modal') return;
  if (verifyFrame.src === event.data.url) return;
  verifyFrame.src = event.data.url;
  verifyFrameModal.showModal();
});

documentModal.addEventListener('click', (event) => {
  if (event.target === documentModal) closePreviewModal();
});

function closePreviewModal() {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  activePdfUrl = null;
  activePdf = null;
  pdfCanvasViewer.innerHTML = '';
  pdfCanvasViewer.classList.add('hidden');
  pdfToolbar.classList.add('hidden');
  documentPreview.src = '';
  documentModal.close();
}

pdfCanvasViewer.addEventListener('touchstart', (event) => {
  if (!isMobileViewport() || event.touches.length !== 2) return;
  pinchStartDistance = pinchDistance(event.touches);
  pinchStartScale = pdfScale;
});

pdfCanvasViewer.addEventListener('touchmove', (event) => {
  if (!isMobileViewport() || event.touches.length !== 2 || pinchStartDistance <= 0) return;
  event.preventDefault();
  pdfScale = Math.max(.6, Math.min(5, pinchStartScale * (pinchDistance(event.touches) / pinchStartDistance)));
}, { passive: false });

pdfCanvasViewer.addEventListener('touchend', async () => {
  if (!isMobileViewport() || pinchStartDistance <= 0) return;
  pinchStartDistance = 0;
  await renderPdfCanvas();
});

closeVerifyFrameModal.addEventListener('click', () => {
  verifyFrame.src = '';
  verifyFrameModal.close();
});

if (!token) window.location.href = 'app/index.html';
load().catch((error) => {
  message.textContent = error.message;
});
