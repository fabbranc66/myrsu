const pdrStorage = sessionStorage.getItem('token') || localStorage.getItem('token') ? localStorage : sessionStorage;
const form = document.querySelector('#pdrForm');
const objectives = document.querySelector('#objectives');
const scales = document.querySelector('#pdrScales');
const result = document.querySelector('#pdrResult');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');
const storageKey = 'myrsu.pdr.calculator';
const objectiveModesStorageKey = 'myrsu.pdr.calculator.objectiveModes';
const objectiveNamesStorageKey = 'myrsu.pdr.calculator.objectiveNames';
const objectiveConfigsStorageKey = 'myrsu.pdr.calculator.objectiveConfigs';
const productivityProfileSeedKey = 'myrsu.pdr.calculator.seed.productivity.20260923';
const attendanceProfileSeedKey = 'myrsu.pdr.calculator.seed.attendance.20260923.v2';
const qualityProfileSeedKey = 'myrsu.pdr.calculator.seed.quality.20260923';
const ebitdaProfileSeedKey = 'myrsu.pdr.calculator.seed.ebitda.20260923.v2';
const objectiveBlocksMigrationKey = 'myrsu.pdr.calculator.migration.dynamicObjectives.20260923';
const equalizationElement = 485;
const gateRows = [
  ['Mantenimento', 6288000, 1],
  ['Miglioramento +5%', 6602400, 1.05],
  ['Miglioramento +10%', 6916800, 1.10],
];

const defaultData = {
  base: 1220,
  objectives: [
    { name: 'EBITDA', weight: 0, mode: 'gate', value: 6288000, baseline: 6288000, unit: '€' },
    { name: 'Obiettivo 1', weight: 25, mode: 'scale', value: 100, baseline: 100, unit: '%' },
    { name: 'Obiettivo 2', weight: 25, mode: 'scale', value: 100, baseline: 100, unit: '%' },
    { name: 'Obiettivo 3', weight: 25, mode: 'scale', value: 100, baseline: 100, unit: '%' },
  ],
  scales: [
    { unit: 'Giornate assenza', rows: [['Nessuna', 0, 0, 130], ['Da 1 a 7', 1, 7, 120], ['Da 8 a 13', 8, 13, 100], ['Da 14 a 18', 14, 18, 80], ['Da 19 a 22', 19, 22, 70], ['Da 23 a 30', 23, 30, 50], ['Oltre 30', 31, '', 30]] },
    { unit: 'Valore KPI', rows: [['Inferiore a 90', 0, 89.99, 80], ['Da 90 a 99,99', 90, 99.99, 90], ['Raggiunto', 100, 100, 100], ['Da 100,01 a 110', 100.01, 110, 110], ['Oltre 110', 110.01, '', 120]] },
    { unit: 'Valore KPI', rows: [['Inferiore a 90', 0, 89.99, 80], ['Da 90 a 99,99', 90, 99.99, 90], ['Raggiunto', 100, 100, 100], ['Da 100,01 a 110', 100.01, 110, 110], ['Oltre 110', 110.01, '', 120]] },
    { unit: 'Valore KPI', rows: [['Inferiore a 90', 0, 89.99, 80], ['Da 90 a 99,99', 90, 99.99, 90], ['Raggiunto', 100, 100, 100], ['Da 100,01 a 110', 100.01, 110, 110], ['Oltre 110', 110.01, '', 120]] },
  ],
};

const savedData = loadSavedData();
migrateProductivityName(savedData);
rememberObjectiveNames(savedData.objectives);
rememberObjectiveConfigs(savedData);
seedEbitdaProfile(savedData);
seedProductivityProfile();
seedAttendanceProfile();
seedQualityProfile();
render(savedData);
Array.from(objectives.querySelectorAll('.pdr-objective')).forEach(applyObjectiveConfig);
persistData();
calculatePdr();

form.addEventListener('submit', (event) => {
  event.preventDefault();
  calculatePdr();
});

function calculatePdr() {
  const data = currentData();
  pdrStorage.setItem(storageKey, JSON.stringify(data));
  const gateIndex = data.objectives.findIndex((row) => row.mode === 'gate');
  const gateObjective = gateIndex >= 0 ? data.objectives[gateIndex] : null;
  const gateCoefficientValue = gateObjective
    ? coefficient(gateObjective, data.scales[gateIndex], data.gate_scales[gateIndex], gateIndex)
    : 1;
  const ebitdaGateClosed = gateCoefficientValue === 0 && isEbitdaObjective(gateObjective?.name);
  const calculated = data.objectives
    .map((row, index) => {
      const rowCoefficient = coefficient(row, data.scales[index], data.gate_scales[index], index);
      return {
        ...row,
        index,
        coefficient: rowCoefficient,
        amount: row.mode === 'gate' || ebitdaGateClosed
          ? 0
          : data.base * (row.weight / 100) * rowCoefficient * gateCoefficientValue,
      };
    })
    .filter((row) => row.name || row.weight > 0 || row.value > 0);
  const gate = calculated.find((row) => row.mode === 'gate');
  const totalWeight = calculated.filter((row) => row.mode !== 'gate').reduce((sum, row) => sum + row.weight, 0);
  const subtotal = calculated.reduce((sum, row) => sum + row.amount, 0);
  const appliesEqualizationElement = gate?.coefficient === 0 && isEbitdaObjective(gate.name);
  const calculatedTotal = subtotal;
  const equalizationIntegration = gate ? Math.max(0, equalizationElement - calculatedTotal) : 0;
  const total = calculatedTotal + equalizationIntegration;
  message.textContent = appliesEqualizationElement
    ? `EBITDA inferiore alla soglia di mantenimento: riconosciuto l'elemento perequativo di ${money(equalizationElement)}.`
    : equalizationIntegration > 0
      ? `Applicato l'elemento perequativo minimo di ${money(equalizationElement)}.`
    : gate?.coefficient === 0
      ? 'Cancello non superato: premio azzerato.'
    : totalWeight === 100 ? 'Calcolo completato.' : `Attenzione: somma pesi ${totalWeight}%.`;
  const integrationHtml = equalizationIntegration > 0
    ? `<div class="pdr-row"><span>Integrazione elemento perequativo</span><strong>${money(equalizationIntegration)}</strong></div>`
    : '';
  result.innerHTML = calculated.map(rowHtml).join('') + integrationHtml + `<div class="pdr-row pdr-total"><span>PDR finale</span><strong>${money(total)}</strong></div>`;
  jsonOutput.textContent = JSON.stringify({ ...data, total_weight: totalWeight, objectives: calculated, calculated_total: calculatedTotal, equalization_integration: equalizationIntegration, total }, null, 2);
}

form.addEventListener('input', (event) => {
  if (event.target.name === 'name') return;
  const objective = objectiveFromTarget(event.target);
  if (objective && ['baseline', 'secondary_baseline'].includes(event.target.name)) applyBaselineScale(objective, false);
  syncObjectiveNameAvailability();
  persistData();
  rememberObjectiveConfig(objective);
  calculatePdr();
});
form.addEventListener('change', (event) => {
  const objective = objectiveFromTarget(event.target);
  if (objective && event.target.name === 'name' && event.target.value === '__new__') {
    createObjectiveName(objective);
  }
  if (objective && event.target.name === 'name' && !applyObjectiveConfig(objective)) applyRememberedObjectiveMode(objective);
  if (objective && event.target.name === 'name') rememberObjectiveName(objective);
  if (objective && (event.target.name === 'name' || event.target.name === 'mode')) rememberObjectiveMode(objective);
  if (objective && ['baseline', 'secondary_baseline'].includes(event.target.name)) applyBaselineScale(objective, true);
  syncObjectiveNameAvailability();
  persistData();
  rememberObjectiveConfig(objective);
  calculatePdr();
});
form.addEventListener('focusout', (event) => {
  const objective = objectiveFromTarget(event.target);
  if (objective && event.target.name === 'name') {
    if (!applyObjectiveConfig(objective)) applyRememberedObjectiveMode(objective);
    rememberObjectiveName(objective);
    rememberObjectiveMode(objective);
  }
  if (objective && ['baseline', 'secondary_baseline'].includes(event.target.name)) applyBaselineScale(objective, true);
  syncObjectiveNameAvailability();
  persistData();
  rememberObjectiveConfig(objective);
  calculatePdr();
});
window.addEventListener('beforeunload', persistData);

function persistData() {
  syncScaleTitles();
  syncScaleModes();
  pdrStorage.setItem(storageKey, JSON.stringify(currentData()));
}

document.querySelector('#resetPdr').addEventListener('click', () => {
  pdrStorage.removeItem(storageKey);
  render(defaultData);
  result.innerHTML = '';
  message.textContent = '';
  jsonOutput.textContent = '';
});

document.querySelector('#addObjective').addEventListener('click', () => {
  const data = currentData();
  data.objectives.push(emptyObjective());
  data.scales.push(defaultObjectiveScale());
  render(data);
  persistData();
  calculatePdr();
});

document.querySelectorAll('[data-demo-preset]').forEach((button) => {
  button.addEventListener('click', () => applyDemoPreset(button.dataset.demoPreset));
});

function applyDemoPreset(preset) {
  const objectiveRows = Array.from(objectives.querySelectorAll('.pdr-objective'));
  const scaleObjectives = objectiveRows.filter((objective) => objective.querySelector('[name="mode"]').value !== 'gate');
  const randomObjective = ['intermediate-min', 'intermediate-max', 'min-plus'].includes(preset) && scaleObjectives.length > 0
    ? scaleObjectives[Math.floor(Math.random() * scaleObjectives.length)]
    : null;
  objectiveRows.forEach((objective, index) => {
    const mode = objective.querySelector('[name="mode"]').value;
    if (mode === 'gate') {
      const rows = readGateRows(scales.querySelector(`.pdr-scale[data-index="${index}"]`));
      const gateValue = preset === 'minimum'
        ? -0.01
        : preset === 'max-plus'
          ? rows[rows.length - 1]?.[1] ?? 0
          : rows.find((row) => Number(row[2]) === 1)?.[1] ?? 0;
      objective.querySelector('[name="value"]').value = formatTableNumber(gateValue);
      return;
    }

    const scale = readScale(scales.querySelector(`.pdr-scale[data-index="${index}"]`));
    const demoPercentage = preset === 'intermediate-min'
      ? objective === randomObjective ? 90 : 80
      : preset === 'intermediate-max'
        ? objective === randomObjective ? 80 : 90
        : preset === 'min-plus' && objective === randomObjective
          ? 80
          : null;
    const row = demoScaleRow(scale.rows, preset, demoPercentage);
    if (!row) return;
    const baseline = Number(objective.querySelector('[name="baseline"]').value || 0);
    const rowValue = demoRowValue(row);
    if (isQualityCompositeObjective(objective.querySelector('[name="name"]').value)) {
      const qualityRatio = (preset === 'target' ? 100 : rowValue) / 100;
      const secondaryBaseline = Number(objective.querySelector('[name="secondary_baseline"]').value || 0);
      objective.querySelector('[name="value"]').value = formatTableNumber(baseline * qualityRatio);
      objective.querySelector('[name="secondary_value"]').value = formatTableNumber(secondaryBaseline * qualityRatio);
      return;
    }
    const targetValue = preset === 'target' && baseline > 0 ? baseline : rowValue;
    objective.querySelector('[name="value"]').value = formatTableNumber(targetValue);
  });
  persistData();
  calculatePdr();
}

function demoScaleRow(rows, preset, demoPercentage = null) {
  if (!rows.length) return null;
  if (preset === 'target') return rows.find((row) => Number(row[3]) === 100) || rows[0];
  if (demoPercentage !== null) {
    return [...rows].sort((left, right) => (
      Math.abs(Number(left[3]) - demoPercentage) - Math.abs(Number(right[3]) - demoPercentage)
    ))[0];
  }
  const percentages = rows.map((row) => Number(row[3]));
  const wanted = preset === 'max' || preset === 'max-plus' ? Math.max(...percentages) : Math.min(...percentages);
  return rows.find((row) => Number(row[3]) === wanted) || rows[0];
}

function demoRowValue(row) {
  const minValue = Number(row[1]);
  const maxValue = row[2] === '' ? null : Number(row[2]);
  if (minValue <= -999999999 && maxValue !== null) return maxValue;
  if (maxValue === null) return minValue;
  return round2((minValue + maxValue) / 2);
}

function render(data) {
  form.base.value = data.base;
  objectives.innerHTML = data.objectives.map((objective, index) => objectiveHtml(index, objective)).join('');
  scales.innerHTML = data.scales.map((scale, index) => scaleHtml(index, scale, data.objectives[index])).join('');
  syncScaleModes();
  syncObjectiveNameAvailability();
}

function currentData() {
  return {
    base: Number(form.base.value || 0),
    objectives: Array.from(objectives.querySelectorAll('.pdr-objective')).map(readObjective),
    scales: Array.from(scales.querySelectorAll('.pdr-scale')).map(readScale),
    gate_scales: Array.from(scales.querySelectorAll('.pdr-scale')).map(readGateRows),
  };
}

function objectiveHtml(index, objective) {
  const isQuality = isQualityCompositeObjective(objective.name);
  return `<fieldset class="pdr-objective" data-index="${index}">
    <label>Obiettivo ${index + 1}<select name="name" data-previous="${escapeHtml(objective.name)}">
      <option value="${escapeHtml(objective.name)}" selected>${escapeHtml(objective.name)}</option>
      <option value="__new__">+ Nuovo obiettivo...</option>
    </select></label>
    <label>Peso %<input name="weight" type="number" min="0" step="0.01" value="${objective.weight}"></label>
    <label>Esito<select name="mode">${modeOptions(objective.mode)}</select></label>
    <label><span class="value-label">${isQuality ? 'Scarti interni' : 'Valore'}</span><input name="value" type="number" step="0.01" value="${objective.value}"></label>
    <label><span class="baseline-label">${isQuality ? 'Baseline interna' : 'Baseline'}</span><input name="baseline" type="number" step="0.01" data-previous-baseline="${objective.baseline ?? objective.value}" value="${objective.baseline ?? objective.value}"></label>
    <label><span class="unit-label">${isQuality ? 'Unità interna' : 'Unità KPI'}</span><input name="unit" type="text" placeholder="%, kg/ora, €, giorni..." value="${escapeHtml(objective.unit || '')}"></label>
    <div class="pdr-secondary-fields"${isQuality ? '' : ' hidden'}>
      <label>PPM esterni<input name="secondary_value" type="number" step="0.01" value="${objective.secondary_value ?? (isQuality ? 880 : 0)}"></label>
      <label>Baseline esterna<input name="secondary_baseline" type="number" step="0.01" data-previous-baseline="${objective.secondary_baseline ?? (isQuality ? 880 : 0)}" value="${objective.secondary_baseline ?? (isQuality ? 880 : 0)}"></label>
      <label>Unità esterna<input name="secondary_unit" type="text" value="${escapeHtml(objective.secondary_unit || (isQuality ? 'PPM' : ''))}"></label>
    </div>
  </fieldset>`;
}

function scaleHtml(index, scale, objective) {
  return `<section class="pdr-scale" data-index="${index}">
    <h2>${escapeHtml(objective?.name || `Obiettivo ${index + 1}`)}</h2>
    <table class="standard-scale"><thead><tr><th>Da / soglia</th><th>A</th><th>% premio</th></tr></thead>
    <tbody>${scale.rows.map((row) => scaleRowHtml(row)).join('')}</tbody></table>
    <div class="gate-scale" hidden>
      <p><strong>Sotto la prima soglia:</strong> cancello non superato.</p>
      <table><thead><tr><th>Scaglione</th><th>EBITDA €</th><th>Moltiplicatore</th></tr></thead>
      <tbody>${gateRowsForObjective(objective?.name).map(gateRowHtml).join('')}</tbody></table>
    </div>
  </section>`;
}

function gateRowHtml([label, threshold, multiplier]) {
  return `<tr>
    <td>${escapeHtml(label)}</td>
    <td><input name="gateThreshold" type="number" step="0.01" value="${formatTableNumber(threshold)}"></td>
    <td><input name="gateMultiplier" type="number" min="0" step="0.01" value="${formatTableNumber(multiplier)}"></td>
  </tr>`;
}

function scaleRowHtml(row) {
  const minValue = Number(row[1]) <= -999999999 ? '' : row[1];
  return `<tr>
    <td><input name="min" type="number" step="0.01" value="${formatTableNumber(minValue)}"></td>
    <td><input name="max" type="number" step="0.01" value="${formatTableNumber(row[2])}"></td>
    <td><input name="percent" type="number" step="0.01" value="${formatTableNumber(row[3])}"></td>
  </tr>`;
}

function formatTableNumber(value) {
  return value === '' || value === null || value === undefined ? '' : Number(value).toFixed(2);
}

function readObjective(row) {
  return {
    name: row.querySelector('[name="name"]').value.trim(),
    weight: round2(Number(row.querySelector('[name="weight"]').value || 0)),
    mode: row.querySelector('[name="mode"]').value,
    value: round2(Number(row.querySelector('[name="value"]').value || 0)),
    baseline: round2(Number(row.querySelector('[name="baseline"]').value || 0)),
    unit: row.querySelector('[name="unit"]').value.trim(),
    secondary_value: round2(Number(row.querySelector('[name="secondary_value"]')?.value || 0)),
    secondary_baseline: round2(Number(row.querySelector('[name="secondary_baseline"]')?.value || 0)),
    secondary_unit: row.querySelector('[name="secondary_unit"]')?.value.trim() || '',
  };
}

function readScale(scale) {
  return {
    unit: '',
    rows: Array.from(scale.querySelectorAll('.standard-scale tbody tr')).map((row) => {
      const minValue = row.querySelector('[name="min"]').value === ''
        ? -999999999
        : round2(Number(row.querySelector('[name="min"]').value));
      const maxValue = row.querySelector('[name="max"]').value === ''
        ? ''
        : round2(Number(row.querySelector('[name="max"]').value));
      return [
        '',
        minValue,
        maxValue,
        round2(Number(row.querySelector('[name="percent"]').value || 0)),
      ];
    }),
  };
}

function readGateRows(scale) {
  return Array.from(scale.querySelectorAll('.gate-scale tbody tr')).map((row, index) => [
    gateRows[index]?.[0] || `Scaglione ${index + 1}`,
    round2(Number(row.querySelector('[name="gateThreshold"]').value || 0)),
    round2(Number(row.querySelector('[name="gateMultiplier"]').value || 0)),
  ]);
}

function coefficient(row, scale, gateScale, index) {
  if (row.mode === 'gate') return gateCoefficient(row.value, gateScale);
  if (isQualityCompositeObjective(row.name)) {
    return scaleCoefficient(qualityIndex(row), scale, index, false);
  }
  if (row.baseline > 0 && round2(row.value) === round2(row.baseline)) return 1;
  return scaleCoefficient(row.value, scale, index);
}

function qualityIndex(row) {
  if (row.baseline <= 0 || row.secondary_baseline <= 0) return 0;
  return round2((((row.value / row.baseline) + (row.secondary_value / row.secondary_baseline)) / 2) * 100);
}

function gateCoefficient(value, rows) {
  const roundedValue = round2(value);
  if (roundedValue < 0) return 0;
  return (rows || gateRows).reduce((coefficientValue, row) => (
    roundedValue >= round2(Number(row[1])) ? round2(Number(row[2])) : coefficientValue
  ), 0);
}

function scaleCoefficient(value, scale, index, zeroMeansBaseline = true) {
  const roundedValue = round2(value);
  if (zeroMeansBaseline && index > 0 && roundedValue <= 0) return 1;
  const rows = scale?.rows || [];
  const match = rows.find((row) => (
    roundedValue >= round2(Number(row[1]))
    && (row[2] === '' || roundedValue <= round2(Number(row[2])))
  ));
  if (!match && rows.length > 0) {
    return roundedValue < round2(Number(rows[0][1]))
      ? Number(rows[0][3]) / 100
      : Number(rows[rows.length - 1][3]) / 100;
  }
  return match ? Number(match[3]) / 100 : 0;
}

function rowHtml(row) {
  const unit = row.unit ? ` ${escapeHtml(row.unit)}` : '';
  const coefficientPercentage = (row.coefficient * 100).toLocaleString('it-IT', { maximumFractionDigits: 2 });
  if (row.mode === 'gate') {
    return `<div class="pdr-row"><span>${escapeHtml(row.name || 'Cancello')} - valore ${row.value}${unit}</span><strong>&times; ${row.coefficient.toLocaleString('it-IT', { minimumFractionDigits: 2 })}</strong></div>`;
  }
  const detail = isQualityCompositeObjective(row.name)
    ? ` - scarti ${row.value}${unit}, esterna ${row.secondary_value} ${escapeHtml(row.secondary_unit || 'PPM')}, indice ${qualityIndex(row).toFixed(2)} - ${coefficientPercentage}%`
    : row.mode === 'scale' ? ` - valore ${row.value}${unit} - ${coefficientPercentage}%` : '';
  return `<div class="pdr-row"><span>${escapeHtml(row.name || 'Obiettivo')}${detail}</span><strong>${money(row.amount)}</strong></div>`;
}

function syncScaleModes() {
  Array.from(objectives.querySelectorAll('.pdr-objective')).forEach((objective, index) => {
    const isGate = objective.querySelector('[name="mode"]').value === 'gate';
    const scale = scales.querySelector(`.pdr-scale[data-index="${index}"]`);
    if (!scale) return;
    scale.querySelector('.standard-scale').hidden = isGate;
    scale.querySelector('.gate-scale').hidden = !isGate;
    const isQuality = isQualityCompositeObjective(objective.querySelector('[name="name"]').value);
    objective.querySelector('.pdr-secondary-fields').hidden = !isQuality;
    objective.querySelector('.value-label').textContent = isQuality ? 'Scarti interni' : 'Valore';
    objective.querySelector('.baseline-label').textContent = isQuality ? 'Baseline interna' : 'Baseline';
    objective.querySelector('.unit-label').textContent = isQuality ? 'Unità interna' : 'Unità KPI';
    const weight = objective.querySelector('[name="weight"]');
    weight.disabled = isGate;
    if (isGate) weight.value = 0;
  });
}

function applyRememberedObjectiveMode(objective) {
  const name = normalizeObjectiveName(objective.querySelector('[name="name"]').value);
  const rememberedMode = loadObjectiveModes()[name];
  if (rememberedMode === 'scale' || rememberedMode === 'gate') {
    objective.querySelector('[name="mode"]').value = rememberedMode;
    syncScaleModes();
  }
}

function rememberObjectiveMode(objective) {
  const name = normalizeObjectiveName(objective.querySelector('[name="name"]').value);
  if (!name) return;
  const modes = loadObjectiveModes();
  modes[name] = objective.querySelector('[name="mode"]').value;
  pdrStorage.setItem(objectiveModesStorageKey, JSON.stringify(modes));
}

function rememberObjectiveConfig(objective) {
  if (!objective) return;
  const name = objective.querySelector('[name="name"]').value;
  if (!name || name === '__new__' || isDefaultObjectiveName(name)) return;
  const index = Number(objective.dataset.index);
  const scale = scales.querySelector(`.pdr-scale[data-index="${index}"]`);
  if (!scale) return;
  const configs = loadObjectiveConfigs();
  configs[normalizeObjectiveName(name)] = {
    name,
    weight: Number(objective.querySelector('[name="weight"]').value || 0),
    mode: objective.querySelector('[name="mode"]').value,
    value: Number(objective.querySelector('[name="value"]').value || 0),
    baseline: Number(objective.querySelector('[name="baseline"]').value || 0),
    unit: objective.querySelector('[name="unit"]').value.trim(),
    secondary_value: Number(objective.querySelector('[name="secondary_value"]')?.value || 0),
    secondary_baseline: Number(objective.querySelector('[name="secondary_baseline"]')?.value || 0),
    secondary_unit: objective.querySelector('[name="secondary_unit"]')?.value.trim() || '',
    scale: readScale(scale),
    gate_rows: readGateRows(scale),
  };
  pdrStorage.setItem(objectiveConfigsStorageKey, JSON.stringify(configs));
}

function rememberObjectiveConfigs(data) {
  const configs = loadObjectiveConfigs();
  (data.objectives || []).forEach((objective, index) => {
    if (!objective.name || isDefaultObjectiveName(objective.name)) return;
    const configKey = normalizeObjectiveName(objective.name);
    if (configs[configKey]) return;
    configs[configKey] = {
      name: objective.name,
      weight: Number(objective.weight || 0),
      mode: objective.mode === 'gate' ? 'gate' : 'scale',
      value: Number(objective.value || 0),
      baseline: Number(objective.baseline ?? objective.value ?? 0),
      unit: objective.unit || '',
      secondary_value: Number(objective.secondary_value || 0),
      secondary_baseline: Number(objective.secondary_baseline || 0),
      secondary_unit: objective.secondary_unit || '',
      scale: data.scales?.[index] || defaultData.scales[index],
      gate_rows: gateRows.map((row) => [...row]),
    };
  });
  pdrStorage.setItem(objectiveConfigsStorageKey, JSON.stringify(configs));
}

function applyObjectiveConfig(objective) {
  if (!objective) return false;
  const name = objective.querySelector('[name="name"]').value;
  const config = loadObjectiveConfigs()[normalizeObjectiveName(name)];
  if (!config) return false;
  objective.querySelector('[name="weight"]').value = config.weight;
  objective.querySelector('[name="mode"]').value = config.mode === 'gate' ? 'gate' : 'scale';
  objective.querySelector('[name="value"]').value = config.value;
  const baselineInput = objective.querySelector('[name="baseline"]');
  baselineInput.value = config.baseline ?? config.value ?? 0;
  baselineInput.dataset.previousBaseline = String(config.baseline ?? config.value ?? 0);
  objective.querySelector('[name="unit"]').value = config.unit || '';
  objective.querySelector('[name="secondary_value"]').value = config.secondary_value ?? 0;
  const secondaryBaselineInput = objective.querySelector('[name="secondary_baseline"]');
  secondaryBaselineInput.value = config.secondary_baseline ?? 0;
  secondaryBaselineInput.dataset.previousBaseline = String(config.secondary_baseline ?? 0);
  objective.querySelector('[name="secondary_unit"]').value = config.secondary_unit || '';
  const index = Number(objective.dataset.index);
  const scale = scales.querySelector(`.pdr-scale[data-index="${index}"]`);
  if (scale && Array.isArray(config.scale?.rows)) {
    scale.querySelector('.standard-scale tbody').innerHTML = config.scale.rows.map((row) => scaleRowHtml(row)).join('');
  }
  if (scale && Array.isArray(config.gate_rows)) {
    scale.querySelector('.gate-scale tbody').innerHTML = config.gate_rows.map(gateRowHtml).join('');
  }
  syncScaleTitles();
  syncScaleModes();
  return true;
}

function loadObjectiveConfigs() {
  try {
    return JSON.parse(pdrStorage.getItem(objectiveConfigsStorageKey) || '{}') || {};
  } catch {
    return {};
  }
}

function migrateProductivityName(data) {
  const legacyName = 'produttivita';
  const correctName = 'Produttività';
  const correctKey = normalizeObjectiveName(correctName);
  (data.objectives || []).forEach((objective) => {
    if (normalizeObjectiveName(objective.name) === legacyName) objective.name = correctName;
  });
  const names = loadObjectiveNames().filter((name) => normalizeObjectiveName(name) !== legacyName);
  pdrStorage.setItem(objectiveNamesStorageKey, JSON.stringify(names));
  const modes = loadObjectiveModes();
  if (modes[legacyName] && !modes[correctKey]) modes[correctKey] = modes[legacyName];
  delete modes[legacyName];
  pdrStorage.setItem(objectiveModesStorageKey, JSON.stringify(modes));
  const configs = loadObjectiveConfigs();
  if (configs[legacyName] && !configs[correctKey]) {
    configs[correctKey] = { ...configs[legacyName], name: correctName };
  }
  delete configs[legacyName];
  pdrStorage.setItem(objectiveConfigsStorageKey, JSON.stringify(configs));
}

function seedEbitdaProfile(data) {
  if (pdrStorage.getItem(ebitdaProfileSeedKey)) return;
  const name = 'EBITDA';
  const configKey = normalizeObjectiveName(name);
  const baseline = 6288000;
  const objective = (data.objectives || []).find((row) => isEbitdaObjective(row.name));
  if (objective) {
    objective.mode = 'gate';
    objective.value = baseline;
    objective.baseline = baseline;
    objective.unit = '€';
  }
  const configs = loadObjectiveConfigs();
  const existingConfig = configs[configKey] || {};
  configs[configKey] = {
    ...existingConfig,
    name,
    weight: 0,
    mode: 'gate',
    value: baseline,
    baseline,
    unit: '€',
    gate_rows: gateRows.map((row) => [...row]),
  };
  pdrStorage.setItem(objectiveConfigsStorageKey, JSON.stringify(configs));
  pdrStorage.setItem(ebitdaProfileSeedKey, '1');
}

function seedProductivityProfile() {
  if (pdrStorage.getItem(productivityProfileSeedKey)) return;
  const name = 'Produttività';
  const configKey = normalizeObjectiveName(name);
  const configs = loadObjectiveConfigs();
  const existingConfig = configs[configKey] || {};
  configs[configKey] = {
    ...existingConfig,
    name,
    weight: existingConfig.weight ?? 25,
    mode: 'scale',
    value: 74.66,
    baseline: 74.66,
    unit: 'kg/ora',
    scale: {
      unit: 'kg/ora',
      rows: [
        ['Netto peggioramento', -999999999, 72.78, 0],
        ['Secondo peggioramento', 72.79, 73.90, 80],
        ['Primo peggioramento', 73.91, 74.65, 90],
        ['Obiettivo raggiunto', 74.66, 76.52, 100],
        ['Miglioramento significativo', 76.53, '', 120],
      ],
    },
    gate_rows: existingConfig.gate_rows || gateRows.map((row) => [...row]),
  };
  pdrStorage.setItem(objectiveConfigsStorageKey, JSON.stringify(configs));
  const names = loadObjectiveNames();
  if (!names.some((savedName) => normalizeObjectiveName(savedName) === configKey)) {
    names.push(name);
    pdrStorage.setItem(objectiveNamesStorageKey, JSON.stringify(names));
  }
  pdrStorage.setItem(productivityProfileSeedKey, '1');
}

function seedAttendanceProfile() {
  if (pdrStorage.getItem(attendanceProfileSeedKey)) return;
  const name = 'Presenze collettive';
  const configKey = normalizeObjectiveName(name);
  const configs = loadObjectiveConfigs();
  const existingConfig = configs[configKey] || {};
  configs[configKey] = {
    ...existingConfig,
    name,
    weight: existingConfig.weight ?? 25,
    mode: 'scale',
    value: 8.90,
    baseline: 8.90,
    unit: '% assenteismo',
    scale: attendanceScale(8.90),
    gate_rows: existingConfig.gate_rows || gateRows.map((row) => [...row]),
  };
  pdrStorage.setItem(objectiveConfigsStorageKey, JSON.stringify(configs));
  const names = loadObjectiveNames();
  if (!names.some((savedName) => normalizeObjectiveName(savedName) === configKey)) {
    names.push(name);
    pdrStorage.setItem(objectiveNamesStorageKey, JSON.stringify(names));
  }
  pdrStorage.setItem(attendanceProfileSeedKey, '1');
}

function seedQualityProfile() {
  const name = 'QUALITÀ IN/OUT';
  const configKey = normalizeObjectiveName(name);
  const legacyKey = normalizeObjectiveName('QUALITA IN/OUT');
  const savedNames = loadObjectiveNames().filter((savedName) => normalizeObjectiveName(savedName) !== legacyKey);
  pdrStorage.setItem(objectiveNamesStorageKey, JSON.stringify(savedNames));
  const savedModes = loadObjectiveModes();
  delete savedModes[legacyKey];
  pdrStorage.setItem(objectiveModesStorageKey, JSON.stringify(savedModes));
  const configs = loadObjectiveConfigs();
  delete configs[legacyKey];
  pdrStorage.setItem(objectiveConfigsStorageKey, JSON.stringify(configs));
  if (pdrStorage.getItem(qualityProfileSeedKey)) return;
  const existingConfig = configs[configKey] || {};
  configs[configKey] = {
    ...existingConfig,
    name,
    weight: existingConfig.weight ?? 25,
    mode: 'scale',
    value: 13,
    baseline: 13,
    unit: '% scarti',
    secondary_value: 880,
    secondary_baseline: 880,
    secondary_unit: 'PPM',
    scale: {
      unit: 'Indice qualità IN/OUT',
      rows: [
        ['Miglioramento significativo', -999999999, 83.62, 120],
        ['Obiettivo raggiunto', 83.63, 100, 100],
        ['Primo peggioramento', 100.01, 116.35, 90],
        ['Secondo peggioramento', 116.36, 132.69, 80],
        ['Netto peggioramento', 132.70, '', 0],
      ],
    },
    gate_rows: existingConfig.gate_rows || gateRows.map((row) => [...row]),
  };
  pdrStorage.setItem(objectiveConfigsStorageKey, JSON.stringify(configs));
  const names = loadObjectiveNames();
  if (!names.some((savedName) => normalizeObjectiveName(savedName) === configKey)) {
    names.push(name);
    pdrStorage.setItem(objectiveNamesStorageKey, JSON.stringify(names));
  }
  pdrStorage.setItem(qualityProfileSeedKey, '1');
}

function applyBaselineScale(objective, formatBaseline = false) {
  const objectiveName = objective.querySelector('[name="name"]').value;
  if (isQualityCompositeObjective(objectiveName)) {
    applyQualityBaselines(objective, formatBaseline);
    return;
  }
  if (isAttendanceObjective(objectiveName)) {
    applyAttendanceBaseline(objective, formatBaseline);
    return;
  }
  applyProductivityBaseline(objective, formatBaseline);
}

function applyQualityBaselines(objective, formatBaseline = false) {
  [
    ['baseline', 'value'],
    ['secondary_baseline', 'secondary_value'],
  ].forEach(([baselineName, valueName]) => {
    const baselineInput = objective.querySelector(`[name="${baselineName}"]`);
    const valueInput = objective.querySelector(`[name="${valueName}"]`);
    const baseline = round2(Number(baselineInput.value || 0));
    const previousBaseline = round2(Number(baselineInput.dataset.previousBaseline || baseline));
    if (round2(Number(valueInput.value || 0)) === previousBaseline) valueInput.value = baseline.toFixed(2);
    if (formatBaseline) baselineInput.value = baseline.toFixed(2);
    baselineInput.dataset.previousBaseline = String(baseline);
  });
}

function isQualityCompositeObjective(name) {
  return normalizeObjectiveName(name) === normalizeObjectiveName('QUALITÀ IN/OUT');
}

function isEbitdaObjective(name) {
  return normalizeObjectiveName(name) === 'ebitda';
}

function applyAttendanceBaseline(objective, formatBaseline = false) {
  const baselineInput = objective.querySelector('[name="baseline"]');
  const valueInput = objective.querySelector('[name="value"]');
  const baseline = round2(Number(baselineInput.value || 0));
  const previousBaseline = round2(Number(baselineInput.dataset.previousBaseline || baseline));
  const index = Number(objective.dataset.index);
  const scale = scales.querySelector(`.pdr-scale[data-index="${index}"]`);
  if (!scale || baseline <= 0) return;
  if (round2(Number(valueInput.value || 0)) === previousBaseline) valueInput.value = baseline.toFixed(2);
  if (formatBaseline) baselineInput.value = baseline.toFixed(2);
  baselineInput.dataset.previousBaseline = String(baseline);
  scale.querySelector('.standard-scale tbody').innerHTML = attendanceScale(baseline).rows.map(scaleRowHtml).join('');
}

function attendanceScale(baseline) {
  const strongImprovementMax = round2(baseline - 1);
  const firstWorseningMax = round2(baseline + 1);
  const secondWorseningMax = round2(baseline + 2);
  return {
    unit: '% assenteismo',
    rows: [
      ['Miglioramento significativo', -999999999, strongImprovementMax, 120],
      ['Obiettivo raggiunto', round2(strongImprovementMax + 0.01), baseline, 100],
      ['Primo peggioramento', round2(baseline + 0.01), firstWorseningMax, 90],
      ['Secondo peggioramento', round2(firstWorseningMax + 0.01), secondWorseningMax, 80],
      ['Netto peggioramento', round2(secondWorseningMax + 0.01), '', 0],
    ],
  };
}

function isAttendanceObjective(name) {
  const normalizedName = String(name || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('it-IT');
  return normalizedName.includes('presenze collettive') || normalizedName.includes('assenteismo');
}

function applyProductivityBaseline(objective, formatBaseline = false) {
  const objectiveName = objective.querySelector('[name="name"]').value;
  const unit = objective.querySelector('[name="unit"]').value;
  if (!isProductivityObjective(objectiveName, unit)) return;
  const baselineInput = objective.querySelector('[name="baseline"]');
  const valueInput = objective.querySelector('[name="value"]');
  const baseline = round2(Number(baselineInput.value || 0));
  const previousBaseline = round2(Number(baselineInput.dataset.previousBaseline || baseline));
  const index = Number(objective.dataset.index);
  const scale = scales.querySelector(`.pdr-scale[data-index="${index}"]`);
  if (!scale || baseline <= 0) return;
  if (round2(Number(valueInput.value || 0)) === previousBaseline) valueInput.value = baseline.toFixed(2);
  if (formatBaseline) baselineInput.value = baseline.toFixed(2);
  baselineInput.dataset.previousBaseline = String(baseline);
  const rows = [
    ['Netto peggioramento', -999999999, round2(round2(baseline * 0.975) - 0.01), 0],
    ['Secondo peggioramento', round2(baseline * 0.975), round2(round2(baseline * 0.99) - 0.01), 80],
    ['Primo peggioramento', round2(baseline * 0.99), round2(baseline - 0.01), 90],
    ['Obiettivo raggiunto', baseline, round2(round2(baseline * 1.025) - 0.01), 100],
    ['Miglioramento significativo', round2(baseline * 1.025), '', 120],
  ];
  scale.querySelector('.standard-scale tbody').innerHTML = rows.map(scaleRowHtml).join('');
}

function isProductivityObjective(name, unit) {
  const normalizedName = String(name || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('it-IT');
  const normalizedUnit = String(unit || '').replace(/\s+/g, '').toLocaleLowerCase('it-IT');
  return normalizedName.includes('produttivit') || normalizedName.includes('performance') || normalizedUnit === 'kg/ora';
}

function round2(value) {
  return Math.round((value + Number.EPSILON) * 100) / 100;
}

function gateRowsForObjective(name) {
  const config = loadObjectiveConfigs()[normalizeObjectiveName(name)];
  return Array.isArray(config?.gate_rows) ? config.gate_rows : gateRows;
}

function objectiveFromTarget(target) {
  const directObjective = target.closest('.pdr-objective');
  if (directObjective) return directObjective;
  const scale = target.closest('.pdr-scale');
  return scale ? objectives.querySelector(`.pdr-objective[data-index="${scale.dataset.index}"]`) : null;
}

function rememberObjectiveName(objective) {
  const input = objective.querySelector('[name="name"]');
  const name = input.value.trim();
  if (!name || isDefaultObjectiveName(name)) return;
  const names = loadObjectiveNames();
  if (!names.some((savedName) => normalizeObjectiveName(savedName) === normalizeObjectiveName(name))) {
    names.push(name);
    pdrStorage.setItem(objectiveNamesStorageKey, JSON.stringify(names));
    renderObjectiveNameOptions();
  }
}

function rememberObjectiveNames(objectiveRows) {
  const names = loadObjectiveNames()
    .concat((objectiveRows || []).map((objective) => objective.name))
    .concat(Object.keys(loadObjectiveModes()))
    .filter((name) => name && !isDefaultObjectiveName(name))
    .filter((name, index, allNames) => allNames.findIndex((item) => normalizeObjectiveName(item) === normalizeObjectiveName(name)) === index);
  pdrStorage.setItem(objectiveNamesStorageKey, JSON.stringify(names));
}

function loadObjectiveNames() {
  try {
    const names = JSON.parse(pdrStorage.getItem(objectiveNamesStorageKey) || '[]');
    return Array.isArray(names) ? names : [];
  } catch {
    return [];
  }
}

function renderObjectiveNameOptions() {
  const inputs = Array.from(objectives.querySelectorAll('select[name="name"]'));
  const availableObjectiveNames = loadObjectiveNames()
    .filter((name, index, allNames) => allNames.findIndex((item) => normalizeObjectiveName(item) === normalizeObjectiveName(name)) === index);
  inputs.forEach((input, index) => {
    const usedByOtherRows = inputs
      .filter((otherInput) => otherInput !== input)
      .map((otherInput) => normalizeObjectiveName(otherInput.value))
      .filter(Boolean);
    const currentName = input.value === '__new__' ? input.dataset.previous || '' : input.value.trim();
    const availableNames = availableObjectiveNames
      .concat(currentName && !isDefaultObjectiveName(currentName) ? [currentName] : [])
      .filter((name) => name && !usedByOtherRows.includes(normalizeObjectiveName(name)))
      .filter((name, nameIndex, allNames) => allNames.findIndex((item) => normalizeObjectiveName(item) === normalizeObjectiveName(name)) === nameIndex);
    input.innerHTML = '<option value="">Seleziona obiettivo</option>'
      + availableNames.map((name) => `<option value="${escapeHtml(name)}">${escapeHtml(name)}</option>`).join('')
      + '<option value="__new__">+ Nuovo obiettivo...</option>';
    input.value = availableNames.some((name) => normalizeObjectiveName(name) === normalizeObjectiveName(currentName)) ? currentName : '';
    input.dataset.previous = input.value;
  });
}

function createObjectiveName(objective) {
  const select = objective.querySelector('select[name="name"]');
  const previousName = select.dataset.previous || '';
  const name = window.prompt('Nome del nuovo obiettivo:', '');
  if (!name || !name.trim()) {
    select.value = previousName;
    return;
  }
  const cleanName = name.trim();
  const duplicate = Array.from(objectives.querySelectorAll('select[name="name"]'))
    .some((otherSelect) => otherSelect !== select && normalizeObjectiveName(otherSelect.value) === normalizeObjectiveName(cleanName));
  if (duplicate) {
    window.alert('Questo obiettivo è già utilizzato.');
    select.value = previousName;
    return;
  }
  const names = loadObjectiveNames();
  if (!names.some((savedName) => normalizeObjectiveName(savedName) === normalizeObjectiveName(cleanName))) {
    names.push(cleanName);
    pdrStorage.setItem(objectiveNamesStorageKey, JSON.stringify(names));
  }
  select.innerHTML = `<option value="${escapeHtml(cleanName)}">${escapeHtml(cleanName)}</option>`;
  select.value = cleanName;
  select.dataset.previous = cleanName;
  renderObjectiveNameOptions();
}

function syncObjectiveNameAvailability() {
  const inputs = Array.from(objectives.querySelectorAll('select[name="name"]'));
  const counts = inputs.reduce((resultCounts, input) => {
    const name = input.value === '__new__' ? '' : normalizeObjectiveName(input.value);
    if (name) resultCounts[name] = (resultCounts[name] || 0) + 1;
    return resultCounts;
  }, {});
  inputs.forEach((input) => {
    const name = input.value === '__new__' ? '' : normalizeObjectiveName(input.value);
    input.setCustomValidity(name && counts[name] > 1 ? 'Questo obiettivo è già utilizzato.' : '');
  });
  renderObjectiveNameOptions();
}

function loadObjectiveModes() {
  try {
    return JSON.parse(pdrStorage.getItem(objectiveModesStorageKey) || '{}') || {};
  } catch {
    return {};
  }
}

function normalizeObjectiveName(value) {
  return String(value || '').trim().toLocaleLowerCase('it-IT');
}

function isDefaultObjectiveName(value) {
  const normalizedName = normalizeObjectiveName(value);
  return /^obiettivo\s+\d+$/.test(normalizedName);
}

function syncScaleTitles() {
  Array.from(objectives.querySelectorAll('[name="name"]')).forEach((input, index) => {
    const title = scales.querySelector(`.pdr-scale[data-index="${index}"] h2`);
    if (title) title.textContent = input.value.trim() || `Obiettivo ${index + 1}`;
  });
}

function modeOptions(selected) {
  return [['scale', 'tabella scaglioni'], ['gate', 'cancello']]
    .map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${label}</option>`).join('');
}

function loadSavedData() {
  try {
    const saved = JSON.parse(pdrStorage.getItem(storageKey) || 'null');
    if (!saved || !Array.isArray(saved.objectives)) {
      pdrStorage.setItem(objectiveBlocksMigrationKey, '1');
      return defaultData;
    }
    const savedObjectives = [...saved.objectives];
    const savedScales = [...(saved.scales || [])];
    if (!pdrStorage.getItem(objectiveBlocksMigrationKey) && savedObjectives.length >= 5) {
      savedObjectives.splice(4, 1);
      savedScales.splice(4, 1);
    }
    pdrStorage.setItem(objectiveBlocksMigrationKey, '1');
    const normalizedObjectives = savedObjectives.map((objective, index) => ({
      ...(defaultData.objectives[index] || emptyObjective()),
      ...objective,
    }));
    const normalizedScales = normalizedObjectives.map((objective, index) => (
      savedScales[index] || defaultData.scales[index] || defaultObjectiveScale()
    ));
    return { base: saved.base || defaultData.base, objectives: normalizedObjectives, scales: normalizedScales };
  } catch {
    return defaultData;
  }
}

function emptyObjective() {
  return { name: '', weight: 0, mode: 'scale', value: 0, baseline: 0, unit: '' };
}

function defaultObjectiveScale() {
  return {
    unit: 'Valore KPI',
    rows: [
      ['Inferiore a 90', -999999999, 89.99, 80],
      ['Da 90 a 99,99', 90, 99.99, 90],
      ['Raggiunto', 100, 100, 100],
      ['Da 100,01 a 110', 100.01, 110, 110],
      ['Oltre 110', 110.01, '', 120],
    ],
  };
}

function money(value) {
  return Number(value || 0).toLocaleString('it-IT', { style: 'currency', currency: 'EUR' });
}

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}
