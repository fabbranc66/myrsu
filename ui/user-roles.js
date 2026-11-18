function userRoleNames(value) {
  const names = Array.isArray(value) ? value : String(value || '').split(',');
  return [...new Set(names.map((name) => String(name).trim()).filter(Boolean))].sort();
}

function userRoleOptions(roles, current = []) {
  const selected = userRoleNames(current);
  const currentValue = selected.join(',');
  const labels = new Map(roles.map((role) => [role.name, role.label]));
  const options = roles.map((role) => ({ value: role.name, label: role.label }));
  if (labels.has('delegato') && labels.has('rls')) {
    options.push({ value: 'delegato,rls', label: 'Delegato + RLS' });
  }
  if (selected.length > 1 && !options.some((option) => option.value === currentValue)) {
    options.push({ value: currentValue, label: selected.map((name) => labels.get(name) || name).join(' + ') });
  }
  const escape = (value) => String(value).replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
  return options.map((option) => `<option value="${escape(option.value)}"${option.value === currentValue ? ' selected' : ''}>${escape(option.label)}</option>`).join('');
}
