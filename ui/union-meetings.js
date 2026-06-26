const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const meetingForm = document.querySelector('#meetingForm');
const meetingsTable = document.querySelector('#meetingsTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const practiceLinkModal = document.querySelector('#practiceLinkModal');
const practiceLinkForm = document.querySelector('#practiceLinkForm');
const closePracticeLinkModal = document.querySelector('#closePracticeLinkModal');
const participantSearch = document.querySelector('#participantSearch');
const participantsText = document.querySelector('#participantsText');
const participantTags = document.querySelector('#participantTags');
const participantResults = document.querySelector('#participantResults');
const meetingEditModal = document.querySelector('#meetingEditModal');
const meetingEditForm = document.querySelector('#meetingEditForm');
const closeMeetingEditModal = document.querySelector('#closeMeetingEditModal');
const editParticipantSearch = document.querySelector('#editParticipantSearch');
const editParticipantsText = document.querySelector('#editParticipantsText');
const editParticipantTags = document.querySelector('#editParticipantTags');
const editParticipantResults = document.querySelector('#editParticipantResults');
const contactModal = document.querySelector('#contactModal');
const contactForm = document.querySelector('#contactForm');
const closeContactModal = document.querySelector('#closeContactModal');
const convocationModal = document.querySelector('#convocationModal');
const convocationPreview = document.querySelector('#convocationPreview');
const closeConvocationModal = document.querySelector('#closeConvocationModal');
let practices = [];
let contacts = [];
let selectedParticipants = [];
let selectedEditParticipants = [];
let activePicker = 'create';

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
  const [meetings, practiceRows] = await Promise.all([api('/union-meetings'), loadPractices(), loadContacts()]);
  practices = practiceRows;
  meetingsTable.innerHTML = meetings.map(row).join('');
}

async function loadPractices() {
  try { return await api('/practices'); } catch { return []; }
}

async function loadContacts() {
  try {
    const data = await api('/contacts');
    contacts = [...(data.users || []), ...(data.institutional || [])];
  } catch {
    contacts = [];
  }
}

function row(meeting) {
  const documentStatus = meeting.public_document_id ? `doc ${meeting.public_document_id}` : '-';
  const convocation = meeting.public_document_id
    ? `<button class="icon-action" data-view-convocation="${meeting.public_document_id}" title="Visualizza convocazione">${MyRsuIcons.get('eye')}</button>`
    : `<button class="icon-action" disabled title="Convocazione non generata">${MyRsuIcons.get('eye')}</button>`;
  return `<tr><td>${escapeHtml(meeting.title)}</td><td>${escapeHtml(meeting.meeting_date)}</td><td>${escapeHtml(meeting.location)}</td><td>${translateStatus(meeting.status)}</td><td>${translateVisibility(meeting.visibility)}</td><td>${documentStatus}</td><td class="actions-cell">${convocation}<button class="icon-action" data-edit-meeting="${meeting.id}" title="Modifica incontro">${MyRsuIcons.get('edit')}</button><button class="icon-action" data-public-comunicato="${meeting.id}" title="Comunicato pubblico">${MyRsuIcons.get('document')}</button><a class="icon-action" href="union-meeting-operational.html?id=${meeting.id}" title="Pagina operativa">${MyRsuIcons.get('logs')}</a><button class="icon-action" data-practice-link="${meeting.id}" title="Collega a pratica">${MyRsuIcons.get('link')}</button></td></tr>`;
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

meetingForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  commitParticipantSearch();
  const form = new FormData(meetingForm);
  const data = Object.fromEntries(form.entries());
  data.participants = selectedParticipants.map((item) => item.label).join('\n');
  data.selected_participants = selectedParticipants
    .filter((item) => item.type !== 'free')
    .map((item) => ({
      type: item.type,
      id: item.id,
      label: item.label,
    }));
  try {
    await api('/union-meetings', { method: 'POST', body: JSON.stringify(data) });
    meetingForm.reset();
    selectedParticipants = [];
    renderParticipantTags();
    await loadMeetings();
  } catch (error) {
    message.textContent = error.message;
  }
});

bindPickerEvents(participantSearch, participantResults, 'create');
bindPickerEvents(editParticipantSearch, editParticipantResults, 'edit');

document.addEventListener('click', (event) => {
  if (event.target.closest('.contact-picker')) return;
  participantResults.classList.add('hidden');
  editParticipantResults.classList.add('hidden');
});

bindPickerResults(participantResults, 'create');
bindPickerResults(editParticipantResults, 'edit');

function bindPickerEvents(input, results, mode) {
  input.addEventListener('input', () => showContactResults(input.value, results));
  input.addEventListener('focus', () => showContactResults(input.value, results));
  input.addEventListener('click', () => showContactResults(input.value, results));
  input.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    if (input.value.trim() === '') return;
    commitParticipantSearch(mode);
  });
}

function bindPickerResults(results, mode) {
  results.addEventListener('click', (event) => {
    const item = event.target.closest('[data-contact-index]');
    const create = event.target.closest('[data-create-contact]');
    if (item) addParticipant(contacts[Number(item.dataset.contactIndex)], mode);
    if (create) openContactModal(mode === 'edit' ? editParticipantSearch.value : participantSearch.value, mode);
  });
}

function showContactResults(query, results) {
  const value = query.trim().toLowerCase();
  const rows = contacts
    .map((contact, index) => ({ contact, index }))
    .filter((item) => item.contact.label.toLowerCase().includes(value))
    .slice(0, 8);
  results.innerHTML = rows.map((item) => `<button class="${item.contact.type === 'user' ? 'contact-internal' : 'contact-institutional'}" type="button" data-contact-index="${item.index}">${escapeHtml(item.contact.label)} <small>${escapeHtml(item.contact.organization || item.contact.role || item.contact.type)}</small></button>`).join('')
    + (value ? `<button type="button" data-create-contact="1">+ crea "${escapeHtml(query)}"</button>` : '');
  results.classList.remove('hidden');
}

function addParticipant(contact, mode = 'create') {
  if (!contact) return;
  const target = mode === 'edit' ? selectedEditParticipants : selectedParticipants;
  const input = mode === 'edit' ? editParticipantSearch : participantSearch;
  if (!target.some((item) => item.type === contact.type && item.id === contact.id)) {
    target.push(contact);
  }
  input.value = '';
  renderParticipantTags(mode);
  input.focus();
  showContactResults('', mode === 'edit' ? editParticipantResults : participantResults);
}

function commitParticipantSearch(mode = 'create') {
  const input = mode === 'edit' ? editParticipantSearch : participantSearch;
  const value = input.value.trim();
  if (value === '') return;
  const match = contacts.find((contact) => contact.label.toLowerCase() === value.toLowerCase());
  addParticipant(match || { type: 'free', id: Date.now(), label: value }, mode);
}

function renderParticipantTags(mode = 'create') {
  const target = mode === 'edit' ? selectedEditParticipants : selectedParticipants;
  const tags = mode === 'edit' ? editParticipantTags : participantTags;
  const hidden = mode === 'edit' ? editParticipantsText : participantsText;
  tags.innerHTML = target.map((item, index) => `<span class="tag">${escapeHtml(item.label)} <button type="button" data-remove-participant="${mode}:${index}">x</button></span>`).join('');
  hidden.value = target.map((item) => item.label).join('\n');
}

function bindTagRemoval(container, mode) {
  container.addEventListener('click', (event) => {
    const button = event.target.closest('[data-remove-participant]');
    if (!button) return;
    const [, index] = button.dataset.removeParticipant.split(':');
    const target = mode === 'edit' ? selectedEditParticipants : selectedParticipants;
    target.splice(Number(index), 1);
    renderParticipantTags(mode);
  });
}

bindTagRemoval(participantTags, 'create');
bindTagRemoval(editParticipantTags, 'edit');

function openContactModal(name, mode) {
  activePicker = mode;
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
      : contact, activePicker);
    contactModal.close();
  } catch (error) {
    message.textContent = error.message;
  }
});

closeContactModal.addEventListener('click', () => contactModal.close());

meetingsTable.addEventListener('click', (event) => {
  const convocationButton = event.target.closest('[data-view-convocation]');
  if (convocationButton) {
    openConvocation(convocationButton.dataset.viewConvocation);
    return;
  }

  const editButton = event.target.closest('[data-edit-meeting]');
  if (editButton) {
    openEditMeeting(editButton.dataset.editMeeting);
    return;
  }

  const comunicadoButton = event.target.closest('[data-public-comunicato]');
  if (comunicadoButton) {
    createPublicComunicato(comunicadoButton.dataset.publicComunicato);
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

closePracticeLinkModal.addEventListener('click', () => practiceLinkModal.close());

async function openEditMeeting(id) {
  try {
    const meeting = await api(`/union-meetings/${id}`);
    meetingEditForm.elements.id.value = meeting.id;
    meetingEditForm.elements.title.value = meeting.title || '';
    meetingEditForm.elements.meeting_date.value = String(meeting.meeting_date || '').replace(' ', 'T').slice(0, 16);
    meetingEditForm.elements.location.value = meeting.location || '';
    meetingEditForm.elements.status.value = meeting.status || 'scheduled';
    meetingEditForm.elements.visibility.value = meeting.visibility || 'rsu';
    meetingEditForm.elements.agenda.value = meeting.agenda || '';
    meetingEditForm.elements.description.value = meeting.description || '';
    selectedEditParticipants = (meeting.selected_participants || []).map((item) => ({
      type: item.participant_type,
      id: item.participant_id,
      label: item.label,
      role: item.role,
      organization: item.organization,
    }));
    if (selectedEditParticipants.length === 0 && meeting.participants) {
      selectedEditParticipants = String(meeting.participants).split('\n').filter(Boolean).map((label, index) => ({ type: 'free', id: `free-${index}`, label }));
    }
    renderParticipantTags('edit');
    meetingEditModal.showModal();
  } catch (error) {
    message.textContent = error.message;
  }
}

meetingEditForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  commitParticipantSearch('edit');
  const form = new FormData(meetingEditForm);
  const id = form.get('id');
  const data = Object.fromEntries(form.entries());
  data.participants = selectedEditParticipants.map((item) => item.label).join('\n');
  data.selected_participants = selectedEditParticipants
    .filter((item) => item.type !== 'free')
    .map((item) => ({ type: item.type, id: item.id, label: item.label }));
  try {
    await api(`/union-meetings/${id}`, { method: 'PATCH', body: JSON.stringify(data) });
    meetingEditModal.close();
    await loadMeetings();
  } catch (error) {
    message.textContent = error.message;
  }
});

function openConvocation(documentId) {
  convocationPreview.src = `${apiBase}/documents/${documentId}/preview?token=${encodeURIComponent(token || '')}`;
  convocationModal.classList.remove('hidden');
}

closeConvocationModal.addEventListener('click', () => {
  convocationPreview.src = '';
  convocationModal.classList.add('hidden');
});

convocationModal.addEventListener('click', (event) => {
  if (event.target !== convocationModal) return;
  convocationPreview.src = '';
  convocationModal.classList.add('hidden');
});

closeMeetingEditModal.addEventListener('click', () => meetingEditModal.close());
loadMeetings().catch((error) => { message.textContent = error.message; });
