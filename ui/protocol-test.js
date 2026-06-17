const apiBase = '../api/v1';
let token = sessionStorage.getItem('token');

const loginForm = document.querySelector('#loginForm');
const protocolTable = document.querySelector('#protocolTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const protocolCount = document.querySelector('#protocolCount');
const directionFilter = document.querySelector('#directionFilter');
let protocolEntries = [];

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

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
        <a class="button" href="protocol-view.html?id=${entry.id}">View</a>
        <a class="button" href="protocol-edit.html?id=${entry.id}">Edit</a>
        <button data-cancel="${entry.id}" ${canceled ? 'disabled' : ''}>Delete</button>
      </div>
    </article>
  `;
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
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
});

directionFilter.addEventListener('change', renderProtocol);

protocolTable.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button || !button.dataset.cancel) return;
  await api(`/protocol/${button.dataset.cancel}`, { method: 'DELETE' });
  await loadProtocol();
});

if (token) loadProtocol().catch((error) => {
  message.textContent = error.message;
});
