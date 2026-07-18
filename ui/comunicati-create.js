const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const table = document.querySelector('#comunicatiTable');
const jsonOutput = document.querySelector('#jsonOutput');
const message = document.querySelector('#message');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const verifyFrameModal = document.querySelector('#verifyFrameModal');
const verifyFrame = document.querySelector('#verifyFrame');
const closeVerifyFrameModal = document.querySelector('#closeVerifyFrameModal');
let documentPreviewUrl = null;

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
  return `<tr>
    <td title="${escapeHtml(subject)}">${draft ? '<span class="doc-type-tag draft">DRAFT</span>' : '<span class="doc-type-tag">PDF</span>'} ${escapeHtml(title)}</td>
    <td>${document.visibility}</td>
    <td>${draft ? 'bozza' : 'ufficiale'}</td>
    <td>${document.created_at || '-'}</td>
    <td class="actions-cell">${draft ? '' : `<button class="icon-action" data-view="${document.id}" title="Anteprima">${MyRsuIcons.get('eye')}</button>`}<a class="icon-action" href="${editUrl}" title="Modifica">${MyRsuIcons.get('edit')}</a>${draft ? `<button class="draft-generate-button" data-generate="${document.id}">${MyRsuIcons.get('document')} Genera ufficiale</button>` : ''}</td>
  </tr>`;
}

function escapeHtml(value) {
  return String(value || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

async function load() {
  const documents = await api('/documents');
  table.innerHTML = documents.filter((document) => document.category === 'comunicati').map(row).join('');
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
  const response = await fetch(`${apiBase}/documents/${id}/preview`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  if (!response.ok) throw new Error('Anteprima non disponibile.');
  documentPreviewUrl = URL.createObjectURL(await response.blob());
  documentPreview.src = documentPreviewUrl;
  documentModal.showModal();
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
  documentPreview.src = '';
  documentModal.close();
}

closeVerifyFrameModal.addEventListener('click', () => {
  verifyFrame.src = '';
  verifyFrameModal.close();
});

if (!token) window.location.href = 'app/index.html';
load().catch((error) => {
  message.textContent = error.message;
});
