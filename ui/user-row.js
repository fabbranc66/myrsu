function renderUserRow(user, roles, access = {}) {
  const nextStatus = user.status === 'active' ? 'suspended' : 'active';
  const currentRole = String(user.roles || '').split(',')[0] || '';
  const options = roles
    .map((role) => `<option value="${role.name}" ${role.name === currentRole ? 'selected' : ''}>${role.label}</option>`)
    .join('');
  const name = access.canUpdate ? `<input data-name="${user.id}" value="${user.name}">` : user.name;
  const email = access.canUpdate ? `<input data-email="${user.id}" type="email" value="${user.email}">` : user.email;
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
      <td>${name}</td>
      <td>${email}</td>
      <td>${password}</td>
      <td>${status}</td>
      <td>${role}</td>
      <td class="actions-cell">
        ${editActions}
        ${gdprAction}
        ${activityAction}
        ${deleteAction}
      </td>
    </tr>
  `;
}
