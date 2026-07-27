const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const contractForm = document.querySelector('#contractForm');
const movementForm = document.querySelector('#movementForm');
const statementForm = document.querySelector('#statementForm');
const reconciliationForm = document.querySelector('#reconciliationForm');
const contractsTable = document.querySelector('#contractsTable');
const movementsTable = document.querySelector('#movementsTable');
const fundsSummary = document.querySelector('#fundsSummary');
const statementResult = document.querySelector('#statementResult');
const documentModal = document.querySelector('#documentModal');
const documentPreview = document.querySelector('#documentPreview');
const closeDocumentModal = document.querySelector('#closeDocumentModal');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
let contracts = [];
let movements = [];

if (!token) window.location.href = 'login.html';

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}), Authorization: `Bearer ${token}` };
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Operazione fallita');
  return payload.data;
}

async function loadFunds() {
  const data = await api('/funds');
  contracts = data.contracts;
  movements = data.movements;
  renderSummary(data.balance);
  renderContracts();
  renderMovements();
  fillContractSelect();
}

function renderSummary(balance) {
  fundsSummary.innerHTML = [
    card('Entrate', money(balance.income)),
    card('Uscite', money(balance.expense)),
    card('Saldo', money(balance.balance)),
  ].join('');
}

function renderContracts() {
  contractsTable.innerHTML = contracts.map((row) => `<tr>
    <td>${escapeHtml(row.supplier_name)}</td>
    <td>${escapeHtml(row.contract_number || '-')}</td>
    <td>${escapeHtml(row.start_date)} / ${escapeHtml(row.end_date || '-')}</td>
    <td>${statusLabel(row.status)}</td>
    <td>${documentCell(row.document_id, row.document_name)}</td>
    <td class="actions-cell">${actions('contract', row.id)}</td>
  </tr>`).join('') || '<tr><td colspan="6">Nessun contratto.</td></tr>';
}

function renderMovements() {
  movementsTable.innerHTML = movements.map((row) => `<tr>
    <td>${escapeHtml(row.movement_date)}</td>
    <td>${typeLabel(row.movement_type)}</td>
    <td>${money(row.amount)}</td>
    <td>${escapeHtml(row.reason)}</td>
    <td>${escapeHtml(row.supplier_name || '-')}</td>
    <td>${documentCell(row.document_id, row.document_name)}</td>
    <td class="actions-cell">${actions('movement', row.id)}</td>
  </tr>`).join('') || '<tr><td colspan="7">Nessun movimento.</td></tr>';
}

function fillContractSelect() {
  movementForm.contract_id.innerHTML = '<option value="">-</option>' + contracts.map((row) => `<option value="${row.id}">${escapeHtml(row.supplier_name)}</option>`).join('');
  updateMovementContractState();
}

contractForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const formData = new FormData(contractForm);
  const file = formData.get('file');
  if (file instanceof File && file.size > 0) {
    const upload = new FormData();
    upload.append('file', file);
    upload.append('category', 'documenti');
    upload.append('visibility', 'rsu');
    const document = await uploadDocument(upload);
    formData.set('document_id', document.id);
  }
  formData.delete('file');
  const body = clean(Object.fromEntries(formData.entries()));
  const id = body.id;
  delete body.id;
  const result = await api(id ? `/funds/contracts/${id}` : '/funds/contracts', { method: id ? 'PATCH' : 'POST', body: JSON.stringify(body) });
  message.textContent = result.protocol_error
    ? `Contratto salvato. Protocollo non creato: ${result.protocol_error}`
    : `Contratto salvato${result.protocol?.protocol_number ? ` e protocollato ${result.protocol.protocol_number}` : ''}`;
  contractForm.reset();
  await loadFunds();
});

movementForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const formData = new FormData(movementForm);
  if (formData.get('movement_type') === 'expense') formData.set('contract_id', '');
  const file = formData.get('file');
  if (file instanceof File && file.size > 0) {
    const upload = new FormData();
    upload.append('file', file);
    upload.append('category', 'documenti');
    upload.append('visibility', 'rsu');
    const document = await uploadDocument(upload);
    formData.set('document_id', document.id);
  }
  formData.delete('file');
  const body = clean(Object.fromEntries(formData.entries()));
  const id = body.id;
  delete body.id;
  await api(id ? `/funds/movements/${id}` : '/funds/movements', { method: id ? 'PATCH' : 'POST', body: JSON.stringify(body) });
  message.textContent = 'Movimento salvato';
  movementForm.reset();
  await loadFunds();
});

statementForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const formData = new FormData(statementForm);
  const file = formData.get('file');
  if (!(file instanceof File) || file.size === 0) throw new Error('Documento estratto conto mancante');
  const upload = new FormData();
  upload.append('file', file);
  upload.append('category', 'documenti');
  upload.append('visibility', 'rsu');
  const document = await uploadDocument(upload);
  const body = clean({
    statement_date: formData.get('statement_date'),
    statement_balance: formData.get('statement_balance'),
    document_id: document.id,
  });
  const result = await api('/funds/statements', { method: 'POST', body: JSON.stringify(body) });
  renderStatementResult(result);
  message.textContent = `Estratto conto protocollato ${result.protocol.protocol_number}`;
  statementForm.reset();
});

reconciliationForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const body = clean(Object.fromEntries(new FormData(reconciliationForm).entries()));
  body.movement_type = 'expense';
  body.contract_id = '';
  await api('/funds/movements', { method: 'POST', body: JSON.stringify(body) });
  message.textContent = 'Voce contabile registrata';
  reconciliationForm.classList.add('hidden');
  reconciliationForm.reset();
  await loadFunds();
});

contractsTable.addEventListener('click', tableAction);
movementsTable.addEventListener('click', tableAction);
document.querySelector('#resetContract').addEventListener('click', () => contractForm.reset());
document.querySelector('#resetMovement').addEventListener('click', () => {
  movementForm.reset();
  updateMovementContractState();
});
movementForm.movement_type.addEventListener('change', updateMovementContractState);

async function tableAction(event) {
  const button = event.target.closest('button');
  if (!button) return;
  if (button.dataset.viewDocument) return showDocument(button.dataset.viewDocument);
  const type = button.dataset.type;
  const id = Number(button.dataset.id);
  if (button.dataset.action === 'edit') return fillEdit(type, id);
  if (!confirm('Eliminare elemento?')) return;
  await api(type === 'contract' ? `/funds/contracts/${id}` : `/funds/movements/${id}`, { method: 'DELETE' });
  message.textContent = 'Elemento eliminato';
  await loadFunds();
}

function fillEdit(type, id) {
  const row = (type === 'contract' ? contracts : movements).find((item) => Number(item.id) === id);
  const form = type === 'contract' ? contractForm : movementForm;
  Object.keys(row || {}).forEach((key) => {
    if (form[key]) form[key].value = row[key] || '';
  });
  if (type === 'movement') updateMovementContractState();
}

function updateMovementContractState() {
  const isExpense = movementForm.movement_type.value === 'expense';
  movementForm.contract_id.disabled = isExpense;
  if (isExpense) movementForm.contract_id.value = '';
}

function renderStatementResult(result) {
  const difference = Number(result.reconciliation.difference || 0);
  statementResult.classList.remove('hidden');
  statementResult.innerHTML = [
    `<strong>Riconciliazione al ${escapeHtml(result.statement_date)}</strong>`,
    `<span>Entrate: ${money(result.reconciliation.income)}</span>`,
    `<span>Uscite: ${money(result.reconciliation.expense)}</span>`,
    `<span>Saldo: ${money(result.reconciliation.balance)}</span>`,
    `<span>Saldo estratto: ${money(result.reconciliation.statement_balance)}</span>`,
    `<span>Differenza: ${money(difference)}</span>`,
  ].join('');
  reconciliationForm.classList.toggle('hidden', difference >= 0);
  if (difference < 0) {
    reconciliationForm.movement_date.value = result.statement_date;
    reconciliationForm.amount.value = Math.abs(difference).toFixed(2);
  }
}

function actions(type, id) {
  return `<button class="icon-action" data-action="edit" data-type="${type}" data-id="${id}" title="Modifica">${MyRsuIcons.get('edit')}</button><button class="icon-action danger" data-action="delete" data-type="${type}" data-id="${id}" title="Elimina">${MyRsuIcons.get('trash')}</button>`;
}

async function uploadDocument(body) {
  const file = body.get('file');
  if (file instanceof File && file.type === 'application/pdf' && window.MyRsuPdfRasterizer) {
    message.textContent = 'Preparazione PDF contratto...';
    const renderedPdf = await MyRsuPdfRasterizer.rasterizePdf(file, () => {});
    body.append('rendered_pdf', renderedPdf, 'rendered-pages.pdf');
  }

  const response = await fetch(`${apiBase}/documents`, {
    method: 'POST',
    headers: { Authorization: `Bearer ${token}` },
    body,
  });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Upload documento fallito');
  return payload.data;
}

function showDocument(id) {
  documentPreview.src = `${apiBase}/documents/${id}/preview?token=${encodeURIComponent(token || '')}`;
  documentModal.showModal();
}

closeDocumentModal.addEventListener('click', () => {
  documentPreview.src = '';
  documentModal.close();
});

function documentCell(id, name) {
  return id ? `<button class="icon-action" data-view-document="${id}" title="Visualizza">${MyRsuIcons.get('eye')}</button> ${escapeHtml(name || `doc ${id}`)}` : '-';
}

function clean(data) {
  Object.keys(data).forEach((key) => { if (data[key] === '') data[key] = null; });
  return data;
}

function card(label, value) { return `<div class="fund-card"><span>${label}</span><strong>${value}</strong></div>`; }
function money(value) { return Number(value || 0).toLocaleString('it-IT', { style: 'currency', currency: 'EUR' }); }
function statusLabel(value) { return { active: 'attivo', expired: 'scaduto', closed: 'chiuso' }[value] || value; }
function typeLabel(value) { return value === 'income' ? 'entrata' : 'uscita'; }
function escapeHtml(value) { return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char])); }

loadFunds().catch((error) => { message.textContent = error.message; });
