const apiBase = '../api/v1';
const token = sessionStorage.getItem('token') || localStorage.getItem('token');
const form = document.querySelector('#electionForm');
const listsBox = document.querySelector('#lists');
const result = document.querySelector('#result');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const draftStatus = document.querySelector('#draftStatus');
const draftFields = ['electors', 'voters', 'valid_votes', 'blank_votes', 'null_votes', 'seats'];
let draftReady = false;
let draftTimer;
let draftRevision = 0;
let savedRevision = 0;
let saveQueue = Promise.resolve();

async function api(path, options = {}, showOutput = true) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  if (showOutput) jsonOutput.textContent = JSON.stringify(payload, null, 2);
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
  for (let index = 1; index <= 5; index++) addCandidate(list, index);
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

function restoreDraft(data) {
  draftFields.forEach((field) => {
    form.elements.namedItem(field).value = data[field] ?? '';
  });
  listsBox.querySelectorAll('[data-list]').forEach((list) => {
    const name = list.querySelector('[data-field="name"]').value;
    const saved = data.lists.find((item) => item.name === name);
    if (!saved) return;
    ['votes', 'presentation_order'].forEach((field) => {
      list.querySelector(`[data-field="${field}"]`).value = saved[field] ?? '';
    });
    list.querySelectorAll('[data-candidate]').forEach((candidate, index) => {
      candidate.querySelector('[data-field="name"]').value = saved.candidates[index]?.name ?? '';
      candidate.querySelector('[data-field="preferences"]').value = saved.candidates[index]?.preferences ?? '0';
    });
  });
}

function saveDraft(force = false) {
  clearTimeout(draftTimer);
  if (!draftReady) return Promise.reject(new Error('Attendere il caricamento dei dati personali.'));
  const revision = draftRevision;
  const data = payload();
  saveQueue = saveQueue.catch(() => {}).then(async () => {
    if (!force && revision <= savedRevision) return;
    if (revision === draftRevision) draftStatus.textContent = 'Salvataggio in corso...';
    await api('/rsu-elections/draft', { method: 'POST', body: JSON.stringify(data), keepalive: true }, false);
    savedRevision = revision;
    if (revision === draftRevision) draftStatus.textContent = 'Dati salvati nel tuo account.';
  }).catch((error) => {
    draftStatus.textContent = `Dati non salvati: ${error.message}`;
    throw error;
  });
  return saveQueue;
}

function scheduleDraftSave(event) {
  if (!draftReady) return;
  draftRevision++;
  draftStatus.textContent = 'Modifiche da salvare...';
  clearTimeout(draftTimer);
  if (event.type === 'change') {
    saveDraft().catch(() => {});
  } else {
    draftTimer = setTimeout(() => saveDraft().catch(() => {}), 500);
  }
}

async function analyzeResults() {
  const data = await api('/rsu-elections/analyze', { method: 'POST', body: JSON.stringify(payload()) });
  render(data);
  message.textContent = 'Analisi completata.';
}

async function initializeElection() {
  if (!token) {
    window.location.href = 'app/index.html';
    return;
  }
  renderFixedLists();
  form.querySelectorAll('input, button').forEach((control) => { control.disabled = true; });
  try {
    const saved = await api('/rsu-elections/draft', { cache: 'no-store' }, false);
    if (saved) restoreDraft(saved.form);
    draftReady = true;
    draftStatus.textContent = saved ? 'Dati personali ripristinati.' : 'I dati saranno salvati automaticamente nel tuo account.';
    form.querySelectorAll('input, button').forEach((control) => { control.disabled = false; });
    if (saved && form.checkValidity()) {
      await analyzeResults();
    }
  } catch (error) {
    message.textContent = error.message;
    draftStatus.textContent = draftReady ? 'Dati ripristinati; analisi non disponibile.' : 'Ripristino non riuscito. Ricarica la pagina prima di modificare i dati.';
  }
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
    await saveDraft(true);
    await analyzeResults();
  } catch (error) {
    message.textContent = error.message;
  }
});

form.addEventListener('input', scheduleDraftSave);
form.addEventListener('change', scheduleDraftSave);
window.addEventListener('pagehide', () => {
  if (draftReady && draftRevision > savedRevision) saveDraft().catch(() => {});
});
initializeElection();
