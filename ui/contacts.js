const apiBase = '../api/v1';
const token = sessionStorage.getItem('token') || localStorage.getItem('token');
const contactForm = document.querySelector('#contactForm');
const contactsTable = document.querySelector('#contactsTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
let contacts = [];

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
  contacts = data.institutional || [];
  contactsTable.innerHTML = contacts.map(row).join('');
}

function row(contact) {
  return `<tr>
    <td data-label="Nome"><span class="truncate-title" title="${escapeAttr(contact.label)}">${escapeHtml(contact.label)}</span></td>
    <td data-label="Tipo">${escapeHtml(contact.contact_type || '-')}</td>
    <td data-label="Ruolo"><span class="truncate-title" title="${escapeAttr(contact.role)}">${escapeHtml(contact.role || '-')}</span></td>
    <td data-label="Organizzazione"><span class="truncate-title" title="${escapeAttr(contact.organization)}">${escapeHtml(contact.organization || '-')}</span></td>
    <td data-label="Email"><span class="truncate-title" title="${escapeAttr(contact.email)}">${escapeHtml(contact.email || '-')}</span></td>
    <td data-label="Telefono">${escapeHtml(contact.phone || '-')}</td>
    <td data-label="Azioni" class="actions-cell"><button class="icon-action" data-edit="${contact.id}" title="Modifica">${MyRsuIcons.get('edit')}</button><button class="icon-action danger" data-delete="${contact.id}" title="Cancella">${MyRsuIcons.get('trash')}</button></td>
  </tr>`;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
  return escapeHtml(value).replace(/`/g, '&#096;');
}

contactForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  const form = new FormData(contactForm);
  try {
    const id = contactForm.dataset.editId;
    await api(id ? `/institutional-contacts/${id}` : '/institutional-contacts', {
      method: id ? 'PATCH' : 'POST',
      body: JSON.stringify(Object.fromEntries(form.entries())),
    });
    delete contactForm.dataset.editId;
    contactForm.reset();
    await loadContacts();
  } catch (error) {
    message.textContent = error.message;
  }
});

contactsTable.addEventListener('click', async (event) => {
  const edit = event.target.closest('[data-edit]');
  const remove = event.target.closest('[data-delete]');
  if (edit) {
    const contact = contacts.find((item) => Number(item.id) === Number(edit.dataset.edit));
    if (!contact) return;
    contactForm.dataset.editId = contact.id;
    contactForm.elements.type.value = contact.contact_type || 'esterno';
    contactForm.elements.name.value = contact.label || '';
    contactForm.elements.role.value = contact.role || '';
    contactForm.elements.organization.value = contact.organization || '';
    contactForm.elements.email.value = contact.email || '';
    contactForm.elements.phone.value = contact.phone || '';
    contactForm.elements.notes.value = contact.notes || '';
    message.textContent = 'Modifica contatto attiva.';
    return;
  }
  if (!remove || !confirm('Eliminare contatto?')) return;
  await api(`/institutional-contacts/${remove.dataset.delete}`, { method: 'DELETE' });
  message.textContent = 'Contatto eliminato.';
  await loadContacts();
});

loadContacts().catch((error) => { message.textContent = error.message; });
