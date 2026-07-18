const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const meetingsTable = document.querySelector('#meetingsTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const practiceLinkModal = document.querySelector('#practiceLinkModal');
const practiceLinkForm = document.querySelector('#practiceLinkForm');
const closePracticeLinkModal = document.querySelector('#closePracticeLinkModal');
const convocationModal = document.querySelector('#convocationModal');
const convocationPreview = document.querySelector('#convocationPreview');
const closeConvocationModal = document.querySelector('#closeConvocationModal');
let practices = [];

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

async function loadMeetings() {
  const [meetings, practiceRows] = await Promise.all([api('/union-meetings'), loadPractices()]);
  practices = practiceRows;
  meetingsTable.innerHTML = meetings.map(row).join('');
}

async function loadPractices() {
  try { return await api('/practices'); } catch { return []; }
}

function row(meeting) {
  const documentStatus = meeting.public_document_id ? `doc ${meeting.public_document_id}` : '-';
  const convocation = meeting.public_document_id
    ? `<button class="icon-action" data-view-convocation="${meeting.public_document_id}" title="Visualizza convocazione">${MyRsuIcons.get('eye')}</button>`
    : `<button class="icon-action" disabled title="Convocazione non generata">${MyRsuIcons.get('eye')}</button>`;
  return `<tr><td>${escapeHtml(meeting.title)}</td><td>${escapeHtml(meeting.meeting_date)}</td><td>${escapeHtml(meeting.location)}</td><td>${translateStatus(meeting.status)}</td><td>${translateVisibility(meeting.visibility)}</td><td>${documentStatus}</td><td class="actions-cell">${convocation}<a class="icon-action" href="union-meeting-editor.html?id=${meeting.id}" title="Modifica incontro">${MyRsuIcons.get('edit')}</a><button class="icon-action" data-public-comunicato="${meeting.id}" title="Comunicato pubblico">${MyRsuIcons.get('document')}</button><a class="icon-action" href="union-meeting-operational.html?id=${meeting.id}" title="Pagina operativa">${MyRsuIcons.get('logs')}</a><button class="icon-action" data-practice-link="${meeting.id}" title="Collega a pratica">${MyRsuIcons.get('link')}</button></td></tr>`;
}

function translateStatus(value) {
  return { scheduled: 'programmato', done: 'svolto', cancelled: 'annullato' }[value] || value || '-';
}

function translateVisibility(value) {
  return { public: 'pubblico', members: 'membri', rsu: 'rsu' }[value] || value || '-';
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

meetingsTable.addEventListener('click', (event) => {
  const convocationButton = event.target.closest('[data-view-convocation]');
  if (convocationButton) {
    openConvocation(convocationButton.dataset.viewConvocation);
    return;
  }

  const comunicatoButton = event.target.closest('[data-public-comunicato]');
  if (comunicatoButton) {
    createPublicComunicato(comunicatoButton.dataset.publicComunicato);
    return;
  }

  const button = event.target.closest('[data-practice-link]');
  if (!button) return;
  const select = practiceLinkForm.elements.practice_id;
  practiceLinkForm.elements.meeting_id.value = button.dataset.practiceLink;
  select.innerHTML = practices.length
    ? practices.map((item) => `<option value="${item.id}">${escapeHtml(item.title)}</option>`).join('')
    : '<option value="">Nessuna pratica disponibile</option>';
  practiceLinkModal.showModal();
});

async function createPublicComunicato(id) {
  try {
    const data = await api(`/union-meetings/${id}/public-comunicato`, { method: 'POST', body: '{}' });
    const documentId = data.document?.id || data.meeting?.public_document_id;
    message.textContent = 'Convocazione generata correttamente.';
    await loadMeetings();
    if (documentId) openConvocation(documentId);
  } catch (error) {
    message.textContent = error.message;
  }
}

practiceLinkForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(practiceLinkForm);
  try {
    await api('/practice-links', {
      method: 'POST',
      body: JSON.stringify({ practice_id: form.get('practice_id'), entity_type: 'meeting', entity_id: form.get('meeting_id') }),
    });
    practiceLinkModal.close();
  } catch (error) {
    message.textContent = error.message;
  }
});

function openConvocation(documentId) {
  convocationPreview.src = `${apiBase}/documents/${documentId}/preview?token=${encodeURIComponent(token || '')}`;
  convocationModal.classList.remove('hidden');
}

closeConvocationModal.addEventListener('click', closeConvocation);
convocationModal.addEventListener('click', (event) => {
  if (event.target === convocationModal) closeConvocation();
});

function closeConvocation() {
  convocationPreview.src = '';
  convocationModal.classList.add('hidden');
}

closePracticeLinkModal.addEventListener('click', () => practiceLinkModal.close());
if (!token) window.location.href = 'app/index.html';
loadMeetings().catch((error) => { message.textContent = error.message; });
