function renderUserRow(user, roles, access = {}) {
  const nextStatus = user.status === 'active' ? 'suspended' : 'active';
  const currentRole = String(user.roles || '').split(',')[0] || '';
  const options = roles
    .map((role) => `<option value="${role.name}" ${role.name === currentRole ? 'selected' : ''}>${role.label}</option>`)
    .join('');
  const name = access.canUpdate ? `<input data-name="${user.id}" value="${escapeAttr(user.name)}">` : `<span class="truncate-title" title="${escapeAttr(user.name)}">${escapeHtml(user.name)}</span>`;
  const email = access.canUpdate ? `<input data-email="${user.id}" type="email" value="${escapeAttr(user.email)}">` : `<span class="truncate-title" title="${escapeAttr(user.email)}">${escapeHtml(user.email)}</span>`;
  const password = access.canUpdate ? `<input data-password="${user.id}" type="password" placeholder="New password">` : '-';
  const status = access.canUpdate
    ? `<button class="icon-action status-action" data-status="${nextStatus}" data-id="${user.id}" title="Change status">${MyRsuIcons.status(user.status)}</button>`
    : user.status;
  const role = access.canManageRoles ? `<select data-role="${user.id}">${options}</select>` : (currentRole || '-');
  const editActions = access.canUpdate
    ? `<a class="icon-action" href="user-edit.html?id=${user.id}" title="Edit">${MyRsuIcons.get('edit')}</a><button class="icon-action" data-save="${user.id}" title="Save">${MyRsuIcons.get('save')}</button>`
    : '';
  const deleteAction = access.canDelete
    ? `<button class="icon-action danger" data-delete="${user.id}" title="Delete">${MyRsuIcons.get('trash')}</button>`
    : '';
  const gdprAction = access.canViewGdpr
    ? `<button class="icon-action" data-consents="${user.id}" title="GDPR">${MyRsuIcons.get('shield')}</button>`
    : '';
  const activityAction = access.canViewActivity
    ? `<button class="icon-action" data-activity="${user.id}" title="Logs">${MyRsuIcons.get('logs')}</button>`
    : '';

  return `
    <tr>
      <td data-label="Nome">${name}</td>
      <td data-label="Email">${email}</td>
      <td data-label="Password">${password}</td>
      <td data-label="Stato">${status}</td>
      <td data-label="Ruolo">${role}</td>
      <td data-label="Azioni" class="actions-cell">
        ${editActions}
        ${gdprAction}
        ${activityAction}
        ${deleteAction}
      </td>
    </tr>
  `;
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function escapeAttr(value) {
  return escapeHtml(value).replace(/`/g, '&#096;');
}
