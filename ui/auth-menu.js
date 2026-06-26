(() => {
  const nav = document.querySelector('.app-nav');
  if (!nav || nav.querySelector('[data-auth-menu]')) return;

  const token = sessionStorage.getItem('token');
  const appRoot = window.location.pathname.split('/ui/')[0];

  function hidePrivateMenus() {
    nav.querySelectorAll('.menu-group').forEach((item) => {
      item.classList.add('hidden');
    });
  }

  function appendAuthItem() {
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
  }

  async function applyRoleMenu() {
    if (!token) {
      hidePrivateMenus();
      return;
    }

    try {
      const response = await fetch(`${appRoot}/api/v1/me`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const payload = await response.json();
      const roles = payload.data?.roles || [];
      if (!response.ok || roles.includes('membro')) {
        hidePrivateMenus();
      }
    } catch {
      hidePrivateMenus();
    }
  }

  appendAuthItem();
  applyRoleMenu();
})();
