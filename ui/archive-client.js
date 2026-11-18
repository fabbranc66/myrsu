export const appRoot = window.location.pathname.split('/ui/')[0];
export const token = () => sessionStorage.getItem('token') || localStorage.getItem('token');

export async function archiveRequest(path, options = {}, output = null) {
  const headers = new Headers(options.headers);
  if (token()) headers.set('Authorization', `Bearer ${token()}`);
  if (options.body) headers.set('Content-Type', 'application/json');
  const response = await fetch(`${appRoot}/api/v1${path}`, { cache: 'no-store', ...options, headers });
  const payload = await response.json();
  if (output) output.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Archivio non disponibile.');
  return payload.data;
}

export function itemUrl(item) {
  const params = new URLSearchParams({ source: String(item.item_source_configuration_id || item.source_configuration_id), type: item.type || item.item_type, id: String(item.item_id || item.id) });
  return `${appRoot}/ui/archive-item.html?${params}`;
}

export function textElement(tag, text, className = '') {
  const element = document.createElement(tag);
  element.textContent = text ?? '-';
  if (className) element.className = className;
  return element;
}

export function statusLabel(status) {
  if (typeof MyRsuPracticeOptions !== 'undefined' && MyRsuPracticeOptions.statuses.some(([key]) => key === status)) return MyRsuPracticeOptions.label(MyRsuPracticeOptions.statuses, status);
  return ({ ready: 'Pronto', pending: 'In elaborazione', failed: 'Errore', new: 'Nuova', closed: 'Chiusa', archived: 'Archiviata' })[status] || status;
}
