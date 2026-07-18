const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const assemblyForm = document.querySelector('#assemblyForm');
const sessionsBox = document.querySelector('#sessions');
const addSession = document.querySelector('#addSession');
const practiceSelect = document.querySelector('#practiceSelect');
const participantSearch = document.querySelector('#participantSearch');
const participantTags = document.querySelector('#participantTags');
const participantResults = document.querySelector('#participantResults');
const contactModal = document.querySelector('#contactModal');
const contactForm = document.querySelector('#contactForm');
const closeContactModal = document.querySelector('#closeContactModal');
const attachmentsPanel = document.querySelector('#attachmentsPanel');
const attachmentForm = document.querySelector('#attachmentForm');
const existingDocumentForm = document.querySelector('#existingDocumentForm');
const existingDocumentSelect = document.querySelector('#existingDocumentSelect');
const attachmentsTable = document.querySelector('#attachmentsTable');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const pageTitle = document.querySelector('#pageTitle');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const assemblyId = new URLSearchParams(window.location.search).get('id');
let sessionIndex = 0;
let contacts = [];
let existingDocuments = [];
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

async function loadPractices() {
  try {
    const practices = await api('/practices');
    practiceSelect.innerHTML = '<option value="">non associata</option>' + practices.map((item) => `<option value="${item.id}">${escapeHtml(item.title)}</option>`).join('');
  } catch {
    practiceSelect.innerHTML = '<option value="">non associata</option>';
  }
}

async function loadContacts() {
  const data = await api('/contacts');
  contacts = [...(data.users || []), ...(data.institutional || [])];
}

async function loadAssembly() {
  if (!assemblyId) {
    addSessionRow({ shift_label: 'Turno 1' });
    return;
  }
  const assembly = await api(`/workers-assemblies/${assemblyId}`);
  attachmentsPanel.classList.remove('hidden');
  pageTitle.textContent = 'Modifica assemblea';
  assemblyForm.title.value = assembly.title || '';
  assemblyForm.status.value = assembly.status || 'draft';
  assemblyForm.visibility.value = assembly.visibility || 'members';
  assemblyForm.practice_id.value = assembly.practice_id || '';
  assemblyForm.voting_enabled.value = Number(assembly.voting_enabled) === 1 ? '1' : '';
  assemblyForm.voting_subject.value = assembly.voting_subject || '';
  selectedParticipants = (assembly.selected_participants || []).map((item) => ({
    type: item.participant_type,
    id: item.participant_id,
    label: item.label,
  }));
  renderParticipantTags();
  assemblyForm.agenda.value = assembly.agenda || '';
  assemblyForm.description.value = assembly.description || '';
  assemblyForm.final_statement.value = assembly.final_statement || '';
  (assembly.sessions || []).forEach(addSessionRow);
  renderAttachments(assembly.documents || []);
  await loadExistingDocuments(assembly.documents || []);
}

function addSessionRow(session = {}) {
  sessionIndex += 1;
  sessionsBox.insertAdjacentHTML('beforeend', `<div class="grid session-row">
    <label>Turno<input data-field="shift_label" value="${escapeAttr(session.shift_label || `Turno ${sessionIndex}`)}" required></label>
    <label>Data<input data-field="assembly_date" type="date" value="${escapeAttr(session.assembly_date || '')}" required></label>
    <label>Ora inizio<input data-field="time_start" type="time" value="${escapeAttr(String(session.time_start || '').slice(0, 5))}" required></label>
    <label>Ora fine<input data-field="time_end" type="time" value="${escapeAttr(String(session.time_end || '').slice(0, 5))}"></label>
    <label>Modalita<select data-field="mode"><option value="in_person">presenza</option><option value="online">online</option><option value="mixed">mista</option></select></label>
    <label>Luogo<input data-field="place" value="${escapeAttr(session.place || '')}"></label>
    <label>Stato<select data-field="status"><option value="scheduled">programmata</option><option value="done">svolta</option><option value="cancelled">annullata</option></select></label>
    <button type="button" data-remove-session="1">Rimuovi</button>
  </div>`);
  const row = sessionsBox.lastElementChild;
  row.querySelector('[data-field="mode"]').value = session.mode || 'in_person';
  row.querySelector('[data-field="status"]').value = session.status || 'scheduled';
}

function collectSessions() {
  return [...sessionsBox.querySelectorAll('.session-row')].map((row) => {
    const data = {};
    row.querySelectorAll('[data-field]').forEach((field) => { data[field.dataset.field] = field.value; });
    return data;
  });
}

assemblyForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!commitParticipantSearch()) return;
  const data = Object.fromEntries(new FormData(assemblyForm).entries());
  data.selected_participants = selectedParticipants
    .filter((item) => item.type !== 'free')
    .map((item) => ({ type: item.type, id: item.id, label: item.label }));
  data.sessions = collectSessions();
  try {
    const path = assemblyId ? `/workers-assemblies/${assemblyId}` : '/workers-assemblies';
    await api(path, { method: assemblyId ? 'PATCH' : 'POST', body: JSON.stringify(data) });
    message.textContent = 'Assemblea salvata.';
    window.setTimeout(() => { window.location.href = assemblyId ? 'workers-assemblies.html' : 'workers-assemblies.html'; }, 500);
  } catch (error) {
    message.textContent = error.message;
  }
});

attachmentForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!assemblyId) return;
  try {
    const response = await fetch(`${apiBase}/workers-assemblies/${assemblyId}/documents`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
      body: new FormData(attachmentForm),
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
  if (!assemblyId) return;
  try {
    const data = await api(`/workers-assemblies/${assemblyId}/documents/link`, {
      method: 'POST',
      body: JSON.stringify({ document_id: existingDocumentSelect.value }),
    });
    renderAttachments(data.documents || []);
    renderExistingDocumentOptions(data.documents || []);
    message.textContent = 'Documento collegato.';
  } catch (error) {
    message.textContent = error.message;
  }
});

attachmentsTable.addEventListener('click', async (event) => {
  const view = event.target.closest('[data-view-document]');
  if (view) {
    openDocument(view.dataset.viewDocument);
    return;
  }
  const remove = event.target.closest('[data-remove-document]');
  if (!remove || !assemblyId) return;
  const data = await api(`/workers-assemblies/${assemblyId}/documents/${remove.dataset.removeDocument}`, { method: 'DELETE' });
  renderAttachments(data.documents || []);
  renderExistingDocumentOptions(data.documents || []);
});

function renderAttachments(documents) {
  attachmentsTable.innerHTML = documents.length
    ? documents.map((document) => `<tr><td>${escapeHtml(document.original_name)}</td><td>${escapeHtml(document.conversion_status)}</td><td class="actions-cell"><button class="icon-action" data-view-document="${document.document_id}" title="Anteprima">${MyRsuIcons.get('eye')}</button><button class="icon-action danger" data-remove-document="${document.document_id}" title="Rimuovi">${MyRsuIcons.get('trash')}</button></td></tr>`).join('')
    : '<tr><td colspan="3">Nessun allegato.</td></tr>';
}

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
  if (value === '') return true;
  const match = contacts.find((contact) => contact.label.toLowerCase() === value.toLowerCase());
  if (!match) {
    openContactModal(value);
    return false;
  }
  addParticipant(match);
  return true;
}

function renderParticipantTags() {
  participantTags.innerHTML = selectedParticipants.map((item, index) => `<span class="tag">${escapeHtml(item.label)} <button type="button" data-remove-participant="${index}">x</button></span>`).join('');
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
  const data = Object.fromEntries(new FormData(contactForm).entries());
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

addSession.addEventListener('click', () => addSessionRow());
sessionsBox.addEventListener('click', (event) => {
  const button = event.target.closest('[data-remove-session]');
  if (!button) return;
  button.closest('.session-row').remove();
});

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
  return escapeHtml(value).replace(/`/g, '&#096;');
}

if (!token) window.location.href = 'app/index.html';
Promise.all([loadPractices(), loadContacts()]).then(loadAssembly).catch((error) => { message.textContent = error.message; });
