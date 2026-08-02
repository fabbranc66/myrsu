import * as pdfjsLib from './vendor/pdfjs/pdf.mjs';

pdfjsLib.GlobalWorkerOptions.workerSrc = './vendor/pdfjs/pdf.worker.mjs';

const apiBase = '../api/v1';

const uploadForm = document.querySelector('#uploadForm');
const documentsTable = document.querySelector('#documentsTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const documentMobilePreview = document.querySelector('#documentMobilePreview');
const pdfCanvasViewer = document.querySelector('#pdfCanvasViewer');
const pdfToolbar = document.querySelector('#pdfToolbar');
const pdfZoomValue = document.querySelector('#pdfZoomValue');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const verifyFrameModal = document.querySelector('#verifyFrameModal');
const verifyFrame = document.querySelector('#verifyFrame');
const closeVerifyFrameModal = document.querySelector('#closeVerifyFrameModal');
const uploadProgress = document.querySelector('#uploadProgress');
const uploadProgressFill = document.querySelector('#uploadProgressFill');
const uploadProgressText = document.querySelector('#uploadProgressText');
const practiceLinkModal = document.querySelector('#practiceLinkModal');
const practiceLinkForm = document.querySelector('#practiceLinkForm');
const closePracticeLinkModal = document.querySelector('#closePracticeLinkModal');
const practiceUnlinkModal = document.querySelector('#practiceUnlinkModal');
const practiceUnlinkForm = document.querySelector('#practiceUnlinkForm');
const closePracticeUnlinkModal = document.querySelector('#closePracticeUnlinkModal');
let practices = [];
let documents = [];
let documentPreviewUrl = null;
let activePdfUrl = null;
let activeVerifyUrl = null;
let activePdf = null;
let pdfScale = 1;
let pinchStartDistance = 0;
let pinchStartScale = 1;

function authToken() {
  return sessionStorage.getItem('token') || localStorage.getItem('token');
}

async function api(path, options = {}) {
  const headers = options.headers || {};
  const token = authToken();
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);

  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

function setUploadProgress(value) {
  if (!uploadProgress || !uploadProgressFill || !uploadProgressText) return;
  uploadProgressFill.style.width = `${value}%`;
  uploadProgressText.textContent = `${value}%`;
}

function resetUploadProgress() {
  if (!uploadProgress || !uploadProgressFill || !uploadProgressText) return;
  uploadProgress.classList.add('hidden');
  uploadProgressFill.style.width = '0%';
  uploadProgressText.textContent = '0%';
}

function uploadDocument(formData, startProgress = 0) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', `${apiBase}/documents`);
    const token = authToken();
    if (token) {
      xhr.setRequestHeader('Authorization', `Bearer ${token}`);
    }

    xhr.upload.addEventListener('progress', (event) => {
      if (!event.lengthComputable) return;
      const uploaded = Math.round((event.loaded / event.total) * (95 - startProgress));
      const value = Math.max(1, Math.min(95, startProgress + uploaded));
      setUploadProgress(value);
    });

    xhr.addEventListener('load', () => {
      const payload = xhr.responseText ? JSON.parse(xhr.responseText) : {};
      jsonOutput.textContent = JSON.stringify(payload, null, 2);
      if (xhr.status < 200 || xhr.status >= 300) {
        reject(new Error(payload.error?.detail || payload.error?.message || 'Upload fallito'));
        return;
      }
      resolve(payload.data);
    });

    xhr.addEventListener('error', () => reject(new Error('Upload fallito')));
    xhr.send(formData);
  });
}

async function loadDocuments() {
  const [documentRows, practiceRows] = await Promise.all([api('/documents'), loadPractices()]);
  documents = documentRows;
  practices = practiceRows;
  uploadForm.classList.remove('hidden');
  documentsTable.innerHTML = documentRows.filter((document) => document.category !== 'comunicati').map(row).join('');
}

async function loadPractices() {
  try {
    return await api('/practices');
  } catch {
    return [];
  }
}

function row(document) {
  if (isDraftComunicato(document)) {
    return `
      <tr>
        <td data-label="Nome"><span class="table-value-title"><span class="doc-type-tag draft">DRAFT</span><span class="truncate-title" title="${escapeHtml(document.original_name)}">${escapeHtml(document.original_name)}</span></span></td>
        <td data-label="Categoria">${document.category || '-'}</td>
        <td data-label="Visibilita">${translateVisibility(document.visibility)}</td>
        <td data-label="Stato"><span class="doc-origin-tag draft">Bozza - documento non generato</span></td>
        <td data-label="Dimensione">-</td>
        <td data-label="Azioni" class="actions-cell">
          <a class="icon-action" href="comunicati-editor.html?id=${document.id}" title="Modifica bozza">${MyRsuIcons.get('edit')}</a>
          <button class="draft-generate-button" data-generate="${document.id}" title="Genera documento ufficiale">${MyRsuIcons.get('document')} Genera ufficiale</button>
          <button class="icon-action danger" data-delete="${document.id}" title="Elimina">${MyRsuIcons.get('trash')}</button>
        </td>
      </tr>`;
  }

  return `
    <tr>
      <td data-label="Nome"><span class="table-value-title"><span class="doc-type-tag">${documentsSystemType(document)}</span><span class="truncate-title" title="${escapeHtml(document.original_name)}">${escapeHtml(document.original_name)}</span></span></td>
      <td data-label="Categoria">${document.category || '-'}</td>
      <td data-label="Visibilita">${translateVisibility(document.visibility)}</td>
      <td data-label="Stato">${translateStatus(document.conversion_status)}</td>
      <td data-label="Dimensione">${document.pdf_size_bytes || document.size_bytes}</td>
      <td data-label="Azioni" class="actions-cell">
        <button class="icon-action" data-view="${document.id}" title="Anteprima">${MyRsuIcons.get('eye')}</button>
        <button class="icon-action document-comments-icon ${Number(document.approved_comments_count || 0) > 0 ? 'has-comments' : 'no-comments'}" data-comments="${document.id}" title="${Number(document.approved_comments_count || 0)} commenti approvati" ${Number(document.approved_comments_count || 0) === 0 ? 'disabled' : ''}>${MyRsuIcons.get('note')}</button>
        <a class="icon-action" href="document-edit.html?id=${document.id}" title="${document.category === 'comunicati' ? 'Modifica comunicato e rigenera PDF' : 'Modifica'}">${MyRsuIcons.get('edit')}</a>
        <button class="icon-action" data-download="${document.id}" title="Scarica">${MyRsuIcons.get('download')}</button>
        <button class="icon-action" data-projection="${document.id}" title="Proietta sul video esterno">${MyRsuIcons.get('projection')}</button>
        <button class="icon-action" data-practice-link="${document.id}" title="Collega a pratica">${MyRsuIcons.get('link')}</button>
        <button class="icon-action danger" data-practice-unlink="${document.id}" title="Scollega da pratica">${MyRsuIcons.get('link')}</button>
        <button class="icon-action" data-protocol-in="${document.id}" title="Protocolla in entrata">${MyRsuIcons.get('protocolIn')}</button>
        <button class="icon-action danger" data-delete="${document.id}" title="Elimina">${MyRsuIcons.get('trash')}</button>
      </td>
    </tr>
  `;
}

function isDraftComunicato(document) {
  return document.category === 'comunicati' && document.conversion_status === 'pending';
}

function documentsSystemType(document) {
  const mime = String(document.mime_type || '');
  if (mime === 'application/pdf') return 'PDF';
  return mime.startsWith('image/') ? 'IMG' : 'FILE';
}

function originalType(document) {
  const name = String(document.original_name || '');
  const extension = name.includes('.') ? name.split('.').pop().toUpperCase() : '';
  if (extension) return extension;
  const mime = String(document.original_mime_type || document.mime_type || '');
  if (mime === 'application/pdf') return 'PDF';
  if (mime.startsWith('image/')) return 'IMG';
  if (mime.startsWith('text/')) return 'TXT';
  return 'DOC';
}


function translateVisibility(value) {
  const map = { public: 'pubblico', members: 'membri', rsu: 'rsu' };
  return map[value] || value || '-';
}

function translateStatus(value) {
  const map = { ready: 'pronto', pending: 'in attesa', failed: 'errore' };
  return map[value] || value || '-';
}

uploadForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  if (uploadProgress) {
    uploadProgress.classList.remove('hidden');
  }
  setUploadProgress(0);
  try {
    const formData = new FormData(uploadForm);
    const file = formData.get('file');
    let startProgress = 0;
    if (file instanceof File && file.type === 'application/pdf') {
      message.textContent = 'Preparazione pagine PDF...';
      const renderedPdf = await MyRsuPdfRasterizer.rasterizePdf(file, setUploadProgress);
      formData.append('rendered_pdf', renderedPdf, 'rendered-pages.pdf');
      startProgress = 30;
      message.textContent = 'Caricamento documento...';
    }
    await uploadDocument(formData, startProgress);
    setUploadProgress(100);
    await new Promise((resolve) => window.setTimeout(resolve, 250));
    message.textContent = 'Documento caricato';
    uploadForm.reset();
    await loadDocuments();
  } catch (error) {
    message.textContent = error.message;
  } finally {
    window.setTimeout(resetUploadProgress, 500);
  }
});

documentsTable.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;

  if (button.dataset.comments) return;

  if (button.dataset.download) {
    await downloadDocument(button.dataset.download);
    return;
  }

  if (button.dataset.projection) {
    try {
      const session = await api(`/documents/${button.dataset.projection}/projection`, { method: 'POST' });
      message.textContent = `Documento proiettato: ${session.document_name}`;
      window.alert(`Documento inviato alla proiezione: ${session.document_name}`);
    } catch (error) {
      message.textContent = error.message;
      window.alert(error.message);
    }
    return;
  }

  if (button.dataset.generate) {
    const result = await api(`/comunicati/${button.dataset.generate}/generate`, { method: 'POST' });
    message.textContent = `Generato ${result.protocol.protocol_number}`;
    await loadDocuments();
    return;
  }

  if (button.dataset.protocolIn) {
    await protocolIn(button.dataset.protocolIn);
    await loadDocuments();
    return;
  }

  if (button.dataset.view) {
    try {
      await showDocument(button.dataset.view);
    } catch (error) {
      message.textContent = error.message;
    }
    return;
  }

  if (button.dataset.practiceLink) {
    openPracticeLink(button.dataset.practiceLink);
    return;
  }

  if (button.dataset.practiceUnlink) {
    openPracticeUnlink(button.dataset.practiceUnlink);
    return;
  }

  if (!confirm('Eliminare documento?')) return;
  await api(`/documents/${button.dataset.delete}`, { method: 'DELETE' });
  message.textContent = 'Documento eliminato';
  await loadDocuments();
});

async function showDocument(id) {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  const documentRow = documents.find((item) => Number(item.id) === Number(id));
  activeVerifyUrl = documentRow?.signature
    ? `document-verify.html?id=${encodeURIComponent(id)}&sig=${encodeURIComponent(documentRow.signature)}`
    : null;
  localStorage.setItem('myrsu_verify_return_url', window.location.href);
  const mobile = isMobileViewport();
  documentPreview.src = '';
  documentPreview.classList.toggle('hidden', mobile);
  pdfCanvasViewer.classList.toggle('hidden', !mobile);
  pdfToolbar.classList.toggle('hidden', !mobile);
  if (mobile) pdfCanvasViewer.innerHTML = '<p class="muted">Caricamento PDF...</p>';
  if (!documentModal.open) documentModal.showModal();

  let previewUrl;
  try {
    previewUrl = await fetchDocumentPreview(id);
  } catch (error) {
    pdfCanvasViewer.classList.remove('hidden');
    pdfCanvasViewer.textContent = error.message;
    throw error;
  }
  if (mobile) {
    documentMobilePreview.src = '';
    documentMobilePreview.classList.add('hidden');
    activePdfUrl = previewUrl;
    activePdf = null;
    pdfScale = 1;
    try {
      await renderPdfCanvas();
    } catch (error) {
      pdfCanvasViewer.textContent = error.message;
      throw error;
    }
  } else {
    pdfCanvasViewer.classList.add('hidden');
    pdfToolbar.classList.add('hidden');
    documentMobilePreview.src = '';
    documentMobilePreview.classList.add('hidden');
    documentPreview.src = previewUrl;
    documentPreview.classList.remove('hidden');
  }
}

async function fetchDocumentPreview(id) {
  const response = await fetch(`${apiBase}/documents/${id}/preview`, {
    headers: { Authorization: `Bearer ${authToken() || ''}` },
  });
  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    throw new Error(payload.error?.message || 'Anteprima documento non disponibile.');
  }
  documentPreviewUrl = URL.createObjectURL(await response.blob());
  return documentPreviewUrl;
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
  if (pageNumber === 1 && activeVerifyUrl) {
    addPdfLink(pageWrap, viewport, [502, 728, 554, 780], activeVerifyUrl);
  }
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

function pinchDistance(touches) {
  const dx = touches[0].clientX - touches[1].clientX;
  const dy = touches[0].clientY - touches[1].clientY;
  return Math.hypot(dx, dy);
}

function isMobileViewport() {
  return window.matchMedia('(max-width: 900px), (pointer: coarse)').matches;
}

window.addEventListener('message', (event) => {
  if (event.origin !== window.location.origin || typeof event.data !== 'object') return;
  if (event.data?.type === 'myrsu:close-verify-modal') {
    verifyFrame.src = '';
    if (verifyFrameModal.open) verifyFrameModal.close();
    return;
  }
  if (event.data?.type !== 'myrsu:verify-modal') return;
  if (verifyFrame.src === event.data.url) return;
  verifyFrame.src = event.data.url;
  verifyFrameModal.showModal();
});

async function protocolIn(id) {
  const document = await api(`/documents/${id}`);
  await api('/protocol', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      direction: 'IN',
      type_code: 'DOC',
      subject: document.original_name,
      document_id: Number(id),
    }),
  });
  message.textContent = 'Protocollo in entrata creato';
}

closeDocumentModal.addEventListener('click', () => {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  documentPreview.src = '';
  documentMobilePreview.src = '';
  pdfCanvasViewer.innerHTML = '';
  pdfCanvasViewer.classList.add('hidden');
  pdfToolbar.classList.add('hidden');
  activePdfUrl = null;
  activeVerifyUrl = null;
  activePdf = null;
  documentModal.close();
});

pdfCanvasViewer.addEventListener('click', (event) => {
  const link = event.target.closest('.pdf-page-link');
  if (!link) return;
  openPdfLink(event, link);
});

pdfCanvasViewer.addEventListener('pointerup', (event) => {
  if (!isMobileViewport()) return;
  const link = event.target.closest('.pdf-page-link');
  if (link) {
    openPdfLink(event, link);
    return;
  }
  if (isQrTap(event)) {
    openVerifyModal(event);
  }
});

function openPdfLink(event, link) {
  const url = link.dataset.pdfLink || link.href;
  if (!url.includes('document-verify.html')) return;
  event.preventDefault();
  event.stopPropagation();
  openVerifyModal(event, url);
}

function openVerifyModal(event, url = activeVerifyUrl) {
  if (!url) return;
  event.preventDefault();
  event.stopPropagation();
  verifyFrame.src = url;
  verifyFrameModal.showModal();
}

function isQrTap(event) {
  if (!activeVerifyUrl) return false;
  const firstPage = pdfCanvasViewer.querySelector('.pdf-page-wrap');
  if (!firstPage || !firstPage.contains(event.target)) return false;
  const rect = firstPage.getBoundingClientRect();
  const x = event.clientX - rect.left;
  const y = event.clientY - rect.top;
  return x > rect.width * 0.76 && y < rect.height * 0.18;
}

pdfCanvasViewer.addEventListener('touchstart', (event) => {
  if (!isMobileViewport() || event.touches.length !== 2) return;
  event.preventDefault();
  pinchStartDistance = pinchDistance(event.touches);
  pinchStartScale = pdfScale;
}, { passive: false });

pdfCanvasViewer.addEventListener('touchmove', (event) => {
  if (!isMobileViewport() || event.touches.length !== 2 || pinchStartDistance <= 0) return;
  event.preventDefault();
  const nextScale = pinchStartScale * (pinchDistance(event.touches) / pinchStartDistance);
  pdfScale = Math.max(0.6, Math.min(5, nextScale));
  pdfZoomValue.textContent = `${Math.round(pdfScale * 100)}%`;
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

function openPracticeLink(documentId) {
  const document = documents.find((item) => Number(item.id) === Number(documentId));
  const linkedIds = practiceIds(document);
  const availablePractices = practices.filter((practice) => !linkedIds.includes(Number(practice.id)));
  practiceLinkForm.document_id.value = documentId;
  practiceLinkForm.practice_id.innerHTML = availablePractices.length > 0
    ? availablePractices.map((practice) => `<option value="${practice.id}">${practice.title}</option>`).join('')
    : '<option value="">Nessuna pratica disponibile</option>';
  practiceLinkForm.querySelector('button').disabled = availablePractices.length === 0;
  practiceLinkModal.showModal();
}

closePracticeLinkModal.addEventListener('click', () => {
  practiceLinkModal.close();
});

function openPracticeUnlink(documentId) {
  const document = documents.find((item) => Number(item.id) === Number(documentId));
  const linkedIds = practiceIds(document);
  const availablePractices = practices.filter((practice) => linkedIds.includes(Number(practice.id)));
  practiceUnlinkForm.document_id.value = documentId;
  practiceUnlinkForm.practice_id.innerHTML = availablePractices.length > 0
    ? availablePractices.map((practice) => `<option value="${practice.id}">${practice.title}</option>`).join('')
    : '<option value="">Nessuna pratica disponibile</option>';
  practiceUnlinkForm.querySelector('button').disabled = availablePractices.length === 0;
  practiceUnlinkModal.showModal();
}

closePracticeUnlinkModal.addEventListener('click', () => {
  practiceUnlinkModal.close();
});

practiceLinkForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(practiceLinkForm).entries());
  await api('/practice-links', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      practice_id: Number(data.practice_id),
      entity_type: 'document',
      entity_id: Number(data.document_id),
    }),
  });
  message.textContent = 'Documento collegato alla pratica';
  practiceLinkModal.close();
  await loadDocuments();
});

practiceUnlinkForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(practiceUnlinkForm).entries());
  await unlinkPractice(Number(data.document_id), Number(data.practice_id));
  practiceUnlinkModal.close();
});

async function downloadDocument(id) {
  const token = authToken();
  const response = await fetch(`${apiBase}/documents/${id}/download`, {
    headers: { Authorization: `Bearer ${token}` },
  });

  if (!response.ok) throw new Error('Download fallito');

  const blob = await response.blob();
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `document-${id}`;
  link.click();
  URL.revokeObjectURL(url);
}

async function unlinkPractice(documentId, practiceId) {
  if (!confirm('Scollegare documento dalla pratica?')) return;
  await api('/practice-links', {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      practice_id: Number(practiceId),
      entity_type: 'document',
      entity_id: Number(documentId),
    }),
  });
  message.textContent = 'Documento scollegato dalla pratica';
  await loadDocuments();
}

function practiceIds(document) {
  return String(document?.practice_ids || '')
    .split(',')
    .map((id) => Number(id))
    .filter(Boolean);
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

if (!authToken()) {
  window.location.href = 'app/index.html';
} else {
  loadDocuments().catch((error) => {
    message.textContent = error.message;
  });
}
