(() => {
  const nav = document.querySelector('.app-nav');
  if (!nav || nav.querySelector('[data-auth-menu]')) return;

  const token = sessionStorage.getItem('token');
  const item = document.createElement(token ? 'button' : 'a');
  item.dataset.authMenu = 'true';
  item.className = 'nav-button';
  item.textContent = token ? 'Esci' : 'Login';

  if (!token) {
    item.href = 'app/index.html';
    nav.appendChild(item);
    return;
  }

  item.type = 'button';
  item.addEventListener('click', async () => {
    const appRoot = window.location.pathname.split('/ui/')[0];
    try {
      await fetch(`${appRoot}/api/v1/auth/logout`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${token}` },
      });
    } finally {
      sessionStorage.removeItem('token');
      window.location.href = 'app/index.html';
    }
  });
  nav.appendChild(item);
})();
