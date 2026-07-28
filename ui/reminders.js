const apiBase = '../api/v1';
const token = sessionStorage.getItem('token') || localStorage.getItem('token');
const form = document.querySelector('#reminderForm');
const table = document.querySelector('#remindersTable');
const statusFilter = document.querySelector('#statusFilter');
const typeFilter = document.querySelector('#typeFilter');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');

if (!token) window.location.href = 'login.html';

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}), Authorization: `Bearer ${token}` };
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Operazione fallita');
  return payload.data;
}

async function load() {
  const query = new URLSearchParams();
  if (statusFilter.value) query.set('status', statusFilter.value);
  if (typeFilter.value) query.set('entity_type', typeFilter.value);
  const rows = await api(`/reminders${query.toString() ? `?${query}` : ''}`);
  table.innerHTML = rows.map(row).join('') || '<tr><td colspan="5">Nessun reminder.</td></tr>';
}

function row(reminder) {
  const overdue = reminder.status === 'pending' && new Date(reminder.due_at.replace(' ', 'T')) < new Date();
  return `<tr class="${overdue ? 'reminder-overdue' : ''}">
    <td data-label="Scadenza">${escapeHtml(reminder.due_at)}</td>
    <td data-label="Tipo">${typeLabel(reminder.entity_type)} #${escapeHtml(reminder.entity_id)}</td>
    <td data-label="Titolo"><span class="truncate-title" title="${escapeHtml(reminder.title)}">${escapeHtml(reminder.title)}</span></td>
    <td data-label="Stato">${statusLabel(reminder.status)}${overdue ? ' - scaduto' : ''}</td>
    <td data-label="Azioni" class="actions-cell">
      <button class="icon-action" data-edit="${reminder.id}" title="Modifica">${MyRsuIcons.get('edit')}</button>
      ${reminder.status === 'pending' ? `<button class="icon-action" data-done="${reminder.id}" title="Completa">${MyRsuIcons.get('active')}</button>` : ''}
      <button class="icon-action danger" data-delete="${reminder.id}" title="Cancella">${MyRsuIcons.get('trash')}</button>
    </td>
  </tr>`;
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(form).entries());
  const id = data.id;
  delete data.id;
  await api(id ? `/reminders/${id}` : '/reminders', { method: id ? 'PATCH' : 'POST', body: JSON.stringify(data) });
  form.reset();
  message.textContent = 'Reminder salvato';
  await load();
});

table.addEventListener('click', async (event) => {
  const edit = event.target.closest('[data-edit]');
  const done = event.target.closest('[data-done]');
  const remove = event.target.closest('[data-delete]');
  if (edit) return fillEdit(edit.dataset.edit);
  if (done) {
    await api(`/reminders/${done.dataset.done}/done`, { method: 'POST', body: '{}' });
    return load();
  }
  if (!remove || !confirm('Cancellare reminder?')) return;
  await api(`/reminders/${remove.dataset.delete}`, { method: 'DELETE' });
  message.textContent = 'Reminder cancellato';
  await load();
});

async function fillEdit(id) {
  const rows = await api('/reminders');
  const reminder = rows.find((item) => Number(item.id) === Number(id));
  if (!reminder) return;
  Object.keys(reminder).forEach((key) => {
    if (form[key]) form[key].value = key === 'due_at' ? reminder[key].replace(' ', 'T').slice(0, 16) : (reminder[key] || '');
  });
}

document.querySelector('#resetReminder').addEventListener('click', () => form.reset());
document.querySelector('#filterForm').addEventListener('submit', (event) => {
  event.preventDefault();
  load();
});
statusFilter.addEventListener('change', load);
typeFilter.addEventListener('change', load);

function typeLabel(value) {
  return ({ call: 'telefonata', email: 'e-mail' }[value] || value);
}

function statusLabel(value) {
  return ({ pending: 'pendente', done: 'completato', cancelled: 'annullato' }[value] || value);
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

load().catch((error) => { message.textContent = error.message; });
