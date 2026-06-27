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
let draftId = null;

function setUploadProgress(value) {
  if (!uploadProgress || !uploadProgressFill || !uploadProgressText) return;
  uploadProgressFill.style.width = `${value}%`;
  uploadProgressText.textContent = `${value}%`;
}

function resetUploadProgress() {
  if (!uploadProgress || !uploadProgressFill || !uploadProgressText) return;
  uploadProgress.classList.add('hidden');
  uploadProgressFill.style.width = '0%';
  uploadProgressText.textContent = '0%';
}

function createComunicato(data) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', `${apiBase}/comunicati`);
    xhr.setRequestHeader('Content-Type', 'application/json');
    if (token) xhr.setRequestHeader('Authorization', `Bearer ${token}`);

    xhr.upload.addEventListener('progress', (event) => {
      if (!event.lengthComputable) return;
      const value = Math.max(1, Math.min(95, Math.round((event.loaded / event.total) * 95)));
      setUploadProgress(value);
    });

    xhr.addEventListener('load', () => {
      const payload = xhr.responseText ? JSON.parse(xhr.responseText) : {};
      jsonOutput.textContent = JSON.stringify(payload, null, 2);
      if (xhr.status < 200 || xhr.status >= 300) {
        reject(new Error(payload.error?.message || 'Creazione fallita'));
        return;
      }
      resolve(payload.data);
    });

    xhr.addEventListener('error', () => reject(new Error('Creazione fallita')));
    xhr.send(JSON.stringify(data));
  });
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  message.textContent = '';
  if (uploadProgress) uploadProgress.classList.remove('hidden');
  setUploadProgress(0);

  try {
    const data = Object.fromEntries(new FormData(form).entries());
    const result = await createComunicato(data);
    setUploadProgress(100);
    await new Promise((resolve) => window.setTimeout(resolve, 250));
    draftId = Number(result.document.id);
    draftActions.classList.remove('hidden');
    message.textContent = 'Bozza salvata. Documento ufficiale non ancora generato.';
    form.reset();
  } catch (error) {
    message.textContent = error.message;
  } finally {
    window.setTimeout(resetUploadProgress, 500);
  }
});

generateButton.addEventListener('click', async () => {
  if (!draftId) return;
  message.textContent = '';
  uploadProgress.classList.remove('hidden');
  setUploadProgress(15);
  try {
    const response = await fetch(`${apiBase}/comunicati/${draftId}/generate`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` },
    });
    const payload = await response.json();
    jsonOutput.textContent = JSON.stringify(payload, null, 2);
    if (!response.ok) throw new Error(payload.error?.message || 'Generazione fallita');
    setUploadProgress(100);
    message.textContent = `Generato ${payload.data.protocol.protocol_number}`;
    draftActions.classList.add('hidden');
    draftId = null;
  } catch (error) {
    message.textContent = error.message;
  } finally {
    window.setTimeout(resetUploadProgress, 500);
  }
});

if (!token) {
  window.location.href = 'app/index.html';
}
