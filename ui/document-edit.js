const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const id = new URLSearchParams(window.location.search).get('id');
const editForm = document.querySelector('#editForm');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const titleField = document.querySelector('#titleField');
const bodyField = document.querySelector('#bodyField');
const saveButton = document.querySelector('#saveButton');
const previewButton = document.querySelector('#previewButton');
let isComunicato = false;
let isDraft = false;

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
  if (document.category === 'comunicati' && document.conversion_status === 'pending') {
    window.location.href = `comunicati-editor.html?id=${id}`;
    return;
  }
  previewButton.classList.remove('hidden');
  if (document.category === 'comunicati' && document.comunicato) {
    isComunicato = true;
    isDraft = document.conversion_status === 'pending';
    titleField.classList.remove('hidden');
    bodyField.classList.remove('hidden');
    saveButton.textContent = isDraft ? 'Salva bozza' : 'Salva e rigenera PDF';
    titleField.value = document.comunicato.title || '';
    bodyField.value = document.comunicato.body || '';
  }
});

previewButton.addEventListener('click', async () => {
  try {
    const response = await fetch(`${apiBase}/documents/${id}/preview`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    if (!response.ok) throw new Error('Anteprima non disponibile.');
    const url = URL.createObjectURL(await response.blob());
    window.open(url, '_blank', 'noopener');
  } catch (error) {
    message.className = 'error-message';
    message.textContent = error.message;
  }
});

editForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(editForm);
  saveButton.disabled = true;
  saveButton.textContent = isDraft ? 'Salvataggio bozza...' : (isComunicato ? 'Rigenerazione in corso...' : 'Salvataggio...');
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
    message.innerHTML = isDraft
      ? 'Bozza aggiornata. <a href="documents.html">Torna ad archivio</a>'
      : isComunicato
      ? 'Conferma: comunicato aggiornato, PDF ufficiale rigenerato e firma aggiornata. <a href="documents.html">Torna ad archivio</a>'
      : 'Documento salvato. <a href="documents.html">Torna ad archivio</a>';
  } catch (error) {
    message.className = 'error-message';
    message.textContent = error.message;
  } finally {
    saveButton.disabled = false;
    saveButton.textContent = isDraft ? 'Salva bozza' : (isComunicato ? 'Salva e rigenera PDF' : 'Salva');
  }
});
