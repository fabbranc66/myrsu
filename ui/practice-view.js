const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const practiceId = Number(new URLSearchParams(window.location.search).get('id'));
const form = document.querySelector('#practiceEditForm');
const noteForm = document.querySelector('#practiceNoteForm');
const timeline = document.querySelector('#practiceTimeline');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
let assignees = [];

if (!token || !practiceId) window.location.href = 'practices.html';

async function api(path, options = {}) {
  const headers = { ...(options.headers || {}), Authorization: `Bearer ${token}` };
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Operazione fallita');
  return payload.data;
}

async function load() {
  const [data, operatorRows] = await Promise.all([api(`/practices/${practiceId}`), api('/practices/assignees')]);
  assignees = operatorRows;
  renderPractice(data.practice);
  renderTimeline(data.timeline);
}

function renderPractice(practice) {
  document.querySelector('#practiceHeading').textContent = `${practice.code} - ${practice.title}`;
  document.querySelector('#practiceSummary').textContent = practice.summary || 'Nessuna sintesi.';
  document.querySelector('#practiceMeta').innerHTML = meta('Stato', label('statuses', practice.status)) + meta('Priorità', label('priorities', practice.priority)) + meta('Assegnata a', practice.assigned_user_name || '-') + meta('Origine', label('sources', practice.source_type)) + meta('Scadenza', practice.due_date || '-') + meta('Ultima modifica', practice.updated_at);
  form.title.value = practice.title;
  form.summary.value = practice.summary || '';
  form.type.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.types, practice.type);
  form.status.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.statuses, practice.status);
  form.priority.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.priorities, practice.priority);
  form.source_type.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.sources, practice.source_type);
  form.visibility.innerHTML = MyRsuPracticeOptions.options(MyRsuPracticeOptions.visibilities, practice.visibility);
  form.assigned_user_id.innerHTML = '<option value="">-</option>' + assignees.map((user) => `<option value="${user.id}"${Number(user.id) === Number(practice.assigned_user_id) ? ' selected' : ''}>${escapeHtml(user.name)}</option>`).join('');
  form.due_date.value = practice.due_date || '';
}

function renderTimeline(items) {
  timeline.innerHTML = items.length ? items.map(timelineItem).join('') : '<p class="muted">Nessun elemento collegato.</p>';
}

function timelineItem(item) {
  const link = itemLink(item);
  return `<article class="timeline-item"><header><span class="timeline-type">${typeLabel(item.type)}</span><time>${escapeHtml(item.event_at)}</time></header><strong>${escapeHtml(item.title)}</strong><p>${escapeHtml(item.summary)}</p>${item.status ? `<small>${escapeHtml(item.status)}</small>` : ''}${link ? `<div><a href="${link}">Apri</a></div>` : ''}</article>`;
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(form).entries());
  await api(`/practices/${practiceId}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
  message.textContent = 'Pratica aggiornata';
  await load();
});

noteForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(noteForm).entries());
  await api(`/practices/${practiceId}/notes`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
  noteForm.reset();
  message.textContent = 'Nota inserita';
  await load();
});

function meta(name, value) { return `<div><span class="muted">${name}</span><strong>${escapeHtml(value)}</strong></div>`; }
function label(group, value) { return MyRsuPracticeOptions.label(MyRsuPracticeOptions[group], value); }
function typeLabel(type) { return { document: 'documento', report: 'segnalazione', comment: 'commento', protocol: 'protocollo', attachment: 'allegato', meeting: 'incontro', call: 'telefonata', note: 'nota operativa' }[type] || type; }
function itemLink(item) { if (item.type === 'protocol') return `protocol-view.html?id=${item.id}`; if (item.document_id) return `document-view.html?id=${item.document_id}`; if (item.type === 'meeting') return 'union-meetings.html'; if (item.type === 'call') return 'calls.html'; if (item.type === 'report') return 'reports-moderation.html'; if (item.type === 'comment') return 'comments-moderation.html'; return ''; }
function escapeHtml(value) { return String(value || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }

load().catch((error) => { message.textContent = error.message; });
