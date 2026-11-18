export function createDocumentFolderPermissions({ element, listing, api, reload, message }) {
  const dialog = document.querySelector('#documentFolderPermissionsDialog');
  const form = document.querySelector('#documentFolderPermissionsForm');
  const roleList = document.querySelector('#documentFolderRoles');
  const title = document.querySelector('#documentFolderPermissionsTitle');
  const errorMessage = document.querySelector('#documentFolderPermissionsError');
  const submit = form.querySelector('button[type="submit"]');
  let data = {};
  let folderId = null;

  function escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
  }

  function updateInputs() {
    const visibility = form.elements.visibility;
    if (visibility) visibility.disabled = form.elements.inherit_permissions.checked;
    const custom = !visibility || visibility.value === '';
    roleList.hidden = form.elements.inherit_permissions.checked || !custom;
    for (const select of roleList.querySelectorAll('select')) {
      select.disabled = form.elements.inherit_permissions.checked || select.dataset.admin === 'true';
    }
  }

  function open(event) {
    const button = event.target.closest('button[data-folder-permissions]');
    if (!button || !data.can_set_permissions) return;
    folderId = Number(button.dataset.folderPermissions);
    const folder = data.folders.find((item) => Number(item.id) === folderId);
    if (!folder?.can_set_permissions) return;
    title.textContent = `Permessi · ${folder.name}`;
    errorMessage.textContent = '';
    form.elements.inherit_permissions.checked = Boolean(Number(folder.inherit_permissions));
    if (form.elements.visibility) form.elements.visibility.value = folder.visibility ?? (Boolean(Number(folder.inherit_permissions)) ? 'document' : '');
    roleList.innerHTML = data.roles.map((role) => {
      const current = role.name === 'admin' ? 'manage'
        : data.permissions.find((item) => Number(item.folder_id) === folderId && Number(item.role_id) === Number(role.id))?.access_level || '';
      return `<label>${escape(role.label || role.name)}<select data-role-id="${Number(role.id)}" data-admin="${role.name === 'admin'}">
        <option value="" ${current === '' ? 'selected' : ''}>Nessuno</option>
        <option value="read" ${current === 'read' ? 'selected' : ''}>Lettura</option>
        <option value="manage" ${current === 'manage' ? 'selected' : ''}>Gestione</option>
      </select></label>`;
    }).join('');
    updateInputs();
    dialog.showModal();
  }

  element.addEventListener('click', open);
  listing.addEventListener('click', open);
  form.elements.inherit_permissions.addEventListener('change', updateInputs);
  form.elements.visibility?.addEventListener('change', updateInputs);
  document.querySelector('#cancelDocumentFolderPermissions').addEventListener('click', () => dialog.close());
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submit.disabled) return;
    submit.disabled = true;
    try {
      const permissions = [...roleList.querySelectorAll('select')].filter((select) => select.value !== '')
        .map((select) => ({ role_id: Number(select.dataset.roleId), access_level: select.value }));
      const body = { inherit_permissions: form.elements.inherit_permissions.checked, ...(form.elements.visibility ? { visibility: form.elements.visibility.value || null } : {}), permissions };
      const request = (confirmed = false) => api(`/document-folders/${folderId}/permissions`, {
        method: 'PATCH', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...body, ...(confirmed ? { confirm_visibility_change: true } : {}) }),
      });
      try {
        await request();
      } catch (error) {
        if (error.code !== 'document_visibility_expansion') throw error;
        if (!window.confirm(error.message)) { errorMessage.textContent = 'Modifica annullata.'; return; }
        await request(true);
      }
      await reload();
      dialog.close();
      message.textContent = 'Permessi effettivi aggiornati. La visibilità propria dei documenti resta memorizzata.';
    } catch (error) {
      errorMessage.textContent = error.message;
    } finally {
      submit.disabled = false;
    }
  });

  return { setData(folderData) { data = folderData; } };
}
