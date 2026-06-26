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
        ['users.html', 'Utenti'],
        ['profile.html', 'Profilo'],
        ['contacts.html', 'Contatti'],
      ],
    },
    {
      label: 'Protocollo',
      pages: [
        ['protocol.html', 'Registro'],
      ],
      match: ['protocol.html', 'protocol-view.html', 'protocol-edit.html'],
    },
    {
      label: 'Archivio',
      pages: [
        ['documents.html', 'Documenti'],
        ['private-documents.html', 'Privato'],
        ['comunicati-create.html', 'Comunicati'],
        ['union-meetings.html', 'Incontri sindacali'],
        ['calls.html', 'Telefonate'],
        ['reports.html', 'Segnalazioni'],
        ['reports-moderation.html', 'Moderazione segnalazioni'],
        ['comments-moderation.html', 'Moderazione commenti'],
        ['pending-queue.html', 'Coda PDF'],
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
      const links = section.pages.map(([href, label]) => {
        const active = href === currentPage ? ' class="active"' : '';
        return `<a${active} href="${href}">${label}</a>`;
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
      if (!response.ok || roles.includes('membro')) {
        hidePrivateMenus();
      }
    } catch {
      hidePrivateMenus();
    }
  }

  renderMenu();
  appendAuthItem();
  applyRoleMenu();
})();
