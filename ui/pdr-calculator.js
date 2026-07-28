const token = sessionStorage.getItem('token') || localStorage.getItem('token');
const form = document.querySelector('#pdrForm');
const objectives = document.querySelector('#objectives');
const scales = document.querySelector('#pdrScales');
const result = document.querySelector('#pdrResult');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const storageKey = 'myrsu.pdr.calculator';

if (!token) window.location.href = 'login.html';

const defaultData = {
  base: 1220,
  objectives: [
    { name: 'Presenza', weight: 40, mode: 'scale', value: 0 },
    { name: 'Obiettivo 1', weight: 30, mode: 'scale', value: 100 },
    { name: 'Obiettivo 2', weight: 30, mode: 'scale', value: 100 },
    { name: 'Obiettivo 3', weight: '', mode: 'scale', value: 100 },
    { name: 'Obiettivo 4', weight: '', mode: 'scale', value: 100 },
  ],
  scales: [
    { unit: 'Giornate assenza', rows: [['Nessuna', 0, 0, 130], ['Da 1 a 7', 1, 7, 120], ['Da 8 a 13', 8, 13, 100], ['Da 14 a 18', 14, 18, 80], ['Da 19 a 22', 19, 22, 70], ['Da 23 a 30', 23, 30, 50], ['Oltre 30', 31, '', 30]] },
    { unit: 'Valore KPI', rows: [['Inferiore a 90', 0, 89.99, 80], ['Da 90 a 99,99', 90, 99.99, 90], ['Raggiunto', 100, 100, 100], ['Da 100,01 a 110', 100.01, 110, 110], ['Oltre 110', 110.01, '', 120]] },
    { unit: 'Valore KPI', rows: [['Inferiore a 90', 0, 89.99, 80], ['Da 90 a 99,99', 90, 99.99, 90], ['Raggiunto', 100, 100, 100], ['Da 100,01 a 110', 100.01, 110, 110], ['Oltre 110', 110.01, '', 120]] },
    { unit: 'Valore KPI', rows: [['Inferiore a 90', 0, 89.99, 80], ['Da 90 a 99,99', 90, 99.99, 90], ['Raggiunto', 100, 100, 100], ['Da 100,01 a 110', 100.01, 110, 110], ['Oltre 110', 110.01, '', 120]] },
    { unit: 'Valore KPI', rows: [['Inferiore a 90', 0, 89.99, 80], ['Da 90 a 99,99', 90, 99.99, 90], ['Raggiunto', 100, 100, 100], ['Da 100,01 a 110', 100.01, 110, 110], ['Oltre 110', 110.01, '', 120]] },
  ],
};

const savedData = loadSavedData();
render(savedData);

form.addEventListener('submit', (event) => {
  event.preventDefault();
  const data = currentData();
  localStorage.setItem(storageKey, JSON.stringify(data));
  const calculated = data.objectives
    .map((row, index) => ({ ...row, index, coefficient: coefficient(row, data.scales[index], index), amount: data.base * (row.weight / 100) * coefficient(row, data.scales[index], index) }))
    .filter((row) => row.name || row.weight > 0 || row.value > 0);
  const totalWeight = calculated.reduce((sum, row) => sum + row.weight, 0);
  const total = calculated.reduce((sum, row) => sum + row.amount, 0);
  message.textContent = totalWeight === 100 ? 'Calcolo completato.' : `Attenzione: somma pesi ${totalWeight}%.`;
  result.innerHTML = calculated.map(rowHtml).join('') + `<div class="pdr-row pdr-total"><span>PDR finale</span><strong>${money(total)}</strong></div>`;
  jsonOutput.textContent = JSON.stringify({ ...data, total_weight: totalWeight, objectives: calculated, total }, null, 2);
});

form.addEventListener('input', persistData);
form.addEventListener('change', persistData);
form.addEventListener('focusout', persistData);
window.addEventListener('beforeunload', persistData);

function persistData() {
  syncScaleTitles();
  localStorage.setItem(storageKey, JSON.stringify(currentData()));
}

document.querySelector('#resetPdr').addEventListener('click', () => {
  localStorage.removeItem(storageKey);
  render(defaultData);
  result.innerHTML = '';
  message.textContent = '';
  jsonOutput.textContent = '';
});

function render(data) {
  form.base.value = data.base;
  objectives.innerHTML = data.objectives.map((objective, index) => objectiveHtml(index, objective)).join('');
  scales.innerHTML = data.scales.map((scale, index) => scaleHtml(index, scale, data.objectives[index]?.name)).join('');
}

function currentData() {
  return {
    base: Number(form.base.value || 0),
    objectives: Array.from(objectives.querySelectorAll('.pdr-objective')).map(readObjective),
    scales: Array.from(scales.querySelectorAll('.pdr-scale')).map(readScale),
  };
}

function objectiveHtml(index, objective) {
  return `<fieldset class="pdr-objective" data-index="${index}">
    <label>Obiettivo ${index + 1}<input name="name" placeholder="Nome obiettivo" value="${escapeHtml(objective.name)}"></label>
    <label>Peso %<input name="weight" type="number" min="0" step="0.01" value="${objective.weight}"></label>
    <label>Esito<select name="mode">${modeOptions(objective.mode)}</select></label>
    <label>Valore<input name="value" type="number" step="0.01" value="${objective.value}"></label>
  </fieldset>`;
}

function scaleHtml(index, scale, title) {
  return `<section class="pdr-scale" data-index="${index}">
    <h2>${escapeHtml(title || `Obiettivo ${index + 1}`)}</h2>
    <table><thead><tr><th>A / soglia</th><th>% premio</th></tr></thead>
    <tbody>${scale.rows.map((row) => scaleRowHtml(row)).join('')}</tbody></table>
  </section>`;
}

function scaleRowHtml(row) {
  return `<tr>
    <td><input name="max" type="number" step="0.01" value="${row[2]}"></td>
    <td><input name="percent" type="number" step="0.01" value="${row[3]}"></td>
  </tr>`;
}

function readObjective(row) {
  return {
    name: row.querySelector('[name="name"]').value.trim(),
    weight: Number(row.querySelector('[name="weight"]').value || 0),
    mode: row.querySelector('[name="mode"]').value,
    value: Number(row.querySelector('[name="value"]').value || 0),
  };
}

function readScale(scale) {
  let minValue = -999999999;
  return {
    unit: '',
    rows: Array.from(scale.querySelectorAll('tbody tr')).map((row) => {
      const maxValue = row.querySelector('[name="max"]').value === '' ? '' : Number(row.querySelector('[name="max"]').value);
      const scaleRow = [
        '',
        minValue,
        maxValue,
        Number(row.querySelector('[name="percent"]').value || 0),
      ];
      minValue = maxValue === '' ? minValue : maxValue + 0.01;
      return scaleRow;
    }),
  };
}

function coefficient(row, scale, index) {
  if (row.mode === 'scale') return scaleCoefficient(row.value, scale, index);
  if (row.mode === 'over') return 1 + (row.value / 100);
  if (row.mode === 'under') return Math.max(0, 1 - ((row.value * 2) / 100));
  return 1;
}

function scaleCoefficient(value, scale, index) {
  if (index > 0 && value <= 0) return 1;
  const rows = scale?.rows || [];
  const match = rows.find((row) => value >= Number(row[1]) && (row[2] === '' || value <= Number(row[2])));
  if (!match && rows.length > 0) {
    return value < Number(rows[0][1])
      ? Number(rows[0][3]) / 100
      : Number(rows[rows.length - 1][3]) / 100;
  }
  return match ? Number(match[3]) / 100 : 0;
}

function rowHtml(row) {
  const detail = row.mode === 'scale' ? ` - valore ${row.value} - ${Math.round(row.coefficient * 100)}%` : '';
  return `<div class="pdr-row"><span>${escapeHtml(row.name || 'Obiettivo')}${detail}</span><strong>${money(row.amount)}</strong></div>`;
}

function syncScaleTitles() {
  Array.from(objectives.querySelectorAll('[name="name"]')).slice(0, 5).forEach((input, index) => {
    const title = scales.querySelector(`.pdr-scale[data-index="${index}"] h2`);
    if (title) title.textContent = input.value.trim() || `Obiettivo ${index + 1}`;
  });
}

function modeOptions(selected) {
  return [['scale', 'tabella scaglioni'], ['reached', 'raggiunto'], ['over', 'superato'], ['under', 'non raggiunto']]
    .map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${label}</option>`).join('');
}

function loadSavedData() {
  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
    if (!saved || !Array.isArray(saved.objectives)) return defaultData;
    return { base: saved.base || defaultData.base, objectives: normalize(saved.objectives, defaultData.objectives), scales: normalize(saved.scales || [], defaultData.scales) };
  } catch {
    return defaultData;
  }
}

function normalize(savedRows, fallbackRows) {
  return fallbackRows.map((fallback, index) => ({ ...fallback, ...(savedRows[index] || {}) }));
}

function money(value) {
  return Number(value || 0).toLocaleString('it-IT', { style: 'currency', currency: 'EUR' });
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}
