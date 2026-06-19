const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const id = new URLSearchParams(window.location.search).get('id');
const details = document.querySelector('#details');
const jsonOutput = document.querySelector('#jsonOutput');

async function api(path) {
  const response = await fetch(`${apiBase}${path}`, {
    headers: { Authorization: `Bearer ${token}` },
  });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

function row(label, value) {
  return `<dt>${label}</dt><dd>${value || '-'}</dd>`;
}

api(`/documents/${id}`).then((document) => {
  details.innerHTML = [
    row('Name', document.original_name),
    row('Mime', document.mime_type),
    row('Size', document.size_bytes),
    row('Category', document.category),
    row('Visibility', document.visibility),
    row('PDF', document.pdf_public_path),
    row('Conversion', document.conversion_status),
    row('Checksum', document.checksum_sha256),
    row('Uploaded by', document.uploaded_by),
    row('Created at', document.created_at),
  ].join('');
});
