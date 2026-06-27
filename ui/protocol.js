const apiBase = '../api/v1';
let token = sessionStorage.getItem('token');

const protocolTable = document.querySelector('#protocolTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const protocolCount = document.querySelector('#protocolCount');
const directionFilter = document.querySelector('#directionFilter');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const reportModal = document.querySelector('#reportModal');
const reportModalTitle = document.querySelector('#reportModalTitle');
const reportModalMeta = document.querySelector('#reportModalMeta');
const reportModalMessage = document.querySelector('#reportModalMessage');
const reportModalDocument = document.querySelector('#reportModalDocument');
const reportModalAttachments = document.querySelector('#reportModalAttachments');
const closeReportModal = document.querySelector('#closeReportModal');
const attachmentModal = document.querySelector('#attachmentModal');
const attachmentModalTitle = document.querySelector('#attachmentModalTitle');
const attachmentModalBody = document.querySelector('#attachmentModalBody');
const closeAttachmentModal = document.querySelector('#closeAttachmentModal');
let protocolEntries = [];
let documentPreviewUrl = null;
const attachmentMap = new Map();

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

  if (response.status === 401) {
    sessionStorage.removeItem('token');
    token = null;
  }

  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  renderJson(payload);
  return payload.data;
}

function renderJson(payload) {
  if (jsonOutput) {
    jsonOutput.textContent = JSON.stringify(payload, null, 2);
  }
}

async function loadProtocol() {
  protocolEntries = await api('/protocol');
  renderProtocol();
}

function renderProtocol() {
  const direction = directionFilter.value;
  const entries = direction
    ? protocolEntries.filter((entry) => entry.direction === direction)
    : protocolEntries;

  protocolCount.textContent = entries.length;
  protocolTable.innerHTML = entries.map(row).join('');
}

function row(entry) {
  const canceled = Boolean(entry.canceled_at);
  const status = canceled
    ? `Annullato ${entry.canceled_at} da ${entry.canceled_by_name || '-'}`
    : `Attivo - da ${entry.created_by_name || '-'}`;

  const viewAction = `<button class="icon-action" data-view="${entry.preview_document_id || ''}" title="Anteprima" ${entry.preview_document_id ? '' : 'disabled'}>${MyRsuIcons.get('eye')}</button>`;

  return `
    <article class="register-row ${canceled ? 'is-canceled' : ''}">
      <div class="register-code">${entry.protocol_number}</div>
      <div class="register-meta">${entry.created_at}</div>
      <div class="register-meta">${entry.direction}</div>
      <div class="register-title">${entry.subject}</div>
      <div class="register-meta">${entry.document_id || '-'}</div>
      <div class="register-meta">${status}</div>
      <div class="register-actions">
        ${viewAction}
        <a class="icon-action" href="protocol-edit.html?id=${entry.id}" title="Modifica">${MyRsuIcons.get('edit')}</a>
        <button class="icon-action danger" data-cancel="${entry.id}" title="Annulla protocollo" ${canceled ? 'disabled' : ''}>${MyRsuIcons.get('protocolDelete')}</button>
      </div>
    </article>
  `;
}

directionFilter.addEventListener('change', renderProtocol);

protocolTable.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;

  if (button.dataset.view) {
    try {
      await openDocumentPreview(button.dataset.view);
      documentModal.showModal();
    } catch (error) {
      message.textContent = error.message;
    }
    return;
  }

  if (button.dataset.cancel) {
    if (!confirm('Annullare protocollo?')) return;
    await api(`/protocol/${button.dataset.cancel}`, { method: 'DELETE' });
    message.textContent = 'Protocollo annullato';
    await loadProtocol();
  }
});

closeDocumentModal.addEventListener('click', () => {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  documentPreview.src = '';
  documentModal.close();
});

async function openDocumentPreview(id) {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  const headers = {};
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}/documents/${id}/preview`, { headers });
  if (!response.ok) {
    const payload = await response.json().catch(() => ({}));
    throw new Error(payload.error?.message || 'Anteprima non disponibile');
  }
  documentPreviewUrl = URL.createObjectURL(await response.blob());
  documentPreview.src = documentPreviewUrl;
}

async function showReport(id) {
  const report = await api(`/reports/${id}`);
  reportModalTitle.textContent = report.subject;
  reportModalMeta.textContent = `${report.tracking_code} - ${translateStatus(report.status)}${report.protocol_number ? ` - ${report.protocol_number}` : ''}`;
  reportModalMessage.textContent = report.message;
  reportModalDocument.innerHTML = report.document_id
    ? `<h3>Documento protocollato</h3><a class="button" href="${apiBase}/documents/${report.document_id}/preview?token=${encodeURIComponent(token)}" target="_blank" rel="noopener">Apri PDF</a>`
    : '';
  reportModalAttachments.innerHTML = attachmentsHtml(report.attachments || []);
  reportModal.showModal();
}

function attachmentsHtml(attachments) {
  if (attachments.length === 0) {
    return '<p class="muted">Nessun allegato.</p>';
  }

  return `<h3>Allegati privati (${attachments.length})</h3>${attachments.map((attachment) => {
    const url = `${apiBase}/reports/attachments/${attachment.id}/preview?token=${encodeURIComponent(token)}`;
    attachmentMap.set(String(attachment.id), { ...attachment, url });
    if (String(attachment.mime_type).startsWith('image/')) {
      return `<button class="attachment-preview" type="button" data-attachment="${attachment.id}">
        <img src="${url}" alt="${escapeHtml(attachment.original_name)}">
        <span>${escapeHtml(attachment.original_name)}</span>
      </button>`;
    }

    return `<button class="attachment-preview attachment-video" type="button" data-attachment="${attachment.id}">
      <span class="video-placeholder">▶</span>
      <span>${escapeHtml(attachment.original_name)}</span>
    </button>`;
  }).join('')}`;
}

function showAttachment(id) {
  const attachment = attachmentMap.get(String(id));
  if (!attachment) return;
  attachmentModalTitle.textContent = attachment.original_name;
  attachmentModalBody.innerHTML = String(attachment.mime_type).startsWith('image/')
    ? `<img class="attachment-modal-media" src="${attachment.url}" alt="${escapeHtml(attachment.original_name)}">`
    : `<video class="attachment-modal-media" src="${attachment.url}" controls></video>`;
  attachmentModal.showModal();
}

function translateStatus(status) {
  return { pending: 'da moderare', approved: 'approvata', rejected: 'respinta' }[status] || status;
}

function escapeHtml(value) {
  return String(value || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

closeReportModal.addEventListener('click', () => {
  reportModal.close();
});

reportModalAttachments.addEventListener('click', (event) => {
  const button = event.target.closest('[data-attachment]');
  if (button) showAttachment(button.dataset.attachment);
});

closeAttachmentModal.addEventListener('click', () => {
  attachmentModal.close();
  attachmentModalBody.innerHTML = '';
});

if (!token) {
  window.location.href = 'app/index.html';
} else {
  loadProtocol().catch((error) => {
    message.textContent = error.message;
  });
}
