const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const voteSection = document.querySelector('#voteSection');
const manageSection = document.querySelector('#manageSection');
const warningBox = document.querySelector('#warningBox');
const voteForm = document.querySelector('#voteForm');
const votingForm = document.querySelector('#votingForm');
const votingsTable = document.querySelector('#votingsTable');
const tokensPanel = document.querySelector('#tokensPanel');
const tokensTitle = document.querySelector('#tokensTitle');
const tokensCount = document.querySelector('#tokensCount');
const tokensTable = document.querySelector('#tokensTable');
const generateTokens = document.querySelector('#generateTokens');
const closeTokens = document.querySelector('#closeTokens');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
let voteToken = null;
let currentVotingId = null;

async function api(path, requestOptions = {}) {
  const headers = { 'Content-Type': 'application/json', ...(requestOptions.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...requestOptions, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

async function boot() {
  if (token && await isManager()) {
    manageSection.classList.remove('hidden');
    await loadVotings();
    return;
  }
  voteSection.classList.remove('hidden');
  await loadOpenVoting();
}

async function isManager() {
  try {
    const me = await api('/me');
    return (me.roles || []).some((role) => ['admin', 'delegato', 'rls'].includes(role));
  } catch {
    return false;
  }
}

async function loadOpenVoting() {
  try {
    const voting = await api('/public/votings/open');
    voteToken = voting.vote_token;
    document.querySelector('#title').textContent = voting.title;
    document.querySelector('#description').textContent = voting.description || '';
    document.querySelector('#options').innerHTML = voting.options.map((option) => `<label><input type="radio" name="option_id" value="${option.id}" required> ${escapeHtml(option.label)}</label>`).join('');
    voteForm.classList.remove('hidden');
    warningBox.classList.add('hidden');
  } catch {
    warningBox.classList.remove('hidden');
    voteForm.classList.add('hidden');
  }
}

async function loadVotings() {
  const rows = await api('/votings');
  votingsTable.innerHTML = rows.map(votingRow).join('');
}

function votingRow(voting) {
  const tokens = voting.tokens || [];
  const free = tokens.filter((item) => item.status === 'unused').length;
  const used = tokens.filter((item) => item.status === 'used').length;
  const cancelled = tokens.filter((item) => item.status === 'cancelled').length;
  const results = (voting.results || []).map((item) => `${escapeHtml(item.label)}: ${item.votes}`).join('<br>');
  return `<tr><td>${escapeHtml(voting.title)}</td><td>${statusLabel(voting.status)}</td><td>liberi ${free} / usati ${used} / annullati ${cancelled}</td><td>${results || '-'}</td><td class="actions-cell"><button class="icon-action" data-edit="${voting.id}" title="Modifica">${MyRsuIcons.get('edit')}</button><button class="icon-action" data-tokens="${voting.id}" title="Token">${MyRsuIcons.get('link')}</button></td></tr>`;
}

voteForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(voteForm).entries());
  data.local_identifier = getLocalIdentifier();
  await api(`/public/votings/token/${voteToken}/vote`, { method: 'POST', body: JSON.stringify(data) });
  voteForm.classList.add('hidden');
  message.textContent = 'Voto registrato correttamente.';
});

votingForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(votingForm).entries());
  data.options = [data.option_1, data.option_2, data.option_3, data.option_4];
  data.starts_at = data.starts_at ? data.starts_at.replace('T', ' ') : '';
  data.ends_at = data.ends_at ? data.ends_at.replace('T', ' ') : '';
  const saved = await api(data.id ? `/votings/${data.id}` : '/votings', { method: data.id ? 'PATCH' : 'POST', body: JSON.stringify(data) });
  votingForm.id.value = saved.id;
  message.textContent = 'Votazione salvata.';
  await loadVotings();
});

votingsTable.addEventListener('click', async (event) => {
  const edit = event.target.closest('[data-edit]');
  if (edit) return fillForm(await api(`/votings/${edit.dataset.edit}`));
  const tokens = event.target.closest('[data-tokens]');
  if (tokens) await openTokens(tokens.dataset.tokens);
});

generateTokens.addEventListener('click', async () => {
  await api(`/votings/${currentVotingId}/tokens`, { method: 'POST', body: JSON.stringify({ count: tokensCount.value }) });
  await openTokens(currentVotingId);
  await loadVotings();
});

closeTokens.addEventListener('click', () => tokensPanel.classList.add('hidden'));

tokensTable.addEventListener('click', async (event) => {
  const cancel = event.target.closest('[data-cancel-token]');
  if (!cancel) return;
  await api(`/votings/${currentVotingId}/tokens/${cancel.dataset.cancelToken}/cancel`, { method: 'POST' });
  await openTokens(currentVotingId);
  await loadVotings();
});

async function openTokens(votingId) {
  const voting = await api(`/votings/${votingId}`);
  currentVotingId = voting.id;
  tokensTitle.textContent = voting.title;
  tokensPanel.classList.remove('hidden');
  tokensTable.innerHTML = (voting.tokens || []).map(tokenRow).join('');
}

function tokenRow(row) {
  const vote = row.status === 'unused' ? `<a class="icon-action" href="voting-public.html?token=${row.token}" target="_blank" title="Vota">${MyRsuIcons.get('vote')}</a>` : '';
  const cancel = row.status === 'unused' ? `<button class="icon-action" data-cancel-token="${row.id}" title="Annulla">${MyRsuIcons.get('trash')}</button>` : '';
  return `<tr><td>${row.token}</td><td>${tokenLabel(row.status)}</td><td>${row.used_at || '-'}</td><td class="actions-cell">${vote}${cancel}</td></tr>`;
}

function fillForm(voting) {
  votingForm.id.value = voting.id;
  votingForm.title.value = voting.title;
  votingForm.status.value = voting.status;
  votingForm.anonymous.value = Number(voting.anonymous) === 1 ? '1' : '';
  votingForm.starts_at.value = String(voting.starts_at || '').replace(' ', 'T').slice(0, 16);
  votingForm.ends_at.value = String(voting.ends_at || '').replace(' ', 'T').slice(0, 16);
  votingForm.description.value = voting.description || '';
  votingForm.option_1.value = voting.options?.[0]?.label || '';
  votingForm.option_2.value = voting.options?.[1]?.label || '';
  votingForm.option_3.value = voting.options?.[2]?.label || '';
  votingForm.option_4.value = voting.options?.[3]?.label || '';
}

function getLocalIdentifier() {
  const key = 'myrsu_vote_local_identifier';
  let value = localStorage.getItem(key);
  if (!value) {
    value = crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
    localStorage.setItem(key, value);
  }
  return value;
}

function statusLabel(value) {
  return { draft: 'bozza', open: 'aperta', closed: 'chiusa', cancelled: 'annullata' }[value] || value || '-';
}

function tokenLabel(value) {
  return { unused: 'libero', used: 'usato', cancelled: 'annullato' }[value] || value || '-';
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

boot().catch((error) => { message.textContent = error.message; });
