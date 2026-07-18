const form = document.getElementById('verifyForm');
const result = document.getElementById('result');
const serverResult = document.getElementById('serverResult');
const jsonOutput = document.getElementById('jsonOutput');
const verifyModal = document.getElementById('verifyModal');
const closeVerifyModal = document.getElementById('closeVerifyModal');

const params = new URLSearchParams(window.location.search);
document.getElementById('documentId').value = params.get('id') || '';
document.getElementById('signature').value = params.get('sig') || '';

if (window.self !== window.top && window.parent && window.frameElement?.id !== 'verifyFrame') {
  window.parent.postMessage({
    type: 'myrsu:verify-modal',
    url: window.location.href,
  }, window.location.origin);
}

verifyModal.showModal();
closeVerifyModal.addEventListener('click', () => {
  if (window.self === window.top && history.length > 1) {
    history.back();
    return;
  }
  verifyModal.close();
});

async function verifyServerFile() {
  const id = document.getElementById('documentId').value;
  const signature = document.getElementById('signature').value;

  if (!id || !signature) {
    return;
  }

  const response = await fetch(`../api/v1/documents/${id}/verify?sig=${encodeURIComponent(signature)}`);
  const data = await response.json();
  jsonOutput.textContent = JSON.stringify(data, null, 2);

  const valid = Boolean(data.data?.valid);
  serverResult.textContent = valid
    ? 'QR valido: documento ufficiale server autentico. Per controllare questa copia PDF, caricala qui sotto.'
    : 'QR NON valido.';
  serverResult.className = valid ? 'alert success' : 'alert danger';
}

verifyServerFile();

form.addEventListener('submit', async (event) => {
  event.preventDefault();

  const id = document.getElementById('documentId').value;
  const signature = document.getElementById('signature').value;
  const file = document.getElementById('file').files[0];
  const body = new FormData();

  body.append('sig', signature);
  body.append('file', file);

  const response = await fetch(`../api/v1/documents/${id}/verify-file`, {
    method: 'POST',
    body,
  });

  const data = await response.json();
  jsonOutput.textContent = JSON.stringify(data, null, 2);

  const valid = Boolean(data.data?.valid);
  result.textContent = valid ? 'Copia PDF valida' : 'Copia PDF NON valida';
  result.className = valid ? 'alert success' : 'alert danger';
});
