const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const queueTable = document.querySelector('#queueTable');
const alertBox = document.querySelector('#alertBox');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const refreshButton = document.querySelector('#refreshButton');
const processButton = document.querySelector('#processButton');

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

async function loadQueue() {
  const data = await api('/local/comunicati/pending');
  alertBox.textContent = data.count > 0
    ? `Attenzione: ${data.count} operazioni pendenti.`
    : 'Nessuna operazione pendente.';
  alertBox.className = `status-box ${data.count > 0 ? 'status-alert' : 'status-ok'}`;
  queueTable.innerHTML = data.items.map(row).join('') || '<tr><td colspan="5">Nessun elemento</td></tr>';
}

function row(item) {
  return `
    <tr>
      <td>${item.id}</td>
      <td>${item.protocol_number || '-'}</td>
      <td>${escapeHtml(item.subject || item.comunicato?.title || '-')}</td>
      <td>${item.protocol_created_at || '-'}</td>
      <td>in attesa</td>
    </tr>
  `;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

refreshButton.addEventListener('click', () => {
  loadQueue().catch((error) => {
    message.textContent = error.message;
  });
});

processButton.addEventListener('click', async () => {
  const data = await api('/local/comunicati/process', { method: 'POST', body: '{}' });
  message.textContent = `Processati: ${data.processed} - Errori: ${data.errors}`;
  await loadQueue();
});

async function boot() {
  if (!token) {
    window.location.replace('app/index.html');
    return;
  }

  const user = await api('/me');
  const roles = Array.isArray(user.roles) ? user.roles : [];
  if (!roles.includes('admin')) {
    window.location.replace('app/index.html');
    return;
  }
  await loadQueue();
}

boot().catch((error) => { message.textContent = error.message; });
