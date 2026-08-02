import { escapeHtml, roomApi, showError, statusLabel, token } from './room-api.js';

const form = document.querySelector('#roomForm');
const table = document.querySelector('#roomsTable');
const statusFilter = document.querySelector('#statusFilter');
let rooms = [];

if (!token) window.location.href = 'login.html';

async function load() {
  const [categories, rows, me] = await Promise.all([
    roomApi('/rooms/categories'), roomApi('/rooms'), roomApi('/me'),
  ]);
  form.category_id.innerHTML = categories.map((item) => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join('');
  rooms = rows;
  const operator = (me.roles || []).some((role) => ['admin', 'delegato', 'rls'].includes(role));
  document.querySelector('#newRoom').classList.toggle('hidden', !operator);
  render();
}

function render() {
  const filtered = rooms.filter((room) => !statusFilter.value || room.status === statusFilter.value);
  table.innerHTML = filtered.map((room) => `<tr>
    <td data-label="Codice">${escapeHtml(room.code)}</td>
    <td data-label="Titolo"><span class="truncate-title" title="${escapeHtml(room.title)}">${escapeHtml(room.title)}</span></td>
    <td data-label="Categoria">${escapeHtml(room.category_name)}</td>
    <td data-label="Stato"><span class="room-status status-${escapeHtml(room.status)}">${statusLabel(room.status)}</span></td>
    <td data-label="Partecipanti">${room.participants_count}</td>
    <td data-label="Ultima attività">${escapeHtml(room.last_activity_at || room.updated_at)}</td>
    <td data-label="Azioni" class="actions-cell"><a class="icon-action" href="room-view.html?id=${room.id}" title="Apri Tavolo">${MyRsuIcons.get('eye')}</a></td>
  </tr>`).join('') || '<tr><td colspan="7">Nessun Tavolo disponibile.</td></tr>';
}

document.querySelector('#newRoom').addEventListener('click', () => form.classList.remove('hidden'));
document.querySelector('#cancelRoom').addEventListener('click', () => { form.reset(); form.classList.add('hidden'); });
statusFilter.addEventListener('change', render);
form.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const room = await roomApi('/rooms', { method: 'POST', body: JSON.stringify(Object.fromEntries(new FormData(form).entries())) });
    window.location.href = `room-view.html?id=${room.id}`;
  } catch (error) { showError(error); }
});

load().catch(showError);
