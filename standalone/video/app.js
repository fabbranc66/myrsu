import * as pdfjsLib from '/myrsu/ui/vendor/pdfjs/pdf.mjs';

pdfjsLib.GlobalWorkerOptions.workerSrc = '/myrsu/ui/vendor/pdfjs/pdf.worker.mjs';

const apiBase = `${location.origin}/myrsu/api/v1`;
const params = new URLSearchParams(location.search);
const publicToken = params.get('token') || '';
const operator = params.get('operator') === '1';
const controlToken = params.get('control') || '';
const viewer = document.querySelector('#pdfViewer');
const emptyState = document.querySelector('#emptyState');
const meetingTitle = document.querySelector('#meetingTitle');
const documentTitle = document.querySelector('#documentTitle');
const connectionState = document.querySelector('#connectionState');
const operatorPanel = document.querySelector('#operatorPanel');
const jsonOutput = document.querySelector('#jsonOutput');
let currentDocumentId = null;
let lastRevision = 0;
let positionTimer = null;
let suppressScroll = false;
let zoomScale = 1;
let pinchStartDistance = 0;
let pinchStartZoom = 1;
let activePdf = null;

if (operator) {
  document.body.classList.add('operator');
  operatorPanel.classList.remove('hidden');
}

document.querySelector('#copyLink').addEventListener('click', async () => {
  await navigator.clipboard.writeText(document.querySelector('#publicLink').href);
});

viewer.addEventListener('scroll', () => {
  if (!operator || suppressScroll || !controlToken) return;
  clearTimeout(positionTimer);
  positionTimer = setTimeout(updatePosition, 160);
});

viewer.addEventListener('touchstart', startPinch, {passive: true});
viewer.addEventListener('touchmove', movePinch, {passive: false});
viewer.addEventListener('touchend', endPinch, {passive: true});

async function poll() {
  try {
    const response = await fetch(`${apiBase}/projection-access?token=${encodeURIComponent(publicToken)}&control=${encodeURIComponent(controlToken)}`, {cache: 'no-store'});
    const payload = await response.json();
    showJson(payload);
    if (!response.ok) throw new Error(payload.error?.message || 'Proiezione non disponibile.');
    const state = payload.data;
    connectionState.textContent = 'Attiva';
    meetingTitle.textContent = state.meeting_title;
    if (operator && state.qr_data_url) showOperatorData(state);
    if (state.document_id !== currentDocumentId) await loadDocument(state);
    if (!operator && state.revision !== lastRevision && state.document_id) alignScroll(state.scroll_ratio);
    lastRevision = state.revision;
  } catch (error) {
    showJson({error: {message: error.message}});
    connectionState.textContent = 'Terminata';
    documentTitle.textContent = error.message;
    viewer.innerHTML = '';
    emptyState.classList.remove('hidden');
    return;
  }
  setTimeout(poll, 650);
}

async function loadDocument(state) {
  currentDocumentId = state.document_id;
  zoomScale = 1;
  viewer.innerHTML = '';
  documentTitle.textContent = state.document_name || 'In attesa del documento…';
  emptyState.classList.toggle('hidden', Boolean(state.document_id));
  if (!state.document_id) return;
  const url = `${apiBase}/projection-access/document?token=${encodeURIComponent(publicToken)}&v=${state.revision}`;
  activePdf = await pdfjsLib.getDocument({
    url,
    cMapUrl: '/myrsu/ui/vendor/pdfjs/cmaps/',
    cMapPacked: true,
    standardFontDataUrl: '/myrsu/ui/vendor/pdfjs/standard_fonts/',
    wasmUrl: '/myrsu/ui/vendor/pdfjs/wasm/',
  }).promise;
  await renderPdfPages();
  alignScroll(state.scroll_ratio);
}

async function renderPdfPages() {
  if (!activePdf) return;
  const verticalRatio = viewer.scrollTop / Math.max(1, viewer.scrollHeight - viewer.clientHeight);
  const horizontalRatio = viewer.scrollLeft / Math.max(1, viewer.scrollWidth - viewer.clientWidth);
  const fragment = document.createDocumentFragment();
  suppressScroll = true;
  for (let pageNumber = 1; pageNumber <= activePdf.numPages; pageNumber++) {
    const page = await activePdf.getPage(pageNumber);
    const baseViewport = page.getViewport({scale: 1});
    const fitScale = Math.min(2, Math.max(.55, (viewer.clientWidth - 16) / baseViewport.width));
    const scale = fitScale * zoomScale;
    const viewport = page.getViewport({scale});
    const outputScale = Math.min(3, Math.max(1, window.devicePixelRatio || 1));
    const canvas = document.createElement('canvas');
    canvas.className = 'pdf-page';
    canvas.width = Math.floor(viewport.width * outputScale);
    canvas.height = Math.floor(viewport.height * outputScale);
    canvas.dataset.baseWidth = String(viewport.width / zoomScale);
    canvas.dataset.baseHeight = String(viewport.height / zoomScale);
    canvas.style.width = `${viewport.width}px`;
    canvas.style.height = `${viewport.height}px`;
    fragment.appendChild(canvas);
    await page.render({
      canvasContext: canvas.getContext('2d'),
      viewport,
      transform: outputScale === 1 ? null : [outputScale, 0, 0, outputScale, 0, 0],
    }).promise;
  }
  viewer.replaceChildren(fragment);
  viewer.scrollTop = verticalRatio * Math.max(0, viewer.scrollHeight - viewer.clientHeight);
  viewer.scrollLeft = horizontalRatio * Math.max(0, viewer.scrollWidth - viewer.clientWidth);
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
  await fetch(`${apiBase}/projection-session/position`, {
    method: 'PATCH',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({scroll_ratio: viewer.scrollTop / available, control_token: controlToken}),
  });
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
}

function movePinch(event) {
  if (event.touches.length !== 2 || !pinchStartDistance) return;
  event.preventDefault();
  applyZoom(Math.max(.75, Math.min(3, pinchStartZoom * touchDistance(event.touches) / pinchStartDistance)));
}

function endPinch() {
  if (!pinchStartDistance) return;
  pinchStartDistance = 0;
  renderPdfPages().catch((error) => showJson({error: {message: error.message}}));
}

function applyZoom(nextZoom) {
  const verticalRatio = viewer.scrollTop / Math.max(1, viewer.scrollHeight - viewer.clientHeight);
  const horizontalRatio = viewer.scrollLeft / Math.max(1, viewer.scrollWidth - viewer.clientWidth);
  zoomScale = nextZoom;
  viewer.querySelectorAll('.pdf-page').forEach((canvas) => {
    canvas.style.width = `${Number(canvas.dataset.baseWidth) * zoomScale}px`;
    canvas.style.height = `${Number(canvas.dataset.baseHeight) * zoomScale}px`;
  });
  viewer.scrollTop = verticalRatio * Math.max(0, viewer.scrollHeight - viewer.clientHeight);
  viewer.scrollLeft = horizontalRatio * Math.max(0, viewer.scrollWidth - viewer.clientWidth);
}

function touchDistance(touches) {
  return Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);
}

function showJson(payload) {
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
}

poll();
