const loginView = document.querySelector('#loginView');
const appView = document.querySelector('#appView');
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
const adminMenu = document.querySelector('#adminMenu');
const anagraphicsMenu = document.querySelector('#anagraphicsMenu');
const protocolMenu = document.querySelector('#protocolMenu');
const profileMenuLink = document.querySelector('#profileMenuLink');
const contactsMenuLink = document.querySelector('#contactsMenuLink');
const usersMenuLink = document.querySelector('#usersMenuLink');
const pendingQueueLink = document.querySelector('#pendingQueueLink');
const reportsCard = document.querySelector('#reportsCard');
const reportsPendingCount = document.querySelector('#reportsPendingCount');
const commentsCard = document.querySelector('#commentsCard');
const commentsPendingCount = document.querySelector('#commentsPendingCount');
const votingsCard = document.querySelector('#votingsCard');
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

function syncPublicAuthMenu() {
  const link = document.querySelector('#publicAuthLink');
  const token = sessionStorage.getItem('token');
  if (!link) return;
  link.textContent = token ? 'Esci' : 'Login';
  link.href = token ? '#' : '../login.html';
}

async function loadPublicBoard(target) {
  if (!target) return;
  publicComunicati.clear();
  publicDocuments.clear();
  const response = await fetch(`${appRoot}/api/v1/public/documents?_=${Date.now()}`, {
    cache: 'no-store',
  });
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
    const title = cleanText(document.comunicato?.title || document.original_name);
    const body = cleanText(document.comunicato?.body || '');
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
  const value = cleanText(text).replace(/\s+/g, ' ').trim();
  return value.length > limit ? `${value.slice(0, limit)}...` : value;
}

function cleanText(value) {
  return String(value || '')
    .replaceAll('Ã ', 'à')
    .replaceAll('Ã¨', 'è')
    .replaceAll('Ã©', 'é')
    .replaceAll('Ã¬', 'ì')
    .replaceAll('Ã²', 'ò')
    .replaceAll('Ã¹', 'ù')
    .replaceAll('Ã€', 'À')
    .replaceAll('Ãˆ', 'È')
    .replaceAll('Ã‰', 'É')
    .replaceAll('ÃŒ', 'Ì')
    .replaceAll('Ã’', 'Ò')
    .replaceAll('Ã™', 'Ù')
    .replaceAll('â€™', "'")
    .replaceAll('â€œ', '"')
    .replaceAll('â€', '"')
    .replaceAll('â€“', '-')
    .replaceAll('â€”', '-');
}

function escapeHtml(value) {
  return cleanText(value)
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
  return { comunicati: '&#128227;', documenti: '&#128196;' }[category] || '&#128204;';
}

function setView(authenticated) {
  syncPublicAuthMenu();
  loginView.classList.toggle('hidden', authenticated);
  appView.classList.toggle('hidden', !authenticated);
}

function renderUser(user) {
  const profile = user.user || user;
  const roles = normalizeRoles(user).join(', ');

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

function isOperator(user) {
  const roles = normalizeRoles(user);
  return roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
}

function isAdmin(user) {
  const roles = normalizeRoles(user);
  return roles.includes('admin');
}

function isOnlyMember(user) {
  const roles = normalizeRoles(user);
  return roles.length === 1 && roles.includes('membro');
}

function canAccessArchive(user) {
  const roles = normalizeRoles(user);
  return roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
}

function canModerateReports(user) {
  const roles = normalizeRoles(user);
  return roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
}

function normalizeRoles(user) {
  return Array.isArray(user.roles)
    ? user.roles.map((role) => typeof role === 'string' ? role : role.name).filter(Boolean)
    : [];
}

async function loadReportStats() {
  const data = await MyRsuApi.request('/reports/stats');
  if (reportsPendingCount) {
    reportsPendingCount.textContent = String(data.pending || 0);
  }
}

async function loadCommentStats() {
  const data = await MyRsuApi.request('/comments/stats');
  if (commentsPendingCount) {
    commentsPendingCount.textContent = String(data.pending || 0);
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

function toggleRoleMenus(user) {
  const roles = normalizeRoles(user);
  const operationalEnabled = roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
  const adminEnabled = roles.includes('admin');
  const profileEnabled = operationalEnabled || roles.includes('membro');
  setMenuVisibility({
    anagraphics: profileEnabled,
    users: adminEnabled,
    profile: profileEnabled,
    contacts: operationalEnabled,
    protocol: operationalEnabled,
    archive: operationalEnabled,
    admin: adminEnabled,
    privateDocuments: adminEnabled,
    pendingQueue: adminEnabled,
  });
}

function setMenuVisibility(permissions) {
  [
    [anagraphicsMenu, permissions.anagraphics],
    [usersMenuLink, permissions.users],
    [profileMenuLink, permissions.profile],
    [contactsMenuLink, permissions.contacts],
    [protocolMenu, permissions.protocol],
    [archiveMenu, permissions.archive],
    [adminMenu, permissions.admin],
    [privateDocumentsLink, permissions.privateDocuments],
    [pendingQueueLink, permissions.pendingQueue],
  ].forEach(([element, visible]) => element?.classList.toggle('hidden', !visible));
}

function toggleReportsBadge(enabled) {
  if (reportsCard) {
    reportsCard.classList.toggle('hidden', !enabled);
  }
  if (commentsCard) {
    commentsCard.classList.toggle('hidden', !enabled);
  }
  if (votingsCard) {
    votingsCard.classList.toggle('hidden', !enabled);
  }
}

async function boot() {
  if (!sessionStorage.getItem('token')) {
    setMenuVisibility({});
    setView(false);
    await loadPublicBoard(publicBoardGuest);
    return;
  }

  try {
    const me = await MyRsuAuth.me();
    toggleRoleMenus(me);
    if (isOnlyMember(me)) {
      await loadPublicBoard(publicBoardGuest);
      setView(false);
      return;
    }
    renderUser(me);
    toggleAdminQueue(isAdmin(me));
    toggleReportsBadge(canModerateReports(me));
    if (isAdmin(me)) {
      await loadQueueStatus();
    }
    if (canModerateReports(me)) {
      await Promise.all([loadReportStats(), loadCommentStats()]);
    }
    await loadPublicBoard(publicBoardUser);
    setView(true);
  } catch (error) {
    sessionStorage.removeItem('token');
    setView(false);
  }
}

document.querySelector('#publicAuthLink')?.addEventListener('click', async (event) => {
  if (!sessionStorage.getItem('token')) return;
  event.preventDefault();
  try {
    await MyRsuAuth.logout();
  } catch (error) {
    showMessage(error.message);
  }
  sessionStorage.removeItem('token');
  window.location.replace(`index.html?logout=${Date.now()}`);
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
  document.querySelector(`#comunicatoModalTitle${suffix}`).textContent = cleanText(item?.title || '');
  document.querySelector(`#comunicatoModalBody${suffix}`).textContent = cleanText(item?.body || '');
  modal.classList.remove('hidden');
  loadComments(button.dataset.comunicato, `#comunicatoComments${suffix}`);
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
  loadComments(button.dataset.document, `#documentComments${suffix}`);
});

async function loadComments(documentId, targetSelector) {
  const target = document.querySelector(targetSelector);
  if (!target) return;
  const response = await fetch(`${appRoot}/api/v1/documents/${documentId}/comments`);
  const payload = await response.json();
  target.innerHTML = commentsHtml(documentId, response.ok ? payload.data : []);
}

function commentsHtml(documentId, comments) {
  const antibot = newAntiBot();
  const rows = comments.length > 0
    ? comments.map((comment) => {
      const reply = comment.reply ? `<div class="comment-reply"><strong>Risposta RSU</strong><p>${escapeHtml(comment.reply)}</p></div>` : '';
      return `<article class="comment-row"><p>${escapeHtml(comment.message)}</p>${reply}<small>${escapeHtml(comment.created_at || '')}</small></article>`;
    }).join('')
    : '<p class="muted">Nessun commento approvato.</p>';

  return `<h3>Commenti</h3>${rows}<form class="comment-form" data-comment-form="${documentId}">
    <textarea name="message" placeholder="Commento" rows="3" required></textarea>
    <input name="contact" placeholder="Contatto opzionale" maxlength="255">
    <input class="bot-field" name="website" autocomplete="off" tabindex="-1">
    <input name="antibot_a" type="hidden" value="${antibot.a}">
    <input name="antibot_b" type="hidden" value="${antibot.b}">
    <input name="antibot_answer" inputmode="numeric" placeholder="Quanto fa ${antibot.a} + ${antibot.b}?" required>
    <input name="antibot_errors" type="hidden" value="0">
    <button type="submit">Invia commento</button>
  </form>`;
}

function newAntiBot() {
  return {
    a: Math.floor(Math.random() * 8) + 2,
    b: Math.floor(Math.random() * 8) + 2,
  };
}

function resetCommentAntiBot(form) {
  const antibot = newAntiBot();
  form.antibot_a.value = String(antibot.a);
  form.antibot_b.value = String(antibot.b);
  form.antibot_answer.value = '';
  form.antibot_answer.placeholder = `Quanto fa ${antibot.a} + ${antibot.b}?`;
}

document.addEventListener('submit', async (event) => {
  const form = event.target.closest('[data-comment-form]');
  if (!form) return;
  event.preventDefault();
  const headers = { 'Content-Type': 'application/json' };
  const token = sessionStorage.getItem('token');
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${appRoot}/api/v1/documents/${form.dataset.commentForm}/comments`, {
    method: 'POST',
    headers,
    body: JSON.stringify(Object.fromEntries(new FormData(form).entries())),
  });
  const payload = await response.json();
  showMessage(response.ok ? 'Commento inviato in moderazione' : payload.error?.message || 'Errore commento');
  if (response.ok) {
    form.reset();
    form.antibot_errors.value = '0';
    resetCommentAntiBot(form);
    closeParentModal(form);
    return;
  }

  if ((payload.error?.message || '').includes('antibot')) {
    const errors = Number(form.antibot_errors.value || 0) + 1;
    form.antibot_errors.value = String(errors);
    if (errors >= 3) {
      closeParentModal(form);
      return;
    }
  }
  resetCommentAntiBot(form);
});

function closeParentModal(element) {
  const modal = element.closest('.document-modal');
  if (modal) {
    modal.classList.add('hidden');
    const frame = modal.querySelector('iframe');
    if (frame) frame.src = '';
  }
}

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

function bindMobileMenu() {
  document.querySelectorAll('.app-nav').forEach((nav) => {
    nav.addEventListener('click', (event) => {
      if (window.innerWidth > 760) return;
      const groupTitle = event.target.closest('.menu-group > span');

      if (event.target === nav) {
        nav.classList.toggle('menu-open');
        return;
      }

      if (groupTitle) {
        event.preventDefault();
        groupTitle.parentElement.classList.toggle('submenu-open');
      }
    });
  });

  document.addEventListener('click', (event) => {
    if (window.innerWidth > 760 || event.target.closest('.app-nav')) return;
    document.querySelectorAll('.app-nav').forEach((nav) => nav.classList.remove('menu-open'));
    document.querySelectorAll('.submenu-open').forEach((item) => item.classList.remove('submenu-open'));
  });
}

bindMobileMenu();
boot();
