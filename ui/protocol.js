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
let protocolEntries = [];

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

  return `
    <article class="register-row ${canceled ? 'is-canceled' : ''}">
      <div class="register-code">${entry.protocol_number}</div>
      <div class="register-meta">${entry.created_at}</div>
      <div class="register-meta">${entry.direction}</div>
      <div class="register-title">${entry.subject}</div>
      <div class="register-meta">${entry.document_id || '-'}</div>
      <div class="register-meta">${status}</div>
      <div class="register-actions">
        <button class="icon-action" data-view="${entry.preview_document_id || ''}" title="Anteprima" ${entry.preview_document_id ? '' : 'disabled'}>${MyRsuIcons.get('eye')}</button>
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
    documentPreview.src = `${apiBase}/documents/${button.dataset.view}/preview?token=${encodeURIComponent(token)}`;
    documentModal.showModal();
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
  documentPreview.src = '';
  documentModal.close();
});

if (!token) {
  window.location.href = 'app/index.html';
} else {
  loadProtocol().catch((error) => {
    message.textContent = error.message;
  });
}
