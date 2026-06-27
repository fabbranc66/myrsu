const apiBase = '../api/v1';
let token = sessionStorage.getItem('token');

const uploadForm = document.querySelector('#uploadForm');
const documentsTable = document.querySelector('#documentsTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const uploadProgress = document.querySelector('#uploadProgress');
const uploadProgressFill = document.querySelector('#uploadProgressFill');
const uploadProgressText = document.querySelector('#uploadProgressText');
const practiceLinkModal = document.querySelector('#practiceLinkModal');
const practiceLinkForm = document.querySelector('#practiceLinkForm');
const closePracticeLinkModal = document.querySelector('#closePracticeLinkModal');
let practices = [];
let documentPreviewUrl = null;

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

function uploadDocument(formData) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', `${apiBase}/documents`);
    if (token) {
      xhr.setRequestHeader('Authorization', `Bearer ${token}`);
    }

    xhr.upload.addEventListener('progress', (event) => {
      if (!event.lengthComputable) return;
      const value = Math.max(1, Math.min(95, Math.round((event.loaded / event.total) * 95)));
      setUploadProgress(value);
    });

    xhr.addEventListener('load', () => {
      const payload = xhr.responseText ? JSON.parse(xhr.responseText) : {};
      jsonOutput.textContent = JSON.stringify(payload, null, 2);
      if (xhr.status < 200 || xhr.status >= 300) {
        reject(new Error(payload.error?.message || 'Upload fallito'));
        return;
      }
      resolve(payload.data);
    });

    xhr.addEventListener('error', () => reject(new Error('Upload fallito')));
    xhr.send(formData);
  });
}

async function loadDocuments() {
  const [documents, practiceRows] = await Promise.all([api('/documents'), loadPractices()]);
  practices = practiceRows;
  uploadForm.classList.remove('hidden');
  documentsTable.innerHTML = documents.map(row).join('');
}

async function loadPractices() {
  try {
    return await api('/practices');
  } catch {
    return [];
  }
}

function row(document) {
  return `
    <tr>
      <td><span class="doc-type-tag">${documentsSystemType(document)}</span> ${document.original_name}</td>
      <td>${document.category || '-'}</td>
      <td>${translateVisibility(document.visibility)}</td>
      <td>${translateStatus(document.conversion_status)}</td>
      <td>${document.pdf_size_bytes || document.size_bytes}</td>
      <td class="actions-cell">
        <button class="icon-action" data-view="${document.id}" title="Anteprima">${MyRsuIcons.get('eye')}</button>
        <a class="icon-action" href="document-edit.html?id=${document.id}" title="Modifica">${MyRsuIcons.get('edit')}</a>
        <button class="icon-action" data-download="${document.id}" title="Scarica">${MyRsuIcons.get('download')}</button>
        <button class="icon-action" data-practice-link="${document.id}" title="Collega a pratica">${MyRsuIcons.get('link')}</button>
        <button class="icon-action" data-protocol-in="${document.id}" title="Protocolla in entrata">${MyRsuIcons.get('protocolIn')}</button>
        <button class="icon-action danger" data-delete="${document.id}" title="Elimina">${MyRsuIcons.get('trash')}</button>
      </td>
    </tr>
  `;
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
    await uploadDocument(new FormData(uploadForm));
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

  if (!confirm('Eliminare documento?')) return;
  await api(`/documents/${button.dataset.delete}`, { method: 'DELETE' });
  message.textContent = 'Documento eliminato';
  await loadDocuments();
});

async function showDocument(id) {
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
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  documentPreview.src = '';
  documentModal.close();
});

function openPracticeLink(documentId) {
  practiceLinkForm.document_id.value = documentId;
  practiceLinkForm.practice_id.innerHTML = practices.length > 0
    ? practices.map((practice) => `<option value="${practice.id}">${practice.title}</option>`).join('')
    : '<option value="">Nessuna pratica disponibile</option>';
  practiceLinkForm.querySelector('button').disabled = practices.length === 0;
  practiceLinkModal.showModal();
}

closePracticeLinkModal.addEventListener('click', () => {
  practiceLinkModal.close();
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
