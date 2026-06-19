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

api(`/documents/${id}`).then((document) => {
  editForm.original_name.value = document.original_name;
  editForm.category.value = document.category || '';
  editForm.visibility.value = document.visibility;
});

editForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(editForm);
  await api(`/documents/${id}`, {
    method: 'PATCH',
    body: JSON.stringify({ visibility: form.get('visibility') }),
  });
  message.textContent = 'Document saved';
});
