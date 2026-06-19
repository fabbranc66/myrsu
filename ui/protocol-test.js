const apiBase = '../api/v1';
let token = sessionStorage.getItem('token');

const loginForm = document.querySelector('#loginForm');
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
    ? `Canceled ${entry.canceled_at} by ${entry.canceled_by_name || '-'}`
    : `Active · by ${entry.created_by_name || '-'}`;

  return `
    <article class="register-row ${canceled ? 'is-canceled' : ''}">
      <div class="register-code">${entry.protocol_number}</div>
      <div class="register-meta">${entry.created_at}</div>
      <div class="register-meta">${entry.direction}</div>
      <div class="register-title">${entry.subject}</div>
      <div class="register-meta">${entry.document_id || '-'}</div>
      <div class="register-meta">${status}</div>
      <div class="register-actions">
        <button data-view="${entry.document_id || ''}" ${entry.document_id ? '' : 'disabled'}>View</button>
        <a class="button" href="protocol-edit.html?id=${entry.id}">Edit</a>
        <button data-cancel="${entry.id}" ${canceled ? 'disabled' : ''}>Delete</button>
      </div>
    </article>
  `;
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const form = new FormData(loginForm);
    const data = await api('/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        email: form.get('email'),
        password: form.get('password'),
        device_name: 'protocol-test',
      }),
    });
    token = data.access_token;
    sessionStorage.setItem('token', token);
    await loadProtocol();
  } catch (error) {
    message.textContent = error.message;
  }
});

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
    await api(`/protocol/${button.dataset.cancel}`, { method: 'DELETE' });
    await loadProtocol();
  }
});

closeDocumentModal.addEventListener('click', () => {
  documentPreview.src = '';
  documentModal.close();
});

if (token) loadProtocol().catch((error) => {
  message.textContent = error.message;
});
