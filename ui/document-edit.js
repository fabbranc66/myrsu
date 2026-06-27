const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const id = new URLSearchParams(window.location.search).get('id');
const editForm = document.querySelector('#editForm');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const titleField = document.querySelector('#titleField');
const bodyField = document.querySelector('#bodyField');
const saveButton = document.querySelector('#saveButton');
let isComunicato = false;

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
  if (document.category === 'comunicati' && document.comunicato) {
    isComunicato = true;
    titleField.classList.remove('hidden');
    bodyField.classList.remove('hidden');
    saveButton.textContent = 'Salva e rigenera PDF';
    titleField.value = document.comunicato.title || '';
    bodyField.value = document.comunicato.body || '';
  }
});

editForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(editForm);
  saveButton.disabled = true;
  saveButton.textContent = isComunicato ? 'Rigenerazione in corso...' : 'Salvataggio...';
  message.className = '';
  message.textContent = '';

  try {
    await api(`/documents/${id}`, {
      method: 'PATCH',
      body: JSON.stringify({
        visibility: form.get('visibility'),
        title: form.get('title'),
        body: form.get('body'),
      }),
    });
    message.className = 'success-message';
    message.textContent = isComunicato
      ? 'Conferma: comunicato aggiornato, PDF ufficiale rigenerato e firma aggiornata.'
      : 'Documento salvato.';
  } catch (error) {
    message.className = 'error-message';
    message.textContent = error.message;
  } finally {
    saveButton.disabled = false;
    saveButton.textContent = isComunicato ? 'Salva e rigenera PDF' : 'Salva';
  }
});
