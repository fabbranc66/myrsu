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
  editForm.first_name.value = data.user.first_name || '';
  editForm.last_name.value = data.user.last_name || '';
  editForm.email.value = data.user.email;
  editForm.phone.value = data.user.phone || '';
  editForm.mobile.value = data.user.mobile || '';
  editForm.city.value = data.user.city || '';
  editForm.country.value = data.user.country || '';
  editForm.union_code.value = data.user.union_code || '';
  editForm.status.value = data.user.status;
  editForm.id.value = data.user.id;
  editForm.created_at.value = data.user.created_at;
  editForm.updated_at.value = data.user.updated_at;
  roleSelect.value = data.roles[0] || '';
  syncUnionCodeField();
  subtitle.textContent = `${data.user.name} - ${data.user.email}`;
  gdprTable.innerHTML = consents.map(consentRow).join('');
}

function syncUnionCodeField() {
  const enabled = ['delegato', 'rls'].includes(roleSelect.value);
  editForm.union_code.disabled = !enabled;
  editForm.union_logo.disabled = !enabled;
  if (!enabled) editForm.union_code.value = '';
  if (!enabled) editForm.union_logo.value = '';
}

roleSelect.addEventListener('change', syncUnionCodeField);

function consentRow(consent) {
  return `
    <tr>
      <td>${consent.consent_type}</td>
      <td>${consent.document_version}</td>
      <td>${Number(consent.accepted) === 1 ? 'si' : 'no'}</td>
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
  if (!['delegato', 'rls'].includes(role)) {
    body.union_code = '';
  }

  if (!body.password) {
    delete body.password;
  }

  await api(`/users/${id}/roles`, {
    method: 'POST',
    body: JSON.stringify({ roles: [role] }),
  });

  await api(`/users/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  });

  if (editForm.union_logo.files.length > 0 && ['delegato', 'rls'].includes(role)) {
    await uploadUnionLogo(editForm.union_logo.files[0]);
  }

  message.textContent = 'Utente salvato';
});

async function uploadUnionLogo(file) {
  const body = new FormData();
  body.append('logo', file);
  const response = await fetch(`${apiBase}/users/${id}/union-logo`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}` },
    body,
  });
  const payload = await response.json();
  renderJson(payload);
  if (!response.ok) throw new Error(payload.error?.message || 'Upload logo fallito');
}

load().catch((error) => {
  message.textContent = error.message;
});
