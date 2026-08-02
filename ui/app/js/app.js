const loginView = document.querySelector('#loginView');
const appView = document.querySelector('#appView');
const message = document.querySelector('#message');
const userName = document.querySelector('#userName');
const userRole = document.querySelector('#userRole');
const communicationsMenu = document.querySelector('#communicationsMenu');
const meetingsMenu = document.querySelector('#meetingsMenu');
const practicesMenu = document.querySelector('#practicesMenu');
const documentsMenu = document.querySelector('#documentsMenu');
const rsuMenu = document.querySelector('#rsuMenu');
const administrationMenu = document.querySelector('#administrationMenu');
const profileMenuLink = document.querySelector('#profileMenuLink');
const contactsMenuLink = document.querySelector('#contactsMenuLink');
const usersMenuLink = document.querySelector('#usersMenuLink');
const reportsCard = document.querySelector('#reportsCard');
const reportsPendingCount = document.querySelector('#reportsPendingCount');
const commentsCard = document.querySelector('#commentsCard');
const commentsPendingCount = document.querySelector('#commentsPendingCount');
const remindersCard = document.querySelector('#remindersCard');
const remindersPendingCount = document.querySelector('#remindersPendingCount');
const remindersPendingLabel = document.querySelector('#remindersPendingLabel');
const reminderWarningModal = document.querySelector('#reminderWarningModal');
const reminderWarningBody = document.querySelector('#reminderWarningBody');
const closeReminderWarningModal = document.querySelector('#closeReminderWarningModal');
const votingsCard = document.querySelector('#votingsCard');
const privateDocumentsLink = document.querySelector('#privateDocumentsLink');
const publicBoardGuest = document.querySelector('#publicBoardGuestContent');
const publicBoardUser = document.querySelector('#publicBoardUserContent');
const dashboardVerifyModal = document.querySelector('#dashboardVerifyModal');
const dashboardVerifyFrame = document.querySelector('#dashboardVerifyFrame');
const closeDashboardVerifyModal = document.querySelector('#closeDashboardVerifyModal');
const appRoot = window.location.pathname.split('/ui/')[0];
const publicComunicati = new Map();
const publicDocuments = new Map();

function authToken() {
  return sessionStorage.getItem('token') || localStorage.getItem('token');
}

function clearAuthToken() {
  sessionStorage.removeItem('token');
  localStorage.removeItem('token');
}

function showMessage(text = '') {
  message.textContent = text;
}

function syncPublicAuthMenu() {
  const link = document.querySelector('#publicAuthLink');
  const token = authToken();
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
    verifyUrl: document.signature
      ? `${appRoot}/ui/document-verify.html?id=${encodeURIComponent(document.id)}&sig=${encodeURIComponent(document.signature)}`
      : '',
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

async function loadReminderStats() {
  const rows = await MyRsuApi.request('/reminders?status=pending');
  const overdue = rows.filter((item) => new Date(String(item.due_at).replace(' ', 'T')) < new Date()).length;
  const urgent = urgentReminders(rows);
  if (remindersPendingCount) {
    remindersPendingCount.textContent = String(rows.length);
  }
  if (remindersPendingLabel) {
    remindersPendingLabel.textContent = overdue > 0 ? `${overdue} scaduti` : 'pendenti';
  }
  remindersCard?.classList.toggle('dashboard-warning-card', overdue > 0 || urgent.length > 0);
  showReminderWarning(urgent);
}

function urgentReminders(rows) {
  const now = Date.now();
  const limit = now + (24 * 60 * 60 * 1000);
  return rows.filter((item) => {
    const due = new Date(String(item.due_at).replace(' ', 'T')).getTime();
    return Number.isFinite(due) && due <= limit;
  });
}

function showReminderWarning(rows) {
  if (!reminderWarningModal || !reminderWarningBody || rows.length === 0) return;
  reminderWarningBody.innerHTML = rows.slice(0, 6).map((item) => `
    <article class="reminder-warning-row">
      <strong>${escapeHtml(item.title)}</strong>
      <span>${escapeHtml(item.due_at)} - ${escapeHtml(item.entity_type)} #${escapeHtml(item.entity_id)}</span>
    </article>
  `).join('');
  reminderWarningModal.classList.remove('hidden');
}

function toggleAdminPanels(enabled) {
  if (privateDocumentsLink) {
    privateDocumentsLink.classList.toggle('hidden', !enabled);
  }


}

function toggleRoleMenus(user) {
  const roles = normalizeRoles(user);
  const operationalEnabled = roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
  const adminEnabled = roles.includes('admin');
  const profileEnabled = operationalEnabled || roles.includes('membro');
  setMenuVisibility({
    communications: operationalEnabled,
    meetings: operationalEnabled,
    practices: operationalEnabled,
    documents: operationalEnabled,
    rsu: operationalEnabled || adminEnabled,
    administration: profileEnabled,
    users: adminEnabled,
    profile: profileEnabled,
    contacts: operationalEnabled,
    privateDocuments: adminEnabled,
  });
}

function setMenuVisibility(permissions) {
  [
    [communicationsMenu, permissions.communications],
    [meetingsMenu, permissions.meetings],
    [practicesMenu, permissions.practices],
    [documentsMenu, permissions.documents],
    [rsuMenu, permissions.rsu],
    [administrationMenu, permissions.administration],
    [usersMenuLink, permissions.users],
    [profileMenuLink, permissions.profile],
    [contactsMenuLink, permissions.contacts],
    [privateDocumentsLink, permissions.privateDocuments],
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
  if (remindersCard) {
    remindersCard.classList.toggle('hidden', !enabled);
  }
}

async function boot() {
  if (!authToken()) {
    setMenuVisibility({});
    setView(false);
    await loadPublicBoard(publicBoardGuest);
    return;
  }

  let me;
  try {
    me = await MyRsuAuth.me();
  } catch (error) {
    clearAuthToken();
    setView(false);
    await loadPublicBoard(publicBoardGuest);
    return;
  }

  toggleRoleMenus(me);
  if (isOnlyMember(me)) {
    await loadPublicBoard(publicBoardGuest);
    setView(false);
    return;
  }
  renderUser(me);
  toggleAdminPanels(isAdmin(me));
  toggleReportsBadge(canModerateReports(me));
  if (canModerateReports(me)) {
    Promise.all([loadReportStats(), loadCommentStats(), loadReminderStats()]).catch((error) => showMessage(error.message));
  }
  loadPublicBoard(publicBoardUser).catch((error) => showMessage(error.message));
  setView(true);
}

document.querySelector('#publicAuthLink')?.addEventListener('click', async (event) => {
  if (!authToken()) return;
  event.preventDefault();
  try {
    await MyRsuAuth.logout();
  } catch (error) {
    showMessage(error.message);
  }
  clearAuthToken();
  window.location.replace(`index.html?logout=${Date.now()}`);
});

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
  const pdfViewer = document.querySelector(`#documentModalPdf${suffix}`);
  document.querySelector(`#documentModalTitle${suffix}`).textContent = item?.title || '';
  modal.classList.remove('hidden');
  if (isMobileDashboard()) {
    frame.src = '';
    frame.classList.add('hidden');
    pdfViewer.classList.remove('hidden');
    renderDashboardPdf(item || {}, pdfViewer);
  } else {
    pdfViewer.classList.add('hidden');
    pdfViewer.innerHTML = '';
    frame.src = item?.url || '';
    frame.classList.remove('hidden');
  }
  loadComments(button.dataset.document, `#documentComments${suffix}`);
});

function isMobileDashboard() {
  return window.matchMedia('(max-width: 900px), (pointer: coarse)').matches;
}

async function renderDashboardPdf(item, viewer) {
  viewer.innerHTML = '<p class="muted">Caricamento documento...</p>';
  try {
    const pdfjsLib = await import('../../vendor/pdfjs/pdf.mjs');
    pdfjsLib.GlobalWorkerOptions.workerSrc = '../../vendor/pdfjs/pdf.worker.mjs';
    const response = await fetch(item.url || '', { cache: 'no-store' });
    if (!response.ok) throw new Error('Documento non disponibile.');
    const pdf = await pdfjsLib.getDocument({
      data: await response.arrayBuffer(),
      cMapUrl: '../../vendor/pdfjs/cmaps/',
      cMapPacked: true,
      standardFontDataUrl: '../../vendor/pdfjs/standard_fonts/',
      wasmUrl: '../../vendor/pdfjs/wasm/',
    }).promise;
    viewer.innerHTML = '';
    for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
      const page = await pdf.getPage(pageNumber);
      const baseViewport = page.getViewport({ scale: 1 });
      const availableWidth = Math.max(280, viewer.clientWidth - 16);
      const viewport = page.getViewport({ scale: availableWidth / baseViewport.width });
      const canvas = document.createElement('canvas');
      const pageWrap = document.createElement('div');
      canvas.width = Math.ceil(viewport.width);
      canvas.height = Math.ceil(viewport.height);
      canvas.className = 'dashboard-pdf-page';
      pageWrap.className = 'dashboard-pdf-page-wrap';
      pageWrap.style.width = `${canvas.width}px`;
      pageWrap.style.height = `${canvas.height}px`;
      pageWrap.appendChild(canvas);
      viewer.appendChild(pageWrap);
      await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
      const annotations = await page.getAnnotations({ intent: 'display' });
      annotations.filter((annotation) => (annotation.url || annotation.unsafeUrl) && annotation.rect)
        .forEach((annotation) => addDashboardPdfLink(
          pageWrap,
          viewport,
          annotation.rect,
          annotation.url || annotation.unsafeUrl,
        ));
      if (pageNumber === 1 && item.verifyUrl) {
        addDashboardPdfLink(pageWrap, viewport, [502, 728, 554, 780], item.verifyUrl);
      }
    }
  } catch (error) {
    viewer.textContent = error.message || 'Anteprima non disponibile.';
  }
}

function addDashboardPdfLink(pageWrap, viewport, rect, url) {
  const [x1, y1, x2, y2] = viewport.convertToViewportRectangle(rect);
  const link = document.createElement('a');
  link.href = url;
  link.className = 'dashboard-pdf-link';
  link.dataset.pdfLink = url;
  link.setAttribute('aria-label', 'Verifica autenticità');
  link.style.left = `${Math.min(x1, x2)}px`;
  link.style.top = `${Math.min(y1, y2)}px`;
  link.style.width = `${Math.abs(x2 - x1)}px`;
  link.style.height = `${Math.abs(y2 - y1)}px`;
  pageWrap.appendChild(link);
}

document.addEventListener('click', (event) => {
  const link = event.target.closest('.dashboard-pdf-link');
  if (!link) return;
  event.preventDefault();
  dashboardVerifyFrame.src = link.dataset.pdfLink || link.href;
  dashboardVerifyModal.classList.remove('hidden');
});

if (closeDashboardVerifyModal) closeDashboardVerifyModal.addEventListener('click', () => {
  dashboardVerifyFrame.src = '';
  dashboardVerifyModal.classList.add('hidden');
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
    <button class="comment-submit" type="submit" title="Invia commento" aria-label="Invia commento">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 21 3l-8.5 18-2.2-7.3L3 11.5Z"></path><path d="m10.3 13.7 4.4-4.4"></path></svg>
      <span>Invia</span>
    </button>
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
  const token = authToken();
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
  const pdfViewer = document.querySelector(`#documentModalPdf${suffix}`);
  if (closeButton && modal) closeButton.addEventListener('click', () => {
    modal.classList.add('hidden');
    if (frame) frame.src = '';
    if (pdfViewer) pdfViewer.innerHTML = '';
  });
  if (modal) modal.addEventListener('click', (event) => {
    if (event.target === modal) {
      modal.classList.add('hidden');
      if (frame) frame.src = '';
      if (pdfViewer) pdfViewer.innerHTML = '';
    }
  });
});

if (closeReminderWarningModal && reminderWarningModal) {
  closeReminderWarningModal.addEventListener('click', () => reminderWarningModal.classList.add('hidden'));
  reminderWarningModal.addEventListener('click', (event) => {
    if (event.target === reminderWarningModal) reminderWarningModal.classList.add('hidden');
  });
}

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
