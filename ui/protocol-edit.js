const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const id = new URLSearchParams(window.location.search).get('id');
const editForm = document.querySelector('#editForm');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

api(`/protocol/${id}`).then((entry) => {
  editForm.protocol_number.value = entry.protocol_number;
  editForm.subject.value = entry.subject;
  editForm.document_id.value = entry.document_id || '';
});

editForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(editForm);
  await api(`/protocol/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({
      subject: form.get('subject'),
      document_id: form.get('document_id') || null,
    }),
  });
  message.textContent = 'Protocol saved';
});
