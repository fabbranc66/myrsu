const apiBase = '../api/v1';
let token = sessionStorage.getItem('token');

const loginForm = document.querySelector('#loginForm');
const protocolForm = document.querySelector('#protocolForm');
const protocolTable = document.querySelector('#protocolTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const protocolCount = document.querySelector('#protocolCount');

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
  const entries = await api('/protocol');
  protocolForm.classList.remove('hidden');
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
      <input class="register-title" data-subject="${entry.id}" value="${entry.subject}">
      <input class="register-doc" data-document="${entry.id}" type="number" value="${entry.document_id || ''}" placeholder="Document ID">
      <div class="register-meta">${status}</div>
      <div class="register-actions">
        <button data-save="${entry.id}" ${canceled ? 'disabled' : ''}>Save</button>
        <button data-cancel="${entry.id}" ${canceled ? 'disabled' : ''}>Cancel</button>
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

protocolForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(protocolForm).entries());
  if (!data.document_id) delete data.document_id;
  await api('/protocol', { method: 'POST', body: JSON.stringify(data) });
  await loadProtocol();
});

protocolTable.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;

  if (button.dataset.save) {
    const id = button.dataset.save;
    const body = {
      subject: document.querySelector(`[data-subject="${id}"]`).value,
      document_id: document.querySelector(`[data-document="${id}"]`).value || null,
    };
    await api(`/protocol/${id}`, { method: 'PATCH', body: JSON.stringify(body) });
  }

  if (button.dataset.cancel) {
    await api(`/protocol/${button.dataset.cancel}`, { method: 'DELETE' });
  }

  await loadProtocol();
});

if (token) loadProtocol().catch((error) => {
  message.textContent = error.message;
});
