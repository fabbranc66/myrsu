(() => {
  if (window.MyRsuConfiguration) return;
  const root = window.location.pathname.split('/ui/')[0];
  const originalFetch = window.fetch.bind(window);
  let expected = null;
  const token = () => sessionStorage.getItem('token') || localStorage.getItem('token');
  function applies(url) {
    return url.origin === window.location.origin && url.pathname.startsWith(`${root}/api/v1/`) && !url.pathname.endsWith('/admin/configurations/selection') && !url.pathname.endsWith('/me');
  }
  window.fetch = (input, options = {}) => {
    const url = new URL(typeof input === 'string' || input instanceof URL ? input : input.url, window.location.href);
    if (expected && token() && applies(url)) {
      const headers = new Headers(options.headers || input.headers);
      headers.set('X-Configuration-Id', expected);
      options = { ...options, headers };
    }
    return originalFetch(input, options);
  };
  const originalOpen = XMLHttpRequest.prototype.open;
  const originalSend = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (method, url, ...args) {
    this.configurationGuard = applies(new URL(url, window.location.href));
    return originalOpen.call(this, method, url, ...args);
  };
  XMLHttpRequest.prototype.send = function (body) {
    if (this.configurationGuard && expected && token()) this.setRequestHeader('X-Configuration-Id', expected);
    return originalSend.call(this, body);
  };
  const ready = token() ? originalFetch(`${root}/api/v1/me`, { headers: { Authorization: `Bearer ${token()}` } }).then(async (response) => {
    const payload = await response.json();
    const data = payload.data;
    if (!response.ok || !data?.roles?.includes('admin') || !data.configuration) {
      expected = null;
      sessionStorage.removeItem('myrsu_configuration_id');
      return null;
    }
    const current = data.configuration;
    expected = String(current.id);
    sessionStorage.setItem('myrsu_configuration_id', expected);
    const banner = document.createElement('aside');
    banner.id = 'myrsuConfigurationBanner';
    banner.style.cssText = `padding:8px 16px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;border-bottom:1px solid #cbd5e1;background:${current.is_operational ? '#eff6ff' : '#fff2cc'}`;
    const label = document.createElement('strong');
    label.textContent = `${current.is_operational ? 'Operativo' : 'Configurazione non operativa'}: ${current.name}`;
    const link = document.createElement('a');
    link.href = `${root}/ui/configurations.html`;
    link.textContent = 'Gestisci configurazioni';
    banner.append(label, link);
    const archive = document.createElement('a');
    archive.href = `${root}/ui/archive.html`;
    archive.textContent = 'Archivio e condivisioni';
    banner.append(archive);
    if (!current.is_operational) {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = 'Torna all’Operativo';
      button.addEventListener('click', async () => {
        const result = await originalFetch(`${root}/api/v1/admin/configurations/selection`, { method: 'POST', headers: { Authorization: `Bearer ${token()}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ configuration_id: null }) });
        if (result.ok) window.location.reload();
        else alert('Cambio configurazione non riuscito. Apri Gestisci configurazioni.');
      });
      banner.append(button);
    }
    document.querySelector('.app-header, .shell')?.before(banner);
    return current;
  }).catch(() => null) : Promise.resolve(null);
  window.MyRsuConfiguration = { ready };
})();
