const loginView = document.querySelector('#loginView');
const appView = document.querySelector('#appView');
const loginForm = document.querySelector('#loginForm');
const logoutButton = document.querySelector('#logoutButton');
const message = document.querySelector('#message');
const userName = document.querySelector('#userName');
const userRole = document.querySelector('#userRole');

function showMessage(text = '') {
  message.textContent = text;
}

function setView(authenticated) {
  loginView.classList.toggle('hidden', authenticated);
  appView.classList.toggle('hidden', !authenticated);
}

function renderUser(user) {
  const profile = user.user || user;
  const roles = Array.isArray(user.roles) ? user.roles.map((role) => role.name).join(', ') : '';

  userName.textContent = profile.name || 'Utente';
  userRole.textContent = roles;
}

async function boot() {
  if (!sessionStorage.getItem('token')) {
    setView(false);
    return;
  }

  try {
    renderUser(await MyRsuAuth.me());
    setView(true);
  } catch (error) {
    sessionStorage.removeItem('token');
    setView(false);
  }
}

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  showMessage();

  try {
    const form = new FormData(loginForm);
    await MyRsuAuth.login(String(form.get('email')), String(form.get('password')));
    renderUser(await MyRsuAuth.me());
    setView(true);
  } catch (error) {
    showMessage(error.message);
  }
});

logoutButton.addEventListener('click', async () => {
  try {
    await MyRsuAuth.logout();
  } catch (error) {
    showMessage(error.message);
  }

  sessionStorage.removeItem('token');
  setView(false);
});

boot();
