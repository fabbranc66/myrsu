import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const dashboardSource = fs.readFileSync('ui/app/js/app.js', 'utf8');
const menuSource = fs.readFileSync('ui/auth-menu.js', 'utf8');
const dashboardHtml = fs.readFileSync('ui/app/index.html', 'utf8');
const menuIds = [
  'communicationsMenu', 'meetingsMenu', 'unionMeetingsMenuLink', 'workersAssembliesMenuLink',
  'votingsMenuLink', 'roomsMenuLink', 'practicesMenu', 'documentsMenu', 'rsuMenu',
  'administrationMenu', 'usersMenuLink', 'profileMenuLink', 'contactsMenuLink',
  'fundsMenuLink', 'pdrMenuLink', 'normativaMenuLink', 'privateDocumentsLink',
];
const elements = Object.fromEntries(menuIds.map((id) => [id, {
  hidden: true,
  classList: {toggle(className, hidden) {elements[id].hidden = hidden;}},
}]));
const context = vm.createContext(elements);
const normalizeSource = dashboardSource.match(/function normalizeRoles\(user\) \{[\s\S]*?\n\}/)[0];
const visibilitySource = dashboardSource.slice(
  dashboardSource.indexOf('function toggleRoleMenus(user)'),
  dashboardSource.indexOf('function toggleReportsBadge(enabled)'),
);
vm.runInContext(`${normalizeSource}\n${visibilitySource}`, context);

for (const roles of [['membro'], ['admin'], ['delegato'], ['rls'], ['delegato', 'rls'], ['membro', 'delegato'], []]) {
  context.toggleRoleMenus({roles});
  const operational = roles.some((role) => ['admin', 'delegato', 'rls'].includes(role));
  const profile = operational || roles.includes('membro');
  for (const id of menuIds) {
    const visible = ['administrationMenu', 'profileMenuLink'].includes(id) ? profile
      : ['usersMenuLink', 'privateDocumentsLink'].includes(id) ? roles.includes('admin')
      : operational;
    assert.equal(elements[id].hidden, !visible, `${roles.join(',') || 'ospite'}: ${id}`);
  }
}
context.toggleRoleMenus({roles: [{name: 'membro'}]});
assert.equal(elements.profileMenuLink.hidden, false);
assert.equal(elements.meetingsMenu.hidden, true);

for (const id of menuIds) assert.ok(dashboardHtml.includes(`id="${id}"`), id);
for (const id of ['fundsMenuLink', 'pdrMenuLink', 'normativaMenuLink']) {
  assert.ok(dashboardHtml.includes(`id="${id}" class="hidden"`), id);
}
const sectionsContext = vm.createContext({});
const sectionsSource = menuSource.slice(menuSource.indexOf('const sections = ['), menuSource.indexOf('  function renderMenu()'));
vm.runInContext(`${sectionsSource}\nglobalThis.sections = sections;`, sectionsContext);
for (const role of ['membro', 'admin', 'delegato', 'rls']) {
  const sections = sectionsContext.sections;
  const meetings = sections.find((section) => section.label === 'Incontri');
  assert.equal(meetings.pages.some((page) => page[2].includes(role)), role !== 'membro');
  if (role === 'membro') {
    const administration = sections.find((section) => section.label === 'Amministrazione');
    assert.deepEqual(Array.from(administration.pages.filter((page) => page[2].includes(role)), (page) => page[1]), ['Profilo']);
  }
}
console.log('OK: menu Membro con solo Profilo in Amministrazione, senza Incontri; altri ruoli e ospiti verificati. Nessuna richiesta reale.');
