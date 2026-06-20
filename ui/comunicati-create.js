const apiBase = '../api/v1';
let token = sessionStorage.getItem('token');
const form = document.querySelector('#comunicatoForm');
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

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(form).entries());
  const result = await api('/comunicati', { method: 'POST', body: JSON.stringify(data) });
  message.textContent = `Creato ${result.protocol.protocol_number}`;
  form.reset();
});

if (!token) {
  window.location.href = 'app/index.html';
}
