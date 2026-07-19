const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const assemblyId = new URLSearchParams(window.location.search).get('id');
const title = document.querySelector('#assemblyTitle');
const meta = document.querySelector('#assemblyMeta');
const sessionsList = document.querySelector('#sessionsList');
const sessionSelect = document.querySelector('#sessionSelect');
const noteForm = document.querySelector('#noteForm');
const finalStatementForm = document.querySelector('#finalStatementForm');
const notesList = document.querySelector('#notesList');
const attachmentsList = document.querySelector('#attachmentsList');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
let currentAssembly = null;

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

async function loadAssembly() {
  const assembly = await api(`/workers-assemblies/${assemblyId}`);
  currentAssembly = assembly;
  title.textContent = assembly.title;
  meta.textContent = `${translateStatus(assembly.status)} - ${translateVisibility(assembly.visibility)}`;
  renderSessions(assembly.sessions || []);
  renderSessionSelect(assembly.sessions || []);
  renderNotes(assembly.notes || []);
  finalStatementForm.final_statement.value = assembly.final_statement || '';
  renderAttachments(assembly.documents || []);
}

function renderSessions(sessions) {
  sessionsList.innerHTML = sessions.length
    ? sessions.map((session) => `<article class="assembly-session-card" data-session-card="${session.id}">
      <strong>${escapeHtml(session.shift_label)}</strong>
      <span>${escapeHtml(session.assembly_date)}</span>
      <span>${escapeHtml(String(session.time_start).slice(0, 5))} - ${escapeHtml(String(session.time_end || '').slice(0, 5) || '-')}</span>
      <small>${escapeHtml(session.place || '-')}</small>
      <div>${voteSummary(session.voting)}</div>
      ${voteAction(session)}
    </article>`).join('')
    : '<p class="muted">Nessun turno.</p>';
  syncSelectedSession();
}

function voteAction(session) {
  if (isVotingLocked(session.voting)) {
    return '<span class="secondary-link disabled-link">Scrutinio registrato</span>';
  }
  return `<a class="secondary-link" href="votings.html?assembly_id=${assemblyId}&session_id=${session.id}">Votazione turno</a>`;
}

function isVotingLocked(voting) {
  if (!voting) return false;
  return ['closed', 'cancelled'].includes(String(voting.status || ''));
}

function voteSummary(voting) {
  if (!voting) return '<small>Nessuna votazione turno.</small>';
  const results = (voting.results || []).map((item) => `${escapeHtml(item.label)}: ${item.votes}`).join(' · ');
  return `<small>${escapeHtml(voting.title)}<br>${results || 'Nessun voto'}</small>`;
}

function renderSessionSelect(sessions) {
  sessionSelect.innerHTML = sessions.map((session) => `<option value="${session.id}">${escapeHtml(session.shift_label)} - ${escapeHtml(session.assembly_date)} ${escapeHtml(String(session.time_start).slice(0, 5))}</option>`).join('');
  sessionSelect.disabled = sessions.length === 0;
}

function renderNotes(notes) {
  const sessionId = sessionSelect.value;
  const rows = (notes || []).filter((note) => String(note.session_id) === String(sessionId));
  notesList.innerHTML = rows.length
    ? rows.map((note) => `<article class="meeting-note"><strong>${label(note.note_type)}</strong><p>${escapeHtml(note.body)}</p><small>${escapeHtml(note.author_name)} - ${escapeHtml(note.created_at)}</small></article>`).join('')
    : '<p class="muted">Nessun contenuto per questo turno.</p>';
  syncSelectedSession();
}

function renderAttachments(documents) {
  attachmentsList.innerHTML = documents.length
    ? documents.map((document) => `<article class="meeting-note"><strong>${escapeHtml(document.original_name)}</strong><p>${escapeHtml(document.conversion_status)}</p><button class="icon-action" data-view-document="${document.document_id}" title="Anteprima">${MyRsuIcons.get('eye')}</button></article>`).join('')
    : '<p class="muted">Nessun allegato.</p>';
}

attachmentsList.addEventListener('click', (event) => {
  const view = event.target.closest('[data-view-document]');
  if (!view) return;
  documentPreview.src = `${apiBase}/documents/${view.dataset.viewDocument}/preview?token=${encodeURIComponent(token || '')}`;
  documentModal.showModal();
});

closeDocumentModal.addEventListener('click', () => {
  documentPreview.src = '';
  documentModal.close();
});

sessionSelect.addEventListener('change', () => {
  renderNotes(currentAssembly?.notes || []);
});

sessionsList.addEventListener('click', (event) => {
  const card = event.target.closest('[data-session-card]');
  if (!card) return;
  sessionSelect.value = card.dataset.sessionCard;
  renderNotes(currentAssembly?.notes || []);
});

function syncSelectedSession() {
  sessionsList.querySelectorAll('[data-session-card]').forEach((card) => {
    card.classList.toggle('active', String(card.dataset.sessionCard) === String(sessionSelect.value));
  });
}

noteForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(noteForm).entries());
  try {
    await api(`/workers-assemblies/${assemblyId}/sessions/${data.session_id}/notes`, {
      method: 'POST',
      body: JSON.stringify({ note_type: data.note_type, body: data.body }),
    });
    noteForm.reset();
    sessionSelect.value = data.session_id;
    await loadAssembly();
    sessionSelect.value = data.session_id;
    renderNotes(currentAssembly?.notes || []);
  } catch (error) {
    message.textContent = error.message;
  }
});

finalStatementForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    await api(`/workers-assemblies/${assemblyId}/final-statement`, {
      method: 'PATCH',
      body: JSON.stringify(Object.fromEntries(new FormData(finalStatementForm).entries())),
    });
    message.textContent = 'Chiosa finale salvata.';
    await loadAssembly();
  } catch (error) {
    message.textContent = error.message;
  }
});

function label(value) {
  return { discussion: 'Discussione', question: 'Domanda/intervento', answer: 'Risposta', proposal: 'Proposta', decision: 'Decisione/mandato', note: 'Nota' }[value] || value;
}

function translateStatus(value) {
  return { draft: 'bozza', called: 'convocata', done: 'svolta', cancelled: 'annullata' }[value] || value || '-';
}

function translateVisibility(value) {
  return { public: 'pubblico', members: 'membri', rsu: 'rsu' }[value] || value || '-';
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

if (!token) window.location.href = 'app/index.html';
loadAssembly().catch((error) => { message.textContent = error.message; });
