const loginView = document.querySelector('#loginView');
const appView = document.querySelector('#appView');
const loginForm = document.querySelector('#loginForm');
const loginToggle = document.querySelector('#loginToggle');
const loginPanel = document.querySelector('#loginPanel');
const loginError = document.querySelector('#loginError');
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
const anagraphicsMenu = document.querySelector('#anagraphicsMenu');
const protocolMenu = document.querySelector('#protocolMenu');
const profileMenuLink = document.querySelector('#profileMenuLink');
const contactsMenuLink = document.querySelector('#contactsMenuLink');
const usersMenuLink = document.querySelector('#usersMenuLink');
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

function showLoginError(text = '') {
  if (!loginError) return;
  loginError.textContent = text;
  loginError.hidden = text === '';
  loginError.classList.toggle('hidden', text === '');
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

function isOperator(user) {
  const roles = Array.isArray(user.roles) ? user.roles : [];
  return roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
}

function isAdmin(user) {
  const roles = Array.isArray(user.roles) ? user.roles : [];
  return roles.includes('admin');
}

function canAccessArchive(user) {
  const roles = Array.isArray(user.roles) ? user.roles : [];
  return roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
}

function canModerateReports(user) {
  const roles = Array.isArray(user.roles) ? user.roles : [];
  return roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
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

function toggleRoleMenus(user) {
  const roles = Array.isArray(user.roles) ? user.roles : [];
  const operationalEnabled = roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
  const adminEnabled = roles.includes('admin');
  const profileEnabled = operationalEnabled || roles.includes('membro');
  anagraphicsMenu?.classList.toggle('hidden', !profileEnabled);
  usersMenuLink?.classList.toggle('hidden', !adminEnabled);
  profileMenuLink?.classList.toggle('hidden', !profileEnabled);
  contactsMenuLink?.classList.toggle('hidden', !operationalEnabled);
  protocolMenu?.classList.toggle('hidden', !operationalEnabled);
  if (archiveMenu) {
    archiveMenu.classList.toggle('hidden', !operationalEnabled);
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
    toggleRoleMenus(me);
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
  showLoginError();

  try {
    const form = new FormData(loginForm);
    await MyRsuAuth.login(String(form.get('email')), String(form.get('password')));
    const me = await MyRsuAuth.me();
    renderUser(me);
    toggleRoleMenus(me);
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
    const errorMessage = error.message || 'Login non riuscito.';
    if (loginPanel) loginPanel.classList.remove('hidden');
    showLoginError(errorMessage);
    showMessage(errorMessage);
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

boot();
