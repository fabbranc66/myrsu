const MyRsuApi = (() => {
  const appRoot = window.location.pathname.split('/ui/')[0];
  const baseUrl = `${appRoot}/api/v1`;

  async function request(path, options = {}) {
    const token = sessionStorage.getItem('token');
    const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };

    if (token) {
      headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(`${baseUrl}${path}`, { ...options, headers });
    const text = await response.text();
    const payload = text ? JSON.parse(text) : {};

    if (!response.ok) {
      throw new Error(payload.error?.message || 'Errore richiesta');
    }

    return payload.data;
  }

  return { request };
})();
