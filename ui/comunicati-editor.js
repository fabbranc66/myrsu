const apiBase = '../api/v1';
let token = sessionStorage.getItem('token');
const form = document.querySelector('#comunicatoForm');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const uploadProgress = document.querySelector('#uploadProgress');
const uploadProgressFill = document.querySelector('#uploadProgressFill');
const uploadProgressText = document.querySelector('#uploadProgressText');
const draftActions = document.querySelector('#draftActions');
const generateButton = document.querySelector('#generateButton');
const params = new URLSearchParams(window.location.search);
let draftId = Number(params.get('id') || 0) || null;
let editingDraft = draftId !== null;

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

function setUploadProgress(value) {
  if (!uploadProgress || !uploadProgressFill || !uploadProgressText) return;
  uploadProgress.classList.remove('hidden');
  uploadProgressFill.style.width = `${value}%`;
  uploadProgressText.textContent = `${value}%`;
}

function resetUploadProgress() {
  uploadProgress.classList.add('hidden');
  uploadProgressFill.style.width = '0%';
  uploadProgressText.textContent = '0%';
}

function createComunicato(data) {
  return api('/comunicati', { method: 'POST', body: JSON.stringify(data) });
}

async function loadDraft() {
  if (!draftId) return;
  const document = await api(`/documents/${draftId}`);
  if (document.category !== 'comunicati' || document.conversion_status !== 'pending') throw new Error('Bozza non trovata.');
  form.title.value = document.comunicato?.title || '';
  form.body.value = document.comunicato?.body || '';
  form.visibility.value = document.visibility || 'public';
  draftActions.classList.remove('hidden');
  message.textContent = 'Bozza caricata.';
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  setUploadProgress(15);
  try {
    const data = Object.fromEntries(new FormData(form).entries());
    const result = editingDraft
      ? { document: await api(`/documents/${draftId}`, { method: 'PATCH', body: JSON.stringify(data) }) }
      : await createComunicato(data);
    setUploadProgress(100);
    draftId = Number(result.document.id);
    editingDraft = true;
    draftActions.classList.remove('hidden');
    message.textContent = 'Bozza salvata. Documento ufficiale non ancora generato.';
  } catch (error) {
    message.textContent = error.message;
  } finally {
    window.setTimeout(resetUploadProgress, 500);
  }
});

generateButton.addEventListener('click', async () => {
  if (!draftId) return;
  message.textContent = '';
  setUploadProgress(15);
  try {
    const data = await api(`/comunicati/${draftId}/generate`, { method: 'POST' });
    setUploadProgress(100);
    message.textContent = `Generato ${data.protocol.protocol_number}`;
    window.setTimeout(() => { window.location.href = 'comunicati-create.html'; }, 700);
  } catch (error) {
    message.textContent = error.message;
  } finally {
    window.setTimeout(resetUploadProgress, 500);
  }
});

if (!token) window.location.href = 'app/index.html';
loadDraft().catch((error) => {
  message.textContent = error.message;
});
