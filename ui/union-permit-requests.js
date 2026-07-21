const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const form = document.querySelector('#requestForm');
const delegateSelect = document.querySelector('#delegateSelect');
const delegateInfo = document.querySelector('#delegateInfo');
const balances = document.querySelector('#balances');
const requestsTable = document.querySelector('#requestsTable');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const submitButton = document.querySelector('#submitButton');
const resetFormButton = document.querySelector('#resetFormButton');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
let currentDelegates = [];
let currentRequests = [];
let documentPreviewUrl = null;

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function permitLabel(type) {
  return type === 'rls' ? 'RLS sicurezza' : 'RSU sindacale';
}

function scopeLabel(scope) {
  return scope === 'external' ? 'Esterno' : 'Interno';
}

function statusLabel(status) {
  return status === 'canceled' ? 'annullato' : 'emesso';
}

async function load() {
  const [me, delegates] = await Promise.all([
    api('/me'),
    api('/union-permits/delegates'),
  ]);
  currentDelegates = delegates;
  delegateSelect.innerHTML = delegates.map((delegate) => (
    `<option value="${delegate.id}">${escapeHtml(delegate.name)} (${escapeHtml(delegate.roles)})</option>`
  )).join('');
  delegateSelect.disabled = !(me.roles || []).includes('admin');
  await loadDelegate();
}

async function loadDelegate() {
  const delegate = currentDelegates.find((item) => String(item.id) === String(delegateSelect.value));
  if (!delegate) return;
  const [allocations, requests] = await Promise.all([
    api(`/union-permits/allocations?user_id=${delegate.id}`),
    api(`/union-permits/requests?user_id=${delegate.id}`),
  ]);
  currentRequests = requests;
  form.union_name.value = delegate.union_code || 'RSU';
  delegateInfo.innerHTML = `<strong>${escapeHtml(delegate.name)}</strong><span>${escapeHtml(delegate.email)} | ${escapeHtml(delegate.mobile || delegate.phone || '-')} | ${escapeHtml(delegate.roles)}</span>`;
  balances.innerHTML = allocations.map((row) => {
    const remaining = Number(row.annual_hours) - Number(row.used_hours);
    return `<div class="stat-card"><strong>${permitLabel(row.permit_type)} ${row.year}</strong><span>${remaining.toFixed(2)} ore residue</span></div>`;
  }).join('') || '<p>Nessun monte ore assegnato.</p>';
  requestsTable.innerHTML = requests.map((row) => `<tr>
    <td>${escapeHtml(row.protocol_number || '-')}</td>
    <td>${escapeHtml(row.request_date)}</td>
    <td>${scopeLabel(row.request_scope)}</td>
    <td>${permitLabel(row.permit_type)}</td>
    <td>${escapeHtml(row.hours)}</td>
    <td>${statusLabel(row.status)}</td>
    <td>${escapeHtml(row.subject)}</td>
    <td class="actions-cell">${actions(row)}</td>
  </tr>`).join('');
}

delegateSelect.addEventListener('change', loadDelegate);

requestsTable.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;
  try {
    if (button.dataset.preview) await showDocument(button.dataset.preview);
    if (button.dataset.edit) editRequest(button.dataset.edit);
    if (button.dataset.delete) await deleteRequest(button.dataset.delete);
  } catch (error) {
    message.textContent = error.message;
  }
});

function actions(row) {
  const disabled = row.status === 'canceled';
  const preview = row.document_id ? `<button class="icon-action" data-preview="${row.document_id}" type="button" title="Visualizza">${MyRsuIcons.get('eye')}</button>` : '';
  const edit = disabled ? '' : `<button class="icon-action" data-edit="${row.id}" type="button" title="Modifica">${MyRsuIcons.get('edit')}</button>`;
  const remove = disabled ? '' : `<button class="icon-action danger" data-delete="${row.id}" type="button" title="Cancella">${MyRsuIcons.get('trash')}</button>`;
  return preview + edit + remove;
}

function editRequest(id) {
  const row = currentRequests.find((item) => String(item.id) === String(id));
  if (!row) return;
  form.request_id.value = row.id;
  form.request_scope.value = row.request_scope;
  form.permit_type.value = row.permit_type;
  form.union_name.value = row.union_name;
  form.subject.value = row.subject;
  form.request_date.value = row.request_date;
  form.start_at.value = String(row.start_at).replace(' ', 'T').slice(0, 16);
  form.end_at.value = String(row.end_at).replace(' ', 'T').slice(0, 16);
  form.hours.value = row.hours;
  form.notes.value = row.notes || '';
  delegateSelect.value = row.user_id;
  delegateSelect.disabled = true;
  submitButton.textContent = 'Salva e rigenera PDF';
  message.textContent = `Modifica permesso ${row.protocol_number || ''}`;
}

async function deleteRequest(id) {
  if (!confirm('Cancellare permesso?')) return;
  const data = await api(`/union-permits/requests/${id}`, { method: 'DELETE' });
  message.textContent = data.hours_restored ? 'Permesso cancellato, ore ripristinate.' : 'Permesso cancellato, ore non ripristinate.';
  resetForm();
  await loadDelegate();
}

async function showDocument(id) {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  documentPreview.src = `${apiBase}/documents/${id}/preview?token=${encodeURIComponent(token || '')}`;
  documentModal.showModal();
}

closeDocumentModal.addEventListener('click', () => {
  if (documentPreviewUrl) URL.revokeObjectURL(documentPreviewUrl);
  documentPreviewUrl = null;
  documentPreview.src = '';
  documentModal.close();
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const body = Object.fromEntries(new FormData(form).entries());
    const id = body.request_id;
    delete body.request_id;
    const data = await api(id ? `/union-permits/requests/${id}` : '/union-permits/requests', {
      method: id ? 'PATCH' : 'POST',
      body: JSON.stringify(body),
    });
    message.textContent = id ? 'Permesso salvato e PDF rigenerato.' : 'PDF richiesta generato.';
    resetForm();
    await loadDelegate();
    if (data.document?.id) await showDocument(data.document.id);
  } catch (error) {
    message.textContent = error.message;
  }
});

function resetForm() {
  const unionName = form.union_name.value;
  form.reset();
  form.union_name.value = unionName || 'RSU';
  form.request_id.value = '';
  form.request_date.value = new Date().toISOString().slice(0, 10);
  delegateSelect.disabled = currentDelegates.length <= 1;
  submitButton.textContent = 'Genera PDF richiesta';
}

resetFormButton.addEventListener('click', resetForm);

if (!token) window.location.href = 'login.html';
form.request_date.value = new Date().toISOString().slice(0, 10);
load().catch((error) => { message.textContent = error.message; });
