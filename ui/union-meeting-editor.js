const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const meetingForm = document.querySelector('#meetingForm');
const pageTitle = document.querySelector('#pageTitle');
const saveButton = document.querySelector('#saveButton');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const attachmentsPanel = document.querySelector('#attachmentsPanel');
const attachmentForm = document.querySelector('#attachmentForm');
const existingDocumentForm = document.querySelector('#existingDocumentForm');
const existingDocumentSelect = document.querySelector('#existingDocumentSelect');
const attachmentsTable = document.querySelector('#attachmentsTable');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const participantSearch = document.querySelector('#participantSearch');
const participantsText = document.querySelector('#participantsText');
const participantTags = document.querySelector('#participantTags');
const participantResults = document.querySelector('#participantResults');
const contactModal = document.querySelector('#contactModal');
const contactForm = document.querySelector('#contactForm');
const closeContactModal = document.querySelector('#closeContactModal');
const meetingId = new URLSearchParams(window.location.search).get('id');
let contacts = [];
let existingDocuments = [];
let selectedParticipants = [];
let activeMeetingId = meetingId;
let documentPreviewUrl = null;

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

async function loadContacts() {
  const data = await api('/contacts');
  contacts = [...(data.users || []), ...(data.institutional || [])];
}

async function loadMeeting() {
  if (!meetingId) return;
  const meeting = await api(`/union-meetings/${meetingId}`);
  activeMeetingId = meeting.id;
  attachmentsPanel.classList.remove('hidden');
  pageTitle.textContent = 'Modifica incontro';
  saveButton.textContent = 'Salva modifica';
  meetingForm.elements.title.value = meeting.title || '';
  meetingForm.elements.meeting_date.value = String(meeting.meeting_date || '').replace(' ', 'T').slice(0, 16);
  meetingForm.elements.location.value = meeting.location || '';
  meetingForm.elements.status.value = meeting.status || 'scheduled';
  meetingForm.elements.visibility.value = meeting.visibility || 'rsu';
  meetingForm.elements.agenda.value = meeting.agenda || '';
  meetingForm.elements.description.value = meeting.description || '';
  selectedParticipants = (meeting.selected_participants || []).map((item) => ({
    type: item.participant_type,
    id: item.participant_id,
    label: item.label,
  }));
  if (selectedParticipants.length === 0 && meeting.participants) {
    selectedParticipants = String(meeting.participants).split('\n').filter(Boolean).map((label, index) => ({ type: 'free', id: `free-${index}`, label }));
  }
  renderParticipantTags();
  renderAttachments(meeting.documents || []);
  await loadExistingDocuments(meeting.documents || []);
}

meetingForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  saveButton.disabled = true;
  commitParticipantSearch();
  const form = new FormData(meetingForm);
  const data = Object.fromEntries(form.entries());
  data.participants = selectedParticipants.map((item) => item.label).join('\n');
  data.selected_participants = selectedParticipants
    .filter((item) => item.type !== 'free')
    .map((item) => ({ type: item.type, id: item.id, label: item.label }));
  try {
    const path = meetingId ? `/union-meetings/${meetingId}` : '/union-meetings';
    const saved = await api(path, { method: meetingId ? 'PATCH' : 'POST', body: JSON.stringify(data) });
    message.textContent = 'Incontro salvato.';
    window.setTimeout(() => { window.location.href = `union-meeting-editor.html?id=${saved.id}`; }, 500);
  } catch (error) {
    message.textContent = error.message;
  } finally {
    saveButton.disabled = false;
  }
});

attachmentForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!activeMeetingId) return;
  const body = new FormData(attachmentForm);
  try {
    const response = await fetch(`${apiBase}/union-meetings/${activeMeetingId}/documents`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body,
    });
    const payload = await response.json();
    jsonOutput.textContent = JSON.stringify(payload, null, 2);
    if (!response.ok) throw new Error(payload.error?.message || 'Upload fallito');
    attachmentForm.reset();
    renderAttachments(payload.data.documents || []);
    renderExistingDocumentOptions(payload.data.documents || []);
  } catch (error) {
    message.textContent = error.message;
  }
});

existingDocumentForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!activeMeetingId) return;
  const documentId = existingDocumentSelect.value;
  try {
    const data = await api(`/union-meetings/${activeMeetingId}/documents/link`, {
      method: 'POST',
      body: JSON.stringify({ document_id: documentId }),
    });
    renderAttachments(data.documents || []);
    renderExistingDocumentOptions(data.documents || []);
    message.textContent = 'Documento collegato.';
  } catch (error) {
    message.textContent = error.message;
  }
});

function renderAttachments(documents) {
  attachmentsTable.innerHTML = documents.length
    ? documents.map((document) => `<tr><td>${escapeHtml(document.original_name)}</td><td>${escapeHtml(document.conversion_status)}</td><td class="actions-cell"><button class="icon-action" data-view-document="${document.document_id}" title="Anteprima">${MyRsuIcons.get('eye')}</button><button class="icon-action danger" data-remove-document="${document.document_id}" title="Rimuovi">${MyRsuIcons.get('trash')}</button></td></tr>`).join('')
    : '<tr><td colspan="3">Nessun allegato.</td></tr>';
}

attachmentsTable.addEventListener('click', async (event) => {
  const view = event.target.closest('[data-view-document]');
  if (view) {
    openDocument(view.dataset.viewDocument);
    return;
  }
  const remove = event.target.closest('[data-remove-document]');
  if (!remove || !activeMeetingId) return;
  const data = await api(`/union-meetings/${activeMeetingId}/documents/${remove.dataset.removeDocument}`, { method: 'DELETE' });
  renderAttachments(data.documents || []);
  renderExistingDocumentOptions(data.documents || []);
});

async function loadExistingDocuments(linkedDocuments) {
  const data = await api('/documents');
  existingDocuments = (data || []).filter((document) => document.category === 'documenti');
  renderExistingDocumentOptions(linkedDocuments);
}

function renderExistingDocumentOptions(linkedDocuments) {
  const linkedIds = new Set((linkedDocuments || []).map((document) => Number(document.document_id)));
  const available = existingDocuments.filter((document) => !linkedIds.has(Number(document.id)));
  existingDocumentSelect.innerHTML = available.length
    ? available.map((document) => `<option value="${document.id}">${escapeHtml(document.original_name)} - ${escapeHtml(document.conversion_status)}</option>`).join('')
    : '<option value="">Nessun documento disponibile</option>';
  existingDocumentSelect.disabled = available.length === 0;
}

function openDocument(documentId) {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreview.src = `${apiBase}/documents/${documentId}/preview?token=${encodeURIComponent(token || '')}`;
  documentModal.showModal();
}

closeDocumentModal.addEventListener('click', () => {
  documentPreview.src = '';
  documentModal.close();
});

participantSearch.addEventListener('input', () => showContactResults(participantSearch.value));
participantSearch.addEventListener('focus', () => showContactResults(participantSearch.value));
participantSearch.addEventListener('click', () => showContactResults(participantSearch.value));
participantSearch.addEventListener('keydown', (event) => {
  if (event.key !== 'Enter') return;
  event.preventDefault();
  commitParticipantSearch();
});

document.addEventListener('click', (event) => {
  if (!event.target.closest('.contact-picker')) participantResults.classList.add('hidden');
});

participantResults.addEventListener('click', (event) => {
  const item = event.target.closest('[data-contact-index]');
  const create = event.target.closest('[data-create-contact]');
  if (item) addParticipant(contacts[Number(item.dataset.contactIndex)]);
  if (create) openContactModal(participantSearch.value);
});

function showContactResults(query) {
  const value = query.trim().toLowerCase();
  const rows = contacts.map((contact, index) => ({ contact, index }))
    .filter((item) => item.contact.label.toLowerCase().includes(value))
    .slice(0, 8);
  participantResults.innerHTML = rows.map((item) => `<button class="${item.contact.type === 'user' ? 'contact-internal' : 'contact-institutional'}" type="button" data-contact-index="${item.index}">${escapeHtml(item.contact.label)} <small>${escapeHtml(item.contact.organization || item.contact.role || item.contact.type)}</small></button>`).join('')
    + (value ? `<button type="button" data-create-contact="1">+ crea "${escapeHtml(query)}"</button>` : '');
  participantResults.classList.remove('hidden');
}

function addParticipant(contact) {
  if (!contact) return;
  if (!selectedParticipants.some((item) => item.type === contact.type && item.id === contact.id)) selectedParticipants.push(contact);
  participantSearch.value = '';
  renderParticipantTags();
  participantSearch.focus();
  showContactResults('');
}

function commitParticipantSearch() {
  const value = participantSearch.value.trim();
  if (value === '') return;
  const match = contacts.find((contact) => contact.label.toLowerCase() === value.toLowerCase());
  addParticipant(match || { type: 'free', id: Date.now(), label: value });
}

function renderParticipantTags() {
  participantTags.innerHTML = selectedParticipants.map((item, index) => `<span class="tag">${escapeHtml(item.label)} <button type="button" data-remove-participant="${index}">x</button></span>`).join('');
  participantsText.value = selectedParticipants.map((item) => item.label).join('\n');
}

participantTags.addEventListener('click', (event) => {
  const button = event.target.closest('[data-remove-participant]');
  if (!button) return;
  selectedParticipants.splice(Number(button.dataset.removeParticipant), 1);
  renderParticipantTags();
});

function openContactModal(name) {
  contactForm.reset();
  contactForm.elements.name.value = name.trim();
  contactModal.showModal();
}

contactForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(contactForm);
  const data = Object.fromEntries(form.entries());
  try {
    const contact = data.source === 'user'
      ? await api('/users', { method: 'POST', body: JSON.stringify({
        name: data.name, email: data.email, password: data.password, role: data.user_role,
        first_name: '', last_name: '', phone: data.phone, mobile: '', city: '', country: '',
      }) })
      : await api('/institutional-contacts', { method: 'POST', body: JSON.stringify(data) });
    await loadContacts();
    addParticipant(data.source === 'user'
      ? { type: 'user', id: contact.id, label: contact.name, organization: 'MyRSU', role: data.user_role }
      : contact);
    contactModal.close();
  } catch (error) {
    message.textContent = error.message;
  }
});

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

closeContactModal.addEventListener('click', () => contactModal.close());
if (!token) window.location.href = 'app/index.html';
loadContacts().then(loadMeeting).catch((error) => { message.textContent = error.message; });
