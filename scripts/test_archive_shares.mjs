import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const helper = fs.readFileSync('ui/archive-client.js', 'utf8').replace(/^export /gm, '');
const source = (name) => fs.readFileSync(`ui/${name}.js`, 'utf8').replace(/^import .*;\r?\n/gm, '');
class Element {
  constructor(tagName = 'div') { this.tagName = tagName; this.children = []; this.events = {}; this.dataset = {}; this.value = ''; this.hidden = false; }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.children = children; }
  addEventListener(name, action) { this.events[name] = action; }
  setAttribute(name, value) { this[name] = value; }
  set innerHTML(value) { throw new Error(`HTML non sicuro: ${value}`); }
}
const item = { id: 3, source_configuration_id: 7, source_configuration_name: 'Demo', name: '<img src=x>', code: 'PRA-1', type: 'practice', status: 'new', is_current: false, explicitly_shared: false };
function runtime(elements, responder, section = null) {
  const calls = [];
  const document = { createElement: (tag) => new Element(tag), querySelector: (selector) => elements[selector], querySelectorAll: () => section ? [section] : [] };
  const window = { location: { pathname: '/myrsu/ui/archive.html', search: '' }, MyRsuConfiguration: { ready: Promise.resolve() } };
  const context = vm.createContext({ window, document, URLSearchParams, Headers,
    sessionStorage: { getItem: () => 'test-token' }, localStorage: { getItem: () => null },
    MyRsuPracticeOptions: { statuses: [['new', 'nuova']], label: (options, value) => options.find(([key]) => key === value)?.[1] || value },
    fetch: async (url, options) => { calls.push({ url, options }); return { ok: true, json: async () => ({ data: responder(url, options) }) }; },
    FormData: class { constructor(form) { this.form = form; } *[Symbol.iterator]() { for (const [name, field] of Object.entries(this.form.elements)) yield [name, field.value]; } },
  });
  vm.runInContext(helper, context);
  return { context, calls };
}
const basic = runtime({}, () => ({}));
assert.equal(vm.runInContext('itemUrl({id:3,type:"practice",source_configuration_id:7})', basic.context), '/myrsu/ui/archive-item.html?source=7&type=practice&id=3');
assert.equal(vm.runInContext('itemUrl({id:19,item_type:"document",item_id:3,source_configuration_id:8})', basic.context), '/myrsu/ui/archive-item.html?source=8&type=document&id=3');
assert.equal(vm.runInContext('itemUrl({id:19,item_type:"practice",item_id:3,source_configuration_id:8,item_source_configuration_id:9})', basic.context), '/myrsu/ui/archive-item.html?source=9&type=practice&id=3');
assert.equal(vm.runInContext('statusLabel("new")', basic.context), 'nuova');
assert.equal(vm.runInContext('textElement("td", "<script>malicious</script>").textContent', basic.context), '<script>malicious</script>');
const selectors = ['#archiveSearch', '#archiveMessage', '#archiveJson', '#archiveRows', '#archiveTarget', '#archiveWarnings', '#archivePanel', '#archivePage', '#archivePrevious', '#archiveNext'];
const elements = Object.fromEntries(selectors.map((selector) => [selector, new Element()]));
elements['#archiveSearch'].elements = { type: { value: 'practice' }, source: new Element('select'), q: { value: '' } };
elements['#archiveSearch'].elements.source.value = '0';
let shared = false;
const manager = runtime(elements, (url, options) => {
  if (url.endsWith('/admin/archive/shares')) { shared = JSON.parse(options.body).enabled; return { enabled: shared }; }
  return { target: { id: 9, name: 'Operativo' }, configurations: [{ id: 7, name: 'Demo' }], warnings: [], items: [{ ...item, explicitly_shared: shared }, { ...item, is_current: true }], total: 2, limit: 50 };
});
vm.runInContext(source('archive'), manager.context);
await new Promise((resolve) => setTimeout(resolve, 0));
assert.equal(elements['#archiveTarget'].textContent, 'Operativo');
assert.equal(elements['#archiveRows'].children.length, 2);
const row = elements['#archiveRows'].children[0];
assert.equal(row.children[1].textContent, 'PRA-1 · <img src=x>');
assert.equal(row.children[2].textContent, 'Demo');
assert.match(row.children[4].children[0].href, /source=7&type=practice&id=3$/);
const flag = row.children[0].children[0].children[0];
flag.checked = true;
await flag.events.change();
const post = manager.calls.find((call) => call.options.method === 'POST');
assert.deepEqual(JSON.parse(post.options.body), { source_configuration_id: 7, entity_type: 'practice', entity_id: 3, enabled: true });
assert.equal(post.options.headers.get('Authorization'), 'Bearer test-token');
assert.equal(elements['#archiveRows'].children[0].children[0].children[0].children[0].checked, true);
assert.equal(elements['#archiveRows'].children[1].children[0].textContent, 'Già in questa configurazione');
const directElements = Object.fromEntries(selectors.map((selector) => [selector, new Element()]));
directElements['#archiveSearch'].elements = { type: { value: 'practice' }, source: new Element('select'), q: { value: '' } };
directElements['#archiveSearch'].elements.source.value = '0';
let transferred = false;
const direct = runtime(directElements, (url, options) => {
  if (url.endsWith('/transfer')) {
    if (options.method === 'POST') {
      if (JSON.parse(options.body).confirm !== 'PRA-1') throw new Error('Codice di conferma errato');
      transferred = true;
      return { practice_id: 17, code: 'PRA-2' };
    }
    return { practice: { code: 'PRA-1' }, source: { name: 'Demo' }, target: { name: 'Operativo' }, notice: 'Gli originali restano collegati.' };
  }
  return { target: { id: 9, name: 'Operativo' }, configurations: [{ id: 7, name: 'Demo' }], warnings: [], items: [transferred ? { ...item, source_configuration_id: 9, id: 17, is_current: true } : item, { ...item, type: 'document' }, { ...item, is_current: true }], total: 3, limit: 50 };
});
vm.runInContext('var prompt = () => null;', direct.context);
vm.runInContext(source('archive'), direct.context);
await new Promise((resolve) => setTimeout(resolve, 0));
const directButton = directElements['#archiveRows'].children[0].children[4].children[1];
assert.equal(directButton.textContent, 'Sposta qui');
assert.equal(directElements['#archiveRows'].children[1].children[4].children.length, 1);
assert.equal(directElements['#archiveRows'].children[2].children[4].children.length, 1);
await directButton.events.click();
assert.equal(direct.calls.filter((call) => call.options.method === 'POST').length, 0);
assert.equal(directButton.disabled, false);
vm.runInContext('prompt = () => "errato";', direct.context);
await directButton.events.click();
assert.equal(transferred, false);
assert.equal(directElements['#archiveMessage'].textContent, 'Codice di conferma errato');
vm.runInContext('prompt = () => "PRA-1";', direct.context);
await Promise.all([directButton.events.click(), directButton.events.click()]);
assert.equal(transferred, true);
assert.equal(direct.calls.filter((call) => call.options.method === 'POST').length, 2);
assert.equal(direct.calls.filter((call) => call.url.endsWith('/admin/archive/shares')).length, 0);
assert.equal(directElements['#archiveRows'].children[0].children[4].children.length, 1);
assert.ok(directElements['#archiveMessage'].textContent.includes('spostata in Operativo'));
assert.equal(directElements['#archiveMessage'].children[0].href, '/myrsu/ui/practice-view.html?id=17');
assert.equal(vm.runInContext('window.location.href', direct.context), undefined);
console.log('OK: Sposta qui nella gestione, senza flag o apertura scheda, annullamento/errori, doppio clic bloccato e aggiornamento nella stessa pagina.');
for (const admin of [true, false]) {
  const parts = Object.fromEntries(['json', 'message', 'manage', 'list', 'count'].map((name) => [`[data-archive-${name}]`, new Element()]));
  const section = new Element(); section.dataset.archiveShared = 'practice'; section.querySelector = (selector) => parts[selector];
  const sharedView = runtime({}, (url) => url.endsWith('/me') ? { roles: admin ? ['admin'] : ['delegato'] } : { items: [item] }, section);
  vm.runInContext(source('archive-shared'), sharedView.context);
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.equal(parts['[data-archive-manage]'].hidden, !admin);
  assert.equal(parts['[data-archive-count]'].textContent, '1');
  assert.equal(parts['[data-archive-list]'].children[0].children[1].children[0].children[1].textContent, 'Demo');
}
for (const [page, type] of [['practices', 'practice'], ['documents', 'document'], ['protocol', 'protocol']]) {
  const html = fs.readFileSync(`ui/${page}.html`, 'utf8');
  assert.ok(html.includes(`data-archive-shared="${type}"`));
  assert.ok(html.includes('archive-shared.js?'));
  assert.ok(html.includes('configuration-context.js?'));
}
console.log('OK: flag admin, salvataggio immediato, origine e ID distinti, nomi sicuri, consultazione condivisa e collegamenti dei moduli.');

for (const admin of [true, false]) {
  const ids = ['archiveItemMessage', 'archiveItemJson', 'archiveItemPanel', 'archiveItemTitle', 'archiveItemOrigin', 'archiveItemStatus', 'archiveOpenOrigin', 'archiveMovePractice', 'archiveItemSummary', 'archiveItemDocuments', 'archiveItemNotes', 'archiveItemHistory', 'archiveItemProtocols', 'archiveVerify', 'archiveDownload'];
  const nodes = Object.fromEntries(ids.map((id) => [`#${id}`, new Element()]));
  const viewer = runtime(nodes, (url, options) => {
    if (url.endsWith('/me')) return { roles: admin ? ['admin'] : ['delegato'], configuration: { id: 9 } };
    if (url.endsWith('/transfer')) return options.method === 'POST' ? { practice_id: 15 } : { practice: { code: 'PRA-1' }, source: { name: 'Demo' }, target: { name: 'Operativo' }, notice: 'Gli originali restano collegati.' };
    return { item, practice: { summary: 'Sintesi' }, documents: [], notes: [], protocols: [], timeline: [] };
  });
  vm.runInContext('window.location.search = "?source=7&type=practice&id=3"; var renderArchiveHistory = () => {}; var prompt = () => null;', viewer.context);
  vm.runInContext(source('archive-item'), viewer.context);
  await new Promise((resolve) => setTimeout(resolve, 0));
  assert.equal(nodes['#archiveMovePractice'].hidden, !admin);
  if (admin) {
    await nodes['#archiveMovePractice'].events.click();
    assert.equal(viewer.calls.filter((call) => call.options.method === 'POST').length, 0);
    vm.runInContext('prompt = () => "PRA-1";', viewer.context);
    await nodes['#archiveMovePractice'].events.click();
    const request = viewer.calls.find((call) => call.options.method === 'POST');
    assert.ok(request.url.endsWith('/admin/archive/practices/7/3/transfer'));
    assert.deepEqual(JSON.parse(request.options.body), { confirm: 'PRA-1' });
    assert.equal(vm.runInContext('window.location.href', viewer.context), '/myrsu/ui/practice-view.html?id=15');
  }
}
console.log('OK: spostamento solo admin, annullamento senza scritture, conferma codice e apertura nella destinazione.');
