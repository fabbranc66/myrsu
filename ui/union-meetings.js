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
const contactModal = document.querySelector('#contactModal');
const contactForm = document.querySelector('#contactForm');
const closeContactModal = document.querySelector('#closeContactModal');
let practices = [];
let contacts = [];
let selectedParticipants = [];

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
  return `<tr><td>${escapeHtml(meeting.title)}</td><td>${escapeHtml(meeting.meeting_date)}</td><td>${escapeHtml(meeting.location)}</td><td>${translateStatus(meeting.status)}</td><td>${translateVisibility(meeting.visibility)}</td><td>${documentStatus}</td><td class="actions-cell"><button class="icon-action" data-public-comunicato="${meeting.id}" title="Comunicato pubblico">${MyRsuIcons.get('document')}</button><a class="icon-action" href="union-meeting-operational.html?id=${meeting.id}" title="Pagina operativa">${MyRsuIcons.get('edit')}</a><button class="icon-action" data-practice-link="${meeting.id}" title="Collega a pratica">${MyRsuIcons.get('link')}</button></td></tr>`;
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

participantSearch.addEventListener('input', () => showContactResults(participantSearch.value));
participantSearch.addEventListener('focus', () => showContactResults(participantSearch.value));
participantSearch.addEventListener('click', () => showContactResults(participantSearch.value));
participantSearch.addEventListener('keydown', (event) => {
  if (event.key !== 'Enter') return;
  event.preventDefault();
  const value = participantSearch.value.trim();
  if (value === '') return;
  commitParticipantSearch();
});

document.addEventListener('click', (event) => {
  if (event.target.closest('.contact-picker')) return;
  participantResults.classList.add('hidden');
});

participantResults.addEventListener('click', (event) => {
  const item = event.target.closest('[data-contact-index]');
  const create = event.target.closest('[data-create-contact]');
  if (item) addParticipant(contacts[Number(item.dataset.contactIndex)]);
  if (create) openContactModal(participantSearch.value);
});

function showContactResults(query) {
  const value = query.trim().toLowerCase();
  const rows = contacts
    .map((contact, index) => ({ contact, index }))
    .filter((item) => item.contact.label.toLowerCase().includes(value))
    .slice(0, 8);
  participantResults.innerHTML = rows.map((item) => `<button class="${item.contact.type === 'user' ? 'contact-internal' : 'contact-institutional'}" type="button" data-contact-index="${item.index}">${escapeHtml(item.contact.label)} <small>${escapeHtml(item.contact.organization || item.contact.role || item.contact.type)}</small></button>`).join('')
    + (value ? `<button type="button" data-create-contact="1">+ crea "${escapeHtml(query)}"</button>` : '');
  participantResults.classList.remove('hidden');
}

function addParticipant(contact) {
  if (!contact) return;
  if (!selectedParticipants.some((item) => item.type === contact.type && item.id === contact.id)) {
    selectedParticipants.push(contact);
  }
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

closeContactModal.addEventListener('click', () => contactModal.close());

meetingsTable.addEventListener('click', (event) => {
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
    await api(`/union-meetings/${id}/public-comunicato`, { method: 'POST', body: '{}' });
    await loadMeetings();
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
loadMeetings().catch((error) => { message.textContent = error.message; });
