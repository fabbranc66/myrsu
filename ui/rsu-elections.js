const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const form = document.querySelector('#electionForm');
const listsBox = document.querySelector('#lists');
const result = document.querySelector('#result');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

function renderFixedLists() {
  listsBox.innerHTML = '';
  ['CGIL', 'CISL'].forEach((name) => addList(name));
}

function addList(name) {
  const order = name === 'CGIL' ? 1 : 2;
  listsBox.insertAdjacentHTML('beforeend', `<section class="panel list-row" data-list>
    <header class="topbar"><h2>Lista ${name}</h2></header>
    <div class="grid">
      <label>Nome lista<input data-field="name" value="${name}" readonly required></label>
      <label>Voti lista<input data-field="votes" type="number" min="0" required></label>
      <label>Ordine presentazione<input data-field="presentation_order" type="number" min="1" value="${order}" required></label>
    </div>
    <div data-candidates></div>
  </section>`);
  const list = listsBox.lastElementChild;
  for (let index = 1; index <= 3; index++) addCandidate(list, index);
}

function addCandidate(list, index) {
  list.querySelector('[data-candidates]').insertAdjacentHTML('beforeend', `<div class="grid candidate-row" data-candidate>
    <label>Candidato ${index}<input data-field="name"></label>
    <label>Preferenze<input data-field="preferences" type="number" min="0" value="0"></label>
  </div>`);
}

function payload() {
  const data = Object.fromEntries(new FormData(form).entries());
  data.lists = [...listsBox.querySelectorAll('[data-list]')].map((list) => ({
    name: list.querySelector('[data-field="name"]').value,
    votes: list.querySelector('[data-field="votes"]').value,
    presentation_order: list.querySelector('[data-field="presentation_order"]').value,
    candidates: [...list.querySelectorAll('[data-candidate]')].map((candidate) => ({
      name: candidate.querySelector('[data-field="name"]').value,
      preferences: candidate.querySelector('[data-field="preferences"]').value,
    })),
  }));
  return data;
}

function render(data) {
  const checks = data.checks;
  const rows = data.lists.map((list) => `<tr>
    <td>${escapeHtml(list.name)}</td><td>${list.votes}</td><td>${list.seats_base}</td>
    <td>${Number(list.remainder).toFixed(3)}</td><td>${list.presentation_order}</td><td>${list.seats}</td>
    <td>${list.elected.map((item) => `${escapeHtml(item.name)} (${item.preferences})`).join('<br>') || '-'}</td>
    <td>${escapeHtml(list.assignment_note || '-')}</td>
  </tr>`).join('');
  result.innerHTML = `<div class="grid">
    <div class="stat-card"><strong>Validita voto</strong><span>${checks.turnout_ok ? 'OK' : 'NON valida'}</span></div>
    <div class="stat-card"><strong>Controllo schede</strong><span>${checks.ballots_ok ? 'OK' : 'Errore'}</span></div>
    <div class="stat-card"><strong>Controllo liste</strong><span>${checks.list_votes_ok ? 'OK' : 'Errore'}</span></div>
    <div class="stat-card"><strong>Quorum seggio</strong><span>${data.summary.quorum}</span></div>
  </div>
  <table><thead><tr><th>Lista</th><th>Voti</th><th>Seggi base</th><th>Resto</th><th>Ordine</th><th>Seggi</th><th>Eletti</th><th>Note</th></tr></thead><tbody>${rows}</tbody></table>`;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  try {
    const data = await api('/rsu-elections/analyze', { method: 'POST', body: JSON.stringify(payload()) });
    render(data);
    message.textContent = 'Analisi completata.';
  } catch (error) {
    message.textContent = error.message;
  }
});

if (!token) window.location.href = 'app/index.html';
renderFixedLists();
