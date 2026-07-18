const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const form = document.querySelector('#practiceCreateForm');
const table = document.querySelector('#practicesTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');

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
  const [practices, assignees] = await Promise.all([api('/practices?scope=all'), api('/practices/assignees')]);
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
    <td><span class="practice-type">${MyRsuPracticeOptions.label(MyRsuPracticeOptions.types, practice.type)}</span></td>
    <td>${escapeHtml(practice.code)}</td>
    <td>${escapeHtml(practice.title)}</td>
    <td><div class="practice-progress-label">${MyRsuPracticeOptions.label(MyRsuPracticeOptions.statuses, practice.status)} · ${progress}%</div><div class="practice-progress"><span style="width:${progress}%"></span></div></td>
    <td>${escapeHtml(practice.last_activity_at)}</td>
    <td>${MyRsuPracticeOptions.label(MyRsuPracticeOptions.priorities, practice.priority)}</td>
    <td>${escapeHtml(practice.assigned_user_name || '-')}</td>
    <td><a class="button small" href="practice-view.html?id=${practice.id}">Apri</a></td>
  </tr>`;
}

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
