import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const actions = fs.readFileSync('ui/document-folder-actions.js', 'utf8');
const moveSource = actions.slice(actions.indexOf('  async function move('), actions.indexOf('\n  function openCreate('));
assert.ok(moveSource.includes('confirm_visibility_change'));

for (const confirmed of [false, true]) {
  const calls = [];
  let prompts = 0;
  let reloads = 0;
  const context = vm.createContext({
    data: {folders: [{id: 14, can_manage: true}], placements: []},
    busy: false, canMove: () => true, message: {textContent: ''},
    jsonOptions: body => ({body: JSON.stringify(body)}),
    api: async (path, options) => {
      const body = JSON.parse(options.body);
      calls.push({path, body});
      if (!body.confirm_visibility_change) {
        const error = new Error('Lo spostamento amplia la visibilità.');
        error.code = 'document_visibility_expansion';
        throw error;
      }
    },
    window: {confirm: () => { prompts += 1; return confirmed; }},
    reload: async () => { reloads += 1; },
  });
  vm.runInContext(moveSource, context);
  await context.move(100, 14);
  assert.equal(prompts, 1);
  assert.equal(calls.length, confirmed ? 2 : 1);
  assert.deepEqual(calls[0].body, {folder_id: 14});
  if (confirmed) assert.deepEqual(calls[1].body, {folder_id: 14, confirm_visibility_change: true});
  assert.equal(reloads, confirmed ? 1 : 0);
  assert.equal(context.busy, false);
}

const permissionsSource = fs.readFileSync('ui/document-folder-permissions.js', 'utf8').replace('export function', 'function');
for (const confirmed of [false, true]) {
  const handlers = new Map();
  const calls = [];
  let reloads = 0;
  let closes = 0;
  let prompts = 0;
  const checkbox = {checked: false, addEventListener: () => {}};
  const visibility = {value: 'members', addEventListener: () => {}};
  const submit = {disabled: false};
  const form = {
    elements: {inherit_permissions: checkbox, visibility},
    querySelector: () => submit,
    addEventListener: (name, handler) => handlers.set(`form:${name}`, handler),
  };
  const roleList = {innerHTML: '', querySelectorAll: () => []};
  const nodes = {
    '#documentFolderPermissionsDialog': {showModal: () => {}, close: () => { closes += 1; }},
    '#documentFolderPermissionsForm': form,
    '#documentFolderRoles': roleList,
    '#documentFolderPermissionsTitle': {textContent: ''},
    '#documentFolderPermissionsError': {textContent: ''},
    '#cancelDocumentFolderPermissions': {addEventListener: () => {}},
  };
  const surface = {addEventListener: (name, handler) => handlers.set(name, handler)};
  const context = vm.createContext({document: {querySelector: selector => nodes[selector]}, window: {confirm: () => { prompts += 1; return confirmed; }}});
  vm.runInContext(permissionsSource, context);
  const controller = context.createDocumentFolderPermissions({
    element: surface, listing: surface, message: {textContent: ''},
    reload: async () => { reloads += 1; },
    api: async (path, options) => {
      const body = JSON.parse(options.body);
      calls.push({path, body});
      if (!body.confirm_visibility_change) {
        const error = new Error('La modifica amplia la visibilità.');
        error.code = 'document_visibility_expansion';
        throw error;
      }
    },
  });
  controller.setData({can_set_permissions: true, folders: [{id: 14, can_set_permissions: true, name: 'Test', inherit_permissions: false, visibility: 'members'}], roles: [], permissions: []});
  handlers.get('click')({target: {closest: () => ({dataset: {folderPermissions: '14'}})}});
  await handlers.get('form:submit')({preventDefault: () => {}});
  assert.equal(prompts, 1);
  assert.equal(calls.length, confirmed ? 2 : 1);
  assert.equal(calls[0].body.visibility, 'members');
  assert.equal(calls[0].body.confirm_visibility_change, undefined);
  if (confirmed) assert.equal(calls[1].body.confirm_visibility_change, true);
  assert.equal(reloads, confirmed ? 1 : 0);
  assert.equal(closes, confirmed ? 1 : 0);
  assert.equal(submit.disabled, false);
}

for (const page of ['ui/documents.html', 'ui/document-edit.html', 'ui/comunicati-editor.html']) {
  const html = fs.readFileSync(page, 'utf8');
  for (const [value, label] of [['creator', 'Creatore'], ['rsu', 'Delegati'], ['members', 'Membri'], ['public', 'Ospiti']]) {
    assert.ok(html.includes(`value="${value}">${label}`), `${page}: ${label}`);
  }
}
const documentsSource = fs.readFileSync('ui/documents.js', 'utf8');
const rowBody = documentsSource.match(/function row\(document\) \{([\s\S]*?)\n\}\n\nfunction isDraftComunicato/)[1];
const rowContext = vm.createContext({
  folderActions: {canMove: () => false, moveButton: () => ''},
  isDraftComunicato: () => false, documentsSystemType: () => 'PDF',
  escapeHtml: value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'})[character]), documentVisibilityLabel: () => 'Delegati',
  translateVisibility: value => value, translateStatus: value => value,
  MyRsuIcons: {get: () => ''},
});
vm.runInContext('function row(document) {' + rowBody + '\n}', rowContext);
assert.doesNotMatch(rowContext.row({id: 100, category: 'documenti', can_set_visibility: false}), /document-edit\.html/);
assert.doesNotMatch(rowContext.row({id: 100, category: 'documenti'}), /document-edit\.html/);
assert.match(rowContext.row({id: 100, category: 'documenti', can_set_visibility: true}), /document-edit\.html/);
assert.match(rowContext.row({id: 100, category: 'comunicati', can_set_visibility: false}), /document-edit\.html/);
assert.match(rowContext.row({id: 100, category: 'documenti', author_name: 'Autore <test>'}), /data-label="Autore">Autore &lt;test&gt;<\/td>/);
assert.match(rowContext.row({id: 100, category: 'documenti', author_name: null}), /data-label="Autore">-<\/td>/);
rowContext.isDraftComunicato = () => true;
assert.match(rowContext.row({id: 100, category: 'comunicati', author_name: 'Autore bozza'}), /data-label="Autore">Autore bozza<\/td>/);
rowContext.isDraftComunicato = () => false;
assert.ok(fs.readFileSync('ui/documents.html', 'utf8').includes('<th>Autore</th>'));

const editSource = fs.readFileSync('ui/document-edit.js', 'utf8');
const loadedBody = editSource.match(/api\(`\/documents\/\$\{id\}`\)\.then\(\(document\) => \{([\s\S]*?)\n\}\);/)[1];
const submitBody = editSource.match(/editForm.addEventListener\('submit', async \(event\) => \{([\s\S]*?)\n\}\);/)[1];
for (const [allowed, category] of [[false, 'documenti'], [true, 'documenti'], [false, 'comunicati']]) {
  const field = () => ({value: '', classList: {remove() {}}});
  const titleField = field();
  const bodyField = field();
  const editForm = {original_name: field(), category: field(), visibility: field(), title: titleField, body: bodyField};
  const saveButton = {};
  const calls = [];
  const context = vm.createContext({
    editForm, titleField, bodyField, saveButton, id: 100,
    previewButton: field(), message: {}, window: {document: {querySelector: () => ({})}},
    FormData: class {constructor(form) {this.form = form;} get(name) {return this.form[name]?.value ?? null;}},
    api: async (path, options) => {calls.push({path, body: JSON.parse(options.body)});},
  });
  vm.runInContext('let canSetVisibility = false; let isComunicato = false; let isDraft = false; function loaded(document) {' + loadedBody + '\n} async function submit(event) {' + submitBody + '\n}', context);
  context.loaded({original_name: 'Test.pdf', category, visibility: 'rsu', can_set_visibility: allowed, conversion_status: 'ready', comunicato: {title: 'Oggetto', body: 'Testo'}});
  assert.equal(editForm.visibility.disabled, !allowed);
  assert.equal(saveButton.hidden, !allowed && category !== 'comunicati');
  await context.submit({preventDefault() {}});
  assert.equal(calls.length, allowed || category === 'comunicati' ? 1 : 0);
  if (calls.length) assert.equal('visibility' in calls[0].body, allowed);
  if (category === 'comunicati') assert.equal(calls[0].body.body, 'Testo');
}

const draftSource = fs.readFileSync('ui/comunicati-editor.js', 'utf8');
const draftBody = draftSource.match(/async function loadDraft\(\) \{([\s\S]*?)\n\}\n\nform.addEventListener/)[1];
for (const allowed of [false, true]) {
  const form = {title: {}, body: {}, visibility: {}};
  const context = vm.createContext({form, draftId: 100, draftActions: {classList: {remove() {}}}, message: {}, api: async () => ({category: 'comunicati', conversion_status: 'pending', can_set_visibility: allowed, visibility: 'rsu'})});
  vm.runInContext('async function loadDraft() {' + draftBody + '\n}', context);
  await context.loadDraft();
  assert.equal(form.visibility.disabled, !allowed);
}
console.log('OK: quattro visibilità, conferme, annullamenti e modifica della visibilità riservata ad admin e creatore, anche accedendo direttamente alle schede e alle bozze.');
