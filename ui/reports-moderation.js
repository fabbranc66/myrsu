const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const reportsTable = document.querySelector('#reportsTable');
const statusFilter = document.querySelector('#statusFilter');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const reportModal = document.querySelector('#reportModal');
const reportModalTitle = document.querySelector('#reportModalTitle');
const reportModalMeta = document.querySelector('#reportModalMeta');
const reportModalMessage = document.querySelector('#reportModalMessage');
const reportModalAttachments = document.querySelector('#reportModalAttachments');
const closeReportModal = document.querySelector('#closeReportModal');
const attachmentModal = document.querySelector('#attachmentModal');
const attachmentModalTitle = document.querySelector('#attachmentModalTitle');
const attachmentModalBody = document.querySelector('#attachmentModalBody');
const closeAttachmentModal = document.querySelector('#closeAttachmentModal');
const initialStatus = new URLSearchParams(window.location.search).get('status');
let reports = [];
const attachmentMap = new Map();

if (!token) window.location.href = 'app/index.html';
if (initialStatus) statusFilter.value = initialStatus;

async function api(path, options = {}) {
  const headers = options.headers || {};
  headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

async function loadReports() {
  reports = await api(`/reports?status=${encodeURIComponent(statusFilter.value)}`);
  reportsTable.innerHTML = reports.map(row).join('');
}

function row(report) {
  return `<tr>
    <td>${report.tracking_code}</td>
    <td>${report.subject}</td>
    <td>${report.origin === 'member' ? (report.author_name || 'membro') : 'anonima'}</td>
    <td>${translateStatus(report.status)}</td>
    <td>${attachmentBadge(report.attachments || [])}</td>
    <td>${report.protocol_number || '-'}</td>
    <td class="actions-cell">
      <button class="icon-action" data-approve="${report.id}" title="Approva">${MyRsuIcons.get('active')}</button>
      <button class="icon-action danger" data-reject="${report.id}" title="Respingi">${MyRsuIcons.get('suspended')}</button>
      <button class="icon-action" data-view="${report.id}" title="Visualizza">${MyRsuIcons.get('eye')}</button>
      ${report.document_id ? `<a class="icon-action" href="document-view.html?id=${report.document_id}" title="Documento">${MyRsuIcons.get('download')}</a>` : ''}
    </td>
  </tr>`;
}

function attachmentBadge(attachments) {
  const count = attachments.length;
  return count > 0 ? `<span class="doc-origin-tag converted">${count} allegati</span>` : '-';
}

function translateStatus(status) {
  return { pending: 'da moderare', approved: 'approvata', rejected: 'respinta' }[status] || status;
}

reportsTable.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;
  if (button.dataset.view) {
    showReport(Number(button.dataset.view));
    return;
  }
  const id = button.dataset.approve || button.dataset.reject;
  const status = button.dataset.approve ? 'approved' : 'rejected';
  await api(`/reports/${id}/moderation`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status }),
  });
  message.textContent = status === 'approved' ? 'Segnalazione approvata e protocollata' : 'Segnalazione respinta';
  await loadReports();
});

function showReport(id) {
  const report = reports.find((item) => Number(item.id) === id);
  if (!report) return;
  reportModalTitle.textContent = report.subject;
  reportModalMeta.textContent = `${report.tracking_code} - ${translateStatus(report.status)}`;
  reportModalMessage.textContent = report.message;
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

statusFilter.addEventListener('change', loadReports);
loadReports().catch((error) => { message.textContent = error.message; });
