const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const table = document.querySelector('#privateDocumentsTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');

if (!token) window.location.href = 'app/index.html';

async function api(path, options = {}) {
  const response = await fetch(`${apiBase}${path}`, {
    ...options,
    headers: { Authorization: `Bearer ${token}` },
  });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

function originalType(document) {
  const name = String(document.original_name || '');
  return name.includes('.') ? name.split('.').pop().toUpperCase() : 'FILE';
}

function systemType(document) {
  return document.mime_type === 'application/pdf' ? 'PDF' : 'FILE';
}

function conversionLabel(document) {
  if (document.conversion_status === 'pending') return 'in attesa';
  if (document.conversion_status === 'failed') return 'errore';
  return systemType(document) === 'PDF' && originalType(document) !== 'PDF' ? 'convertito' : 'non convertito';
}

function row(document) {
  return `<tr><td>${document.original_name}</td><td><span class="doc-type-tag">${originalType(document)}</span></td><td><span class="doc-type-tag">${systemType(document)}</span></td><td><span class="doc-origin-tag ${conversionLabel(document) === 'convertito' ? 'converted' : 'native'}">${conversionLabel(document)}</span></td><td>${document.category || '-'}</td><td>${document.created_at || '-'}</td><td class="actions-cell"><button class="icon-action" data-view="${document.id}" title="Visualizza">${MyRsuIcons.get('eye')}</button><button class="icon-action" data-download="${document.id}" title="Scarica">${MyRsuIcons.get('download')}</button><button class="icon-action danger" data-delete="${document.id}" title="Cancella">${MyRsuIcons.get('trash')}</button></td></tr>`;
}

async function loadDocuments() {
  const documents = await api('/documents/private');
  table.innerHTML = documents.map(row).join('');
}

async function boot() {
  const user = await api('/me');
  const roles = Array.isArray(user.roles) ? user.roles : [];
  if (!roles.includes('admin')) {
    window.location.replace('app/index.html');
    return;
  }
  await loadDocuments();
}

table.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;
  if (button.dataset.view) return showDocument(button.dataset.view);
  if (button.dataset.download) return downloadDocument(button.dataset.download);
  if (!confirm('Eliminare documento?')) return;
  await api(`/documents/${button.dataset.delete}`, { method: 'DELETE' });
  message.textContent = 'Documento eliminato';
  await loadDocuments();
});

function showDocument(id) {
  documentPreview.src = `${apiBase}/documents/${id}/private-preview?token=${encodeURIComponent(token)}`;
  documentModal.showModal();
}

async function downloadDocument(id) {
  const response = await fetch(`${apiBase}/documents/${id}/private-download`, {
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

closeDocumentModal.addEventListener('click', () => {
  documentPreview.src = '';
  documentModal.close();
});

boot().catch((error) => { message.textContent = error.message; });
