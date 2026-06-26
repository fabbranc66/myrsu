const apiBase = '../api/v1';
let token = sessionStorage.getItem('token');

const profileForm = document.querySelector('#profileForm');
const passwordForm = document.querySelector('#passwordForm');
const message = document.querySelector('#message');
const gdprBox = document.querySelector('#gdprBox');
const acceptGdpr = document.querySelector('#acceptGdpr');
const jsonOutput = document.querySelector('#jsonOutput');

async function api(path, options = {}) {
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};

  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  renderJson(payload);
  return payload.data;
}

function renderJson(payload) {
  if (jsonOutput) {
    jsonOutput.textContent = JSON.stringify(payload, null, 2);
  }
}

function setMessage(text) {
  message.textContent = text;
}

async function loadProfile() {
  const profile = await api('/profile');
  const consents = await api('/gdpr/consents');
  profileForm.name.value = profile.name;
  profileForm.first_name.value = profile.first_name || '';
  profileForm.last_name.value = profile.last_name || '';
  profileForm.email.value = profile.email;
  profileForm.phone.value = profile.phone || '';
  profileForm.mobile.value = profile.mobile || '';
  profileForm.city.value = profile.city || '';
  profileForm.country.value = profile.country || '';
  const accepted = consents.some((consent) => (
    consent.consent_type === 'privacy_policy'
    && consent.document_version === '2026-06-18'
    && Number(consent.accepted) === 1
  ));

  gdprBox.classList.toggle('hidden', accepted);
}

profileForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(profileForm);
  await api('/profile', {
    method: 'PATCH',
    body: JSON.stringify(Object.fromEntries(form.entries())),
  });
  setMessage('Profilo salvato');
});

passwordForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  const form = new FormData(passwordForm);
  await api('/profile/password', {
    method: 'PATCH',
    body: JSON.stringify({ password: form.get('password') }),
  });
  passwordForm.reset();
  setMessage('Password salvata');
});

acceptGdpr.addEventListener('click', async () => {
  await api('/gdpr/consents', {
    method: 'POST',
    body: JSON.stringify({
      consent_type: 'privacy_policy',
      document_version: '2026-06-18',
      accepted: true,
    }),
  });
  gdprBox.classList.add('hidden');
  setMessage('GDPR accettato');
});

if (!token) {
  window.location.href = 'app/index.html';
} else {
  loadProfile().catch((error) => {
    sessionStorage.removeItem('token');
    token = null;
    setMessage(error.message);
    window.location.href = 'app/index.html';
  });
}
