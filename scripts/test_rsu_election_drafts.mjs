import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = fs.readFileSync('ui/rsu-elections.js', 'utf8').replace(/\ninitializeElection\(\);\s*$/, '');
const fixture = {
  electors: '154', voters: '130', valid_votes: '130', blank_votes: '0', null_votes: '0', seats: '3',
  lists: ['CGIL', 'CISL'].map((name, listIndex) => ({
    name, votes: listIndex ? '22' : '108', presentation_order: String(listIndex + 1),
    candidates: Array.from({length: 5}, (_, index) => ({name: `Candidato à <${index + 1}>`, preferences: String(index)})),
  })),
};

function browser(saved = null, token = 'test-token') {
  const fields = Object.fromEntries(['electors', 'voters', 'valid_votes', 'blank_votes', 'null_votes', 'seats'].map(name => [name, {value: ['blank_votes', 'null_votes'].includes(name) ? '0' : '', disabled: false}]));
  const handlers = {};
  const timers = new Map();
  const requests = [];
  const button = {disabled: false};
  const listsBox = {
    rows: [],
    set innerHTML(value) {this.rows = [];},
    insertAdjacentHTML(position, html) {
      const name = html.match(/Lista (CGIL|CISL)/)[1];
      const inputs = {name: {value: name}, votes: {value: ''}, presentation_order: {value: name === 'CGIL' ? '1' : '2'}};
      const row = {
        inputs, candidates: [],
        querySelector(selector) {
          if (selector === '[data-candidates]') return {insertAdjacentHTML() {
            const candidateInputs = {name: {value: ''}, preferences: {value: '0'}};
            row.candidates.push({inputs: candidateInputs, querySelector(selector) {return candidateInputs[selector.match(/data-field="([^"]+)"/)[1]];}});
          }};
          return inputs[selector.match(/data-field="([^"]+)"/)[1]];
        },
        querySelectorAll() {return this.candidates;},
      };
      this.rows.push(row);
      this.lastElementChild = row;
    },
    querySelectorAll() {return this.rows;},
  };
  const form = {
    elements: {namedItem(name) {return fields[name];}},
    addEventListener(name, handler) {handlers[name] = handler;},
    querySelectorAll() {return [...Object.values(fields), button, ...listsBox.rows.flatMap(row => [...Object.values(row.inputs), ...row.candidates.flatMap(candidate => Object.values(candidate.inputs))])];},
    checkValidity() {return Object.values(fields).every(field => field.value !== '');},
  };
  const nodes = {'#electionForm': form, '#lists': listsBox, '#result': {}, '#message': {}, '#jsonOutput': {}, '#draftStatus': {}};
  const state = {saved: structuredClone(saved), failLoad: false, failSave: false, deferSave: null};
  const context = vm.createContext({
    document: {querySelector(selector) {return nodes[selector];}},
    window: {location: {}, addEventListener(name, handler) {handlers[name] = handler;}},
    sessionStorage: {getItem() {return token;}}, localStorage: {getItem() {return null;}},
    setTimeout(handler) {const id = timers.size + 1; timers.set(id, handler); return id;}, clearTimeout(id) {timers.delete(id);},
    FormData: class {entries() {return Object.entries(fields).filter(([, field]) => !field.disabled).map(([name, field]) => [name, field.value]);}},
    fetch: async (path, options = {}) => {
      requests.push({path, options});
      assert.equal(options.headers.Authorization, `Bearer ${token}`);
      const failed = path.endsWith('/draft') && (options.method === 'POST' ? state.failSave : state.failLoad);
      let data;
      if (path.endsWith('/draft')) {
        if (options.method === 'POST' && !failed) {
          if (state.deferSave) {const wait = state.deferSave; state.deferSave = null; await wait;}
          state.saved = {form: JSON.parse(options.body), updated_at: '2026-11-05 12:00:00'};
        }
        data = state.saved;
      } else {
        data = {checks: {turnout_ok: true, ballots_ok: true, list_votes_ok: true}, summary: {quorum: 130 / 3}, lists: []};
      }
      return {ok: !failed, json: async () => failed ? {error: {message: 'Errore simulato'}} : {data}};
    },
  });
  vm.runInContext(source, context);
  return {context, fields, handlers, timers, requests, nodes, state, listsBox, form};
}

const first = browser();
await first.context.initializeElection();
assert.equal(first.fields.electors.disabled, false);
assert.equal(first.requests.length, 1);
assert.equal(first.state.saved, null);
first.context.restoreDraft(fixture);
first.handlers.input({type: 'input'});
assert.equal(first.state.saved, null);
assert.equal(first.timers.size, 1);
await [...first.timers.values()][0]();
assert.deepEqual(first.state.saved.form, fixture);
assert.match(first.nodes['#draftStatus'].textContent, /Dati salvati/);
assert.equal(first.requests.at(-1).options.keepalive, true);

const reload = browser(first.state.saved);
await reload.context.initializeElection();
assert.deepEqual(JSON.parse(JSON.stringify(reload.context.payload())), fixture);
assert.equal(reload.requests.filter(request => request.path.endsWith('/analyze')).length, 1);
assert.equal(reload.requests.filter(request => request.options.method === 'POST' && request.path.endsWith('/draft')).length, 0);
assert.equal(reload.listsBox.rows[0].candidates[4].inputs.name.value, 'Candidato à <5>');
assert.equal(reload.fields.blank_votes.value, '0');
reload.fields.null_votes.value = '';
reload.handlers.change({type: 'change'});
await vm.runInContext('saveQueue', reload.context);
assert.equal(reload.state.saved.form.null_votes, '');

let releaseSave;
reload.state.deferSave = new Promise(resolve => {releaseSave = resolve;});
reload.fields.electors.value = '155';
reload.handlers.change({type: 'change'});
await Promise.resolve();
await Promise.resolve();
reload.fields.electors.value = '156';
reload.handlers.change({type: 'change'});
releaseSave();
await vm.runInContext('saveQueue', reload.context);
assert.equal(reload.state.saved.form.electors, '156');
reload.state.failSave = true;
reload.fields.electors.value = '157';
reload.handlers.change({type: 'change'});
await assert.rejects(vm.runInContext('saveQueue', reload.context));
assert.match(reload.nodes['#draftStatus'].textContent, /Dati non salvati/);
assert.equal(reload.state.saved.form.electors, '156');
reload.state.failSave = false;
reload.handlers.change({type: 'change'});
await vm.runInContext('saveQueue', reload.context);
assert.equal(reload.state.saved.form.electors, '157');
reload.fields.null_votes.value = '0';
await reload.handlers.submit({preventDefault() {}});
assert.equal(reload.requests.at(-2).path.endsWith('/draft'), true);
assert.equal(reload.requests.at(-1).path.endsWith('/analyze'), true);

const failed = browser({form: fixture});
failed.state.failLoad = true;
await failed.context.initializeElection();
assert.equal(failed.fields.electors.disabled, true);
failed.handlers.input({type: 'input'});
assert.equal(failed.timers.size, 0);
assert.equal(failed.requests.length, 1);
assert.deepEqual(failed.state.saved.form, fixture);
const guest = browser(null, null);
await guest.context.initializeElection();
assert.equal(guest.context.window.location.href, 'app/index.html');
assert.equal(guest.requests.length, 0);
console.log('OK: salvataggio automatico, ripristino dopo login, cinque candidati, serializzazione, errori e ospiti verificati. Nessuna richiesta reale.');
