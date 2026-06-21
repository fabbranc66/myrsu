const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const reportForm = document.querySelector('#reportForm');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');

async function api(path, options = {}) {
  const headers = options.headers || {};
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

reportForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(reportForm).entries());
  const result = await api('/reports', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  message.textContent = `Segnalazione inviata: ${result.report.tracking_code}`;
  reportForm.reset();
});
