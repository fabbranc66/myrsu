const apiBase = '../api/v1';
let token = sessionStorage.getItem('token');

const uploadForm = document.querySelector('#uploadForm');
const documentsTable = document.querySelector('#documentsTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');

async function api(path, options = {}) {
  const headers = options.headers || {};
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);

  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

async function loadDocuments() {
  const documents = await api('/documents');
  uploadForm.classList.remove('hidden');
  documentsTable.innerHTML = documents.map(row).join('');
}

function row(document) {
  return `
    <tr>
      <td>${document.original_name}</td>
      <td>${document.category || '-'}</td>
      <td>${translateVisibility(document.visibility)}</td>
      <td>${translateStatus(document.conversion_status)}</td>
      <td>${document.pdf_size_bytes || document.size_bytes}</td>
      <td class="actions-cell">
        <button class="icon-action" data-view="${document.id}" title="Anteprima">${MyRsuIcons.get('eye')}</button>
        <a class="icon-action" href="document-edit.html?id=${document.id}" title="Modifica">${MyRsuIcons.get('edit')}</a>
        <button class="icon-action" data-download="${document.id}" title="Scarica">${MyRsuIcons.get('download')}</button>
        <button class="icon-action" data-protocol-in="${document.id}" title="Protocolla in entrata">${MyRsuIcons.get('protocolIn')}</button>
        <button class="icon-action danger" data-delete="${document.id}" title="Elimina">${MyRsuIcons.get('trash')}</button>
      </td>
    </tr>
  `;
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
  await api('/documents', { method: 'POST', body: new FormData(uploadForm) });
  message.textContent = 'Documento caricato';
  uploadForm.reset();
  await loadDocuments();
});

documentsTable.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;

  if (button.dataset.download) {
    await downloadDocument(button.dataset.download);
    return;
  }

  if (button.dataset.protocolIn) {
    await protocolIn(button.dataset.protocolIn);
    await loadDocuments();
    return;
  }

  if (button.dataset.view) {
    await showDocument(button.dataset.view);
    return;
  }

  if (!confirm('Eliminare documento?')) return;
  await api(`/documents/${button.dataset.delete}`, { method: 'DELETE' });
  message.textContent = 'Documento eliminato';
  await loadDocuments();
});

async function showDocument(id) {
  documentPreview.src = `${apiBase}/documents/${id}/preview?token=${encodeURIComponent(token)}`;
  documentModal.showModal();
}

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
  documentPreview.src = '';
  documentModal.close();
});

async function downloadDocument(id) {
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

if (!token) {
  window.location.href = 'app/index.html';
} else {
  loadDocuments().catch((error) => {
    message.textContent = error.message;
  });
}
