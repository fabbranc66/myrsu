const loginView = document.querySelector('#loginView');
const appView = document.querySelector('#appView');
const loginForm = document.querySelector('#loginForm');
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
const pendingQueueLink = document.querySelector('#pendingQueueLink');

function showMessage(text = '') {
  message.textContent = text;
}

function setView(authenticated) {
  loginView.classList.toggle('hidden', authenticated);
  appView.classList.toggle('hidden', !authenticated);
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
}

async function boot() {
  if (!sessionStorage.getItem('token')) {
    setView(false);
    return;
  }

  try {
    const me = await MyRsuAuth.me();
    renderUser(me);
    toggleAdminQueue(isAdmin(me));
    if (isAdmin(me)) {
      await loadQueueStatus();
    }
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
    const me = await MyRsuAuth.me();
    renderUser(me);
    toggleAdminQueue(isAdmin(me));
    if (isAdmin(me)) {
      await loadQueueStatus();
    }
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

boot();
