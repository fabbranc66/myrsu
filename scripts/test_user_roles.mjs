import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const helperSource = fs.readFileSync('ui/user-roles.js', 'utf8');
const roles = [
  {name: 'admin', label: 'Admin'},
  {name: 'delegato', label: 'Delegato'},
  {name: 'rls', label: 'RLS'},
  {name: 'worker', label: 'Lavoratore'},
];
const context = vm.createContext({MyRsuIcons: {get: () => '', status: () => ''}});
vm.runInContext(helperSource + fs.readFileSync('ui/user-row.js', 'utf8'), context);
assert.deepEqual(Array.from(context.userRoleNames('rls, delegato,rls')), ['delegato', 'rls']);
assert.deepEqual(Array.from(context.userRoleNames(['rls', 'delegato'])), ['delegato', 'rls']);
assert.match(context.userRoleOptions(roles, ['rls', 'delegato']), /value="delegato,rls" selected>Delegato \+ RLS/);
assert.match(context.userRoleOptions(roles, ['rls']), /value="rls" selected/);
assert.doesNotMatch(context.userRoleOptions(roles, ['rls']), /value="delegato,rls" selected/);
assert.match(context.userRoleOptions(roles, ['admin', 'rls']), /value="admin,rls" selected/);
assert.doesNotMatch(context.userRoleOptions([{name: 'rls', label: 'RLS'}]), /delegato,rls/);
assert.match(context.userRoleOptions([{name: 'worker', label: '<test>'}]), /&lt;test&gt;/);
const user = {id: 14, name: 'Test', email: 'test@example.invalid', status: 'active', roles: 'delegato,rls'};
assert.match(context.renderUserRow(user, roles, {canManageRoles: true}), /value="delegato,rls" selected/);
assert.match(context.renderUserRow(user, roles), /delegato \+ rls/);

const userSource = fs.readFileSync('ui/users.js', 'utf8').replace(/\r\n/g, '\n');
const handler = userSource.match(/usersTable.addEventListener\('change', async \(event\) => \{([\s\S]*?)\n\}\);/)[1];
const changes = [];
const changeContext = vm.createContext({
  state: {isAdmin: true},
  api: async (path, options) => changes.push({path, body: JSON.parse(options.body)}),
  showMessage() {}, showError(error) {throw error;},
});
vm.runInContext(helperSource + '\nasync function changeRole(event) {' + handler + '\n}', changeContext);
for (const value of ['delegato,rls', 'rls', 'delegato']) {
  await changeContext.changeRole({target: {closest: () => ({dataset: {role: '14'}, value})}});
}
assert.deepEqual(changes, [
  {path: '/users/14/roles', body: {roles: ['delegato', 'rls']}},
  {path: '/users/14/roles', body: {roles: ['rls']}},
  {path: '/users/14/roles', body: {roles: ['delegato']}},
]);
const roleLogout = [];
changeContext.state.userId = 14;
changeContext.sessionStorage = {removeItem: (key) => roleLogout.push(['session', key])};
changeContext.localStorage = {removeItem: (key) => roleLogout.push(['local', key])};
changeContext.window = {location: {href: ''}};
changeContext.api = async () => ({sessions_revoked: true});
await changeContext.changeRole({target: {closest: () => ({dataset: {role: '14'}, value: 'rls'})}});
assert.deepEqual(roleLogout, [['session', 'token'], ['local', 'token']]);
assert.equal(changeContext.window.location.href, 'login.html');
roleLogout.length = 0;
changeContext.api = async () => ({sessions_revoked: false});
await changeContext.changeRole({target: {closest: () => ({dataset: {role: '14'}, value: 'rls'})}});
assert.deepEqual(roleLogout, []);

const fields = new Map();
function field(selector) {
  if (!fields.has(selector)) fields.set(selector, {value: '', disabled: false, files: [], handlers: {}, addEventListener(type, handler) {this.handlers[type] = handler;}});
  return fields.get(selector);
}
const form = field('#editForm');
for (const name of ['name', 'first_name', 'last_name', 'email', 'phone', 'mobile', 'city', 'country', 'union_code', 'union_logo', 'status', 'id', 'created_at', 'updated_at', 'password']) form[name] = field(name);
form.role = field('#roleSelect');
const requests = [];
let adminSession = true;
let adminUserId = 1;
const removedTokens = [];
const editContext = vm.createContext({
  document: {querySelector: field}, window: {location: {search: '?id=14'}}, URLSearchParams,
  sessionStorage: {getItem: () => 'test-token', removeItem: (key) => removedTokens.push(['session', key])},
  localStorage: {getItem: () => null, removeItem: (key) => removedTokens.push(['local', key])},
  FormData: class {
    constructor(form) {this.values = Object.entries(form).filter(([, value]) => value && typeof value === 'object' && 'value' in value).map(([name, value]) => [name, value.value]);}
    entries() {return this.values[Symbol.iterator]();}
  },
  fetch: async (path, options) => {
    if (options.method) requests.push({path, body: JSON.parse(options.body)});
    const data = path === '../api/v1/me' ? {user: {id: adminUserId}, roles: adminSession ? ['admin'] : ['delegato', 'rls']}
      : path === '../api/v1/roles' ? roles
      : path === '../api/v1/users/14' ? {user: {...user, union_code: 'TEST'}, roles: ['rls', 'delegato']}
      : [];
    const payload = {data};
    return {ok: true, text: async () => JSON.stringify(payload)};
  },
});
const editSource = fs.readFileSync('ui/user-edit.js', 'utf8').replace(/load\(\)\.catch\([\s\S]*$/, '');
vm.runInContext(helperSource + editSource, editContext);
await editContext.load();
assert.equal(form.role.value, 'delegato,rls');
assert.equal(form.union_code.disabled, false);
assert.equal(form.union_code.value, 'TEST');
await form.handlers.submit({preventDefault() {}});
assert.deepEqual(requests[0], {path: '../api/v1/users/14/roles', body: {roles: ['delegato', 'rls']}});
assert.equal(requests[1].body.union_code, 'TEST');
assert.equal('role' in requests[1].body, false);
form.role.value = 'rls';
editContext.syncUnionCodeField();
assert.equal(form.union_code.disabled, false);
await form.handlers.submit({preventDefault() {}});
assert.deepEqual(requests[2].body.roles, ['rls']);
form.role.value = 'worker';
editContext.syncUnionCodeField();
assert.equal(form.union_code.disabled, true);
assert.equal(form.union_code.value, '');
form.password.value = 'reset-altro-utente';
await form.handlers.submit({preventDefault() {}});
assert.equal(requests.at(-1).body.password, 'reset-altro-utente');
assert.deepEqual(removedTokens, []);
assert.match(field('#message').textContent, /Password reimpostata e sessioni revocate/);
assert.equal(form.password.value, '');
adminUserId = 14;
await editContext.load();
form.password.value = 'reset-password-propria';
await form.handlers.submit({preventDefault() {}});
assert.deepEqual(removedTokens, [['session', 'token'], ['local', 'token']]);
assert.equal(editContext.window.location.href, 'login.html');
assert.equal(form.hidden, true);
assert.equal(form.password.value, '');
await editContext.load();
requests.length = 0;
removedTokens.length = 0;
form.role.value = 'rls';
form.password.value = '';
await form.handlers.submit({preventDefault() {}});
assert.deepEqual(requests.map((request) => request.path), ['../api/v1/users/14', '../api/v1/users/14/roles']);
assert.deepEqual(removedTokens, [['session', 'token'], ['local', 'token']]);
assert.equal(editContext.window.location.href, 'login.html');
await editContext.load();
form.role.value = 'rls';
form.password.value = 'password-e-ruolo-insieme';
const beforeCombinedChange = requests.length;
await form.handlers.submit({preventDefault() {}});
assert.equal(requests.length, beforeCombinedChange);
assert.match(field('#message').textContent, /separatamente/);
adminSession = false;
await editContext.load();
assert.equal(form.hidden, true);
const beforeDeniedSave = requests.length;
await form.handlers.submit({preventDefault() {}});
assert.equal(requests.length, beforeDeniedSave);
changeContext.state.isAdmin = false;
const beforeDeniedRoleChange = changes.length;
await changeContext.changeRole({target: {closest: () => ({dataset: {role: '14'}, value: 'admin'})}});
assert.equal(changes.length, beforeDeniedRoleChange);
const configureBody = userSource.match(/function configureAccess\(me\) \{([\s\S]*?)\n\}\n\ncreateForm.addEventListener/)[1];
const loadUsersBody = userSource.match(/async function loadUsers\(\) \{([\s\S]*?)\n\}\n\nfunction syncCreateUnionCode/)[1];
const loadRolesBody = userSource.match(/async function loadRoles\(\) \{([\s\S]*?)\n\}\n\nasync function checkGdpr/)[1];
for (const actorRoles of [['admin'], ['delegato'], ['rls'], ['delegato', 'rls']]) {
  const accessFlags = [];
  const calls = [];
  let createHidden;
  const management = vm.createContext({
    state: {permissions: [], roles: [], isAdmin: false}, roleSelect: {}, usersTable: {},
    createForm: {classList: {toggle(name, hidden) {createHidden = hidden;}}},
    window: {location: {replace() {throw new Error('Operatore non deve perdere la consultazione.');}}},
    syncCreateUnionCode() {},
    renderUserRow(user, roles, access) {accessFlags.push(access); return '';},
    api: async path => {calls.push(path); return path === '/roles' ? roles : [user];},
  });
  vm.runInContext('function configureAccess(me) {' + configureBody + '\n} async function loadUsers() {' + loadUsersBody + '\n} async function loadRoles() {' + loadRolesBody + '\n}', management);
  management.configureAccess({roles: actorRoles, permissions: ['roles.manage', 'users.create', 'users.update', 'users.delete', 'gdpr.view_all', 'activity.view']});
  await management.loadRoles();
  await management.loadUsers();
  const admin = actorRoles.includes('admin');
  assert.equal(createHidden, !admin);
  assert.equal(calls.includes('/roles'), admin);
  for (const key of ['canUpdate', 'canDelete', 'canManageRoles']) assert.equal(accessFlags[0][key], admin);
  assert.equal(accessFlags[0].canViewGdpr, true);
}
for (const path of ['ui/users.html', 'ui/user-edit.html']) {
  const html = fs.readFileSync(path, 'utf8');
  assert.ok(html.indexOf('user-roles.js?v=') < html.indexOf(path.endsWith('/users.html') ? 'user-row.js?v=' : 'user-edit.js?v='));
}
assert.match(fs.readFileSync('ui/auth-menu.js', 'utf8'), /\['users\.html',\s*'Utenti',\s*\['admin'\]\]/);
console.log('OK: Delegato + RLS, ruoli singoli e combinazioni esistenti conservati; salvataggio da elenco e scheda senza perdere ruolo RLS o sigla sindacale. Nessuna richiesta reale.');
