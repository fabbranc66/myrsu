export const apiBase = '../api/v1';
export const token = sessionStorage.getItem('token') || localStorage.getItem('token');

export async function roomApi(path, options = {}) {
  const headers = { ...(options.headers || {}), Authorization: `Bearer ${token || ''}` };
  if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
  document.querySelector('#jsonOutput').textContent = JSON.stringify(payload || {}, null, 2);
  if (!response.ok) throw new Error(payload?.error?.message || 'Operazione fallita.');
  return payload?.data;
}

export function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  }[char]));
}

export function statusLabel(status) {
  return ({
    draft: 'bozza', open: 'aperto', in_progress: 'in corso', suspended: 'sospeso',
    closed: 'chiuso', archived: 'archiviato', cancelled: 'annullato',
  }[status] || status);
}

export function showError(error) {
  document.querySelector('#message').textContent = error.message || String(error);
}
