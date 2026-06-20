function renderUserRow(user, roles) {
  const nextStatus = user.status === 'active' ? 'suspended' : 'active';
  const currentRole = String(user.roles || '').split(',')[0] || '';
  const options = roles
    .map((role) => `<option value="${role.name}" ${role.name === currentRole ? 'selected' : ''}>${role.label}</option>`)
    .join('');

  return `
    <tr>
      <td><input data-name="${user.id}" value="${user.name}"></td>
      <td><input data-email="${user.id}" type="email" value="${user.email}"></td>
      <td><input data-password="${user.id}" type="password" placeholder="New password"></td>
      <td><button class="icon-action status-action" data-status="${nextStatus}" data-id="${user.id}" title="Change status">${MyRsuIcons.status(user.status)}</button></td>
      <td><select data-role="${user.id}">${options}</select></td>
      <td class="actions-cell">
        <a class="icon-action" href="user-edit.html?id=${user.id}" title="Edit">${MyRsuIcons.get('edit')}</a>
        <button class="icon-action" data-save="${user.id}" title="Save">${MyRsuIcons.get('save')}</button>
        <button class="icon-action" data-consents="${user.id}" title="GDPR">${MyRsuIcons.get('shield')}</button>
        <button class="icon-action" data-activity="${user.id}" title="Logs">${MyRsuIcons.get('logs')}</button>
        <button class="icon-action danger" data-delete="${user.id}" title="Delete">${MyRsuIcons.get('trash')}</button>
      </td>
    </tr>
  `;
}
