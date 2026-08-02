import * as pdfjsLib from './vendor/pdfjs/pdf.mjs';
import { token } from './room-api.js';

pdfjsLib.GlobalWorkerOptions.workerSrc = './vendor/pdfjs/pdf.worker.mjs';

export async function openRoomPdf(url, title) {
  const modal = document.querySelector('#documentModal');
  const frame = document.querySelector('#documentFrame');
  const viewer = document.querySelector('#documentPdf');
  document.querySelector('#documentModalTitle').textContent = title;
  if (!modal.open) modal.showModal();
  if (!isMobile()) {
    viewer.classList.add('hidden');
    frame.classList.remove('hidden');
    frame.src = `${url}?token=${encodeURIComponent(token)}`;
    return;
  }
  frame.src = '';
  frame.classList.add('hidden');
  viewer.classList.remove('hidden');
  viewer.innerHTML = '<p>Caricamento documento...</p>';
  const response = await fetch(url, { headers: { Authorization: `Bearer ${token}` } });
  if (!response.ok) throw new Error('Documento non disponibile.');
  const pdf = await pdfjsLib.getDocument({
    data: await response.arrayBuffer(), cMapUrl: './vendor/pdfjs/cmaps/', cMapPacked: true,
    standardFontDataUrl: './vendor/pdfjs/standard_fonts/', wasmUrl: './vendor/pdfjs/wasm/',
  }).promise;
  viewer.innerHTML = '';
  for (let number = 1; number <= pdf.numPages; number += 1) {
    const page = await pdf.getPage(number);
    const initial = page.getViewport({ scale: 1 });
    const viewport = page.getViewport({ scale: Math.max(0.5, (viewer.clientWidth - 16) / initial.width) });
    const canvas = document.createElement('canvas');
    const pageWrap = document.createElement('div');
    canvas.width = Math.ceil(viewport.width);
    canvas.height = Math.ceil(viewport.height);
    canvas.className = 'room-pdf-page';
    pageWrap.className = 'room-pdf-page-wrap';
    pageWrap.style.width = `${canvas.width}px`;
    pageWrap.style.height = `${canvas.height}px`;
    pageWrap.appendChild(canvas);
    viewer.appendChild(pageWrap);
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    const annotations = await page.getAnnotations({ intent: 'display' });
    annotations.filter((item) => (item.url || item.unsafeUrl) && item.rect).forEach((item) => {
      addPdfLink(pageWrap, viewport, item.rect, item.url || item.unsafeUrl);
    });
  }
}

function addPdfLink(pageWrap, viewport, rect, url) {
  const [x1, y1, x2, y2] = viewport.convertToViewportRectangle(rect);
  const link = document.createElement('button');
  link.type = 'button';
  link.className = 'room-pdf-link';
  link.dataset.verifyUrl = url;
  link.style.left = `${Math.min(x1, x2)}px`;
  link.style.top = `${Math.min(y1, y2)}px`;
  link.style.width = `${Math.abs(x2 - x1)}px`;
  link.style.height = `${Math.abs(y2 - y1)}px`;
  pageWrap.appendChild(link);
}

export function closeRoomPdf() {
  const modal = document.querySelector('#documentModal');
  document.querySelector('#documentFrame').src = '';
  document.querySelector('#documentPdf').innerHTML = '';
  modal.close();
}

function isMobile() {
  return window.matchMedia('(max-width:900px),(pointer:coarse)').matches;
}

document.addEventListener('click', (event) => {
  const link = event.target.closest('.room-pdf-link');
  if (!link) return;
  document.querySelector('#verifyFrame').src = link.dataset.verifyUrl;
  document.querySelector('#verifyModal').showModal();
});
