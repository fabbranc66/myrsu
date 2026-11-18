import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = fs.readFileSync('ui/configuration-context.js', 'utf8');
async function client(admin) {
  const storage = new Map([['token', 'temporary-test-token']]);
  const calls = [];
  const banner = [];
  const sessionStorage = { getItem: (key) => storage.get(key) || null, setItem: (key, value) => storage.set(key, value), removeItem: (key) => storage.delete(key) };
  class Xhr {
    constructor() { this.headers = {}; }
    open() {}
    send() {}
    setRequestHeader(name, value) { this.headers[name] = value; }
  }
  const window = {
    location: { pathname: '/myrsu/ui/documents.html', href: 'https://example.test/myrsu/ui/documents.html', origin: 'https://example.test' },
    fetch: async (input, options) => {
      calls.push({ input, options });
      return { ok: true, json: async () => ({ data: { roles: admin ? ['admin'] : ['membro'], configuration: admin ? { id: 7, name: '<img src=x>', is_operational: false } : null } }) };
    },
  };
  const document = {
    createElement: (tagName) => ({ tagName, style: {}, append(...children) { this.children = [...this.children || [], ...children]; }, addEventListener() {} }),
    querySelector: () => ({ before(element) { banner.push(element); } }),
  };
  const context = vm.createContext({ window, document, sessionStorage, localStorage: { getItem: () => null }, URL, Headers, XMLHttpRequest: Xhr });
  vm.runInContext(source, context);
  await window.MyRsuConfiguration.ready;
  return { window, calls, banner, storage, Xhr };
}
const admin = await client(true);
assert.equal(admin.banner.length, 1);
assert.equal(admin.banner[0].children[0].textContent, 'Configurazione non operativa: <img src=x>');
assert.equal(admin.storage.get('myrsu_configuration_id'), '7');
await admin.window.fetch('/myrsu/api/v1/practices', { method: 'POST', headers: { Authorization: 'Bearer test' } });
let headers = admin.calls.at(-1).options.headers;
assert.equal(headers.get('X-Configuration-Id'), '7');
assert.equal(headers.get('Authorization'), 'Bearer test');
await admin.window.fetch('/myrsu/api/v1/admin/configurations/selection', { method: 'POST' });
assert.equal(admin.calls.at(-1).options.headers, undefined);
await admin.window.fetch('/myrsu/api/v1/admin/configurations', { method: 'POST' });
assert.equal(admin.calls.at(-1).options.headers.get('X-Configuration-Id'), '7');
await admin.window.fetch('https://elsewhere.test/api/v1/practices', { method: 'POST' });
assert.equal(admin.calls.at(-1).options.headers, undefined);
const xhr = new admin.Xhr();
xhr.open('POST', '/myrsu/api/v1/documents');
xhr.send({});
assert.equal(xhr.headers['X-Configuration-Id'], '7');
const member = await client(false);
assert.equal(member.banner.length, 0);
assert.equal(member.storage.has('myrsu_configuration_id'), false);
await member.window.fetch('/myrsu/api/v1/practices', { headers: { Authorization: 'Bearer member' } });
assert.equal(member.calls.at(-1).options.headers['X-Configuration-Id'], undefined);
console.log('OK: banner riservato admin, nomi sicuri, protezione contesto fetch/upload e nessun effetto sui visitatori o richieste esterne.');
