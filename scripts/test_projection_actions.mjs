import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const helper = fs.readFileSync('ui/projection-actions.js', 'utf8');
let prompts = 0;
let accepted = true;
const sandbox = vm.createContext({ window: { confirm() { prompts++; return accepted; } } });
vm.runInContext(helper, sandbox);
const publish = sandbox.window.MyRsuProjection.publish;
const session = { document_name: 'Documento di prova' };
const requests = [];
const restricted = Object.assign(new Error('Documento riservato: confermare la proiezione.'), { code: 'projection_confirmation_required', status: 409 });
let privateDocument = false;
async function api(path, options) {
  requests.push({ path, options });
  if (privateDocument && !options.body) throw restricted;
  return session;
}

assert.equal(await publish(api, 9), session);
assert.equal(requests.length, 1);
assert.equal(prompts, 0);
privateDocument = true;
requests.length = 0;
assert.equal(await publish(api, 9), session);
assert.equal(requests.length, 2);
assert.equal(prompts, 1);
assert.equal(requests[1].path, '/documents/9/projection');
assert.deepEqual(JSON.parse(requests[1].options.body), { confirm_restricted_projection: true });
accepted = false;
requests.length = 0;
assert.equal(await publish(api, 9), null);
assert.equal(requests.length, 1);
for (const status of [401, 403, 410, 500]) {
  const failure = Object.assign(new Error('Errore di prova'), { status });
  const before = prompts;
  await assert.rejects(publish(async () => { throw failure; }, 9), failure);
  assert.equal(prompts, before);
}

accepted = true;
const source = fs.readFileSync('ui/documents.js', 'utf8');
const handlerBody = source.match(/documentsTable.addEventListener\('click', async \(event\) => \{([\s\S]*?)\n\}\);/)[1];
const AsyncFunction = Object.getPrototypeOf(async function() {}).constructor;
const handler = new AsyncFunction('event', 'window', 'message', 'api', handlerBody);
const message = {};
const browser = { ...sandbox.window, alert() {} };
await handler({ target: { closest: () => ({ dataset: { projection: '9' } }) } }, browser, message, api);
assert.equal(message.textContent, 'Documento proiettato: Documento di prova');
accepted = false;
await handler({ target: { closest: () => ({ dataset: { projection: '9' } }) } }, browser, message, api);
assert.equal(message.textContent, 'Proiezione annullata.');
const meetingSource = fs.readFileSync('ui/union-meeting-operational.js', 'utf8');
assert.match(meetingSource, /MyRsuProjection\.publish\(api, project\.dataset\.projectDocument\)/);
assert.match(meetingSource, /error\.code = payload\.error\?\.code/);
for (const page of ['ui/documents.html', 'ui/union-meeting-operational.html']) {
  const html = fs.readFileSync(page, 'utf8');
  assert.ok(html.indexOf('projection-actions.js') < html.indexOf(page.replace('ui/', '').replace('.html', '.js')));
}
console.log('OK: pulsante Documenti, conferma/annullamento, errori non aggirati e integrazione nella pagina incontro. Nessuna richiesta reale.');
