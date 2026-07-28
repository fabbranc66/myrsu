const loginForm = document.querySelector('#loginForm');
const loginError = document.querySelector('#loginError');
const jsonOutput = document.querySelector('#jsonOutput');
const apiBase = `${window.location.pathname.split('/ui/')[0]}/api/v1`;

if (sessionStorage.getItem('token') || localStorage.getItem('token')) {
  window.location.href = 'app/index.html';
}

function showLoginError(text = '') {
  loginError.textContent = text;
  loginError.hidden = text === '';
  loginError.classList.toggle('hidden', text === '');
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  showLoginError();

  try {
    const form = new FormData(loginForm);
    const payload = await login(String(form.get('email')), String(form.get('password')));
    sessionStorage.setItem('token', payload.data.access_token);
    localStorage.setItem('token', payload.data.access_token);
    window.location.href = 'app/index.html';
  } catch (error) {
    showLoginError(error.message || 'Login non riuscito.');
  }
});

async function login(email, password) {
  const response = await fetch(`${apiBase}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password, device_name: 'official-ui' }),
  });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Login non riuscito.');
  return payload;
}
