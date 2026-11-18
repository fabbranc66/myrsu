import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = fs.readFileSync('ui/rsu-elections.js', 'utf8');
const rows = [];
const listsBox = {
  insertAdjacentHTML(position, html) {
    const row = {html, candidates: [], querySelector() {
      return {insertAdjacentHTML(position, candidate) {row.candidates.push(candidate);}};
    }};
    rows.push(row);
    this.lastElementChild = row;
  },
};
const context = vm.createContext({listsBox});
vm.runInContext(source.slice(source.indexOf('function renderFixedLists()'), source.indexOf('function payload()')), context);
context.renderFixedLists();
assert.equal(rows.length, 2);
for (const [listIndex, name] of ['CGIL', 'CISL'].entries()) {
  const row = rows[listIndex];
  assert.ok(row.html.includes(`Lista ${name}`));
  assert.equal(row.candidates.length, 5);
  for (const [candidateIndex, candidate] of row.candidates.entries()) {
    assert.ok(candidate.includes(`Candidato ${candidateIndex + 1}<input data-field="name">`));
    assert.ok(candidate.includes('data-field="preferences" type="number" min="0" value="0"'));
  }
}
assert.match(fs.readFileSync('ui/rsu-elections.html', 'utf8'), /rsu-elections\.js\?v=20261105-personal-draft-1/);
console.log('OK: cinque campi candidati facoltativi per CGIL e CISL, preferenze e versione cache verificati. Nessuna richiesta reale.');
