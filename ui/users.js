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
const logObjectModal = document.querySelector('#logObjectModal');
const logObjectTitle = document.querySelector('#logObjectTitle');
const logObjectFrame = document.querySelector('#logObjectFrame');
const closeLogObjectModal = document.querySelector('#closeLogObjectModal');
const gdprBox = document.querySelector('#gdprBox');
const jsonOutput = document.querySelector('#jsonOutput');

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (state.token) headers.Authorization = `Bearer ${state.token}`;

  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  renderJson(payload);
  return payload.data;
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

async function checkGdpr() {
  const consents = await api('/gdpr/consents');
  const accepted = consents.some((consent) => (
    consent.consent_type === 'privacy_policy'
    && consent.document_version === '2026-06-18'
    && Number(consent.accepted) === 1
  ));

  gdprBox.classList.toggle('hidden', accepted);
}

async function loadUsers() {
  const users = await api('/users');
  usersTable.innerHTML = users.map((user) => renderUserRow(user, state.roles)).join('');
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
    await checkGdpr();
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
      showMessage('Stato salvato');
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
      showMessage('Utente salvato');
    }

    if (button.dataset.delete) {
      if (!confirm('Eliminare utente?')) return;
      await api(`/users/${button.dataset.delete}`, { method: 'DELETE' });
      showMessage('Utente eliminato');
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
      <td>${formatWhere(log)}</td>
      <td>${formatSection(log.metadata_json)}</td>
      <td>${formatObjectLink(log.metadata_json)}${formatMetadata(log.metadata_json)}</td>
      <td>${log.created_at}</td>
    </tr>
  `;
}

function formatAction(log) {
  const actions = {
    'auth.login': 'Login',
    'auth.logout': 'Logout',
    'users.create': 'Utente creato',
    'users.update': 'Utente aggiornato',
    'users.delete': 'Utente eliminato',
    'roles.user_replaced': 'Ruolo cambiato',
    'gdpr.consent.recorded': 'GDPR consent recorded',
    'comments.create': 'Commento inviato',
    'comments.moderate': 'Commento moderato / risposta RSU',
    'reports.create': 'Segnalazione inviata',
    'reports.moderate': 'Segnalazione moderata',
    'documents.preview': 'Documento visualizzato',
    'documents.download': 'Documento scaricato',
    'documents.upload': 'Documento caricato',
    'documents.delete': 'Documento eliminato',
  };

  return actions[log.action] || log.action;
}

function formatWhere(log) {
  const section = rawSection(log.metadata_json);
  if (section !== 'registry') return '';
  return log.target_name || 'user';
}

function formatObjectLink(value) {
  const data = parseMetadata(value);
  if (!data) return '';

  if (data.document_id) {
    return `<button class="log-object-link" data-log-modal="document-view.html?id=${data.document_id}" data-log-title="Documento">apri documento</button> `;
  }

  if (data.report_id) {
    return `<button class="log-object-link" data-log-modal="reports-moderation.html?status=all" data-log-title="Segnalazioni">apri segnalazioni</button> `;
  }

  if (data.comment_id) {
    return `<button class="log-object-link" data-log-modal="comments-moderation.html?status=all" data-log-title="Commenti">apri commenti</button> `;
  }

  const userId = data.created_user_id || data.updated_user_id || data.target_user_id;
  if (userId) {
    return `<button class="log-object-link" data-log-modal="user-edit.html?id=${userId}" data-log-title="Utente">apri utente</button> `;
  }

  return '';
}

function formatMetadata(value) {
  if (!value) return '';

  try {
    const data = parseMetadata(value);
    if (!data) return '';

    if (data.changes) {
      return formatChanges(data.changes);
    }

    if (data.section === 'comments') {
      return formatCommentMetadata(data);
    }

    return Object.entries(data)
      .filter(([key]) => key !== 'section')
      .map(([key, item]) => `${key}: ${Array.isArray(item) ? item.join(', ') : item}`)
      .join(' | ');
  } catch {
    return value;
  }
}

function formatCommentMetadata(data) {
  const parts = [];
  if (data.comment_id) parts.push(`commento: ${data.comment_id}`);
  if (data.document_id) parts.push(`documento: ${data.document_id}`);
  if (data.status) parts.push(`stato: ${translateCommentStatus(data.status)}`);
  if (data.reply_changed) parts.push('risposta RSU aggiornata');
  if (data.reply) parts.push(`risposta: ${data.reply}`);
  return parts.join(' | ');
}

function translateCommentStatus(status) {
  return { pending: 'da moderare', approved: 'approvato', rejected: 'respinto' }[status] || status;
}

function formatSection(value) {
  if (!value) return '';

  try {
    const section = parseMetadata(value)?.section || '';
    return { comments: 'commenti', reports: 'segnalazioni', documents: 'documenti', registry: 'anagrafica' }[section] || section;
  } catch {
    return '';
  }
}

function rawSection(value) {
  if (!value) return '';

  try {
    return parseMetadata(value)?.section || '';
  } catch {
    return '';
  }
}

function parseMetadata(value) {
  if (!value) return null;

  try {
    return JSON.parse(value);
  } catch {
    return null;
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

activityTable.addEventListener('click', (event) => {
  const button = event.target.closest('[data-log-modal]');
  if (!button) return;
  logObjectTitle.textContent = button.dataset.logTitle || 'Oggetto log';
  logObjectFrame.src = button.dataset.logModal;
  logObjectModal.showModal();
});

closeLogObjectModal.addEventListener('click', () => {
  logObjectFrame.src = '';
  logObjectModal.close();
});

usersTable.addEventListener('change', async (event) => {
  const select = event.target.closest('select[data-role]');
  if (!select) return;

  try {
    await api(`/users/${select.dataset.role}/roles`, {
      method: 'POST',
      body: JSON.stringify({ roles: [select.value] }),
    });
    showMessage('Ruolo salvato');
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

document.querySelector('#acceptGdpr').addEventListener('click', async () => {
  await api('/gdpr/consents', {
    method: 'POST',
    body: JSON.stringify({
      consent_type: 'privacy_policy',
      document_version: '2026-06-18',
      accepted: true,
    }),
  });
  gdprBox.classList.add('hidden');
  showMessage('GDPR accepted');
});

setAuthView();
if (state.token) loadRoles().then(checkGdpr).then(loadUsers).catch(showError);
