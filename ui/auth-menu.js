(() => {
  const nav = document.querySelector('.app-nav');
  if (!nav) return;

  const token = sessionStorage.getItem('token');
  const appRoot = window.location.pathname.split('/ui/')[0];
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  const sections = [
    {
      label: 'Anagrafica',
      pages: [
        ['users.html', 'Utenti', ['admin']],
        ['profile.html', 'Profilo', ['admin', 'delegato', 'rls', 'membro']],
        ['contacts.html', 'Contatti', ['admin', 'delegato', 'rls']],
      ],
    },
    {
      label: 'Protocollo',
      pages: [
        ['protocol.html', 'Registro', ['admin', 'delegato', 'rls']],
      ],
      match: ['protocol.html', 'protocol-view.html', 'protocol-edit.html'],
    },
    {
      label: 'Archivio',
      pages: [
        ['documents.html', 'Documenti', ['admin', 'delegato', 'rls']],
        ['private-documents.html', 'Privato', ['admin']],
        ['comunicati-create.html', 'Comunicati', ['admin', 'delegato', 'rls']],
        ['union-meetings.html', 'Incontri sindacali', ['admin', 'delegato', 'rls']],
        ['calls.html', 'Telefonate', ['admin', 'delegato', 'rls']],
        ['reports.html', 'Segnalazioni', ['admin', 'delegato', 'rls']],
        ['reports-moderation.html', 'Moderazione segnalazioni', ['admin', 'delegato', 'rls']],
        ['comments-moderation.html', 'Moderazione commenti', ['admin', 'delegato', 'rls']],
        ['pending-queue.html', 'Coda PDF', ['admin']],
      ],
      match: [
        'documents.html',
        'document-view.html',
        'document-edit.html',
        'private-documents.html',
        'comunicati-create.html',
        'union-meetings.html',
        'union-meeting-operational.html',
        'calls.html',
        'reports.html',
        'reports-moderation.html',
        'comments-moderation.html',
        'pending-queue.html',
      ],
    },
  ];

  function renderMenu() {
    nav.innerHTML = '<a href="app/index.html">Dashboard</a>';
    sections.forEach((section) => {
      const activeSection = (section.match || section.pages.map((page) => page[0])).includes(currentPage);
      const links = section.pages.map(([href, label, allowedRoles]) => {
        const active = href === currentPage ? ' class="active"' : '';
        const roles = Array.isArray(allowedRoles) ? allowedRoles.join(',') : '';
        const access = roles ? ` data-roles="${roles}" class="hidden"` : active;
        return `<a${access} href="${href}">${label}</a>`;
      }).join('');
      nav.insertAdjacentHTML('beforeend', `<div class="menu-group${activeSection ? ' active' : ''}"><span>${section.label}</span><div class="submenu">${links}</div></div>`);
    });
  }

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
      if (!response.ok) {
        hidePrivateMenus();
        return;
      }

      nav.querySelectorAll('[data-roles]').forEach((item) => {
        const allowed = item.dataset.roles.split(',');
        item.classList.toggle('hidden', !roles.some((role) => allowed.includes(role)));
      });
      nav.querySelectorAll('.menu-group').forEach((group) => {
        group.classList.toggle('hidden', !group.querySelector('.submenu a:not(.hidden)'));
      });
      const currentLink = nav.querySelector(`a[href="${currentPage}"]`);
      if (currentLink?.classList.contains('hidden')) {
        window.location.replace('app/index.html');
      }
    } catch {
      hidePrivateMenus();
    }
  }

  renderMenu();
  appendAuthItem();
  applyRoleMenu();
})();
