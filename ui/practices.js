const apiBase = '../api/v1';
const token = sessionStorage.getItem('token') || localStorage.getItem('token');
const form = document.querySelector('#practiceCreateForm');
const table = document.querySelector('#practicesTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const linkModal = document.querySelector('#practiceDocumentLinkModal');
const linkForm = document.querySelector('#practiceDocumentLinkForm');
const unlinkModal = document.querySelector('#practiceDocumentUnlinkModal');
const unlinkForm = document.querySelector('#practiceDocumentUnlinkForm');
let documents = [];

if (!token) window.location.href = 'app/index.html';

async function api(path, options = {}) {
  const headers = { ...(options.headers || {}), Authorization: `Bearer ${token}` };
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Operazione fallita');
  return payload.data;
}

async function boot() {
  const [practices, assignees, documentRows] = await Promise.all([api('/practices?scope=all'), api('/practices/assignees'), api('/documents')]);
  documents = documentRows;
  fillForm(assignees);
  render(practices);
}

function fillForm(assignees) {
  form.type.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.types, 'collective');
  form.status.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.statuses, 'new');
  form.priority.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.priorities, 'medium');
  form.source_type.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.sources, 'manual');
  form.visibility.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.visibilities, 'operators');
  form.assigned_user_id.innerHTML = '<option value="">-</option>' + assignees.map((user) => `<option value="${user.id}">${escapeHtml(user.name)}</option>`).join('');
}

function render(practices) {
  table.innerHTML = practices.length ? practices.map(row).join('') : '<tr><td colspan="8">Nessuna pratica.</td></tr>';
}

function row(practice) {
  const progress = MyRsuPracticeOptions.progress(practice.status);
  return `<tr>
    <td data-label="Tipo"><span class="practice-type">${MyRsuPracticeOptions.label(MyRsuPracticeOptions.types, practice.type)}</span></td>
    <td data-label="Codice">${escapeHtml(practice.code)}</td>
    <td data-label="Titolo" title="${escapeHtml(practice.title)}"><span class="truncate-title">${escapeHtml(practice.title)}</span></td>
    <td data-label="Avanzamento"><div class="practice-progress-label">${MyRsuPracticeOptions.label(MyRsuPracticeOptions.statuses, practice.status)} - ${progress}%</div><div class="practice-progress"><span style="width:${progress}%"></span></div></td>
    <td data-label="Ultimo">${escapeHtml(practice.last_activity_at)}</td>
    <td data-label="Priorita">${MyRsuPracticeOptions.label(MyRsuPracticeOptions.priorities, practice.priority)}</td>
    <td data-label="Assegnata">${escapeHtml(practice.assigned_user_name || '-')}</td>
    <td data-label="Azioni" class="actions-cell">
      <a class="icon-action" href="practice-view.html?id=${practice.id}" title="Apri pratica">${MyRsuIcons.get('eye')}</a>
      <a class="icon-action" href="practice-view.html?id=${practice.id}#edit" title="Modifica pratica">${MyRsuIcons.get('edit')}</a>
      <button class="icon-action" data-link-document="${practice.id}" title="Collega documento">${MyRsuIcons.get('link')}</button>
      <button class="icon-action danger" data-unlink-document="${practice.id}" title="Scollega documento">${MyRsuIcons.get('link')}</button>
    </td>
  </tr>`;
}

table.addEventListener('click', (event) => {
  const linkButton = event.target.closest('[data-link-document]');
  const unlinkButton = event.target.closest('[data-unlink-document]');
  if (linkButton) openDocumentLink(linkButton.dataset.linkDocument);
  if (unlinkButton) openDocumentUnlink(unlinkButton.dataset.unlinkDocument);
});

function openDocumentLink(practiceId) {
  const rows = documents.filter((document) => !practiceIds(document).includes(Number(practiceId)));
  linkForm.practice_id.value = practiceId;
  linkForm.document_id.innerHTML = documentOptions(rows);
  linkForm.querySelector('button').disabled = rows.length === 0;
  linkModal.showModal();
}

function openDocumentUnlink(practiceId) {
  const rows = documents.filter((document) => practiceIds(document).includes(Number(practiceId)));
  unlinkForm.practice_id.value = practiceId;
  unlinkForm.document_id.innerHTML = documentOptions(rows);
  unlinkForm.querySelector('button').disabled = rows.length === 0;
  unlinkModal.showModal();
}

function documentOptions(rows) {
  return rows.length
    ? rows.map((document) => `<option value="${document.id}">${escapeHtml(document.original_name)}</option>`).join('')
    : '<option value="">Nessun documento disponibile</option>';
}

function practiceIds(document) {
  return String(document.practice_ids || '').split(',').map((id) => Number(id)).filter(Boolean);
}

linkForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(linkForm).entries());
  await api('/practice-links', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ practice_id: Number(data.practice_id), entity_type: 'document', entity_id: Number(data.document_id) }),
  });
  message.textContent = 'Documento collegato alla pratica';
  linkModal.close();
  await boot();
});

unlinkForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(unlinkForm).entries());
  await api('/practice-links', {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ practice_id: Number(data.practice_id), entity_type: 'document', entity_id: Number(data.document_id) }),
  });
  message.textContent = 'Documento scollegato dalla pratica';
  unlinkModal.close();
  await boot();
});

document.querySelector('#closePracticeDocumentLinkModal').addEventListener('click', () => linkModal.close());
document.querySelector('#closePracticeDocumentUnlinkModal').addEventListener('click', () => unlinkModal.close());

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(form).entries());
  const result = await api('/practices', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
  window.location.href = `practice-view.html?id=${result.practice.id}`;
});

function escapeHtml(value) {
  return String(value || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

boot().catch((error) => { message.textContent = error.message; });

