const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const params = new URLSearchParams(window.location.search);
const assemblyId = params.get('assembly_id');
const sessionId = params.get('session_id');
const voteSection = document.querySelector('#voteSection');
const manageSection = document.querySelector('#manageSection');
const warningBox = document.querySelector('#warningBox');
const voteForm = document.querySelector('#voteForm');
const votingForm = document.querySelector('#votingForm');
const votingsTable = document.querySelector('#votingsTable');
const tokensPanel = document.querySelector('#tokensPanel');
const tokensTitle = document.querySelector('#tokensTitle');
const onlinePanel = document.querySelector('#onlinePanel');
const manualPanel = document.querySelector('#manualPanel');
const tokensCount = document.querySelector('#tokensCount');
const tokensTableWrap = document.querySelector('#tokensTableWrap');
const tokensTable = document.querySelector('#tokensTable');
const generateTokens = document.querySelector('#generateTokens');
const manualParticipants = document.querySelector('#manualParticipants');
const manualVotes = document.querySelector('#manualVotes');
const manualCheck = document.querySelector('#manualCheck');
const manualVoteButton = document.querySelector('#manualVoteButton');
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
    if (!voting.vote_token) throw new Error('Nessuna votazione online aperta.');
    voteToken = voting.vote_token;
    document.querySelector('#title').textContent = voting.title;
    document.querySelector('#voteTurn').textContent = sessionLabel(voting.session);
    document.querySelector('#description').textContent = voting.description || '';
    document.querySelector('#options').innerHTML = voting.options.map((option) => `<label><input type="radio" name="option_id" value="${option.id}" required> ${escapeHtml(option.label)}</label>`).join('');
    voteForm.classList.remove('hidden');
    voteForm.querySelector('button[type="submit"]').disabled = false;
    warningBox.classList.add('hidden');
  } catch {
    warningBox.classList.remove('hidden');
    voteForm.classList.add('hidden');
    voteForm.querySelector('button[type="submit"]').disabled = true;
  }
}

async function loadVotings() {
  const rows = await api('/votings');
  votingsTable.innerHTML = rows.map(votingRow).join('');
  const linked = rows.find((row) => String(row.assembly_id || '') === String(assemblyId || '') && String(row.session_id || '') === String(sessionId || ''));
  if (linked) fillForm(linked);
  if (!linked && assemblyId && sessionId) {
    await prefillLinkedVoting();
  }
}

async function prefillLinkedVoting() {
  const assembly = await api(`/workers-assemblies/${assemblyId}`);
  const session = (assembly.sessions || []).find((row) => String(row.id) === String(sessionId));
  votingForm.id.value = '';
  votingForm.assembly_id.value = assemblyId;
  votingForm.session_id.value = sessionId;
  votingForm.title.value = `Votazione ${assembly.title} - ${session?.shift_label || 'turno'}`;
  votingForm.status.value = 'draft';
  votingForm.vote_mode.value = assembly.voting_mode || 'online';
  votingForm.anonymous.value = '1';
  votingForm.starts_at.value = session ? `${session.assembly_date}T${String(session.time_start || '').slice(0, 5)}` : '';
  votingForm.ends_at.value = session?.time_end ? `${session.assembly_date}T${String(session.time_end).slice(0, 5)}` : '';
  votingForm.description.value = assembly.voting_subject || assembly.agenda || '';
  const options = assembly.voting_options || ['Favorevole', 'Contrario', 'Astenuto'];
  votingForm.option_1.value = options[0] || '';
  votingForm.option_2.value = options[1] || '';
  votingForm.option_3.value = options[2] || '';
  votingForm.option_4.value = options[3] || '';
  toggleModePanels('online');
}

function votingRow(voting) {
  const tokens = voting.tokens || [];
  const free = tokens.filter((item) => item.status === 'unused').length;
  const used = tokens.filter((item) => item.status === 'used').length;
  const cancelled = tokens.filter((item) => item.status === 'cancelled').length;
  const results = (voting.results || []).map((item) => `${escapeHtml(item.label)}: ${item.votes}`).join('<br>');
  const tokenInfo = voting.vote_mode === 'manual' ? 'scrutinio manuale' : `liberi ${free} / usati ${used} / annullati ${cancelled}`;
  const actionTitle = voting.vote_mode === 'manual' ? 'Scrutinio' : 'Token';
  return `<tr><td>${escapeHtml(voting.title)}</td><td>${statusLabel(voting.status)}</td><td>${tokenInfo}</td><td>${results || '-'}</td><td class="actions-cell"><button class="icon-action" data-edit="${voting.id}" title="Modifica">${MyRsuIcons.get('edit')}</button><button class="icon-action" data-tokens="${voting.id}" title="${actionTitle}">${MyRsuIcons.get('link')}</button><button class="icon-action danger" data-delete="${voting.id}" title="Elimina">${MyRsuIcons.get('trash')}</button></td></tr>`;
}

voteForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!voteToken) {
    message.textContent = 'Token voto assente.';
    return;
  }
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
  const remove = event.target.closest('[data-delete]');
  if (remove) {
    await api(`/votings/${remove.dataset.delete}`, { method: 'DELETE' });
    votingForm.reset();
    tokensPanel.classList.add('hidden');
    message.textContent = 'Votazione eliminata.';
    await loadVotings();
    return;
  }
  const tokens = event.target.closest('[data-tokens]');
  if (tokens) await openTokens(tokens.dataset.tokens);
});

generateTokens.addEventListener('click', async () => {
  if (onlinePanel.classList.contains('hidden')) return;
  await api(`/votings/${currentVotingId}/tokens`, { method: 'POST', body: JSON.stringify({ count: tokensCount.value }) });
  await openTokens(currentVotingId);
  await loadVotings();
});

manualVoteButton.addEventListener('click', async () => {
  if (manualPanel.classList.contains('hidden')) return;
  const votes = {};
  manualVotes.querySelectorAll('[data-manual-option]').forEach((input) => { votes[input.dataset.manualOption] = Number(input.value || 0); });
  await api(`/votings/${currentVotingId}/manual-vote`, { method: 'POST', body: JSON.stringify({ participants_count: manualParticipants.value, votes }) });
  message.textContent = 'Scrutinio manuale registrato.';
  await openTokens(currentVotingId);
  await loadVotings();
});

manualParticipants.addEventListener('input', updateManualCheck);
manualVotes.addEventListener('input', updateManualCheck);

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
  toggleModePanels(voting.vote_mode || 'online');
  manualVotes.innerHTML = (voting.options || []).map((option) => `<label>${escapeHtml(option.label)}<input data-manual-option="${option.id}" type="number" min="0" max="5000" value="0"></label>`).join('');
  updateManualCheck();
  tokensTable.innerHTML = (voting.tokens || []).map(tokenRow).join('');
}

function updateManualCheck() {
  const participants = Number(manualParticipants.value || 0);
  const total = Array.from(manualVotes.querySelectorAll('[data-manual-option]')).reduce((sum, input) => sum + Number(input.value || 0), 0);
  manualCheck.textContent = total === participants
    ? `Riconciliazione ok: ${total}/${participants}`
    : `Da riconciliare: voti ${total} / partecipanti ${participants}`;
}

function tokenRow(row) {
  const vote = row.status === 'unused' ? `<a class="icon-action" href="voting-public.html?token=${row.token}" target="_blank" title="Vota">${MyRsuIcons.get('vote')}</a>` : '';
  const cancel = row.status === 'unused' ? `<button class="icon-action" data-cancel-token="${row.id}" title="Annulla">${MyRsuIcons.get('trash')}</button>` : '';
  return `<tr><td>${row.token}</td><td>${tokenLabel(row.status)}</td><td>${row.used_at || '-'}</td><td class="actions-cell">${vote}${cancel}</td></tr>`;
}

function fillForm(voting) {
  votingForm.id.value = voting.id;
  votingForm.assembly_id.value = voting.assembly_id || assemblyId || '';
  votingForm.session_id.value = voting.session_id || sessionId || '';
  votingForm.title.value = voting.title;
  votingForm.status.value = voting.status;
  votingForm.vote_mode.value = voting.vote_mode || 'online';
  votingForm.anonymous.value = Number(voting.anonymous) === 1 ? '1' : '';
  votingForm.starts_at.value = String(voting.starts_at || '').replace(' ', 'T').slice(0, 16);
  votingForm.ends_at.value = String(voting.ends_at || '').replace(' ', 'T').slice(0, 16);
  votingForm.description.value = voting.description || '';
  votingForm.option_1.value = voting.options?.[0]?.label || '';
  votingForm.option_2.value = voting.options?.[1]?.label || '';
  votingForm.option_3.value = voting.options?.[2]?.label || '';
  votingForm.option_4.value = voting.options?.[3]?.label || '';
  toggleModePanels(votingForm.vote_mode.value);
}

votingForm.vote_mode.addEventListener('change', () => toggleModePanels(votingForm.vote_mode.value));

function toggleModePanels(mode) {
  const manual = mode === 'manual';
  manualPanel.classList.toggle('hidden', !manual);
  onlinePanel.classList.toggle('hidden', manual);
  tokensTableWrap.classList.toggle('hidden', manual);
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

function sessionLabel(session) {
  if (!session) return '';
  const start = String(session.time_start || '').slice(0, 5);
  const end = String(session.time_end || '').slice(0, 5) || '-';
  return `Turno ${session.shift_label} | ${session.assembly_date} | ${start}-${end}`;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

boot().catch((error) => { message.textContent = error.message; });
