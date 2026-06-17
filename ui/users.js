const apiBase = '../api/v1';
const state = { token: sessionStorage.getItem('token'), roles: [] };

const loginPanel = document.querySelector('#loginPanel');
const userPanel = document.querySelector('#userPanel');
const loginForm = document.querySelector('#loginForm');
const createForm = document.querySelector('#createForm');
const usersTable = document.querySelector('#usersTable');
const roleSelect = document.querySelector('#roleSelect');
const message = document.querySelector('#message');
const consentPanel = document.querySelector('#consentPanel');
const consentsTable = document.querySelector('#consentsTable');
const activityPanel = document.querySelector('#activityPanel');
const activityTable = document.querySelector('#activityTable');

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (state.token) headers.Authorization = `Bearer ${state.token}`;

  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

function showError(error) {
  message.textContent = error.message;
}

function showMessage(text) {
  message.textContent = text;
}

function assertEmail(value) {
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
    throw new Error('Invalid email');
  }
}

function assertPassword(value, required = false) {
  if ((required || value) && value.length < 8) {
    throw new Error('Password min 8 chars');
  }
}

function setAuthView() {
  loginPanel.classList.toggle('hidden', Boolean(state.token));
  userPanel.classList.toggle('hidden', !state.token);
}

async function loadRoles() {
  state.roles = await api('/roles');
  roleSelect.innerHTML = state.roles
    .map((role) => `<option value="${role.name}">${role.label}</option>`)
    .join('');
}

async function loadUsers() {
  const users = await api('/users');
  usersTable.innerHTML = users.map(row).join('');
}

function row(user) {
  const nextStatus = user.status === 'active' ? 'suspended' : 'active';
  const currentRole = String(user.roles || '').split(',')[0] || '';
  const options = state.roles
    .map((role) => `<option value="${role.name}" ${role.name === currentRole ? 'selected' : ''}>${role.label}</option>`)
    .join('');

  return `
    <tr>
      <td><input data-name="${user.id}" value="${user.name}"></td>
      <td><input data-email="${user.id}" type="email" value="${user.email}"></td>
      <td><input data-password="${user.id}" type="password" placeholder="New password"></td>
      <td><button data-status="${nextStatus}" data-id="${user.id}">${user.status}</button></td>
      <td>
        <select data-role="${user.id}">
          ${options}
        </select>
      </td>
      <td>
        <button data-save="${user.id}">Save</button>
        <button data-consents="${user.id}">GDPR</button>
        <button data-activity="${user.id}">Logs</button>
        <button data-delete="${user.id}">Delete</button>
      </td>
    </tr>
  `;
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';

  try {
    const form = new FormData(loginForm);
    const data = await api('/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        email: form.get('email'),
        password: form.get('password'),
        device_name: 'users-ui',
      }),
    });

    state.token = data.access_token;
    sessionStorage.setItem('token', state.token);
    setAuthView();
    await loadRoles();
    await loadUsers();
  } catch (error) {
    showError(error);
  }
});

createForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';

  try {
    const form = new FormData(createForm);
    assertEmail(String(form.get('email')));
    assertPassword(String(form.get('password')), true);
    await api('/users', {
      method: 'POST',
      body: JSON.stringify(Object.fromEntries(form.entries())),
    });
    createForm.reset();
    await loadUsers();
  } catch (error) {
    showError(error);
  }
});

usersTable.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;

  try {
    if (button.dataset.status) {
      await api(`/users/${button.dataset.id}`, {
        method: 'PATCH',
        body: JSON.stringify({ status: button.dataset.status }),
      });
      showMessage('Status saved');
    }

    if (button.dataset.save) {
      const id = button.dataset.save;
      const password = document.querySelector(`[data-password="${id}"]`).value;
      const body = {
        name: document.querySelector(`[data-name="${id}"]`).value,
        email: document.querySelector(`[data-email="${id}"]`).value,
      };

      assertEmail(body.email);
      assertPassword(password);

      if (password) {
        body.password = password;
      }

      await api(`/users/${id}`, {
        method: 'PATCH',
        body: JSON.stringify(body),
      });
      showMessage('User saved');
    }

    if (button.dataset.delete) {
      if (!confirm('Delete user?')) return;
      await api(`/users/${button.dataset.delete}`, { method: 'DELETE' });
      showMessage('User deleted');
    }

    if (button.dataset.consents) {
      const consents = await api(`/users/${button.dataset.consents}/gdpr/consents`);
      consentsTable.innerHTML = consents.map(consentRow).join('');
      consentPanel.classList.remove('hidden');
    }

    if (button.dataset.activity) {
      const logs = await api(`/users/${button.dataset.activity}/activity`);
      activityTable.innerHTML = logs.map(activityRow).join('');
      activityPanel.classList.remove('hidden');
    }

    await loadUsers();
  } catch (error) {
    showError(error);
  }
});

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

function activityRow(log) {
  return `
    <tr>
      <td>${formatAction(log)}</td>
      <td>${log.actor_name || ''}</td>
      <td>${formatTarget(log)}</td>
      <td>${formatMetadata(log.metadata_json)}</td>
      <td>${log.created_at}</td>
    </tr>
  `;
}

function formatTarget(log) {
  return log.target_name || '';
}

function formatAction(log) {
  const actions = {
    'auth.login': 'Login',
    'auth.logout': 'Logout',
    'users.create': 'User created',
    'users.update': 'User updated',
    'users.delete': 'User deleted',
    'roles.user_replaced': 'Role changed',
    'gdpr.consent.recorded': 'GDPR consent recorded',
  };

  return actions[log.action] || log.action;
}

function formatMetadata(value) {
  if (!value) return '';

  try {
    const data = JSON.parse(value);

    if (data.changes) {
      return formatChanges(data.changes);
    }

    return Object.entries(data)
      .map(([key, item]) => `${key}: ${Array.isArray(item) ? item.join(', ') : item}`)
      .join(' | ');
  } catch {
    return value;
  }
}

function formatChanges(changes) {
  return Object.entries(changes)
    .map(([field, change]) => {
      if (change === 'changed') return `${field}: changed`;
      return `${field}: ${change.from} -> ${change.to}`;
    })
    .join(' | ');
}

document.querySelector('#closeConsents').addEventListener('click', () => {
  consentPanel.classList.add('hidden');
});

document.querySelector('#closeActivity').addEventListener('click', () => {
  activityPanel.classList.add('hidden');
});

usersTable.addEventListener('change', async (event) => {
  const select = event.target.closest('select[data-role]');
  if (!select) return;

  try {
    await api(`/users/${select.dataset.role}/roles`, {
      method: 'POST',
      body: JSON.stringify({ roles: [select.value] }),
    });
    showMessage('Role saved');
  } catch (error) {
    showError(error);
  }
});

document.querySelector('#logoutButton').addEventListener('click', async () => {
  await api('/auth/logout', { method: 'POST' }).catch(() => {});
  sessionStorage.removeItem('token');
  state.token = null;
  setAuthView();
});

setAuthView();
if (state.token) loadRoles().then(loadUsers).catch(showError);
