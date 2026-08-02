import { escapeHtml, roomApi, showError, statusLabel, token } from './room-api.js';
import { closeRoomPdf, openRoomPdf } from './room-pdf.js?v=20260802-rooms-2';

const roomId = Number(new URLSearchParams(location.search).get('id'));
const participantForm = document.querySelector('#participantForm');
const externalForm = document.querySelector('#externalForm');
const documentForm = document.querySelector('#documentForm');
let state;

if (!token) window.location.href = 'login.html';
if (!roomId) window.location.href = 'rooms.html';

async function load() {
  state = await roomApi(`/rooms/${roomId}`);
  render();
  if (canManage()) await loadOptions();
}

function render() {
  const { room } = state;
  document.querySelector('#roomCode').textContent = room.code;
  document.querySelector('#roomTitle').textContent = room.title;
  document.querySelector('#roomDescription').textContent = room.description || '';
  document.querySelector('#roomSummary').innerHTML = `<span>${escapeHtml(room.category_name)}</span><span>${statusLabel(room.status)}</span><span>Responsabile: ${escapeHtml(room.responsible_name)}</span>`;
  document.querySelector('#managePanel').classList.toggle('hidden', !canManage());
  participantForm.classList.toggle('hidden', !canManage());
  externalForm.classList.toggle('hidden', !canManage());
  documentForm.classList.toggle('hidden', !canManage());
  document.querySelector('#statusForm select[name="status"]').value = room.status;
  document.querySelector('#participants').innerHTML = state.participants.map(participantRow).join('') || '<p>Nessun partecipante.</p>';
  document.querySelector('#documents').innerHTML = state.documents.map(documentRow).join('') || '<p>Nessun documento condiviso.</p>';
  document.querySelector('#externalRoomLink').classList.remove('hidden');
}

function participantRow(item) {
  const external = item.participant_type === 'external';
  const removable = canManage() && (external || Number(item.user_id) !== Number(state.room.responsible_id));
  const remove = removable ? `<button class="icon-action danger" ${external ? `data-remove-external="${item.id}"` : `data-remove-user="${item.user_id}"`} title="Revoca accesso">${MyRsuIcons.get('trash')}</button>` : '';
  const status = external ? (item.registered_at ? 'identificato' : item.verification_sent_at ? 'e-mail inviata' : 'da compilare') : 'MyRSU';
  return `<article class="room-participant"><div><strong>${escapeHtml(item.name || 'Invito esterno')}</strong><small>${status} · ${permissionLabel(item.permission_level)}${item.room_role ? ` · ${escapeHtml(item.room_role)}` : ''}</small></div>${remove}</article>`;
}

function documentRow(item) {
  const revoke = canManage() ? `<button class="icon-action danger" data-revoke-document="${item.document_id}" title="Revoca condivisione">${MyRsuIcons.get('trash')}</button>` : '';
  return `<article class="room-document"><div><button class="room-document-open" data-open-document="${item.document_id}" data-title="${escapeHtml(item.original_name)}"><strong>${escapeHtml(item.original_name)}</strong></button><small>Condiviso da ${escapeHtml(item.shared_by_name)}</small></div>${revoke}</article>`;
}

async function loadOptions() {
  const candidates = await roomApi(`/rooms/${roomId}/participants/candidates`);
  participantForm.querySelector('[name="user_id"]').innerHTML = candidates.map((item) => `<option value="${item.id}">${escapeHtml(item.name)} · ${escapeHtml(item.email)}</option>`).join('');
  const available = await roomApi(`/rooms/${roomId}/documents/available`);
  const shared = new Set(state.documents.map((item) => Number(item.document_id)));
  const select = documentForm.querySelector('[name="document_id"]');
  select.innerHTML = available.filter((item) => !shared.has(Number(item.id))).map((item) => `<option value="${item.id}">${escapeHtml(item.original_name)}</option>`).join('');
  documentForm.classList.toggle('hidden', select.options.length === 0);
}

document.querySelector('#statusForm').addEventListener('submit', (event) => submit(event, `/rooms/${roomId}/status`, 'PATCH'));
participantForm.addEventListener('submit', (event) => submit(event, `/rooms/${roomId}/participants`, 'POST'));
documentForm.addEventListener('submit', (event) => submit(event, `/rooms/${roomId}/documents`, 'POST'));
externalForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const invitation = await roomApi(`/rooms/${roomId}/external-participants`, { method:'POST', body:JSON.stringify(Object.fromEntries(new FormData(externalForm).entries())) });
    const result = document.querySelector('#inviteResult');
    result.innerHTML = `<strong>Link al Tavolo</strong><input readonly value="${escapeHtml(invitation.access_url)}"><div class="room-invite-actions"><a class="button" href="${escapeHtml(invitation.access_url)}" target="_blank" rel="noopener">Apri Room esterna</a><button type="button" data-copy-invite>Copia link</button></div>`;
    result.classList.remove('hidden'); externalForm.reset(); await load();
  } catch (error) { showError(error); }
});

document.addEventListener('click', async (event) => {
  const roomLink = event.target.closest('#externalRoomLink');
  const copy = event.target.closest('[data-copy-invite]');
  const open = event.target.closest('[data-open-document]');
  const user = event.target.closest('[data-remove-user]');
  const external = event.target.closest('[data-remove-external]');
  const documentButton = event.target.closest('[data-revoke-document]');
  if (roomLink) {
    const target = window.open('about:blank', '_blank');
    try { const access = await roomApi(`/rooms/${roomId}/access-link`, { method:'POST' }); target.location.href = access.url; }
    catch (error) { if (target) target.close(); showError(error); }
    return;
  }
  if (copy) return navigator.clipboard.writeText(document.querySelector('#inviteResult input').value);
  if (open) return openRoomPdf(`${location.pathname.split('/ui/')[0]}/api/v1/rooms/${roomId}/documents/${open.dataset.openDocument}/preview`, open.dataset.title).catch(showError);
  if (user && confirm('Revocare accesso?')) return remove(`/rooms/${roomId}/participants/${user.dataset.removeUser}`);
  if (external && confirm('Revocare invito?')) return remove(`/rooms/${roomId}/external-participants/${external.dataset.removeExternal}`);
  if (documentButton && confirm('Revocare documento?')) return remove(`/rooms/${roomId}/documents/${documentButton.dataset.revokeDocument}`);
});

async function submit(event, path, method) { event.preventDefault(); try { await roomApi(path, { method, body:JSON.stringify(Object.fromEntries(new FormData(event.target).entries())) }); await load(); } catch (error) { showError(error); } }
async function remove(path) { try { await roomApi(path, { method:'DELETE' }); await load(); } catch (error) { showError(error); } }
function canManage() { return state.membership.permission_level === 'manage'; }
function permissionLabel(value) { return ({read:'lettura',interact:'interazione',manage:'gestione'}[value] || value); }

document.querySelector('#closeDocumentModal').addEventListener('click', closeRoomPdf);
document.querySelector('#closeVerifyModal').addEventListener('click', () => { document.querySelector('#verifyFrame').src=''; document.querySelector('#verifyModal').close(); });
load().catch(showError);
