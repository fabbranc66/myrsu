import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = fs.readFileSync('ui/profile.js', 'utf8').replace(/\r\n/g, '\n').replace(/\nif \(!token\) \{[\s\S]*$/, '');

function profileSession(succeeds = true) {
  const fields = new Map();
  function field(selector) {
    if (!fields.has(selector)) fields.set(selector, {
      value: '', textContent: '', handlers: {}, resets: 0,
      addEventListener(type, handler) { this.handlers[type] = handler; },
      reset() { this.resets++; },
    });
    return fields.get(selector);
  }
  const passwordForm = field('#passwordForm');
  passwordForm.password = {value: 'password-di-prova-nuova'};
  const profileForm = field('#profileForm');
  profileForm.name = {value: 'Nome test'};
  profileForm.email = {value: 'test@example.invalid'};
  const sessionValues = new Map([['token', 'session-token'], ['draft', 'bozza']]);
  const localValues = new Map([['token', 'local-token'], ['pdr', 'simulazione']]);
  const requests = [];
  const context = vm.createContext({
    document: {querySelector: field},
    window: {location: {href: ''}},
    sessionStorage: {getItem: (key) => sessionValues.get(key), removeItem: (key) => sessionValues.delete(key)},
    localStorage: {getItem: (key) => localValues.get(key), removeItem: (key) => localValues.delete(key)},
    FormData: class {
      constructor(form) { this.form = form; }
      get(name) { return this.form[name].value; }
      entries() { return Object.entries(this.form).filter(([, value]) => value && typeof value === 'object' && 'value' in value).map(([name, value]) => [name, value.value])[Symbol.iterator](); }
    },
    fetch: async (path, options) => {
      requests.push({path, options});
      return {ok: succeeds, text: async () => JSON.stringify(succeeds ? {data: {password_changed: true}} : {error: {message: 'Errore di prova'}})};
    },
  });
  vm.runInContext(source, context);
  return {context, field, passwordForm, profileForm, sessionValues, localValues, requests};
}

const success = profileSession();
await success.passwordForm.handlers.submit({preventDefault() {}});
assert.equal(success.requests[0].path, '../api/v1/profile/password');
assert.equal(success.requests[0].options.headers.Authorization, 'Bearer session-token');
assert.equal(success.passwordForm.resets, 1);
assert.equal(success.sessionValues.has('token'), false);
assert.equal(success.localValues.has('token'), false);
assert.equal(success.sessionValues.get('draft'), 'bozza');
assert.equal(success.localValues.get('pdr'), 'simulazione');
assert.equal(success.context.window.location.href, 'login.html');
assert.match(success.field('#message').textContent, /Accedi nuovamente/);
assert.equal(JSON.parse(success.field('#jsonOutput').textContent).data.password_changed, true);

const failure = profileSession(false);
await assert.rejects(failure.passwordForm.handlers.submit({preventDefault() {}}), /Errore di prova/);
assert.equal(failure.passwordForm.resets, 0);
assert.equal(failure.sessionValues.get('token'), 'session-token');
assert.equal(failure.localValues.get('token'), 'local-token');
assert.equal(failure.context.window.location.href, '');

const ordinary = profileSession();
await ordinary.profileForm.handlers.submit({preventDefault() {}});
assert.equal(ordinary.requests[0].path, '../api/v1/profile');
assert.equal(ordinary.sessionValues.get('token'), 'session-token');
assert.equal(ordinary.localValues.get('token'), 'local-token');
assert.equal(ordinary.context.window.location.href, '');

console.log('OK: cambio password richiede nuovo login, pulizia dei soli token, risposta JSON, errore senza logout e salvataggio anagrafico invariato. Nessuna richiesta reale.');
