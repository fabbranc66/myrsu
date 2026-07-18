const loginForm = document.querySelector('#loginForm');
const loginError = document.querySelector('#loginError');

if (sessionStorage.getItem('token')) {
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
    await MyRsuAuth.login(String(form.get('email')), String(form.get('password')));
    window.location.href = 'app/index.html';
  } catch (error) {
    showLoginError(error.message || 'Login non riuscito.');
  }
});
