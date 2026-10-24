import fs from 'node:fs';
import assert from 'node:assert/strict';

const sourceUrl = (source) => `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`;
const modelSource = fs.readFileSync('ui/document-archive-model.js', 'utf8');
const modelUrl = sourceUrl(modelSource);
const { buildDocumentArchive, folderTrail } = await import(modelUrl);
async function module(name) {
  return import(sourceUrl(fs.readFileSync(`ui/${name}.js`, 'utf8').replaceAll('./document-archive-model.js?v=20261024-practice-permissions-1', modelUrl)));
}
function surface(scope) {
  const handlers = new Map();
  return {
    scope, innerHTML: '', value: '', disabled: false, checked: false, open: false,
    classList: { add() {}, remove() {} },
    addEventListener(type, handler) { handlers.set(type, [...handlers.get(type) || [], handler]); },
    async fire(type, event = {}) { for (const handler of handlers.get(type) || []) await handler(event); },
    contains(target) { return target?.scope === scope; },
    querySelector() { return { focus() {} }; },
    focus() {}, showModal() { this.open = true; }, close() { this.open = false; },
  };
}
const element = surface('navigation');
const listing = surface('listing');
const fields = new Map();
const field = (identifier) => {
  if (!fields.has(identifier)) fields.set(identifier, surface(identifier));
  return fields.get(identifier);
};
globalThis.document = { activeElement: null, querySelector: field };
globalThis.window = { MyRsuIcons: { get: () => '<svg><path/></svg>' } };
const form = field('#documentFolderForm');
form.elements = { name: surface('name'), destination: surface('destination') };
const submit = surface('submit');
form.querySelector = () => submit;
const permissionsForm = field('#documentFolderPermissionsForm');
permissionsForm.elements = { inherit_permissions: surface('inherit') };
const permissionsSubmit = surface('permissions-submit');
permissionsForm.querySelector = () => permissionsSubmit;
const roleList = field('#documentFolderRoles');
let roleSelects = [];
Object.defineProperty(roleList, 'innerHTML', {
  set(html) {
    roleSelects = [...html.matchAll(/<select data-role-id="(\d+)" data-admin="([^"]+)">([\s\S]*?)<\/select>/g)].map((match) => ({
      dataset: { roleId: match[1], admin: match[2] }, value: match[3].match(/<option value="([^"]*)" selected>/)?.[1] || '', disabled: false,
    }));
  },
});
roleList.querySelectorAll = () => roleSelects;
const message = {};
const emptyMessage = {};
const listingTitle = {};
let visibleRows;
let folderRows;
let actions;
let permissions;
const documents = [
  { id: 1, category: 'documenti', original_name: 'File libero.pdf', practice_ids: '' },
  { id: 2, category: 'documenti', original_name: 'File pratica.pdf', practice_ids: '7,7' },
  { id: 3, category: 'comunicati', original_name: 'Comunicato.pdf', practice_ids: '7,9' },
];
const practices = [{ id: 7, code: 'P7', title: 'Pratica aperta', status: 'open' }, { id: 9, title: 'Pratica chiusa', status: 'closed' }];
let data = {
  can_manage: true, can_set_permissions: true,
  folders: [
    { id: 10, name: 'Sicurezza <RLS>', parent_id: null, can_manage: true, can_set_permissions: true, inherit_permissions: 1 },
    { id: 100, name: 'Documenti pratiche', parent_id: null, system_key: 'practices', can_manage: true, can_set_permissions: true, inherit_permissions: 1 },
    { id: 101, name: 'Pratica aperta', parent_id: 100, practice_id: 7, system_key: 'practice:7', can_manage: true, can_set_permissions: true, inherit_permissions: 1 },
    { id: 102, name: 'Pratica chiusa', parent_id: 100, practice_id: 9, system_key: 'practice:9', can_manage: true, can_set_permissions: true, inherit_permissions: 1 },
  ],
  placements: [], permissions: [], roles: [{ id: 4, name: 'admin', label: 'Admin' }, { id: 3, name: 'rls', label: 'RLS' }],
};
const originalDocuments = structuredClone(documents);
const { createDocumentArchive } = await module('document-archive');
const archive = createDocumentArchive({
  element, listing, listingTitle, emptyMessage,
  onRows(rows, folders) { visibleRows = rows; folderRows = folders; listing.innerHTML = folders + rows.map((row) => `<tr data-document-id="${row.id}"></tr>`).join(''); },
});
function reload() {
  actions.setData(data, documents);
  permissions.setData(data);
  archive.setData(documents, practices, data);
}
const requests = [];
let nextManualId = 11;
async function api(path, options) {
  const body = JSON.parse(options.body);
  requests.push({ path, method: options.method, body });
  if (path === '/document-folders') {
    data.folders.push({ id: nextManualId++, name: body.name, parent_id: body.parent_id, can_manage: true, can_set_permissions: true, inherit_permissions: 1 });
  } else if (path.endsWith('/permissions')) {
    const identifier = Number(path.split('/')[2]);
    data.folders.find((folder) => folder.id === identifier).inherit_permissions = Number(body.inherit_permissions);
  } else {
    const identifier = Number(path.split('/')[2]);
    data.placements = data.placements.filter((item) => item.document_id !== identifier);
    if (body.folder_id !== null) data.placements.push({ document_id: identifier, folder_id: body.folder_id });
  }
}
const { createDocumentFolderActions } = await module('document-folder-actions');
actions = createDocumentFolderActions({ element, listing, api, reload, message, archive });
const { createDocumentFolderPermissions } = await module('document-folder-permissions');
permissions = createDocumentFolderPermissions({ element, listing, api, reload, message });
reload();
function button(dataset, scope = 'listing') {
  const target = { dataset, scope, hasAttribute: (name) => name === 'data-new-folder' && 'newFolder' in dataset };
  target.closest = (selector) => {
    if (selector === 'button') return target;
    if (selector === 'button[data-archive-view]') return dataset.archiveView ? target : null;
    if (selector === 'button[data-folder-permissions]') return dataset.folderPermissions ? target : null;
    return null;
  };
  return target;
}
async function click(dataset, scope = 'listing') {
  await (scope === 'listing' ? listing : element).fire('click', { target: button(dataset, scope) });
}
assert.deepEqual(visibleRows.map((row) => row.id), [1]);
assert.equal(actions.uploadDestination().folderId, null);
assert.equal((folderRows.match(/class="document-archive-folder-row"/g) || []).length, 2);
assert.match(folderRows, /Sicurezza &lt;RLS&gt;/);
assert.doesNotMatch(folderRows, />Apri<\/button>/);
assert.doesNotMatch(element.innerHTML, /document-archive-folder-row/);
assert.match(folderRows, /data-folder-permissions="100"/);
await click({ folderPermissions: '100' });
assert.equal(field('#documentFolderPermissionsTitle').textContent, 'Permessi · Documenti pratiche');
field('#documentFolderPermissionsDialog').close();
await click({ newFolder: '' }, 'navigation');
assert.equal(field('#documentFolderDialog').open, true);
form.elements.name.value = 'Archivio nuovo';
await form.fire('submit', { preventDefault() {} });
assert.deepEqual(requests.at(-1).body, { name: 'Archivio nuovo', parent_id: null });
await click({ archiveView: 'custom', folderId: '10' });
assert.equal(actions.uploadDestination().folderId, 10);
assert.match(element.innerHTML, /Nuova sottocartella/);
await click({ newFolder: '' }, 'navigation');
form.elements.name.value = 'Verbali';
await form.fire('submit', { preventDefault() {} });
assert.deepEqual(requests.at(-1).body, { name: 'Verbali', parent_id: 10 });
assert.deepEqual(folderTrail(data.folders, 12).map((folder) => folder.id), [10, 12]);
assert.deepEqual(folderTrail([{ id: 1, parent_id: 1 }], 1), []);
archive.openRoot();
const source = { dataset: { documentId: '1' }, innerText: 'File libero.pdf', closest: (selector) => selector === 'tr[data-document-id]' ? source : null };
const transfer = { setData() {} };
const dragEvent = { target: source, dataTransfer: transfer, preventDefault() { this.prevented = true; } };
await listing.fire('dragstart', dragEvent);
assert.equal(transfer.effectAllowed, 'move');
const destination = { scope: 'listing', dataset: { dropFolderId: '10' }, classList: { add() {}, remove() {} }, contains: () => false };
destination.closest = (selector) => selector === '[data-drop-folder-id]' ? destination : null;
const dropEvent = { target: destination, dataTransfer: transfer, preventDefault() { this.prevented = true; } };
await listing.fire('dragover', dropEvent);
assert.equal(dropEvent.prevented, true);
await listing.fire('drop', dropEvent);
assert.deepEqual(requests.at(-1), { path: '/documents/1/folder', method: 'PATCH', body: { folder_id: 10 } });
assert.deepEqual(visibleRows, []);
await click({ archiveView: 'custom', folderId: '10' });
assert.deepEqual(visibleRows.map((row) => row.id), [1]);
await click({ folderMove: '1' });
assert.match(form.elements.destination.innerHTML, /Sicurezza &lt;RLS&gt; \/ Verbali/);
assert.doesNotMatch(form.elements.destination.innerHTML, /value="10[012]"/);
form.elements.destination.value = '12';
await form.fire('submit', { preventDefault() {} });
assert.deepEqual(requests.at(-1).body, { folder_id: 12 });
await click({ archiveView: 'custom', folderId: '12' });
assert.equal(actions.uploadDestination().folderId, 12);
assert.deepEqual(visibleRows.map((row) => row.id), [1]);
await click({ archiveView: 'root' }, 'navigation');
await click({ folderPermissions: '10' });
assert.equal(field('#documentFolderPermissionsDialog').open, true);
assert.equal(roleSelects.every((select) => select.disabled), true);
permissionsForm.elements.inherit_permissions.checked = false;
await permissionsForm.elements.inherit_permissions.fire('change');
assert.equal(roleSelects.find((select) => select.dataset.admin === 'true').disabled, true);
roleSelects.find((select) => select.dataset.roleId === '3').value = 'read';
await permissionsForm.fire('submit', { preventDefault() {} });
assert.equal(requests.at(-1).path, '/document-folders/10/permissions');
assert.deepEqual(requests.at(-1).body.permissions, [{ role_id: 4, access_level: 'manage' }, { role_id: 3, access_level: 'read' }]);
await click({ archiveView: 'practices' });
assert.deepEqual(visibleRows, []);
assert.match(folderRows, /data-folder-permissions="101"/);
assert.match(folderRows, /data-folder-permissions="102"/);
assert.match(element.innerHTML, /data-folder-permissions="100"/);
assert.match(element.innerHTML, /data-new-folder disabled/);
await click({ archiveView: 'practice', practiceId: '7' });
assert.throws(() => actions.uploadDestination(), /Cartella automatica/);
assert.match(element.innerHTML, /data-folder-permissions="101"/);
assert.deepEqual(visibleRows.map((row) => row.id), [2, 3]);
assert.doesNotMatch(folderRows, /document-archive-folder-row/);
const requestCount = requests.length;
await listing.fire('drop', dropEvent);
assert.equal(requests.length, requestCount);
data = { ...data, can_manage: false, can_set_permissions: false, folders: data.folders.map((folder) => ({ ...folder, can_manage: false, can_set_permissions: false })) };
reload();
assert.equal(actions.canMove(1), false);
assert.equal(actions.moveButton(1), '');
await click({ archiveView: 'custom', folderId: '12' });
assert.throws(() => actions.uploadDestination(), /sola lettura/);
assert.doesNotMatch(element.innerHTML, /data-new-folder/);
assert.deepEqual(documents, originalDocuments);
const model = buildDocumentArchive(documents, practices, data);
assert.equal(model.practiceDocuments.length, 2);
assert.equal(model.customFolders.find((folder) => folder.id === 12).documents.length, 1);
assert.equal(model.customFolders.some((folder) => folder.system_key), false);
const hiddenPractices = buildDocumentArchive([documents[0]], practices, { ...data, folders: data.folders.filter((folder) => !folder.system_key) });
assert.equal(hiddenPractices.practiceRoot, null);
assert.equal(hiddenPractices.folders.length, 0);
const sourceMain = fs.readFileSync('ui/documents.js', 'utf8');
const handlerBody = sourceMain.match(/documentsTable.addEventListener\('click', async \(event\) => \{([\s\S]*?)\n\}\);/)[1];
const AsyncFunction = Object.getPrototypeOf(async function() {}).constructor;
const guard = new AsyncFunction('event', 'confirm', handlerBody);
for (const dataset of [{ folderMove: '1' }, { folderPermissions: '10' }, { archiveView: 'custom' }]) {
  await guard({ target: { closest: () => ({ dataset }) } }, () => { throw new Error('Azione cartella passata al gestore eliminazione'); });
}
data = { ...data, can_manage: true, folders: data.folders.map((folder) => ({ ...folder, can_manage: true })) };
reload();
const uploadBody = sourceMain.match(/uploadForm.addEventListener\('submit', async \(event\) => \{([\s\S]*?)\n\}\);/)[1];
assert.doesNotMatch(uploadBody, /openRoot|\/folder/);
const upload = new AsyncFunction('event', 'message', 'uploadProgress', 'setUploadProgress', 'folderActions', 'FormData', 'uploadForm', 'File', 'MyRsuPdfRasterizer', 'uploadDocument', 'window', 'loadDocuments', 'resetUploadProgress', uploadBody);
class UploadFile { constructor(type) { this.type = type; } }
let fileType = 'text/plain';
class UploadData {
  constructor() { this.values = new Map([['file', new UploadFile(fileType)]]); }
  set(key, value) { this.values.set(key, value); }
  get(key) { return this.values.get(key); }
  append(key, value) { this.values.set(key, value); }
}
const uploads = [];
const uploadMessage = {};
const uploadForm = { reset() {} };
const timeoutWindow = { setTimeout(callback) { callback(); } };
async function performUpload() {
  await upload({ preventDefault() {} }, uploadMessage, { classList: { remove() {} } }, () => {}, actions, UploadData, uploadForm, UploadFile,
    { async rasterizePdf() { await click({ archiveView: 'root' }, 'navigation'); return {}; } },
    async (formData) => { uploads.push(formData); }, timeoutWindow, reload, () => {});
}
await performUpload();
assert.equal(uploads.at(-1).get('folder_id'), '12');
assert.equal(archive.getContext().folderId, 12);
assert.match(uploadMessage.textContent, /Verbali/);
fileType = 'application/pdf';
await performUpload();
assert.equal(uploads.at(-1).get('folder_id'), '12');
assert.ok(uploads.at(-1).get('rendered_pdf'));
assert.equal(archive.getContext().view, 'root');
fileType = 'text/plain';
await performUpload();
assert.equal(uploads.at(-1).get('folder_id'), '');
await click({ archiveView: 'practices' });
const uploadCount = uploads.length;
await performUpload();
assert.equal(uploads.length, uploadCount);
assert.match(uploadMessage.textContent, /Cartella automatica/);
console.log('OK: cartelle nella tabella, creazione, sottocartelle, breadcrumb, drag & drop, spostamento accessibile, permessi, readonly e collegamenti preservati.');
console.log('OK: caricamento nella cartella aperta, sottocartelle e radice; destinazione conservata durante la conversione, senza spostamenti successivi.');
