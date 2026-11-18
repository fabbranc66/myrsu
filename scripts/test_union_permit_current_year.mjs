import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('ui/union-permit-requests.js', 'utf8');
function extract(name) {
  const start = source.indexOf(`function ${name}(`);
  const end = source.indexOf('\n}', start);
  assert.ok(start >= 0 && end > start);
  return source.slice(source.slice(start - 6, start) === 'async ' ? start - 6 : start, end + 2);
}

const allocations = [
  {year: 2025, permit_type: 'rsu', annual_hours: 128, used_hours: 20},
  {year: '2026', permit_type: 'rsu', annual_hours: 128, used_hours: 48},
  {year: 2026, permit_type: 'rls', annual_hours: 40, used_hours: 8},
  {year: '2027', permit_type: 'rsu', annual_hours: 128, used_hours: 0},
  {year: 2028, permit_type: 'rsu', annual_hours: 128, used_hours: 0},
];
const requests = [
  {id: 1, request_date: '2025-03-01', request_scope: 'internal', permit_type: 'rsu', hours: 2, status: 'issued', subject: 'Richiesta precedente'},
  {id: 2, request_date: '2026-03-01', request_scope: 'internal', permit_type: 'rls', hours: 3, status: 'issued', subject: 'Richiesta attuale'},
];
const original = JSON.stringify({allocations, requests});
for (const year of [2026, 2027, 2029]) {
  const calls = [];
  const context = vm.createContext({
    currentDelegates: [{id: 7, name: 'Delegato', email: 'test@example.invalid', roles: 'delegato,rls', union_code: 'FIM-CISL'}],
    delegateSelect: {value: '7'}, delegateInfo: {innerHTML: ''}, balances: {innerHTML: ''},
    requestsTable: {innerHTML: ''}, currentRequests: [], form: {union_name: {value: ''}},
    Date: class { getFullYear() { return year; } },
    actions: () => '',
    api: async (path) => { calls.push(path); return path.includes('/allocations?') ? allocations : requests; },
  });
  vm.runInContext(['loadDelegate', 'escapeHtml', 'permitLabel', 'scopeLabel', 'statusLabel'].map(extract).join('\n'), context);
  await context.loadDelegate();
  const expected = allocations.filter((row) => Number(row.year) === year);
  assert.equal((context.balances.innerHTML.match(/class="stat-card"/g) || []).length, expected.length);
  for (const allocation of expected) {
    assert.ok(context.balances.innerHTML.includes(`${allocation.annual_hours - allocation.used_hours}.00 ore residue`));
    assert.ok(context.balances.innerHTML.includes(String(year)));
  }
  for (const otherYear of [2025, 2026, 2027, 2028].filter((value) => value !== year)) {
    assert.ok(!context.balances.innerHTML.includes(String(otherYear)));
  }
  if (!expected.length) assert.ok(context.balances.innerHTML.includes(`Nessun monte ore assegnato per il ${year}`));
  assert.equal(context.currentRequests, requests);
  assert.ok(context.requestsTable.innerHTML.includes('Richiesta precedente'));
  assert.ok(context.requestsTable.innerHTML.includes('Richiesta attuale'));
  assert.deepEqual(calls, ['/union-permits/allocations?user_id=7', '/union-permits/requests?user_id=7']);
}
assert.equal(JSON.stringify({allocations, requests}), original);
assert.match(readFileSync('ui/union-permit-requests.html', 'utf8'), /union-permit-requests\.js\?v=20261114-current-year-1/);
console.log('OK: monte ore solo anno corrente, anni numerici/testuali, cambio anno, assenza assegnazioni e storico invariato. Nessuna richiesta reale.');
