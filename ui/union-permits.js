const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const form = document.querySelector('#permitForm');
const allocationForm = document.querySelector('#allocationForm');
const allocationUser = allocationForm.querySelector('[name="user_id"]');
const allocationsTable = document.querySelector('#allocationsTable');
const result = document.querySelector('#result');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
let currentCalculation = null;

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

function render(data) {
  result.innerHTML = `<div class="grid">
    <div class="stat-card"><strong>RSU annue</strong><span>${data.rsu.annual_hours} ore</span></div>
    <div class="stat-card"><strong>Componenti RSU</strong><span>${data.rsu.members}</span></div>
    <div class="stat-card"><strong>Media per RSU</strong><span>${data.rsu.hours_average_each} ore</span></div>
    <div class="stat-card"><strong>RLS annue totali</strong><span>${data.rls.hours_total} ore</span></div>
    <div class="stat-card"><strong>Totale annuo</strong><span>${data.total.annual_hours} ore</span></div>
  </div>
  <table>
    <tbody>
      <tr><th>Base RSU</th><td>${escapeHtml(data.rsu.basis)}</td></tr>
      <tr><th>Ore RLS cadauno</th><td>${data.rls.hours_each}</td></tr>
      <tr><th>Regola RLS</th><td>${escapeHtml(data.rls.rule_note)}</td></tr>
      <tr><th>Media mensile totale</th><td>${data.total.monthly_average} ore</td></tr>
      <tr><th>Note</th><td>${data.notes.map(escapeHtml).join('<br>')}</td></tr>
    </tbody>
  </table>`;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function permitLabel(type) {
  return type === 'rls' ? 'RLS sicurezza' : 'RSU sindacale';
}

function fillCalculatedHours() {
  if (!currentCalculation) return;
  const roles = allocationUser.selectedOptions[0]?.dataset.roles || '';
  const rsu = Number(currentCalculation.rsu.hours_average_each);
  const rls = roles.includes('rls') ? Number(currentCalculation.rls.hours_each) : 0;
  allocationForm.annual_hours.value = (rsu + rls).toFixed(2);
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const data = await api('/union-permits/analyze', {
      method: 'POST',
      body: JSON.stringify(Object.fromEntries(new FormData(form).entries())),
    });
    currentCalculation = data;
    fillCalculatedHours();
    render(data);
    message.textContent = 'Calcolo completato.';
  } catch (error) {
    message.textContent = error.message;
  }
});

if (!token) window.location.href = 'app/index.html';
requireAdmin();
allocationForm.year.value = new Date().getFullYear();
loadAdminData();

allocationForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!currentCalculation) {
    message.textContent = 'Prima calcola il monte ore.';
    return;
  }
  try {
    const selected = allocationUser.selectedOptions[0];
    const roles = selected?.dataset.roles || '';
    const base = Object.fromEntries(new FormData(allocationForm).entries());
    await saveAllocation(base, roles);
    message.textContent = roles.includes('rls')
      ? 'Monte ore RSU + RLS salvato.'
      : 'Monte ore RSU salvato.';
    await loadAllocations();
  } catch (error) {
    message.textContent = error.message;
  }
});

async function requireAdmin() {
  try {
    const me = await api('/me');
    if (!(me.roles || []).includes('admin')) window.location.href = 'app/index.html';
  } catch {
    window.location.href = 'app/index.html';
  }
}

async function loadAdminData() {
  try {
    const users = await api('/users');
    allocationUser.innerHTML = users
      .filter((user) => String(user.roles || '').match(/admin|delegato|rls/))
      .map((user) => `<option value="${user.id}" data-roles="${escapeHtml(user.roles)}">${escapeHtml(user.name)} (${escapeHtml(user.roles)})</option>`)
    .join('');
    fillCalculatedHours();
    await loadAllocations();
  } catch (error) {
    message.textContent = error.message;
  }
}

allocationUser.addEventListener('change', fillCalculatedHours);

async function saveAllocation(base, roles) {
  return api('/union-permits/allocations', {
    method: 'POST',
    body: JSON.stringify({
      ...base,
      permit_type: 'rsu',
      annual_hours: currentCalculation.rsu.hours_average_each,
      rls_hours: roles.includes('rls') ? currentCalculation.rls.hours_each : 0,
    }),
  });
}

async function loadAllocations() {
  const rows = await api('/union-permits/allocations');
  allocationsTable.innerHTML = rows.map((row) => {
    const remaining = Number(row.annual_hours) - Number(row.used_hours);
    return `<tr>
      <td>${escapeHtml(row.user_name)}</td>
      <td>${row.year}</td>
      <td>${permitLabel(row.permit_type)}</td>
      <td>${row.annual_hours}</td>
      <td>${row.used_hours}</td>
      <td>${remaining.toFixed(2)}</td>
    </tr>`;
  }).join('');
}
