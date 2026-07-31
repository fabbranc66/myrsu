const apiBase = '../api/v1';
const token = sessionStorage.getItem('token') || localStorage.getItem('token');
const practiceId = Number(new URLSearchParams(window.location.search).get('id'));
const form = document.querySelector('#practiceEditForm');
const noteForm = document.querySelector('#practiceNoteForm');
const timeline = document.querySelector('#practiceTimeline');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const pdfCanvasViewer = document.querySelector('#pdfCanvasViewer');
const pdfToolbar = document.querySelector('#pdfToolbar');
const pdfZoomValue = document.querySelector('#pdfZoomValue');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const ccnlViewModal = document.querySelector('#ccnlViewModal');
const ccnlLinkedView = document.querySelector('#ccnlLinkedView');
const closeCcnlViewModal = document.querySelector('#closeCcnlViewModal');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
let assignees = [];
let documentPreviewUrl = null;
let pdfjsLib = null;
let activePdfUrl = null;
let activePdf = null;
let pdfScale = 1;
let pinchStartDistance = 0;
let pinchStartScale = 1;

if (!token || !practiceId) window.location.href = 'practices.html';

async function api(path, options = {}) {
  const headers = { ...(options.headers || {}), Authorization: `Bearer ${token}` };
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Operazione fallita');
  return payload.data;
}

async function load() {
  const [data, operatorRows] = await Promise.all([api(`/practices/${practiceId}`), api('/practices/assignees')]);
  assignees = operatorRows;
  renderPractice(data.practice);
  renderTimeline(data.timeline);
  if (window.location.hash === '#edit') {
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    form.summary.focus();
  }
}

function renderPractice(practice) {
  form.title.value = practice.title;
  form.summary.value = practice.summary || '';
  form.type.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.types, practice.type);
  form.status.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.statuses, practice.status);
  form.priority.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.priorities, practice.priority);
  form.source_type.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.sources, practice.source_type);
  form.visibility.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.visibilities, practice.visibility);
  form.assigned_user_id.innerHTML = '<option value="">-</option>' + assignees.map((user) => `<option value="${user.id}"${Number(user.id) === Number(practice.assigned_user_id) ? ' selected' : ''}>${escapeHtml(user.name)}</option>`).join('');
  form.due_date.value = practice.due_date || '';
}

function renderTimeline(items) {
  timeline.innerHTML = items.length ? items.map(timelineItem).join('') : '<p class="muted">Nessun elemento collegato.</p>';
}

function timelineItem(item) {
  const action = itemAction(item);
  const titleClass = item.type === 'document' ? ' timeline-document-title' : '';
  return `<details class="timeline-item timeline-toggle-item">
    <summary><span class="timeline-type">${typeLabel(item.type)}</span><time>${formatDateTime(item.event_at)}</time></summary>
    <strong class="timeline-title${titleClass}">${escapeHtml(item.title)}</strong><p class="timeline-summary">${escapeHtml(item.summary)}</p>${item.status ? `<small>${escapeHtml(item.status)}</small>` : ''}${action ? `<div>${action}</div>` : ''}
  </details>`;
}

function formatDateTime(value) {
  const date = new Date(String(value || '').replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return escapeHtml(value);
  const part = (number) => String(number).padStart(2, '0');
  return `${part(date.getDate())}-${part(date.getMonth() + 1)}-${String(date.getFullYear()).slice(-2)} ${part(date.getHours())}:${part(date.getMinutes())}`;
}

timeline.addEventListener('click', async (event) => {
  await handleTimelineAction(event);
});

timeline.addEventListener('touchend', async (event) => {
  const target = event.target.closest('.icon-action');
  if (!target) return;
  event.preventDefault();
  await handleTimelineAction(event);
});

async function handleTimelineAction(event) {
  const button = event.target.closest('[data-document-id]');
  if (button) {
    await showDocument(button.dataset.documentId);
    return;
  }

  const unlinkButton = event.target.closest('[data-unlink-document]');
  if (unlinkButton) return unlinkDocument(unlinkButton.dataset.unlinkDocument);

  const unlinkEmailButton = event.target.closest('[data-unlink-email]');
  if (unlinkEmailButton) return unlinkEmail(unlinkEmailButton.dataset.unlinkEmail);

  const ccnlButton = event.target.closest('[data-ccnl-id]');
  if (ccnlButton) return showCcnlLink(ccnlButton.dataset.ccnlId);

  const unlinkCcnlButton = event.target.closest('[data-unlink-ccnl]');
  if (unlinkCcnlButton) return unlinkCcnlLink(unlinkCcnlButton.dataset.unlinkCcnl);
}

async function showDocument(id) {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  const previewUrl = `${apiBase}/documents/${id}/preview?token=${encodeURIComponent(token || '')}`;
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

closeDocumentModal.addEventListener('click', () => {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  activePdfUrl = null;
  activePdf = null;
  pdfCanvasViewer.innerHTML = '';
  pdfCanvasViewer.classList.add('hidden');
  pdfToolbar.classList.add('hidden');
  documentPreview.src = '';
  documentModal.close();
});

async function renderPdfCanvas() {
  if (!activePdfUrl) return;
  pdfCanvasViewer.innerHTML = '<p class="muted">Caricamento PDF...</p>';
  try {
    pdfjsLib = pdfjsLib || await import('./vendor/pdfjs/pdf.mjs');
    pdfjsLib.GlobalWorkerOptions.workerSrc = './vendor/pdfjs/pdf.worker.mjs';
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
      await addPdfLinks(page, viewport, pageWrap);
      pdfCanvasViewer.appendChild(pageWrap);
    }
  } catch {
    pdfCanvasViewer.classList.add('hidden');
    pdfToolbar.classList.add('hidden');
    documentPreview.src = activePdfUrl;
    documentPreview.classList.remove('hidden');
  }
}

async function addPdfLinks(page, viewport, pageWrap) {
  const annotations = await page.getAnnotations({ intent: 'display' });
  annotations.filter((item) => (item.url || item.unsafeUrl) && item.rect).forEach((item) => {
    const [x1, y1, x2, y2] = viewport.convertToViewportRectangle(item.rect);
    const link = document.createElement('a');
    link.href = item.url || item.unsafeUrl;
    link.target = '_blank';
    link.rel = 'noopener';
    link.className = 'pdf-page-link';
    link.style.left = `${Math.min(x1, x2)}px`;
    link.style.top = `${Math.min(y1, y2)}px`;
    link.style.width = `${Math.abs(x2 - x1)}px`;
    link.style.height = `${Math.abs(y2 - y1)}px`;
    pageWrap.appendChild(link);
  });
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

closeCcnlViewModal.addEventListener('click', () => ccnlViewModal.close());

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(form).entries());
  await api(`/practices/${practiceId}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
  message.textContent = 'Pratica aggiornata';
  await load();
});

noteForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(noteForm).entries());
  await api(`/practices/${practiceId}/notes`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
  noteForm.reset();
  message.textContent = 'Nota inserita';
  await load();
});

function meta(name, value) { return `<div><span class="muted">${name}</span><strong>${escapeHtml(value)}</strong></div>`; }
function label(group, value) { return MyRsuPracticeOptions.label(MyRsuPracticeOptions[group], value); }
function typeLabel(type) { return { document: 'documento', report: 'segnalazione', comment: 'commento', protocol: 'protocollo', attachment: 'allegato', meeting: 'incontro', call: 'telefonata', email: 'e-mail', note: 'nota operativa', ccnl: 'riferimento normativo' }[type] || type; }
function itemAction(item) {
  if (item.document_id) {
    return `<span class="actions-cell">
      <button type="button" class="icon-action" data-document-id="${item.document_id}" title="Apri">${MyRsuIcons.get('eye')}</button>
      <a class="icon-action" href="document-edit.html?id=${item.document_id}" title="Modifica">${MyRsuIcons.get('edit')}</a>
      <button type="button" class="icon-action danger" data-unlink-document="${item.document_id}" title="Scollega">${MyRsuIcons.get('link')}</button>
    </span>`;
  }
  if (item.type === 'email') {
    return `<span class="actions-cell">
      <a class="icon-action" href="emails.html?id=${item.id}" title="Visualizza">${MyRsuIcons.get('eye')}</a>
      <a class="icon-action" href="emails.html?id=${item.id}&edit=1" title="Modifica">${MyRsuIcons.get('edit')}</a>
      <button type="button" class="icon-action danger" data-unlink-email="${item.id}" title="Scollega">${MyRsuIcons.get('link')}</button>
    </span>`;
  }
  if (item.type === 'ccnl') {
    return `<span class="actions-cell">
      <button type="button" class="icon-action" data-ccnl-id="${item.id}" title="Visualizza">${MyRsuIcons.get('eye')}</button>
      <button type="button" class="icon-action danger" data-unlink-ccnl="${item.id}" title="Elimina">${MyRsuIcons.get('trash')}</button>
    </span>`;
  }
  const link = itemLink(item);
  return link ? `<a href="${link}">Apri</a>` : '';
}
function itemLink(item) { if (item.type === 'protocol') return `protocol-view.html?id=${item.id}`; if (item.type === 'meeting') return 'union-meetings.html'; if (item.type === 'call') return 'calls.html'; if (item.type === 'email') return `emails.html?id=${item.id}`; if (item.type === 'report') return 'reports-moderation.html'; if (item.type === 'comment') return 'comments-moderation.html'; return ''; }
function escapeHtml(value) { return String(value || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }

async function unlinkDocument(documentId) {
  if (!confirm('Scollegare documento dalla pratica?')) return;
  await api('/practice-links', {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      practice_id: practiceId,
      entity_type: 'document',
      entity_id: Number(documentId),
    }),
  });
  message.textContent = 'Documento scollegato dalla pratica';
  await load();
}

async function unlinkEmail(emailId) {
  if (!confirm('Scollegare e-mail dalla pratica?')) return;
  await api(`/emails/${emailId}/link-practice`, { method: 'DELETE' });
  message.textContent = 'E-mail scollegata dalla pratica';
  await load();
}

async function showCcnlLink(linkId) {
  const data = await api(`/practices/${practiceId}/ccnl-links/${linkId}`);
  ccnlLinkedView.innerHTML = window.MyRsuCcnlRender
    ? window.MyRsuCcnlRender(data.link.excerpt)
    : `<pre>${escapeHtml(data.link.excerpt)}</pre>`;
  ccnlViewModal.showModal();
}

async function unlinkCcnlLink(linkId) {
  if (!confirm('Eliminare riferimento normativo dalla pratica?')) return;
  await api(`/practices/${practiceId}/ccnl-links/${linkId}`, { method: 'DELETE' });
  message.textContent = 'Riferimento normativo eliminato';
  await load();
}

load().catch((error) => { message.textContent = error.message; });
