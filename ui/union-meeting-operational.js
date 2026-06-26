const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const params = new URLSearchParams(window.location.search);
const meetingId = params.get('id');
const title = document.querySelector('#meetingTitle');
const meta = document.querySelector('#meetingMeta');
const noteForm = document.querySelector('#noteForm');
const notesList = document.querySelector('#notesList');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');

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

async function loadMeeting() {
  const meeting = await api(`/union-meetings/${meetingId}`);
  title.textContent = meeting.title;
  meta.textContent = `${meeting.location} - ${meeting.meeting_date}`;
  renderNotes(meeting.notes || []);
}

function renderNotes(notes) {
  notesList.innerHTML = notes.map((note) => `<article class="meeting-note"><strong>${label(note.note_type)}</strong><p>${escapeHtml(note.body)}</p><small>${escapeHtml(note.author_name)} - ${escapeHtml(note.created_at)}</small></article>`).join('');
}

function label(value) {
  return { content: 'Contenuto', answer: 'Risposta', idea: 'Idea', proposal: 'Proposta' }[value] || value;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

noteForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(noteForm);
  try {
    await api(`/union-meetings/${meetingId}/notes`, {
      method: 'POST',
      body: JSON.stringify(Object.fromEntries(form.entries())),
    });
    noteForm.reset();
    await loadMeeting();
  } catch (error) {
    message.textContent = error.message;
  }
});

loadMeeting().catch((error) => { message.textContent = error.message; });
