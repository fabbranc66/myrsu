const loginView = document.querySelector('#loginView');
const appView = document.querySelector('#appView');
const loginForm = document.querySelector('#loginForm');
const loginToggle = document.querySelector('#loginToggle');
const loginPanel = document.querySelector('#loginPanel');
const logoutButton = document.querySelector('#logoutButton');
const message = document.querySelector('#message');
const userName = document.querySelector('#userName');
const userRole = document.querySelector('#userRole');
const queuePanel = document.querySelector('#queuePanel');
const queueTitle = document.querySelector('#queueTitle');
const queueText = document.querySelector('#queueText');
const queueCardText = document.querySelector('#queueCardText');
const queueProcessButton = document.querySelector('#queueProcessButton');
const queueCard = document.querySelector('#queueCard');
const archiveMenu = document.querySelector('#archiveMenu');
const pendingQueueLink = document.querySelector('#pendingQueueLink');
const reportsCard = document.querySelector('#reportsCard');
const reportsPendingCount = document.querySelector('#reportsPendingCount');
const privateDocumentsLink = document.querySelector('#privateDocumentsLink');
const privateDocumentsCard = document.querySelector('#privateDocumentsCard');
const publicBoardGuest = document.querySelector('#publicBoardGuestContent');
const publicBoardUser = document.querySelector('#publicBoardUserContent');
const appRoot = window.location.pathname.split('/ui/')[0];
const publicComunicati = new Map();
const publicDocuments = new Map();

function showMessage(text = '') {
  message.textContent = text;
}

async function loadPublicBoard(target) {
  if (!target) return;
  const response = await fetch(`${appRoot}/api/v1/public/documents`);
  const payload = await response.json();
  if (!response.ok) throw new Error(payload.error?.message || 'Errore bacheca');
  target.innerHTML = publicBoardHtml(payload.data.sections || {});
}

function publicBoardHtml(sections) {
  return ['comunicati', 'documenti']
    .map((category) => {
      const rows = sections[category] || [];
      const content = rows.length > 0
        ? rows.map(publicDocumentRow).join('')
        : '<p class="muted">Nessun contenuto pubblicato.</p>';
      return `<article class="board-card"><div class="board-card-head"><span>${categoryIcon(category)}</span><h3>${categoryLabel(category)}</h3></div>${content}</article>`;
    })
    .join('');
}

function publicDocumentRow(document) {
  if (document.category === 'comunicati') {
    const title = document.comunicato?.title || document.original_name;
    const body = document.comunicato?.body || '';
    publicComunicati.set(String(document.id), { title, body });
    return `<button class="board-document board-comunicato" type="button" data-comunicato="${document.id}">
      <strong>${escapeHtml(title)}</strong>
      <span>${escapeHtml(truncateText(body, 100))}</span>
      <small>${escapeHtml(document.created_at || '')}</small>
    </button>`;
  }

  publicDocuments.set(String(document.id), {
    title: document.original_name,
    url: `${appRoot}/api/v1/documents/${document.id}/preview`,
  });

  return `<button class="board-document board-file" type="button" data-document="${document.id}">
    <strong>${escapeHtml(document.original_name)}</strong>
    <img class="board-file-preview" src="${appRoot}/api/v1/documents/${document.id}/thumbnail" alt="Anteprima ${escapeHtml(document.original_name)}">
    <small>${escapeHtml(document.created_at || '')}</small>
  </button>`;
}

function truncateText(text, limit) {
  const value = String(text || '').replace(/\s+/g, ' ').trim();
  return value.length > limit ? `${value.slice(0, limit)}...` : value;
}

function escapeHtml(value) {
  return String(value || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function categoryLabel(category) {
  return { comunicati: 'Comunicati', documenti: 'Documenti' }[category] || category;
}

function categoryIcon(category) {
  return { comunicati: '📣', documenti: '📄' }[category] || '📌';
}

function setView(authenticated) {
  loginView.classList.toggle('hidden', authenticated);
  appView.classList.toggle('hidden', !authenticated);
  if (loginPanel) loginPanel.classList.add('hidden');
}

function renderUser(user) {
  const profile = user.user || user;
  const roles = Array.isArray(user.roles)
    ? user.roles.map((role) => typeof role === 'string' ? role : role.name).join(', ')
    : '';

  userName.textContent = profile.name || 'Utente';
  userRole.textContent = roles;
}

async function loadQueueStatus() {
  const data = await MyRsuApi.request('/local/comunicati/pending');
  const count = Number(data.count || 0);

  if (queuePanel) {
    queuePanel.classList.remove('hidden', 'queue-panel-alert', 'queue-panel-ok');
    queuePanel.classList.add(count > 0 ? 'queue-panel-alert' : 'queue-panel-ok');
  }

  if (queueTitle) {
    queueTitle.textContent = count > 0
      ? `${count} operazioni pendenti`
      : 'Nessuna operazione pendente';
  }

  if (queueText) {
    queueText.textContent = count > 0
      ? 'Ci sono comunicati hosting da convertire e copiare su hosting.'
      : 'Nessun comunicato da processare.';
  }

  if (queueCardText) {
    queueCardText.textContent = count > 0
      ? `${count} operazioni pendenti da processare in locale.`
      : 'Nessuna operazione pendente.';
  }

  if (queueProcessButton) {
    queueProcessButton.classList.toggle('hidden', count === 0);
  }
}

function isAdmin(user) {
  const roles = Array.isArray(user.roles) ? user.roles : [];
  return roles.includes('admin');
}

function canAccessArchive(user) {
  const roles = Array.isArray(user.roles) ? user.roles : [];
  return roles.includes('admin') || roles.includes('delegato');
}

function canModerateReports(user) {
  const roles = Array.isArray(user.roles) ? user.roles : [];
  return roles.includes('admin') || roles.includes('delegato');
}

async function loadReportStats() {
  const data = await MyRsuApi.request('/reports/stats');
  if (reportsPendingCount) {
    reportsPendingCount.textContent = String(data.pending || 0);
  }
}

function toggleAdminQueue(enabled) {
  if (queuePanel) {
    queuePanel.classList.toggle('hidden', !enabled);
  }

  if (queueCard) {
    queueCard.classList.toggle('hidden', !enabled);
  }

  if (pendingQueueLink) {
    pendingQueueLink.classList.toggle('hidden', !enabled);
  }

  if (privateDocumentsLink) {
    privateDocumentsLink.classList.toggle('hidden', !enabled);
  }

  if (privateDocumentsCard) {
    privateDocumentsCard.classList.toggle('hidden', !enabled);
  }
}

function toggleArchiveMenu(enabled) {
  if (archiveMenu) {
    archiveMenu.classList.toggle('hidden', !enabled);
  }
}

function toggleReportsBadge(enabled) {
  if (reportsCard) {
    reportsCard.classList.toggle('hidden', !enabled);
  }
}

async function boot() {
  if (!sessionStorage.getItem('token')) {
    setView(false);
    await loadPublicBoard(publicBoardGuest);
    return;
  }

  try {
    const me = await MyRsuAuth.me();
    renderUser(me);
    toggleArchiveMenu(canAccessArchive(me));
    toggleAdminQueue(isAdmin(me));
    toggleReportsBadge(canModerateReports(me));
    if (isAdmin(me)) {
      await loadQueueStatus();
    }
    if (canModerateReports(me)) {
      await loadReportStats();
    }
    await loadPublicBoard(publicBoardUser);
    setView(true);
  } catch (error) {
    sessionStorage.removeItem('token');
    setView(false);
  }
}

if (loginForm) loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  showMessage();

  try {
    const form = new FormData(loginForm);
    await MyRsuAuth.login(String(form.get('email')), String(form.get('password')));
    const me = await MyRsuAuth.me();
    renderUser(me);
    toggleArchiveMenu(canAccessArchive(me));
    toggleAdminQueue(isAdmin(me));
    toggleReportsBadge(canModerateReports(me));
    if (isAdmin(me)) {
      await loadQueueStatus();
    }
    if (canModerateReports(me)) {
      await loadReportStats();
    }
    await loadPublicBoard(publicBoardUser);
    setView(true);
  } catch (error) {
    showMessage(error.message);
  }
});

if (loginToggle && loginPanel) loginToggle.addEventListener('click', (event) => {
  event.preventDefault();
  loginPanel.classList.toggle('hidden');
});

if (logoutButton) logoutButton.addEventListener('click', async () => {
  try {
    await MyRsuAuth.logout();
  } catch (error) {
    showMessage(error.message);
  }

  sessionStorage.removeItem('token');
  setView(false);
});

if (queueProcessButton) {
  queueProcessButton.addEventListener('click', async () => {
    try {
      const result = await MyRsuApi.request('/local/comunicati/process', {
        method: 'POST',
        body: '{}',
      });
      showMessage(`Processati: ${result.processed} - Errori: ${result.errors}`);
      await loadQueueStatus();
    } catch (error) {
      showMessage(error.message);
    }
  });
}

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-comunicato]');
  if (!button) return;
  const item = publicComunicati.get(String(button.dataset.comunicato));
  const suffix = appView.classList.contains('hidden') ? 'Guest' : 'User';
  const modal = document.querySelector(`#comunicatoModal${suffix}`);
  document.querySelector(`#comunicatoModalTitle${suffix}`).textContent = item?.title || '';
  document.querySelector(`#comunicatoModalBody${suffix}`).textContent = item?.body || '';
  modal.classList.remove('hidden');
});

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-document]');
  if (!button) return;
  const item = publicDocuments.get(String(button.dataset.document));
  const suffix = appView.classList.contains('hidden') ? 'Guest' : 'User';
  const modal = document.querySelector(`#documentModal${suffix}`);
  const frame = document.querySelector(`#documentModalFrame${suffix}`);
  document.querySelector(`#documentModalTitle${suffix}`).textContent = item?.title || '';
  frame.src = item?.url || '';
  modal.classList.remove('hidden');
});

['Guest', 'User'].forEach((suffix) => {
  const closeButton = document.querySelector(`#closeComunicatoModal${suffix}`);
  const modal = document.querySelector(`#comunicatoModal${suffix}`);
  if (closeButton && modal) closeButton.addEventListener('click', () => modal.classList.add('hidden'));
  if (modal) modal.addEventListener('click', (event) => {
    if (event.target === modal) modal.classList.add('hidden');
  });
});

['Guest', 'User'].forEach((suffix) => {
  const closeButton = document.querySelector(`#closeDocumentModal${suffix}`);
  const modal = document.querySelector(`#documentModal${suffix}`);
  const frame = document.querySelector(`#documentModalFrame${suffix}`);
  if (closeButton && modal) closeButton.addEventListener('click', () => {
    modal.classList.add('hidden');
    if (frame) frame.src = '';
  });
  if (modal) modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.classList.add('hidden');
      if (frame) frame.src = '';
    }
  });
});

boot();
