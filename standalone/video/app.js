import * as pdfjsLib from '/miodel/ui/vendor/pdfjs/pdf.mjs';
import { createProjectionPointer } from './pointer.js?v=20261118-vertical-follow-1';
import { createProjectionPdfViewer } from './pdf-viewer.js?v=20261118-document-zoom-1';

pdfjsLib.GlobalWorkerOptions.workerSrc = '/miodel/ui/vendor/pdfjs/pdf.worker.mjs';

const apiBase = `${location.origin}/miodel/api/v1`;
const params = new URLSearchParams(location.search);
const publicToken = params.get('token') || '';
let operator = params.get('operator') === '1';
let controlToken = params.get('control') || '';
const viewer = document.querySelector('#pdfViewer');
const emptyState = document.querySelector('#emptyState');
const meetingTitle = document.querySelector('#meetingTitle');
const documentTitle = document.querySelector('#documentTitle');
const connectionState = document.querySelector('#connectionState');
const operatorPanel = document.querySelector('#operatorPanel');
const jsonOutput = document.querySelector('#jsonOutput');
const pointer = createProjectionPointer(viewer);
const pdfViewer = createProjectionPdfViewer(viewer, (error) => showJson({error: {message: error.message}}));
const zoomValue = document.querySelector('#zoomValue');
const maximumZoom = 10;
let currentDocumentId = null;
let positionTimer = null;
let positionRequest = null;
let suppressScroll = false;
let zoomScale = 1;
let pinchStartDistance = 0;
let pinchStartZoom = 1;
let pinchAnchor = null;
let activePdf = null;
let highlightPage = null;
let highlightProtectedUntil = 0;

document.querySelector('#copyLink').addEventListener('click', async () => {
  await navigator.clipboard.writeText(document.querySelector('#publicLink').href);
});

viewer.addEventListener('scroll', () => {
  pdfViewer.schedule();
  if (!operator || suppressScroll || !controlToken) return;
  if (!pinchStartDistance) pointer.captureAfterScroll();
  queuePosition();
});

function queuePosition() {
  if (!operator || !controlToken || !currentDocumentId) return;
  if (positionTimer !== null) return;
  positionTimer = setTimeout(() => {
    positionTimer = null;
    if (positionRequest) {
      queuePosition();
      return;
    }
    positionRequest = updatePosition()
      .catch((error) => showJson({error: {message: error.message}}))
      .finally(() => { positionRequest = null; });
  }, 100);
}

function movePointer(event) {
  if (!operator || !controlToken || !currentDocumentId || pinchStartDistance || event.isPrimary === false) return;
  const dragging = highlightPage !== null && (event.buttons & 1) !== 0;
  if (!dragging && highlightPage !== null) finishHighlight();
  const cleared = !dragging && performance.now() >= highlightProtectedUntil ? pointer.clearHighlight() : false;
  const moved = pointer.capture(event.clientX, event.clientY, dragging);
  if (dragging && pointer.snapshot().pointer_page !== highlightPage) {
    pointer.clearHighlight();
    highlightPage = null;
  }
  if (moved || cleared) queuePosition();
}

viewer.addEventListener('pointermove', movePointer, {passive: true});
function finishHighlight() {
  if (highlightPage === null) return;
  pointer.freezeHighlight();
  highlightPage = null;
  highlightProtectedUntil = performance.now() + 5000;
  queuePosition();
}

viewer.addEventListener('pointerdown', (event) => {
  if (event.button !== 0 || event.pointerType !== 'mouse') return movePointer(event);
  if (!operator || !controlToken || !currentDocumentId || !event.target.closest('.pdf-page')) return;
  event.preventDefault();
  highlightProtectedUntil = 0;
  pointer.capture(event.clientX, event.clientY);
  pointer.startHighlight();
  highlightPage = pointer.snapshot().pointer_page;
  queuePosition();
});
window.addEventListener('pointerup', (event) => { if (event.button === 0) finishHighlight(); });
viewer.addEventListener('pointercancel', finishHighlight);

viewer.addEventListener('touchstart', startPinch, {passive: true});
viewer.addEventListener('touchmove', movePinch, {passive: false});
viewer.addEventListener('touchend', endPinch, {passive: true});
viewer.addEventListener('wheel', (event) => {
  if (!event.ctrlKey && !event.metaKey) return;
  event.preventDefault();
  if (!activePdf || !currentDocumentId) return;
  if (operator) pointer.capture(event.clientX, event.clientY);
  const units = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? viewer.clientHeight : 1;
  applyZoom(Math.max(.75, Math.min(maximumZoom, zoomScale * Math.exp(-event.deltaY * units * .002))), zoomAnchor(event.clientX, event.clientY));
  if (operator) queuePosition();
  requestAnimationFrame(() => { suppressScroll = false; });
}, {passive: false});

async function poll() {
  try {
    const response = await fetch(`${apiBase}/projection-access?token=${encodeURIComponent(publicToken)}&control=${encodeURIComponent(controlToken)}`, {cache: 'no-store'});
    const payload = await response.json();
    showJson(payload);
    if (!response.ok) throw new Error(payload.error?.message || 'Proiezione non disponibile.');
    const state = payload.data;
    connectionState.textContent = operator ? 'Regia' : 'Ospite';
    meetingTitle.textContent = state.meeting_title;
    if (operator && state.qr_data_url) showOperatorData(state);
    const documentChanged = state.document_id !== currentDocumentId;
    const pointerChanged = documentChanged || pointer.differs(state);
    if (documentChanged) await loadDocument(state);
    if (!operator) {
      if (pointerChanged && state.document_id) alignScroll(state.scroll_ratio);
      pointer.apply(state, pointerChanged);
    }
  } catch (error) {
    currentDocumentId = null;
    highlightPage = null;
    highlightProtectedUntil = 0;
    pointer.hide();
    if (positionTimer !== null) clearTimeout(positionTimer);
    positionTimer = null;
    showJson({error: {message: error.message}});
    connectionState.textContent = 'Terminata';
    documentTitle.textContent = error.message;
    viewer.innerHTML = '';
    emptyState.classList.remove('hidden');
  } finally {
    setTimeout(poll, 200);
  }
}

async function loadDocument(state) {
  highlightPage = null;
  highlightProtectedUntil = 0;
  currentDocumentId = state.document_id;
  zoomScale = 1;
  zoomValue.textContent = '100%';
  viewer.innerHTML = '';
  documentTitle.textContent = state.document_name || 'In attesa del documento…';
  emptyState.classList.toggle('hidden', Boolean(state.document_id));
  pointer.apply(state);
  if (!state.document_id) return;
  const url = `${apiBase}/projection-access/document?token=${encodeURIComponent(publicToken)}&v=${state.revision}`;
  activePdf = await pdfjsLib.getDocument({
    url,
    cMapUrl: '/miodel/ui/vendor/pdfjs/cmaps/',
    cMapPacked: true,
    standardFontDataUrl: '/miodel/ui/vendor/pdfjs/standard_fonts/',
    wasmUrl: '/miodel/ui/vendor/pdfjs/wasm/',
  }).promise;
  await renderPdfPages();
  alignScroll(state.scroll_ratio);
  pointer.ensureVisible({ horizontal: operator });
}

async function renderPdfPages() {
  if (!activePdf) return;
  const verticalRatio = viewer.scrollTop / Math.max(1, viewer.scrollHeight - viewer.clientHeight);
  const horizontalRatio = viewer.scrollLeft / Math.max(1, viewer.scrollWidth - viewer.clientWidth);
  suppressScroll = true;
  await pdfViewer.load(activePdf, zoomScale);
  viewer.scrollTop = verticalRatio * Math.max(0, viewer.scrollHeight - viewer.clientHeight);
  viewer.scrollLeft = horizontalRatio * Math.max(0, viewer.scrollWidth - viewer.clientWidth);
  pointer.render();
  await pdfViewer.refresh();
  requestAnimationFrame(() => { suppressScroll = false; });
}

function alignScroll(ratio) {
  suppressScroll = true;
  const available = Math.max(0, viewer.scrollHeight - viewer.clientHeight);
  viewer.scrollTop = available * Number(ratio || 0);
  requestAnimationFrame(() => { suppressScroll = false; });
}

async function updatePosition() {
  const available = Math.max(1, viewer.scrollHeight - viewer.clientHeight);
  const response = await fetch(`${apiBase}/projection-session/position`, {
    method: 'PATCH',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({scroll_ratio: viewer.scrollTop / available, control_token: controlToken, document_id: currentDocumentId, ...pointer.snapshot()}),
  });
  const payload = await response.json();
  showJson(payload);
  if (!response.ok) {
    connectionState.textContent = 'Errore sincronizzazione';
    throw new Error(payload.error?.message || 'Sincronizzazione scroll fallita.');
  }
}

function showOperatorData(data) {
  const publicLink = document.querySelector('#publicLink');
  if (publicLink.dataset.loaded === '1') return;
  document.querySelector('#qrCode').src = data.qr_data_url;
  publicLink.href = data.public_url;
  publicLink.textContent = data.public_url;
  publicLink.dataset.loaded = '1';
}

function startPinch(event) {
  if (event.touches.length !== 2) return;
  pinchStartDistance = touchDistance(event.touches);
  pinchStartZoom = zoomScale;
  pinchAnchor = zoomAnchor((event.touches[0].clientX + event.touches[1].clientX) / 2, (event.touches[0].clientY + event.touches[1].clientY) / 2);
}

function movePinch(event) {
  if (event.touches.length !== 2 || !pinchStartDistance) return;
  event.preventDefault();
  const bounds = viewer.getBoundingClientRect();
  const anchor = pinchAnchor ? {
    ...pinchAnchor,
    screenX: (event.touches[0].clientX + event.touches[1].clientX) / 2 - bounds.left,
    screenY: (event.touches[0].clientY + event.touches[1].clientY) / 2 - bounds.top,
  } : null;
  applyZoom(Math.max(.75, Math.min(maximumZoom, pinchStartZoom * touchDistance(event.touches) / pinchStartDistance)), anchor);
}

function endPinch() {
  if (!pinchStartDistance) return;
  pinchStartDistance = 0;
  pinchAnchor = null;
  renderPdfPages().catch((error) => showJson({error: {message: error.message}}));
}

function zoomAnchor(clientX, clientY) {
  const bounds = viewer.getBoundingClientRect();
  const pages = [...viewer.querySelectorAll('.pdf-page')].map((page) => ({page, rect: page.getBoundingClientRect()}));
  const selected = pages.find(({rect}) => clientX >= rect.left && clientX <= rect.right && clientY >= rect.top && clientY <= rect.bottom)
    || pages.find(({rect}) => rect.bottom > bounds.top && rect.top < bounds.bottom);
  if (!selected) return null;
  return {
    page: selected.page,
    x: Math.max(0, Math.min(1, (clientX - selected.rect.left) / selected.rect.width)),
    y: Math.max(0, Math.min(1, (clientY - selected.rect.top) / selected.rect.height)),
    screenX: clientX - bounds.left,
    screenY: clientY - bounds.top,
  };
}

function applyZoom(nextZoom, anchor = null) {
  const bounds = viewer.getBoundingClientRect();
  if (!anchor?.page.isConnected) anchor = zoomAnchor(bounds.left + bounds.width / 2, bounds.top + bounds.height / 2);
  suppressScroll = true;
  zoomScale = nextZoom;
  zoomValue.textContent = `${Math.round(zoomScale * 100)}%`;
  pdfViewer.setZoom(zoomScale);
  pointer.render();
  if (anchor) {
    viewer.scrollTop = anchor.page.offsetTop + anchor.page.offsetHeight * anchor.y - anchor.screenY;
    viewer.scrollLeft = anchor.page.offsetLeft + anchor.page.offsetWidth * anchor.x - anchor.screenX;
  }
  requestAnimationFrame(() => { suppressScroll = false; });
}

function touchDistance(touches) {
  return Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);
}

function showJson(payload) {
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
}

async function initialize() {
  await detectOperator();
  if (operator) {
    document.body.classList.add('operator');
    operatorPanel.classList.remove('hidden');
    connectionState.textContent = 'Regia';
  }
  poll();
}

async function detectOperator() {
  if (operator && controlToken) return;
  const authToken = sessionStorage.getItem('token') || localStorage.getItem('token');
  if (!authToken) return;
  try {
    const response = await fetch(`${apiBase}/projection-session`, {
      headers: {Authorization: `Bearer ${authToken}`},
      cache: 'no-store',
    });
    if (!response.ok) return;
    const payload = await response.json();
    const operatorUrl = payload.data?.operator_url;
    if (!operatorUrl) return;
    const url = new URL(operatorUrl);
    if (url.searchParams.get('token') !== publicToken) return;
    controlToken = url.searchParams.get('control') || '';
    operator = controlToken !== '';
  } catch {
    operator = false;
  }
}

initialize();
