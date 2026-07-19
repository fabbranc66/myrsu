const apiBase = '../api/v1';
const voteToken = new URLSearchParams(window.location.search).get('token');
const title = document.querySelector('#title');
const description = document.querySelector('#description');
const options = document.querySelector('#options');
const voteForm = document.querySelector('#voteForm');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const localIdentifier = getLocalIdentifier();

async function api(path, options = {}) {
  const response = await fetch(`${apiBase}${path}`, { ...options, headers: { 'Content-Type': 'application/json' } });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

async function loadVoting() {
  const voting = await api(`/public/votings/token/${voteToken}`);
  title.textContent = voting.title;
  description.textContent = voting.description || '';
  options.innerHTML = voting.options.map((option) => `<label><input type="radio" name="option_id" value="${option.id}" required> ${escapeHtml(option.label)}</label>`).join('');
}

voteForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = Object.fromEntries(new FormData(voteForm).entries());
  data.local_identifier = localIdentifier;
  try {
    await api(`/public/votings/token/${voteToken}/vote`, { method: 'POST', body: JSON.stringify(data) });
    voteForm.classList.add('hidden');
    message.textContent = 'Voto registrato correttamente.';
  } catch (error) {
    message.textContent = error.message;
  }
});

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
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

loadVoting().catch((error) => { message.textContent = error.message; });
