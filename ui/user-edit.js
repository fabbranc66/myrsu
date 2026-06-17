const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const id = new URLSearchParams(window.location.search).get('id');

const editForm = document.querySelector('#editForm');
const roleSelect = document.querySelector('#roleSelect');
const message = document.querySelector('#message');
const gdprTable = document.querySelector('#gdprTable');
const subtitle = document.querySelector('#subtitle');
const jsonOutput = document.querySelector('#jsonOutput');

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

async function load() {
  const roles = await api('/roles');
  const data = await api(`/users/${id}`);
  const consents = await api(`/users/${id}/gdpr/consents`);

  roleSelect.innerHTML = roles
    .map((role) => `<option value="${role.name}">${role.label}</option>`)
    .join('');

  editForm.name.value = data.user.name;
  editForm.email.value = data.user.email;
  editForm.status.value = data.user.status;
  editForm.id.value = data.user.id;
  editForm.created_at.value = data.user.created_at;
  editForm.updated_at.value = data.user.updated_at;
  roleSelect.value = data.roles[0] || '';
  subtitle.textContent = `${data.user.name} · ${data.user.email}`;
  gdprTable.innerHTML = consents.map(consentRow).join('');
}

function consentRow(consent) {
  return `
    <tr>
      <td>${consent.consent_type}</td>
      <td>${consent.document_version}</td>
      <td>${Number(consent.accepted) === 1 ? 'yes' : 'no'}</td>
      <td>${consent.created_at}</td>
    </tr>
  `;
}

editForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(editForm);
  const body = Object.fromEntries(form.entries());
  const role = body.role;

  delete body.id;
  delete body.role;
  delete body.created_at;
  delete body.updated_at;

  if (!body.password) {
    delete body.password;
  }

  await api(`/users/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  });

  await api(`/users/${id}/roles`, {
    method: 'POST',
    body: JSON.stringify({ roles: [role] }),
  });

  message.textContent = 'User saved';
});

load().catch((error) => {
  message.textContent = error.message;
});
