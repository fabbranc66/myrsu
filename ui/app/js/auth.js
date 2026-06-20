const MyRsuAuth = (() => {
  async function login(email, password) {
    const data = await MyRsuApi.request('/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        email,
        password,
        device_name: 'official-ui',
      }),
    });

    sessionStorage.setItem('token', data.access_token);
    return data;
  }

  async function logout() {
    await MyRsuApi.request('/auth/logout', { method: 'POST' });
    sessionStorage.removeItem('token');
  }

  async function me() {
    return MyRsuApi.request('/me');
  }

  return { login, logout, me };
})();
