const apiBase = '../api/v1';
const token = sessionStorage.getItem('token') || localStorage.getItem('token');
const form = document.querySelector('#emailForm');
const sendEmailForm = document.querySelector('#sendEmailForm');
const table = document.querySelector('#emailsTable');
const practicesSelect = form.practice_id;
const sendPracticesSelect = sendEmailForm.practice_id;
const sendDocumentsSelect = sendEmailForm.querySelector('[name="document_ids[]"]');
const toSearch = document.querySelector('#toSearch');
const ccSearch = document.querySelector('#ccSearch');
const bccSearch = document.querySelector('#bccSearch');
const toTags = document.querySelector('#toTags');
const ccTags = document.querySelector('#ccTags');
const bccTags = document.querySelector('#bccTags');
const toResults = document.querySelector('#toResults');
const ccResults = document.querySelector('#ccResults');
const bccResults = document.querySelector('#bccResults');
const contactModal = document.querySelector('#contactModal');
const contactForm = document.querySelector('#contactForm');
const directionFilter = document.querySelector('#directionFilter');
const statusFilter = document.querySelector('#statusFilter');
const syncEmails = document.querySelector('#syncEmails');
const modal = document.querySelector('#emailModal');
const modalTitle = document.querySelector('#emailModalTitle');
const modalBody = document.querySelector('#emailModalBody');
const attachmentModal = document.querySelector('#attachmentModal');
const attachmentTitle = document.querySelector('#attachmentTitle');
const attachmentFrame = document.querySelector('#attachmentFrame');
const noteForm = document.querySelector('#emailNoteForm');
const reminderModal = document.querySelector('#reminderModal');
const reminderForm = document.querySelector('#reminderForm');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
let emails = [];
let practices = [];
let documents = [];
let contacts = [];
let selectedTo = [];
let selectedCc = [];
let selectedBcc = [];
let currentEmailId = null;
let contactTarget = null;

if (!token) window.location.href = 'login.html';

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}), Authorization: `Bearer ${token}` };
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Operazione fallita');
  return payload.data;
}

async function apiMultipart(path, data) {
  const response = await fetch(`${apiBase}${path}`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}` },
    body: data,
  });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Operazione fallita');
  return payload.data;
}

async function load() {
  const query = new URLSearchParams();
  if (directionFilter.value) query.set('direction', directionFilter.value);
  if (statusFilter.value) query.set('handling_status', statusFilter.value);
  const [emailRows, practiceRows, documentRows, contactRows] = await Promise.all([
    api(`/emails${query.toString() ? `?${query}` : ''}`),
    api('/practices'),
    api('/documents').catch(() => []),
    api('/contacts').catch(() => ({ users: [], institutional: [] })),
  ]);
  emails = emailRows;
  practices = practiceRows;
  documents = documentRows;
  contacts = [...(contactRows.users || []), ...(contactRows.institutional || [])];
  fillPractices();
  fillDocuments();
  render();
  const params = new URLSearchParams(window.location.search);
  const openId = params.get('id');
  if (openId && params.get('edit') === '1') fillEdit(openId);
  else if (openId) openEmail(openId);
}

function fillPractices() {
  const options = '<option value="">-</option>' + practices.map((practice) => `<option value="${practice.id}">${escapeHtml(practice.title)}</option>`).join('');
  practicesSelect.innerHTML = options;
  sendPracticesSelect.innerHTML = options;
}

function fillDocuments() {
  const seen = new Set();
  sendDocumentsSelect.innerHTML = documents
    .filter((document) => document.category === 'documenti' && document.conversion_status === 'ready')
    .filter((document) => {
      const key = systemDocumentName(document).toLowerCase();
      if (!key || seen.has(key)) return false;
      seen.add(key);
      return true;
    })
    .map((document) => `<option value="${document.id}">${escapeHtml(systemDocumentName(document))}</option>`)
    .join('');
}

function systemDocumentName(document) {
  return String(document.stored_name || document.pdf_public_path || document.original_name || '').split('/').pop();
}

function render() {
  table.innerHTML = emails.map((email) => `<tr>
    <td data-label="Data"><span class="truncate-title" title="${escapeHtml(email.message_at)}">${escapeHtml(email.message_at)}</span></td>
    <td data-label="Tipo">${directionLabel(email.direction)}</td>
    <td data-label="Oggetto"><span class="truncate-title" title="${escapeHtml(email.subject)}">${escapeHtml(email.subject)}</span></td>
    <td data-label="Contatto"><span class="truncate-title" title="${escapeHtml(email.from_name || email.from_email || email.to_emails || '-')}">${escapeHtml(email.from_name || email.from_email || email.to_emails || '-')}</span></td>
    <td data-label="Pratica"><span class="truncate-title" title="${escapeHtml(email.practice_title || '-')}">${escapeHtml(email.practice_title || '-')}</span></td>
    <td data-label="Stato">${statusLabel(email.handling_status)}</td>
    <td data-label="Azioni" class="actions-cell">
      <button class="icon-action" data-view="${email.id}" title="Visualizza">${MyRsuIcons.get('eye')}</button>
      <button class="icon-action" data-reminder="${email.id}" data-reminder-title="${escapeHtml(email.subject)}" title="Reminder">${MyRsuIcons.get('reminder')}</button>
      <button class="icon-action" data-edit="${email.id}" title="Modifica">${MyRsuIcons.get('edit')}</button>
      <button class="icon-action" data-download="${email.id}" title="Scarica">${MyRsuIcons.get('download')}</button>
      ${practiceDropdown(email)}
      <button class="icon-action" data-managed="${email.id}" title="Marca gestita">${MyRsuIcons.get('active')}</button>
      <button class="icon-action danger" data-delete="${email.id}" title="Cancella">${MyRsuIcons.get('trash')}</button>
    </td>
  </tr>`).join('') || '<tr><td colspan="7">Nessuna e-mail.</td></tr>';
}

function practiceDropdown(email) {
  const options = practices.map((practice) => {
    const selected = Number(email.practice_id) === Number(practice.id) ? ' selected' : '';
    return `<option value="${practice.id}"${selected}>${escapeHtml(practice.title)}</option>`;
  }).join('');
  return `<select class="email-practice-link" data-link-practice="${email.id}" title="Collega a pratica">
    <option value="">Pratica</option>${options}
  </select>`;
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = clean(Object.fromEntries(new FormData(form).entries()));
  data.read_status = data.handling_status === 'new' ? 'unread' : 'read';
  const id = data.id;
  delete data.id;
  await api(id ? `/emails/${id}` : '/emails', { method: id ? 'PATCH' : 'POST', body: JSON.stringify(data) });
  form.reset();
  message.textContent = 'E-mail salvata';
  await load();
});

sendEmailForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  syncRecipientInputs();
  const button = sendEmailForm.querySelector('button[type="submit"]');
  button.disabled = true;
  message.textContent = 'Invio e-mail in corso...';
  try {
    await apiMultipart('/emails/send', new FormData(sendEmailForm));
    sendEmailForm.reset();
    selectedTo = [];
    selectedCc = [];
    selectedBcc = [];
    renderRecipientTags();
    message.textContent = 'E-mail inviata';
    await load();
  } finally {
    button.disabled = false;
  }
});

[toSearch, ccSearch, bccSearch].forEach((input) => {
  const kind = input === toSearch ? 'to' : (input === ccSearch ? 'cc' : 'bcc');
  input.addEventListener('input', () => showRecipientResults(kind, input.value));
  input.addEventListener('focus', () => showRecipientResults(kind, input.value));
  input.addEventListener('click', () => showRecipientResults(kind, input.value));
  input.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    event.preventDefault();
    addTypedRecipient(kind, input.value);
  });
});

document.addEventListener('click', (event) => {
  if (!event.target.closest('.contact-picker')) {
    toResults.classList.add('hidden');
    ccResults.classList.add('hidden');
    bccResults.classList.add('hidden');
  }
});

[toResults, ccResults, bccResults].forEach((box) => {
  box.addEventListener('click', (event) => {
    const kind = box === toResults ? 'to' : (box === ccResults ? 'cc' : 'bcc');
    const item = event.target.closest('[data-contact-index]');
    const create = event.target.closest('[data-create-contact]');
    if (item) addRecipient(kind, contacts[Number(item.dataset.contactIndex)]);
    if (create) openContactModal(create.dataset.email || '', create.dataset.email || '', kind);
  });
});

function showRecipientResults(kind, query) {
  const box = kind === 'to' ? toResults : (kind === 'cc' ? ccResults : bccResults);
  const value = query.trim().toLowerCase();
  const rows = contacts.map((contact, index) => ({ contact, index }))
    .filter((item) => !value || `${item.contact.label} ${item.contact.email}`.toLowerCase().includes(value))
    .slice(0, 12);
  box.innerHTML = rows.map((item) => `<button class="${item.contact.type === 'user' ? 'contact-internal' : 'contact-institutional'}" type="button" data-contact-index="${item.index}">
    ${escapeHtml(item.contact.label)} <small>${escapeHtml(item.contact.email || item.contact.organization || item.contact.type)}</small>
  </button>`).join('') + (query.trim() ? `<button type="button" data-create-contact="1" data-email="${escapeHtml(query.trim())}">+ crea "${escapeHtml(query.trim())}"</button>` : '');
  box.classList.remove('hidden');
}

function addRecipient(kind, contact) {
  if (!contact?.email) return;
  const list = kind === 'to' ? selectedTo : (kind === 'cc' ? selectedCc : selectedBcc);
  if (!list.some((item) => item.email.toLowerCase() === contact.email.toLowerCase())) list.push(contact);
  (kind === 'to' ? toSearch : (kind === 'cc' ? ccSearch : bccSearch)).value = '';
  renderRecipientTags();
  showRecipientResults(kind, '');
  (kind === 'to' ? toSearch : (kind === 'cc' ? ccSearch : bccSearch)).focus();
}

function addTypedRecipient(kind, value) {
  const text = value.trim();
  if (!text) return;
  const match = contacts.find((contact) => contact.email?.toLowerCase() === text.toLowerCase() || contact.label.toLowerCase() === text.toLowerCase());
  if (match) return addRecipient(kind, match);
  if (text.includes('@')) return openContactModal(text, text, kind);
}

function renderRecipientTags() {
  toTags.innerHTML = selectedTo.map((item, index) => tagHtml(item, index, 'to')).join('');
  ccTags.innerHTML = selectedCc.map((item, index) => tagHtml(item, index, 'cc')).join('');
  bccTags.innerHTML = selectedBcc.map((item, index) => tagHtml(item, index, 'bcc')).join('');
  syncRecipientInputs();
}

function tagHtml(item, index, kind) {
  return `<span class="tag">${escapeHtml(item.label || item.email)} <small>${escapeHtml(item.email)}</small> <button type="button" data-remove-recipient="${kind}:${index}">x</button></span>`;
}

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-remove-recipient]');
  if (!button) return;
  const [kind, index] = button.dataset.removeRecipient.split(':');
  (kind === 'to' ? selectedTo : (kind === 'cc' ? selectedCc : selectedBcc)).splice(Number(index), 1);
  renderRecipientTags();
});

function syncRecipientInputs() {
  sendEmailForm.querySelector('[name="to_emails"]').value = selectedTo.map((item) => item.email).join(', ');
  sendEmailForm.querySelector('[name="cc_emails"]').value = selectedCc.map((item) => item.email).join(', ');
  sendEmailForm.querySelector('[name="bcc_emails"]').value = selectedBcc.map((item) => item.email).join(', ');
}

function contactExists(email) {
  return contacts.some((contact) => String(contact.email || '').toLowerCase() === String(email || '').toLowerCase());
}

function openContactModal(email, name, target) {
  contactTarget = target;
  contactForm.reset();
  contactForm.name.value = name || email;
  contactForm.email.value = email;
  if (!contactModal.open) contactModal.showModal();
}

contactForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = clean(Object.fromEntries(new FormData(contactForm).entries()));
  delete data.source;
  const contact = await api('/institutional-contacts', { method: 'POST', body: JSON.stringify(data) });
  contacts.push(contact);
  if (contactTarget) addRecipient(contactTarget, contact);
  contactModal.close();
  message.textContent = 'Contatto salvato';
});

document.querySelector('#closeContactModal').addEventListener('click', () => contactModal.close());

table.addEventListener('click', async (event) => {
  const view = event.target.closest('[data-view]');
  const edit = event.target.closest('[data-edit]');
  const download = event.target.closest('[data-download]');
  const reminder = event.target.closest('[data-reminder]');
  const managed = event.target.closest('[data-managed]');
  const remove = event.target.closest('[data-delete]');
  if (view) return openEmail(view.dataset.view);
  if (reminder) return openReminder(reminder.dataset.reminder, reminder.dataset.reminderTitle || '');
  if (edit) return fillEdit(edit.dataset.edit);
  if (download) return downloadEmail(download.dataset.download);
  if (remove) return deleteEmail(remove.dataset.delete);
  if (managed) {
    await api(`/emails/${managed.dataset.managed}/manage`, { method: 'POST', body: '{}' });
    message.textContent = 'E-mail marcata come gestita';
    await load();
  }
});

table.addEventListener('change', async (event) => {
  const select = event.target.closest('select[data-link-practice]');
  if (!select || !select.value) return;
  await linkEmailPractice(select.dataset.linkPractice, select.value);
});

async function deleteEmail(id) {
  if (!confirm('Cancellare e-mail e allegati collegati?')) return;
  await api(`/emails/${id}`, { method: 'DELETE' });
  message.textContent = 'E-mail cancellata';
  await load();
}

function downloadEmail(id) {
  const email = emails.find((item) => Number(item.id) === Number(id));
  if (!email) return;
  const content = [
    `Oggetto: ${email.subject || ''}`,
    `Da: ${email.from_name || ''} ${email.from_email || ''}`.trim(),
    `A: ${email.to_emails || ''}`,
    `CC: ${email.cc_emails || ''}`,
    `CCN: ${email.bcc_emails || ''}`,
    `Data: ${email.message_at || ''}`,
    '',
    email.body || '',
  ].join('\n');
  const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `email-${id}.txt`;
  link.click();
  URL.revokeObjectURL(url);
}

async function linkEmailPractice(id, practiceId) {
  await api(`/emails/${id}/link-practice`, {
    method: 'POST',
    body: JSON.stringify({ practice_id: Number(practiceId) }),
  });
  message.textContent = 'E-mail collegata alla pratica';
  await load();
}

async function openEmail(id) {
  const data = await api(`/emails/${id}`);
  currentEmailId = Number(id);
  const email = data.email;
  modalTitle.textContent = email.subject;
  modalBody.innerHTML = `<dl class="email-meta">
    <dt>Da</dt><dd>${escapeHtml(email.from_name || '')} ${escapeHtml(email.from_email || '')}</dd>
    <dt>A</dt><dd>${escapeHtml(email.to_emails || '-')}</dd>
    <dt>CC</dt><dd>${escapeHtml(email.cc_emails || '-')}</dd>
    <dt>CCN</dt><dd>${escapeHtml(email.bcc_emails || '-')}</dd>
    <dt>Data</dt><dd>${escapeHtml(email.message_at)}</dd>
    <dt>Stato</dt><dd>${statusLabel(email.handling_status)}</dd>
  </dl><pre class="email-body">${escapeHtml(email.body)}</pre>${attachmentsHtml(data.attachments || [])}${notesHtml(data.notes)}`;
  modal.showModal();
  if (email.direction === 'incoming' && email.from_email && !contactExists(email.from_email)) {
    setTimeout(() => openContactModal(email.from_email, email.from_name || email.from_email, null), 150);
  }
}

function fillEdit(id) {
  const email = emails.find((item) => Number(item.id) === Number(id));
  Object.keys(email || {}).forEach((key) => {
    if (form[key]) form[key].value = key === 'message_at' ? String(email[key]).replace(' ', 'T').slice(0, 16) : (email[key] || '');
  });
}

noteForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!currentEmailId) return;
  const data = Object.fromEntries(new FormData(noteForm).entries());
  await api(`/emails/${currentEmailId}/notes`, { method: 'POST', body: JSON.stringify(data) });
  noteForm.reset();
  await openEmail(String(currentEmailId));
});

document.querySelector('#closeEmailModal').addEventListener('click', () => modal.close());
document.querySelector('#closeAttachmentModal').addEventListener('click', () => {
  attachmentFrame.src = '';
  attachmentModal.close();
});
document.querySelector('#closeReminderModal').addEventListener('click', () => reminderModal.close());

function openReminder(id, title) {
  reminderForm.reset();
  reminderForm.entity_id.value = id;
  reminderForm.title.value = `Gestire e-mail: ${title}`.trim();
  reminderModal.showModal();
}

reminderForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  await api('/reminders', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(reminderForm).entries())) });
  reminderModal.close();
  message.textContent = 'Reminder creato';
});
modalBody.addEventListener('click', (event) => {
  const button = event.target.closest('[data-attachment]');
  if (!button) return;
  attachmentTitle.textContent = button.dataset.name || 'Allegato';
  attachmentFrame.src = `${apiBase}/emails/attachments/${button.dataset.attachment}/preview?token=${encodeURIComponent(token || '')}`;
  attachmentModal.showModal();
});
document.querySelector('#resetEmail').addEventListener('click', () => form.reset());
directionFilter.addEventListener('change', load);
statusFilter.addEventListener('change', load);
syncEmails.addEventListener('click', async () => {
  syncEmails.disabled = true;
  message.textContent = 'Sincronizzazione in corso...';
  try {
    const result = await api('/emails/sync', { method: 'POST', body: '{}' });
    message.textContent = `Sincronizzazione completata: ${result.imported} nuove su ${result.checked} controllate`;
    await load();
  } finally {
    syncEmails.disabled = false;
  }
});

function attachmentsHtml(attachments) {
  return attachments.length
    ? `<h3>Allegati</h3>${attachments.map((item) => {
      const preview = canPreview(item)
        ? `<button class="icon-action" type="button" data-attachment="${item.id}" data-name="${escapeHtml(item.original_name)}" title="Anteprima">${MyRsuIcons.get('eye')}</button>`
        : '<span class="muted">anteprima non disponibile</span>';
      return `<div class="email-attachment"><strong>${escapeHtml(item.original_name)}</strong><small>${Number(item.size_bytes || 0)} byte</small><span>${preview}<a class="icon-action" href="${apiBase}/emails/attachments/${item.id}/download?token=${encodeURIComponent(token || '')}" title="Scarica">${MyRsuIcons.get('download')}</a></span></div>`;
    }).join('')}`
    : '';
}
function canPreview(item) {
  const mime = String(item.mime_type || '').toLowerCase();
  const name = String(item.original_name || '').toLowerCase();
  return mime.startsWith('image/') || mime === 'application/pdf' || mime.startsWith('text/')
    || /\.(jpg|jpeg|png|gif|webp|pdf|txt)$/i.test(name);
}
function notesHtml(notes) {
  return notes.length ? `<h3>Note interne</h3>${notes.map((note) => `<article class="email-note"><strong>${escapeHtml(note.created_by_name)}</strong><p>${escapeHtml(note.body)}</p></article>`).join('')}` : '<p class="muted">Nessuna nota interna.</p>';
}
function directionLabel(value) { return { incoming: 'ricevuta', outgoing: 'inviata', draft: 'bozza' }[value] || value; }
function statusLabel(value) { return { new: 'nuova', in_progress: 'in lavorazione', managed: 'gestita', archived: 'archiviata' }[value] || value; }
function clean(data) { Object.keys(data).forEach((key) => { if (data[key] === '') data[key] = null; }); return data; }
function escapeHtml(value) { return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char])); }

load().catch((error) => { message.textContent = error.message; });
