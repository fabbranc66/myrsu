const apiBase = '../api/v1';
const token = sessionStorage.getItem('token');
const commentsTable = document.querySelector('#commentsTable');
const statusFilter = document.querySelector('#statusFilter');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const commentModal = document.querySelector('#commentModal');
const commentModalTitle = document.querySelector('#commentModalTitle');
const commentDocumentPreview = document.querySelector('#commentDocumentPreview');
const commentModalList = document.querySelector('#commentModalList');
const closeCommentModal = document.querySelector('#closeCommentModal');
let groups = [];

if (!token) window.location.href = 'app/index.html';

async function api(path, options = {}) {
  const headers = options.headers || {};
  headers.Authorization = `Bearer ${token}`;
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const text = await response.text();
  const payload = text ? JSON.parse(text) : {};
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Request failed');
  return payload.data;
}

async function loadGroups() {
  groups = await api(`/comments?status=${encodeURIComponent(statusFilter.value)}`);
  commentsTable.innerHTML = groups.map(row).join('');
}

function row(group) {
  return `<tr>
    <td>${escapeHtml(group.document_name)}</td>
    <td>${escapeHtml(group.category)}</td>
    <td><span class="doc-origin-tag converted">${group.count} commenti</span></td>
    <td class="actions-cell">
      <button class="icon-action" data-view="${group.document_id}" title="Apri">${MyRsuIcons.get('eye')}</button>
    </td>
  </tr>`;
}

commentsTable.addEventListener('click', (event) => {
  const button = event.target.closest('button[data-view]');
  if (button) showGroup(Number(button.dataset.view));
});

commentModalList.addEventListener('click', async (event) => {
  const button = event.target.closest('button');
  if (!button) return;
  const id = button.dataset.approve || button.dataset.reject;
  const status = button.dataset.approve ? 'approved' : 'rejected';
  await api(`/comments/${id}/moderation`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status, reply: replyValue(button) }),
  });
  message.textContent = status === 'approved' ? 'Commento approvato' : 'Commento respinto';
  await loadGroups();
  showGroup(Number(commentModal.dataset.documentId));
});

function showGroup(documentId) {
  const group = groups.find((item) => Number(item.document_id) === documentId);
  if (!group) {
    commentModal.close();
    return;
  }

  commentModal.dataset.documentId = String(documentId);
  commentModalTitle.textContent = `${group.document_name} (${group.count})`;
  commentDocumentPreview.src = `${apiBase}/documents/${documentId}/preview?token=${encodeURIComponent(token)}`;
  commentModalList.innerHTML = group.comments.map(commentRow).join('');
  commentModal.showModal();
}

function commentRow(comment) {
  return `<article class="comment-moderation-row">
    <header><strong>${translateStatus(comment.status)}</strong><small>${escapeHtml(comment.origin === 'member' ? (comment.author_name || 'membro') : 'anonima')}</small></header>
    <p>${escapeHtml(comment.message)}</p>
    <textarea data-reply="${comment.id}" rows="3" placeholder="Risposta RSU">${escapeHtml(comment.reply || '')}</textarea>
    <div class="actions-cell">
      <button class="icon-action" data-approve="${comment.id}" title="Approva">${MyRsuIcons.get('active')}</button>
      <button class="icon-action danger" data-reject="${comment.id}" title="Respingi">${MyRsuIcons.get('suspended')}</button>
    </div>
  </article>`;
}

function replyValue(button) {
  const row = button.closest('.comment-moderation-row');
  return row?.querySelector('[data-reply]')?.value || '';
}

function translateStatus(status) {
  return { pending: 'da moderare', approved: 'approvato', rejected: 'respinto' }[status] || status;
}

function escapeHtml(value) {
  return String(value || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

closeCommentModal.addEventListener('click', () => {
  commentDocumentPreview.src = '';
  commentModal.close();
});

statusFilter.addEventListener('change', loadGroups);
loadGroups().catch((error) => { message.textContent = error.message; });
