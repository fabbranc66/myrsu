const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const callForm = document.querySelector('#callForm');
const filterForm = document.querySelector('#filterForm');
const callsTable = document.querySelector('#callsTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const practiceSelect = document.querySelector('#practiceSelect');
const practiceFilter = document.querySelector('#practiceFilter');
const practiceLinkModal = document.querySelector('#practiceLinkModal');
const practiceLinkForm = document.querySelector('#practiceLinkForm');
const closePracticeLinkModal = document.querySelector('#closePracticeLinkModal');
const callEditModal = document.querySelector('#callEditModal');
const callEditForm = document.querySelector('#callEditForm');
const closeCallEditModal = document.querySelector('#closeCallEditModal');
const interlocutorSearch = document.querySelector('#interlocutorSearch');
const interlocutorName = document.querySelector('#interlocutorName');
const interlocutorTag = document.querySelector('#interlocutorTag');
const interlocutorResults = document.querySelector('#interlocutorResults');
const contactModal = document.querySelector('#contactModal');
const contactForm = document.querySelector('#contactForm');
const closeContactModal = document.querySelector('#closeContactModal');
let practices = [];
let contacts = [];
let selectedInterlocutor = null;

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
    practices = await api('/practices');
  } catch {
    practices = [];
  }

  const options = practices.map((practice) => `<option value="${practice.id}">${escapeHtml(practice.title)}</option>`).join('');
  practiceSelect.innerHTML = `<option value="">non associata</option>${options}`;
  practiceFilter.innerHTML = `<option value="">tutte</option>${options}`;
  practiceLinkForm.practice_id.innerHTML = options || '<option value="">nessuna pratica</option>';
}

async function loadContacts() {
  try {
    const data = await api('/contacts');
    contacts = [...(data.users || []), ...(data.institutional || [])];
  } catch {
    contacts = [];
  }
}

async function loadCalls(practiceId = '') {
  const query = practiceId ? `?practice_id=${encodeURIComponent(practiceId)}` : '';
  const calls = await api(`/calls${query}`);
  callsTable.innerHTML = calls.map(row).join('');
}

function row(call) {
  return `<tr>
    <td>${escapeHtml(call.datetime.replace('T', ' '))}</td>
    <td><span class="call-direction ${call.direction}">${escapeHtml(call.direction)}</span></td>
    <td><strong>${escapeHtml(call.interlocutor.name)}</strong><br><span class="muted">${escapeHtml(call.interlocutor.role || call.interlocutor.org || '-')}</span></td>
    <td>${escapeHtml(call.outcome || '-')}</td>
    <td>${practiceLabel(call.practice_id)}</td>
    <td class="actions-cell">${call.practice_id ? '' : `<button class="icon-action" data-edit-call="${call.id}" title="Modifica">${MyRsuIcons.get('edit')}</button><button class="icon-action" data-link-practice="${call.id}" title="Collega a pratica">${MyRsuIcons.get('link')}</button><button class="icon-action danger" data-delete-call="${call.id}" title="Elimina">${MyRsuIcons.get('trash')}</button>`}</td>
  </tr>`;
}

function practiceLabel(practiceId) {
  if (!practiceId) return '-';
  const practice = practices.find((item) => Number(item.id) === Number(practiceId));
  return practice ? escapeHtml(practice.title) : `pratica ${practiceId}`;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;',
  }[char]));
}

function showInterlocutorResults(query) {
  const value = query.trim().toLowerCase();
  const rows = contacts
    .map((contact, index) => ({ contact, index }))
    .filter((item) => item.contact.label.toLowerCase().includes(value))
    .slice(0, 8);

  interlocutorResults.innerHTML = rows.map((item) => `
    <button class="${item.contact.type === 'user' ? 'contact-internal' : 'contact-institutional'}" type="button" data-contact-index="${item.index}">
      ${escapeHtml(item.contact.label)} <small>${escapeHtml(item.contact.organization || item.contact.role || item.contact.type)}</small>
    </button>
  `).join('') + (value ? `<button type="button" data-create-contact="1">+ crea "${escapeHtml(query)}"</button>` : '');
  interlocutorResults.classList.remove('hidden');
}

function renderInterlocutor() {
  interlocutorName.value = selectedInterlocutor?.label || '';
  interlocutorSearch.value = '';
  interlocutorTag.innerHTML = selectedInterlocutor
    ? `<span class="tag">${escapeHtml(selectedInterlocutor.label)} <button type="button" data-remove-interlocutor="1">x</button></span>`
    : '';
}

function selectInterlocutor(contact) {
  if (!contact) return;
  selectedInterlocutor = contact;
  callForm.interlocutor_role.value = contact.role || '';
  callForm.interlocutor_org.value = contact.organization || '';
  renderInterlocutor();
  interlocutorSearch.focus();
  showInterlocutorResults('');
}

function commitInterlocutorSearch() {
  const value = interlocutorSearch.value.trim();
  if (value === '') return;
  const match = contacts.find((contact) => contact.label.toLowerCase() === value.toLowerCase());
  if (match) {
    selectInterlocutor(match);
    return;
  }

  selectedInterlocutor = { type: 'free', id: Date.now(), label: value };
  renderInterlocutor();
}

function openContactModal(name) {
  contactForm.reset();
  contactForm.elements.name.value = name.trim();
  contactModal.showModal();
}

callForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  commitInterlocutorSearch();
  try {
    await api('/calls', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(callForm).entries())) });
    callForm.reset();
    selectedInterlocutor = null;
    renderInterlocutor();
    await loadCalls(practiceFilter.value);
  } catch (error) {
    message.textContent = error.message;
  }
});

filterForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  await loadCalls(practiceFilter.value);
});

interlocutorSearch.addEventListener('input', () => showInterlocutorResults(interlocutorSearch.value));
interlocutorSearch.addEventListener('focus', () => showInterlocutorResults(interlocutorSearch.value));
interlocutorSearch.addEventListener('click', () => showInterlocutorResults(interlocutorSearch.value));
interlocutorSearch.addEventListener('keydown', (event) => {
  if (event.key !== 'Enter') return;
  event.preventDefault();
  if (interlocutorSearch.value.trim() === '') return;
  commitInterlocutorSearch();
});

document.addEventListener('click', (event) => {
  if (event.target.closest('.contact-picker')) return;
  interlocutorResults.classList.add('hidden');
});

interlocutorResults.addEventListener('click', (event) => {
  const item = event.target.closest('[data-contact-index]');
  const create = event.target.closest('[data-create-contact]');
  if (item) selectInterlocutor(contacts[Number(item.dataset.contactIndex)]);
  if (create) openContactModal(interlocutorSearch.value);
});

interlocutorTag.addEventListener('click', (event) => {
  const button = event.target.closest('[data-remove-interlocutor]');
  if (!button) return;
  selectedInterlocutor = null;
  renderInterlocutor();
  interlocutorSearch.focus();
  showInterlocutorResults('');
});

callsTable.addEventListener('click', (event) => {
  const editButton = event.target.closest('[data-edit-call]');
  if (editButton) {
    openEditModal(editButton.dataset.editCall);
    return;
  }

  const button = event.target.closest('[data-link-practice]');
  if (button) {
    practiceLinkForm.call_id.value = button.dataset.linkPractice;
    practiceLinkForm.practice_id.innerHTML = practices.map((practice) => `<option value="${practice.id}">${escapeHtml(practice.title)}</option>`).join('') || '<option value="">nessuna pratica</option>';
    practiceLinkForm.querySelector('button').disabled = practices.length === 0;
    practiceLinkModal.showModal();
    return;
  }

  const deleteButton = event.target.closest('[data-delete-call]');
  if (!deleteButton) return;
  if (!confirm('Eliminare telefonata?')) return;
  api(`/calls/${deleteButton.dataset.deleteCall}`, { method: 'DELETE' })
    .then(() => loadCalls(practiceFilter.value))
    .catch((error) => { message.textContent = error.message; });
});

async function openEditModal(id) {
  try {
    const call = await api(`/calls/${id}`);
    callEditForm.id.value = call.id;
    callEditForm.direction.value = call.direction;
    callEditForm.interlocutor_name.value = call.interlocutor.name;
    callEditForm.interlocutor_role.value = call.interlocutor.role || '';
    callEditForm.interlocutor_org.value = call.interlocutor.org || '';
    callEditForm.call_date.value = call.datetime.slice(0, 10);
    callEditForm.call_time.value = call.datetime.slice(11, 16);
    callEditForm.outcome.value = call.outcome || '';
    callEditForm.content.value = call.content;
    callEditModal.showModal();
  } catch (error) {
    message.textContent = error.message;
  }
}

practiceLinkForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(practiceLinkForm).entries());
  try {
    await api(`/calls/${data.call_id}/link-practice`, {
      method: 'POST',
      body: JSON.stringify({ practice_id: Number(data.practice_id) }),
    });
    practiceLinkModal.close();
    await loadCalls(practiceFilter.value);
  } catch (error) {
    message.textContent = error.message;
  }
});

closePracticeLinkModal.addEventListener('click', () => practiceLinkModal.close());
closeCallEditModal.addEventListener('click', () => callEditModal.close());

callEditForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(callEditForm).entries());
  try {
    await api(`/calls/${data.id}`, { method: 'PATCH', body: JSON.stringify(data) });
    callEditModal.close();
    await loadCalls(practiceFilter.value);
  } catch (error) {
    message.textContent = error.message;
  }
});

closeContactModal.addEventListener('click', () => contactModal.close());

contactForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(contactForm).entries());
  try {
    const contact = data.source === 'user'
      ? await api('/users', {
        method: 'POST',
        body: JSON.stringify({
          name: data.name,
          email: data.email,
          password: data.password,
          role: data.user_role,
          first_name: '',
          last_name: '',
          phone: data.phone,
          mobile: '',
          city: '',
          country: '',
        }),
      })
      : await api('/institutional-contacts', { method: 'POST', body: JSON.stringify(data) });

    await loadContacts();
    selectInterlocutor(data.source === 'user'
      ? { type: 'user', id: contact.id, label: contact.name, organization: 'MyRSU', role: data.user_role }
      : contact);
    contactModal.close();
  } catch (error) {
    message.textContent = error.message;
  }
});

if (!token) {
  window.location.href = 'app/index.html';
} else {
  Promise.all([loadPractices(), loadContacts(), loadCalls()]).catch((error) => {
    message.textContent = error.message;
  });
}
