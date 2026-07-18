const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const assembliesTable = document.querySelector('#assembliesTable');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const convocationModal = document.querySelector('#convocationModal');
const convocationPreview = document.querySelector('#convocationPreview');
const closeConvocationModal = document.querySelector('#closeConvocationModal');

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

async function loadAssemblies() {
  const assemblies = await api('/workers-assemblies');
  assembliesTable.innerHTML = assemblies.map(row).join('');
}

function row(assembly) {
  const sessions = (assembly.sessions || []).map((item) => `${item.shift_label} ${item.assembly_date} ${String(item.time_start).slice(0, 5)}`).join('<br>');
  const attachmentCount = Array.isArray(assembly.documents) ? assembly.documents.length : 0;
  const convocation = assembly.public_document_id
    ? `<button class="icon-action" data-view-convocation="${assembly.public_document_id}" title="Visualizza convocazione">${MyRsuIcons.get('eye')}</button>`
    : `<button class="icon-action" data-public-convocation="${assembly.id}" title="Genera convocazione">${MyRsuIcons.get('document')}</button>`;
  const minutes = assembly.minutes_document_id
    ? `<button class="icon-action" data-view-convocation="${assembly.minutes_document_id}" title="Visualizza verbale">${MyRsuIcons.get('note')}</button><button class="icon-action" data-minutes="${assembly.id}" title="Rigenera verbale">${MyRsuIcons.get('save')}</button>`
    : `<button class="icon-action" data-minutes="${assembly.id}" title="Genera verbale">${MyRsuIcons.get('save')}</button>`;
  const vote = Number(assembly.voting_enabled) === 1 ? escapeHtml(assembly.voting_subject || 'predisposto') : '-';
  return `<tr>
    <td>${escapeHtml(assembly.title)}</td>
    <td>${sessions || '-'}</td>
    <td>${translateStatus(assembly.status)}</td>
    <td>${translateVisibility(assembly.visibility)}</td>
    <td>allegati ${attachmentCount}</td>
    <td>${vote}</td>
    <td class="actions-cell">${convocation}${minutes}<a class="icon-action" href="workers-assembly-editor.html?id=${assembly.id}" title="Modifica">${MyRsuIcons.get('edit')}</a><a class="icon-action" href="workers-assembly-operational.html?id=${assembly.id}" title="Pagina operativa">${MyRsuIcons.get('logs')}</a><button class="icon-action danger" data-delete="${assembly.id}" title="Elimina">${MyRsuIcons.get('trash')}</button></td>
  </tr>`;
}

function translateStatus(value) {
  return { draft: 'bozza', called: 'convocata', done: 'svolta', cancelled: 'annullata' }[value] || value || '-';
}

function translateVisibility(value) {
  return { public: 'pubblico', members: 'membri', rsu: 'rsu' }[value] || value || '-';
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

assembliesTable.addEventListener('click', async (event) => {
  const view = event.target.closest('[data-view-convocation]');
  if (view) {
    openConvocation(view.dataset.viewConvocation);
    return;
  }
  const generate = event.target.closest('[data-public-convocation]');
  if (generate) {
    await createConvocation(generate.dataset.publicConvocation);
    return;
  }
  const minutes = event.target.closest('[data-minutes]');
  if (minutes) {
    await createMinutes(minutes.dataset.minutes);
    return;
  }
  const button = event.target.closest('[data-delete]');
  if (!button || !confirm('Eliminare assemblea?')) return;
  try {
    await api(`/workers-assemblies/${button.dataset.delete}`, { method: 'DELETE' });
    await loadAssemblies();
  } catch (error) {
    message.textContent = error.message;
  }
});

async function createConvocation(id) {
  try {
    const data = await api(`/workers-assemblies/${id}/public-convocation`, { method: 'POST', body: '{}' });
    const documentId = data.document?.id || data.assembly?.public_document_id;
    message.textContent = 'Convocazione generata correttamente.';
    await loadAssemblies();
    if (documentId) openConvocation(documentId);
  } catch (error) {
    message.textContent = error.message;
  }
}

async function createMinutes(id) {
  try {
    const data = await api(`/workers-assemblies/${id}/minutes`, { method: 'POST', body: '{}' });
    const documentId = data.document?.id || data.assembly?.minutes_document_id;
    message.textContent = 'Verbale generato/rigenerato correttamente.';
    await loadAssemblies();
    if (documentId) openConvocation(documentId);
  } catch (error) {
    message.textContent = error.message;
  }
}

function openConvocation(documentId) {
  convocationPreview.src = `${apiBase}/documents/${documentId}/preview?token=${encodeURIComponent(token || '')}`;
  convocationModal.classList.remove('hidden');
}

closeConvocationModal.addEventListener('click', closeConvocation);
convocationModal.addEventListener('click', (event) => {
  if (event.target === convocationModal) closeConvocation();
});

function closeConvocation() {
  convocationPreview.src = '';
  convocationModal.classList.add('hidden');
}

if (!token) window.location.href = 'app/index.html';
loadAssemblies().catch((error) => { message.textContent = error.message; });
