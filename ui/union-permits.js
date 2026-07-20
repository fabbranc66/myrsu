const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const form = document.querySelector('#permitForm');
const result = document.querySelector('#result');
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

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const data = await api('/union-permits/analyze', {
      method: 'POST',
      body: JSON.stringify(Object.fromEntries(new FormData(form).entries())),
    });
    render(data);
    message.textContent = 'Calcolo completato.';
  } catch (error) {
    message.textContent = error.message;
  }
});

if (!token) window.location.href = 'app/index.html';
requireAdmin();

async function requireAdmin() {
  try {
    const me = await api('/me');
    if (!(me.roles || []).includes('admin')) window.location.href = 'app/index.html';
  } catch {
    window.location.href = 'app/index.html';
  }
}
