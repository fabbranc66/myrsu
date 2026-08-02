import { closePdf, openPdf } from './pdf.js?v=20260802-room-1';

const apiBase = `${location.origin}/myrsu/api/v1`;
const params = new URLSearchParams(location.search);
const queryInvite = params.get('invite');
const queryToken = params.get('token');
if (queryInvite) sessionStorage.setItem('myrsu_room_invite', queryInvite);
if (queryToken) localStorage.setItem('myrsu_room_access', queryToken);
if (queryInvite || queryToken) history.replaceState({}, '', location.pathname);
let roomToken = queryInvite || queryToken || sessionStorage.getItem('myrsu_room_invite') || localStorage.getItem('myrsu_room_access') || '';
let state;
let signature = '';
let loaded = false;
const registration = document.querySelector('#registrationPanel');
const confirmation = document.querySelector('#confirmationPanel');
const roomPanel = document.querySelector('#roomPanel');
const message = document.querySelector('#message');

async function api(path, options = {}) {
  const headers = {'X-Room-Token':roomToken,...(options.headers||{})};
  if (!(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  document.querySelector('#jsonOutput').textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) {
    const error = payload.error || {};
    const details = [error.message || 'Operazione fallita.', error.detail, error.source, error.error_id]
      .filter(Boolean)
      .join('\n');
    throw new Error(details);
  }
  return payload.data;
}

async function load() {
  if (!roomToken) throw new Error('Link Room mancante.');
  state = await api('/room-access');
  document.querySelector('#pageTitle').textContent = state.room.title;
  registration.classList.toggle('hidden', !state.requires_registration);
  confirmation.classList.add('hidden');
  roomPanel.classList.toggle('hidden', state.requires_registration);
  if (state.requires_registration) return;
  renderRoom();
  await loadTimeline();
}

function renderRoom() {
  document.querySelector('#roomCode').textContent = `${state.room.code} · ${state.room.category}`;
  document.querySelector('#roomTitle').textContent = state.room.title;
  document.querySelector('#roomDescription').textContent = state.room.description || '';
  const external = state.participant.identity_type === 'external';
  document.querySelector('#identityLabel').textContent = external ? 'Identificativo locale' : 'Identità MyRSU';
  document.querySelector('#localIdentifier').textContent = external ? state.participant.local_identifier : state.participant.name;
  document.querySelector('#documents').innerHTML = state.documents.map((item) => `<button class="document" data-document="${item.document_id}" data-title="${escapeHtml(item.original_name)}"><strong>${escapeHtml(item.original_name)}</strong><small>${escapeHtml(item.shared_at)}</small></button>`).join('') || '<p>Nessun documento condiviso.</p>';
  const writable = ['interact','manage'].includes(state.participant.permission_level) && ['open','in_progress'].includes(state.room.status);
  document.querySelector('#messageForm').classList.toggle('hidden', !writable);
}

async function loadTimeline(silent = false) {
  const rows = await api('/room-access/timeline');
  const target = document.querySelector('#timeline');
  const next = rows.map((item) => `${item.timeline_type}-${item.timeline_id}`).join('|');
  if (silent && next === signature) return;
  const nearBottom = target.scrollHeight - target.scrollTop - target.clientHeight < 100;
  signature = next;
  target.innerHTML = rows.map(row).join('') || '<p>Nessuna attività.</p>';
  await hydrateAttachments(target, rows);
  if (!loaded || nearBottom) target.scrollTop = target.scrollHeight;
  loaded = true;
}

export async function refreshRoomTimeline() {
  await loadTimeline();
}

function row(item) {
  if (item.timeline_type === 'message') {
    const own = state.participant.identity_type === 'myrsu' ? Number(item.user_id) === Number(state.participant.id) : Number(item.external_author_id) === Number(state.participant.id);
    const reply = item.entity_id ? `<small class="reply-ref">Risposta al messaggio #${item.entity_id}</small>` : '';
    return `<article class="bubble ${own?'own':''}">${reply}<p>${escapeHtml(item.content)}</p><footer><span>${escapeHtml(item.user_name||'Partecipante')} · ${time(item.created_at)}</span><button type="button" data-reply-message="${item.timeline_id}" data-reply-name="${escapeHtml(item.user_name||'Partecipante')}">↩</button></footer></article>`;
  }
  if (item.timeline_type === 'document') return `<article class="system"><button type="button" data-document="${item.entity_id}" data-title="${escapeHtml(item.content)}">Documento: ${escapeHtml(item.content)}</button><small>${time(item.created_at)}</small></article>`;
  return `<article class="system"><span>${escapeHtml(eventText(item))}</span><small>${time(item.created_at)}</small></article>`;
}

document.querySelector('#registrationForm').addEventListener('submit', async (event) => { event.preventDefault(); try { await api('/room-access/register',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(event.target).entries()))}); sessionStorage.removeItem('myrsu_room_invite'); roomToken=''; registration.classList.add('hidden'); confirmation.classList.remove('hidden'); } catch(error){message.textContent=error.message;} });
document.querySelector('#messageForm').addEventListener('submit', async (event) => { event.preventDefault(); try { await api('/room-access/messages',{method:'POST',body:JSON.stringify(Object.fromEntries(new FormData(event.target).entries()))}); event.target.reset(); clearReply(); await loadTimeline(); } catch(error){message.textContent=error.message;} });
document.addEventListener('click', (event) => { const doc=event.target.closest('[data-document]'); const reply=event.target.closest('[data-reply-message]'); const cancel=event.target.closest('[data-cancel-reply]'); const verify=event.target.closest('.pdf-link'); if(reply)return setReply(reply.dataset.replyMessage,reply.dataset.replyName); if(cancel)return clearReply(); if(doc)return openPdf(`${apiBase}/room-access/documents/${doc.dataset.document}/preview`,doc.dataset.title,{'X-Room-Token':roomToken}).catch((error)=>message.textContent=error.message); if(verify){document.querySelector('#verifyFrame').src=verify.dataset.verifyUrl;document.querySelector('#verifyModal').showModal();} });
document.querySelector('#closeDocument').addEventListener('click',closePdf);
document.querySelector('#closeVerify').addEventListener('click',()=>{document.querySelector('#verifyFrame').src='';document.querySelector('#verifyModal').close();});

function setReply(id,name){const form=document.querySelector('#messageForm');form.querySelector('[name="parent_id"]').value=id;document.querySelector('#replyBar span').textContent=`Rispondi a ${name}`;document.querySelector('#replyBar').classList.remove('hidden');form.querySelector('textarea').focus();}
function clearReply(){document.querySelector('#messageForm [name="parent_id"]').value='';document.querySelector('#replyBar').classList.add('hidden');}
function eventText(item){return ({'room.created':'Tavolo creato','room.updated':'Tavolo aggiornato','room.status_changed':'Stato del Tavolo aggiornato','participant.saved':'Partecipante aggiornato','participant.revoked':'Accesso revocato','external_participant.created':'Invito creato','external_participant.verification_sent':'E-mail di accesso inviata','external_participant.registered':'Partecipante identificato','external_participant.revoked':'Invito revocato','document.shared':'Documento condiviso','document.revoked':'Documento revocato','message.deleted':'Messaggio eliminato'}[item.subtype]||item.subtype);}
function time(value){const date=new Date(String(value).replace(' ','T'));return Number.isNaN(date.getTime())?escapeHtml(value):date.toLocaleString('it-IT',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});}
export function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,(char)=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));}

async function hydrateAttachments(target, rows) {
  const messages = rows.filter((item) => item.timeline_type === 'message');
  const bubbles = [...target.querySelectorAll('.bubble')];
  await Promise.all(messages.map(async (item, index) => {
    if (!item.attachment || !bubbles[index]) return;
    const response = await fetch(`${apiBase}/room-access/attachments/${item.attachment.id}`, {headers:{'X-Room-Token':roomToken}});
    if (!response.ok) return;
    const url = URL.createObjectURL(await response.blob());
    const name = escapeHtml(item.attachment.original_name);
    let html = `<a class="chat-file" href="${url}" download="${name}">📄 ${name}</a>`;
    if (item.attachment.attachment_type === 'image') html = `<img class="chat-media" src="${url}" alt="${name}">`;
    if (item.attachment.attachment_type === 'audio') html = `<audio class="chat-audio" controls src="${url}"></audio>`;
    if (item.attachment.attachment_type === 'video') html = `<video class="chat-media" controls src="${url}"></video>`;
    bubbles[index].querySelector('footer').insertAdjacentHTML('beforebegin', html);
  }));
}

export { api };

load().then(()=>setInterval(()=>{if(!document.hidden&&state?.participant)loadTimeline(true).catch(()=>{});},3000)).catch((error)=>message.textContent=error.message);
