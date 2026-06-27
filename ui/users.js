const apiBase = '../api/v1';
const state = { token: sessionStorage.getItem('token'), roles: [], permissions: [] };

const userPanel = document.querySelector('#userPanel');
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
const logObjectBody = document.querySelector('#logObjectBody');
const closeLogObjectModal = document.querySelector('#closeLogObjectModal');
const deleteOrphanLogs = document.querySelector('#deleteOrphanLogs');
const gdprBox = document.querySelector('#gdprBox');
const jsonOutput = document.querySelector('#jsonOutput');
let currentActivityUserId = null;
let currentOrphanLogIds = [];

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (state.token) headers.Authorization = `Bearer ${state.token}`;

  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

  if (!response.ok) {
    const error = new Error(payload.error?.message || 'Request failed');
    error.status = response.status;
    throw error;
  }
  renderJson(payload);
  return payload.data;
}

function setAuthView() {
  if (!state.token) {
    window.location.href = 'app/index.html';
    return;
  }

  userPanel.classList.remove('hidden');
}

async function loadRoles() {
  if (!state.permissions.includes('roles.manage')) return;
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
  usersTable.innerHTML = users.map((user) => renderUserRow(user, state.roles, {
    canUpdate: state.permissions.includes('users.update'),
    canDelete: state.permissions.includes('users.delete'),
    canManageRoles: state.permissions.includes('roles.manage'),
    canViewGdpr: state.permissions.includes('gdpr.view_all'),
    canViewActivity: state.permissions.includes('activity.view'),
  })).join('');
}

function configureAccess(me) {
  const roles = Array.isArray(me.roles) ? me.roles : [];
  if (!roles.some((role) => ['admin', 'delegato', 'rls'].includes(role))) {
    window.location.replace('app/index.html');
    return;
  }
  state.permissions = Array.isArray(me.permissions) ? me.permissions : [];
  createForm.classList.toggle('hidden', !state.permissions.includes('users.create'));
}

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
      currentActivityUserId = button.dataset.activity;
      const logs = await api(`/users/${button.dataset.activity}/activity`);
      activityTable.innerHTML = await renderActivityRows(logs);
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
      <td>${formatOrphan(log)}${formatObjectLink(log.metadata_json)}${formatMetadata(log.metadata_json)}</td>
      <td>${log.created_at}</td>
    </tr>
  `;
}

function formatOrphan(log) {
  return log.orphan ? `<span class="log-warning">oggetto non trovato</span> <button class="log-object-link danger" data-delete-log="${log.id}">cancella</button> ` : '';
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
    'documents.comunicato_draft_create': 'Bozza comunicato salvata',
    'documents.comunicato_generate': 'Documento ufficiale generato',
    'calls.create': 'Telefonata registrata',
    'calls.link_practice': 'Telefonata collegata a pratica',
    'practices.link': 'Oggetto collegato a pratica',
    'contacts.create': 'Contatto creato',
    'meetings.create': 'Incontro creato',
    'meetings.update': 'Incontro aggiornato',
    'meetings.public_comunicato': 'Comunicato incontro creato',
    'meetings.note_create': 'Nota incontro aggiunta',
    'contacts.update': 'Contatto aggiornato',
  };

  return actions[log.action] || log.action;
}

function formatWhere(log) {
  const section = rawSection(log.metadata_json);
  if (section !== 'registry') return '';
  return log.target_name || 'user';
}

async function renderActivityRows(logs) {
  const orphanIds = await orphanLogIds(logs);
  currentOrphanLogIds = [...orphanIds];
  updateOrphanButton();
  return logs.map((log) => activityRow({ ...log, orphan: orphanIds.has(Number(log.id)) })).join('');
}

function formatObjectLink(value) {
  const data = parseMetadata(value);
  if (!data) return '';

  if (data.document_id) {
    return `<button class="log-object-link" data-log-type="document" data-log-id="${data.document_id}" data-log-title="Documento">apri documento</button> `;
  }

  if (data.call_id) {
    return `<button class="log-object-link" data-log-type="call" data-log-id="${data.call_id}" data-log-title="Telefonata">apri telefonata</button> `;
  }

  if (data.report_id) {
    return `<button class="log-object-link" data-log-type="reports" data-log-id="${data.report_id}" data-log-title="Segnalazione">apri segnalazione</button> `;
  }

  if (data.comment_id) {
    return `<button class="log-object-link" data-log-type="comments" data-log-id="${data.comment_id}" data-log-title="Commento">apri commento</button> `;
  }

  if (data.meeting_id) {
    return `<button class="log-object-link" data-log-type="meeting" data-log-id="${data.meeting_id}" data-log-title="Incontro">apri incontro</button> `;
  }

  if (data.contact_id) {
    return `<button class="log-object-link" data-log-type="contacts" data-log-id="${data.contact_id}" data-log-title="Contatto">apri contatto</button> `;
  }

  if (data.practice_id) {
    return `<button class="log-object-link" data-log-type="practice" data-log-id="${data.practice_id}" data-log-title="Pratica">apri pratica</button> `;
  }

  const userId = data.created_user_id || data.updated_user_id || data.target_user_id;
  if (userId) {
    return `<button class="log-object-link" data-log-type="user" data-log-id="${userId}" data-log-title="Utente">apri utente</button> `;
  }

  return '';
}

async function openLogObject(type, id, title) {
  logObjectTitle.textContent = title;
  logObjectBody.innerHTML = '<p class="muted">Caricamento...</p>';
  logObjectModal.showModal();

  try {
    const data = await loadLogObject(type, id);
    logObjectBody.innerHTML = renderLogObject(type, data);
  } catch (error) {
    logObjectBody.innerHTML = `<p class="log-warning">${escapeHtml(error.message)}</p>`;
  }
}

async function loadLogObject(type, id) {
  if (type === 'document') return api(`/documents/${id}`);
  if (type === 'call') return api(`/calls/${id}`);
  if (type === 'meeting') return api(`/union-meetings/${id}`);
  if (type === 'user') return api(`/users/${id}`);
  if (type === 'contacts') return (await api('/contacts')).institutional.find((item) => Number(item.id) === Number(id));
  if (type === 'reports') return (await api('/reports?status=all')).find((item) => Number(item.id) === Number(id));
  if (type === 'comments') return (await api('/comments?status=all')).find((item) => Number(item.id) === Number(id));
  throw new Error('Oggetto log non gestito.');
}

function renderLogObject(type, data) {
  if (!data) return '<p class="log-warning">Oggetto non trovato</p>';
  if (type === 'document') return logEditForm('document', data.id, {
    visibility: data.visibility,
  }, {
    Nome: data.original_name,
    Categoria: data.category,
    Stato: data.conversion_status,
  });
  if (type === 'meeting') return logEditForm('meeting', data.id, {
    title: data.title,
    location: data.location,
    meeting_date: data.meeting_date?.replace(' ', 'T').slice(0, 16),
    participants: data.participants,
    agenda: data.agenda,
    description: data.description,
    status: data.status,
    visibility: data.visibility,
  });
  if (type === 'user') return logEditForm('user', data.user?.id, {
    name: data.user?.name,
    email: data.user?.email,
    status: data.user?.status,
  }, { Ruoli: (data.roles || []).join(', ') });
  if (type === 'contacts') return logEditForm('contacts', data.id, {
    type: data.contact_type,
    name: data.label,
    role: data.role,
    organization: data.organization,
    email: data.email,
    phone: data.phone,
    notes: data.notes,
  });
  if (type === 'reports') return logDefinitionList({
    Codice: data.tracking_code,
    Oggetto: data.subject,
    Stato: data.status,
    Testo: data.message,
  });
  if (type === 'call') return logDefinitionList({
    Direzione: data.direction,
    Interlocutore: data.interlocutor?.name,
    Ruolo: data.interlocutor?.role,
    Organizzazione: data.interlocutor?.org,
    Data: data.datetime?.replace('T', ' '),
    Esito: data.outcome,
    Pratica: data.practice_id,
    Contenuto: data.content,
  });
  if (type === 'comments') return logDefinitionList({
    Documento: data.document_id,
    Stato: data.status,
    Commento: data.message,
    Risposta: data.reply,
  });
  return `<pre>${escapeHtml(JSON.stringify(data, null, 2))}</pre>`;
}

function logDefinitionList(items) {
  return `<dl class="log-object-list">${Object.entries(items)
    .map(([key, value]) => `<dt>${escapeHtml(key)}</dt><dd>${escapeHtml(value || '-')}</dd>`)
    .join('')}</dl>`;
}

function logEditForm(type, id, fields, readonly = {}) {
  const inputs = Object.entries(fields).map(([key, value]) => logInput(key, value)).join('');
  const fixed = Object.keys(readonly).length ? logDefinitionList(readonly) : '';
  return `${fixed}<form class="log-edit-form" data-log-edit-type="${type}" data-log-edit-id="${id}">${inputs}<button type="submit">Salva modifiche</button></form>`;
}

function logInput(name, value) {
  if (['description', 'participants', 'agenda', 'notes'].includes(name)) {
    return `<label>${labelField(name)}<textarea name="${name}">${escapeHtml(value || '')}</textarea></label>`;
  }
  if (name === 'status') {
    return `<label>Stato<select name="status">${option(value, 'scheduled', 'programmato')}${option(value, 'done', 'svolto')}${option(value, 'cancelled', 'annullato')}${option(value, 'active', 'attivo')}${option(value, 'suspended', 'sospeso')}</select></label>`;
  }
  if (name === 'visibility') {
    return `<label>Visibilita<select name="visibility">${option(value, 'rsu', 'rsu')}${option(value, 'members', 'membri')}${option(value, 'public', 'pubblico')}</select></label>`;
  }
  if (name === 'type') {
    return `<label>Tipo<select name="type">${option(value, 'aziendale', 'aziendale')}${option(value, 'sindacale', 'sindacale')}${option(value, 'esterno', 'esterno')}</select></label>`;
  }
  const inputType = name === 'meeting_date' ? 'datetime-local' : 'text';
  return `<label>${labelField(name)}<input name="${name}" type="${inputType}" value="${escapeHtml(value || '')}"></label>`;
}

function option(current, value, label) {
  return `<option value="${value}" ${current === value ? 'selected' : ''}>${label}</option>`;
}

function labelField(name) {
  return {
    title: 'Titolo', location: 'Luogo', meeting_date: 'Data e ora', participants: 'Partecipanti',
    agenda: 'Ordine del giorno', description: 'Descrizione', name: 'Nome', email: 'Email',
    role: 'Ruolo', organization: 'Organizzazione', phone: 'Telefono', notes: 'Note',
    visibility: 'Visibilita',
  }[name] || name;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  }[char]));
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

    if (data.section === 'practices') {
      return `pratica: ${data.practice_id} | ${data.entity_type}: ${data.entity_id}`;
    }

    if (data.section === 'meetings') {
      return formatMeetingMetadata(data);
    }

  if (data.section === 'contacts') {
      return formatContactMetadata(data);
    }

    if (data.section === 'calls') {
      return formatCallMetadata(data);
    }

    return Object.entries(data)
      .filter(([key]) => key !== 'section')
      .map(([key, item]) => `${key}: ${Array.isArray(item) ? item.join(', ') : item}`)
      .join(' | ');
  } catch {
    return value;
  }
}

async function orphanLogIds(logs) {
  const missing = new Set();
  for (const log of logs) {
    const object = logObjectFromMetadata(parseMetadata(log.metadata_json));
    if (!object) continue;
    try {
      const data = await loadLogObject(object.type, object.id);
      if (!data) missing.add(Number(log.id));
    } catch {
      missing.add(Number(log.id));
    }
  }

  return missing;
}

function logObjectFromMetadata(data) {
  if (!data) return null;
  if (data.document_id) return { type: 'document', id: data.document_id };
  if (data.call_id) return { type: 'call', id: data.call_id };
  if (data.report_id) return { type: 'reports', id: data.report_id };
  if (data.comment_id) return { type: 'comments', id: data.comment_id };
  if (data.meeting_id) return { type: 'meeting', id: data.meeting_id };
  if (data.contact_id) return { type: 'contacts', id: data.contact_id };
  const userId = data.created_user_id || data.updated_user_id || data.target_user_id;
  if (userId) return { type: 'user', id: userId };
  return null;
}

function formatMeetingMetadata(data) {
  const parts = [];
  if (data.meeting_id) parts.push(`incontro: ${data.meeting_id}`);
  if (data.note_type) parts.push(`tipo nota: ${translateMeetingNoteType(data.note_type)}`);
  if (data.document_id) parts.push(`documento: ${data.document_id}`);
  if (data.protocol_number) parts.push(`protocollo: ${data.protocol_number}`);
  if (data.title) parts.push(`titolo: ${data.title}`);
  return parts.join(' | ');
}

function formatContactMetadata(data) {
  const parts = [];
  if (data.contact_id) parts.push(`contatto: ${data.contact_id}`);
  if (data.type) parts.push(`tipo: ${data.type}`);
  return parts.join(' | ');
}

function formatCallMetadata(data) {
  const parts = [];
  if (data.call_id) parts.push(`telefonata: ${data.call_id}`);
  if (data.practice_id) parts.push(`pratica: ${data.practice_id}`);
  return parts.join(' | ');
}

function translateMeetingNoteType(type) {
  return { content: 'contenuto', answer: 'risposta', idea: 'idea', proposal: 'proposta' }[type] || type;
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
    return { comments: 'commenti', reports: 'segnalazioni', documents: 'documenti', practices: 'pratiche', registry: 'anagrafica', meetings: 'incontri', contacts: 'anagrafica', calls: 'telefonate' }[section] || section;
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

deleteOrphanLogs.addEventListener('click', async () => {
  if (currentOrphanLogIds.length === 0) return;
  await Promise.all(currentOrphanLogIds.map((id) => api(`/activity/${id}`, { method: 'DELETE' })));
  await refreshActivityLogs();
});

activityTable.addEventListener('click', (event) => {
  const deleteButton = event.target.closest('[data-delete-log]');
  if (deleteButton) {
    api(`/activity/${deleteButton.dataset.deleteLog}`, { method: 'DELETE' })
      .then(refreshActivityLogs)
      .catch(showError);
    return;
  }

  const button = event.target.closest('[data-log-type]');
  if (!button) return;
  openLogObject(button.dataset.logType, button.dataset.logId, button.dataset.logTitle || 'Oggetto log');
});

async function refreshActivityLogs() {
  if (!currentActivityUserId) return;
  const logs = await api(`/users/${currentActivityUserId}/activity`);
  activityTable.innerHTML = await renderActivityRows(logs);
  activityPanel.classList.remove('hidden');
}

function updateOrphanButton() {
  if (!deleteOrphanLogs) return;
  deleteOrphanLogs.textContent = `Cancella orfani (${currentOrphanLogIds.length})`;
  deleteOrphanLogs.disabled = currentOrphanLogIds.length === 0;
}

closeLogObjectModal.addEventListener('click', () => {
  logObjectBody.innerHTML = '';
  logObjectModal.close();
});

logObjectBody.addEventListener('submit', async (event) => {
  const form = event.target.closest('[data-log-edit-type]');
  if (!form) return;
  event.preventDefault();
  const type = form.dataset.logEditType;
  const id = form.dataset.logEditId;
  const data = Object.fromEntries(new FormData(form).entries());

  try {
    await saveLogObject(type, id, data);
    const fresh = await loadLogObject(type, id);
    logObjectBody.innerHTML = renderLogObject(type, fresh);
  } catch (error) {
    message.textContent = error.message;
  }
});

async function saveLogObject(type, id, data) {
  if (type === 'document') return api(`/documents/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
  if (type === 'meeting') return api(`/union-meetings/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
  if (type === 'user') return api(`/users/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
  if (type === 'contacts') return api(`/institutional-contacts/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
  throw new Error('Modifica non disponibile.');
}

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
if (state.token) {
  api('/me').then(configureAccess).then(loadRoles).then(checkGdpr).then(loadUsers).catch((error) => {
    showError(error);
    if (error.status === 401) {
      sessionStorage.removeItem('token');
      state.token = null;
      window.location.href = 'app/index.html';
    }
  });
}
