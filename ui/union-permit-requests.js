const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const form = document.querySelector('#requestForm');
const balances = document.querySelector('#balances');
const requestsTable = document.querySelector('#requestsTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');

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

async function load() {
  const [allocations, requests] = await Promise.all([
    api('/union-permits/allocations'),
    api('/union-permits/requests'),
  ]);
  balances.innerHTML = allocations.map((row) => {
    const remaining = Number(row.annual_hours) - Number(row.used_hours);
    return `<div class="stat-card"><strong>${permitLabel(row.permit_type)} ${row.year}</strong><span>${remaining.toFixed(2)} ore residue</span></div>`;
  }).join('') || '<p>Nessun monte ore assegnato.</p>';
  requestsTable.innerHTML = requests.map((row) => `<tr>
    <td>${escapeHtml(row.request_date)}</td>
    <td>${permitLabel(row.permit_type)}</td>
    <td>${escapeHtml(row.hours)}</td>
    <td>${escapeHtml(row.subject)}</td>
    <td>${row.document_id ? `<a href="document-view.html?id=${row.document_id}">Visualizza</a>` : '-'}</td>
  </tr>`).join('');
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const data = await api('/union-permits/requests', {
      method: 'POST',
      body: JSON.stringify(Object.fromEntries(new FormData(form).entries())),
    });
    message.textContent = 'PDF richiesta generato.';
    form.reset();
    form.union_name.value = 'RSU';
    form.request_date.value = new Date().toISOString().slice(0, 10);
    await load();
    if (data.document?.id) window.open(`document-view.html?id=${data.document.id}`, '_blank');
  } catch (error) {
    message.textContent = error.message;
  }
});

if (!token) window.location.href = 'login.html';
form.request_date.value = new Date().toISOString().slice(0, 10);
load().catch((error) => { message.textContent = error.message; });
