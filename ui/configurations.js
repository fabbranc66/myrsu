(() => {
  const root = window.location.pathname.split('/ui/')[0];
  const panel = document.querySelector('#configurationPanel');
  const message = document.querySelector('#configurationMessage');
  const rows = document.querySelector('#configurationRows');
  const saveForm = document.querySelector('#configurationSaveForm');
  const createForm = document.querySelector('#configurationCreateForm');
  let state;
  let busy = false;
  async function api(path, options = {}) {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token');
    const response = await fetch(`${root}/api/v1/admin/configurations${path}`, { ...options, headers: { Authorization: `Bearer ${token || ''}`, 'Content-Type': 'application/json' } });
    const data = await response.json();
    document.querySelector('#configurationJson').textContent = JSON.stringify(data, null, 2);
    if (!response.ok) throw new Error(data.error?.message || 'Operazione non riuscita.');
    return data.data;
  }
  async function action(callback) {
    if (busy) return;
    busy = true;
    document.querySelectorAll('#configurationPanel button').forEach((button) => { button.disabled = true; });
    try { await callback(); } catch (error) { message.textContent = error.message; }
    finally { busy = false; document.querySelectorAll('#configurationPanel button').forEach((button) => { button.disabled = false; }); }
  }
  async function load() {
    state = await api('');
    panel.hidden = false;
    document.querySelector('#configurationCurrent').textContent = state.current.name;
    saveForm.elements.name.value = state.current.name;
    rows.replaceChildren();
    state.configurations.forEach((configuration) => {
      const row = document.createElement('tr');
      [configuration.name, configuration.is_operational ? 'Operativa per tutti' : 'Riservata admin', configuration.updated_at].forEach((text) => {
        const cell = document.createElement('td'); cell.textContent = text; row.append(cell);
      });
      const actions = document.createElement('td');
      const open = document.createElement('button');
      open.type = 'button'; open.textContent = configuration.id === state.current.id ? 'Aperta' : 'Apri';
      open.addEventListener('click', () => action(async () => { await api('/selection', { method: 'POST', body: JSON.stringify({ configuration_id: configuration.id }) }); window.location.reload(); }));
      actions.append(open);
      if (!configuration.is_operational) {
        const operational = document.createElement('button');
        operational.type = 'button'; operational.textContent = 'Rendi operativa';
        operational.addEventListener('click', () => action(async () => {
          const confirmation = prompt(`Questa configurazione diventerà operativa per tutti gli utenti. I dati attuali non vengono cancellati. Per confermare scrivi: ${configuration.name}`);
          if (confirmation !== configuration.name) return;
          await api(`/${configuration.id}/operational`, { method: 'POST', body: JSON.stringify({ confirm: confirmation }) });
          window.location.reload();
        }));
        actions.append(operational);
      }
      row.append(actions); rows.append(row);
    });
  }
  saveForm.addEventListener('submit', (event) => {
    event.preventDefault();
    action(async () => {
      if (!confirm(`Aggiornare “${state.current.name}” senza creare altre copie?`)) return;
      await api(`/${state.current.id}`, { method: 'PATCH', body: JSON.stringify({ name: saveForm.elements.name.value, confirm: state.current.name }) });
      message.textContent = 'Configurazione aggiornata, senza duplicati.';
      await load();
    });
  });
  createForm.addEventListener('submit', (event) => {
    event.preventDefault();
    action(async () => {
      message.textContent = 'Creazione configurazione in corso…';
      await api('', { method: 'POST', body: JSON.stringify({ name: createForm.elements.name.value, mode: createForm.elements.mode.value }) });
      createForm.reset(); message.textContent = 'Configurazione creata. Aprila oppure rendila operativa.';
      await load();
    });
  });
  document.querySelector('#configurationReturn').addEventListener('click', () => action(async () => {
    await api('/selection', { method: 'POST', body: JSON.stringify({ configuration_id: null }) }); window.location.reload();
  }));
  window.MyRsuConfiguration.ready.then(load).catch((error) => { message.textContent = error.message; });
})();
