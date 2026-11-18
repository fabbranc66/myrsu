window.MyRsuProjection = {
  async publish(api, documentId) {
    const path = `/documents/${documentId}/projection`;
    try {
      return await api(path, { method: 'POST' });
    } catch (error) {
      if (error.code !== 'projection_confirmation_required') throw error;
      if (!window.confirm(error.message)) return null;
      return api(path, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ confirm_restricted_projection: true }),
      });
    }
  },
};
