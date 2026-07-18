(() => {
  const table = document.querySelector('#documentsTable');
  const modal = document.querySelector('#documentCommentsModal');
  const list = document.querySelector('#documentCommentsList');
  const closeButton = document.querySelector('#closeDocumentCommentsModal');
  const jsonOutput = document.querySelector('#jsonOutput');
  const token = sessionStorage.getItem('token');

  table?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-comments]');
    if (!button) return;
    const response = await fetch(`../api/v1/comments/document/${button.dataset.comments}`, {
      headers: { Authorization: `Bearer ${token}` },
    });
    const payload = await response.json();
    jsonOutput.textContent = JSON.stringify(payload, null, 2);
    if (!response.ok) return;
    list.innerHTML = commentsHtml(payload.data || []);
    modal.showModal();
  });

  closeButton?.addEventListener('click', () => modal.close());

  function commentsHtml(comments) {
    if (comments.length === 0) {
      return '<p class="muted">Nessun commento approvato.</p>';
    }

    return comments.map((comment) => `
      <article class="comment-card">
        <p>${escapeHtml(comment.message)}</p>
        ${comment.reply ? `<p><strong>Risposta RSU:</strong> ${escapeHtml(comment.reply)}</p>` : ''}
        <small>${escapeHtml(comment.created_at)}</small>
      </article>
    `).join('');
  }

  function escapeHtml(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }
})();
