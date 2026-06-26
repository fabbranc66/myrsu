const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const contactForm = document.querySelector('#contactForm');
const contactsTable = document.querySelector('#contactsTable');
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

async function loadContacts() {
  const data = await api('/contacts');
  contactsTable.innerHTML = (data.institutional || []).map(row).join('');
}

function row(contact) {
  return `<tr><td>${escapeHtml(contact.label)}</td><td>${escapeHtml(contact.contact_type)}</td><td>${escapeHtml(contact.role)}</td><td>${escapeHtml(contact.organization)}</td><td>${escapeHtml(contact.email)}</td><td>${escapeHtml(contact.phone)}</td></tr>`;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

contactForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  const form = new FormData(contactForm);
  try {
    await api('/institutional-contacts', { method: 'POST', body: JSON.stringify(Object.fromEntries(form.entries())) });
    contactForm.reset();
    await loadContacts();
  } catch (error) {
    message.textContent = error.message;
  }
});

loadContacts().catch((error) => { message.textContent = error.message; });
