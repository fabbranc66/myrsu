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

api(`/protocol/${id}`).then((entry) => {
  details.innerHTML = [
    row('Number', entry.protocol_number),
    row('Direction', entry.direction),
    row('Type', entry.type_code),
    row('Subject', entry.subject),
    row('Document ID', entry.document_id),
    row('Created by', entry.created_by_name),
    row('Created at', entry.created_at),
    row('Canceled by', entry.canceled_by_name),
    row('Canceled at', entry.canceled_at),
  ].join('');
});
