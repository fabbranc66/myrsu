(() => {
  const band = (min, max, amount, minInclusive = true, maxInclusive = true) => ({ min, max, amount, minInclusive, maxInclusive });
  const qualityBands = () => [band(0, 5, 190), band(5.1, 6.5, 165), band(6.6, 8.5, 125), band(8.6, 10, 90), band(10, null, 0, false)];
  const claimBands = () => [band(0, 5, 190, true, false), band(5, 9, 165), band(10, 14, 125), band(15, 20, 90), band(20, null, 0, false)];
  const defaults = {
    year: 2026, profitability: '', months: 6, partTime: 100, events: 0, inForce: true, excluded: false, welfare: 0,
    kpis: [
      { code: 'P1', name: 'Produttività getti', unit: 'kg/h uomo', baseline: 73, value: 73, rows: [band(88, null, 390, false), band(85, 88, 290), band(81, 84, 240), band(72, 80, 190), band(0, 72, 0, true, false)] },
      { code: 'P2', name: 'Produttività rotori', unit: 'stampate/h uomo', baseline: 28, value: 28, rows: [band(40, null, 390, false), band(35, 40, 290), band(30, 34, 240), band(26, 29, 190), band(0, 26, 0, true, false)] },
      { code: 'Qi1', name: 'Qualità interna getti', unit: '%', baseline: 10, value: 10, rows: qualityBands() },
      { code: 'Qi2', name: 'Qualità interna rotori', unit: '%', baseline: 10, value: 10, rows: qualityBands() },
      { code: 'Qe1', name: 'Qualità esterna getti', unit: 'reclami', baseline: 10, value: 10, rows: claimBands() },
      { code: 'Qe2', name: 'Qualità esterna rotori', unit: 'reclami', baseline: 1, value: 1, rows: claimBands() },
      { code: 'TAC', name: 'Assenteismo collettivo', unit: '%', baseline: 6, value: 6, rows: [band(0, 6.5, 60), band(6.5, null, 0, false)] },
    ],
    individualRows: [band(0, 0, 1.3), band(1, 2, 1.15), band(3, 5, 1.05), band(5, null, 1, false)],
  };
  const number = (value) => value === '' || value === null || value === undefined || typeof value === 'boolean' ? null : Number.isFinite(Number(value)) ? Number(value) : null;
  const cents = (value) => Math.round((Number(value) + Number.EPSILON) * 100);
  const round = (value) => cents(value) / 100;

  function match(value, rows) {
    const actual = number(value);
    if (actual === null || !Array.isArray(rows) || !rows.length) return { amount: null, index: -1, error: 'Dati incompleti.' };
    const matched = [];
    for (const [index, row] of rows.entries()) {
      const lower = row.min === null ? null : number(row.min);
      const upper = row.max === null ? null : number(row.max);
      const amount = number(row.amount);
      if ((row.min !== null && lower === null) || (row.max !== null && upper === null) || amount === null || amount < 0 || (lower !== null && upper !== null && lower > upper)) {
        return { amount: null, index: -1, error: 'Tabella non valida.' };
      }
      const above = lower === null || (row.minInclusive ? cents(actual) >= cents(lower) : cents(actual) > cents(lower));
      const below = upper === null || (row.maxInclusive ? cents(actual) <= cents(upper) : cents(actual) < cents(upper));
      if (above && below) matched.push({ amount, index });
    }
    if (matched.length !== 1) return { amount: null, index: -1, error: matched.length ? 'Scaglioni sovrapposti per questo valore.' : 'Valore non coperto dalle soglie pubblicate.' };
    return matched[0];
  }

  function minimumScenario(data, objectiveCount = 1) {
    const options = (rows, integer = false, maximum = null) => rows.map((row, index) => {
      const lower = row.min === null ? 0 : number(row.min);
      if (lower === null) return null;
      let value = Math.max(0, round(lower + (row.minInclusive ? 0 : 0.01)));
      if (integer) value = Math.ceil(value);
      const evaluated = match(value, rows);
      return evaluated.index === index && (maximum === null || value <= maximum)
        ? { value, amount: evaluated.amount } : null;
    }).filter(Boolean).sort((left, right) => left.amount - right.amount);
    const individual = options(data.individualRows, true).find((option) => option.amount > 0);
    const choices = data.kpis.map((kpi) => options(kpi.rows, kpi.code.startsWith('Qe'), ['Qi1', 'Qi2', 'TAC'].includes(kpi.code) ? 100 : null));
    if (!individual || !Number.isInteger(objectiveCount) || objectiveCount < 1 || objectiveCount > choices.length || choices.some((rows) => !rows.length)) return null;
    const selected = choices.map((rows) => rows[0]);
    const mandatoryCount = selected.filter((option) => option.amount > 0).length;
    const positive = choices.map((rows, index) => ({ index, option: rows.find((row) => row.amount > 0) }))
      .filter((candidate) => candidate.option && selected[candidate.index].amount === 0)
      .sort((left, right) => left.option.amount - right.option.amount);
    const additionalCount = objectiveCount - mandatoryCount;
    if (additionalCount < 0 || positive.length < additionalCount) return null;
    positive.slice(0, additionalCount).forEach((candidate) => { selected[candidate.index] = candidate.option; });
    const scenario = JSON.parse(JSON.stringify(data));
    scenario.profitability = 1;
    scenario.events = individual.value;
    scenario.months = Number(scenario.year) === 2026 ? 6 : 12;
    scenario.partTime = 100;
    scenario.inForce = true;
    scenario.excluded = false;
    scenario.kpis.forEach((kpi, index) => { kpi.value = selected[index].value; });
    return calculate(scenario).total > 0 ? scenario : null;
  }

  function calculate(data) {
    const issues = [];
    const year = Number(data.year);
    const periodMonths = year === 2026 ? 6 : 12;
    const periodFactor = year === 2026 ? 0.5 : 1;
    const profitability = number(data.profitability);
    const months = number(data.months);
    const partTime = number(data.partTime);
    const events = number(data.events);
    const welfare = number(data.welfare);
    if (![2026, 2027, 2028].includes(year)) issues.push('Anno non valido.');
    if (profitability === null) issues.push('Inserire il valore di redditività della società.');
    if (months === null || !Number.isInteger(months) || months < 0 || months > periodMonths) issues.push(`Mesi utili: inserire un intero tra 0 e ${periodMonths}.`);
    if (partTime === null || partTime < 0 || partTime > 100) issues.push('Orario contrattuale: inserire una percentuale tra 0 e 100.');
    if (welfare === null || welfare < 0 || welfare > 100) issues.push('Conversione welfare: inserire una percentuale tra 0 e 100.');
    if (events === null || !Number.isInteger(events) || events < 0) issues.push('Eventi di malattia: inserire un intero non negativo.');
    const individual = match(events, data.individualRows);
    if (individual.error) issues.push(`Ai: ${individual.error}`);
    const quotas = data.kpis.map((kpi) => {
      const value = number(kpi.value);
      const validValue = value !== null && value >= 0 && (!['Qi1', 'Qi2', 'TAC'].includes(kpi.code) || value <= 100) && (!kpi.code.startsWith('Qe') || Number.isInteger(value));
      const evaluated = validValue ? match(value, kpi.rows) : { amount: null, index: -1, error: 'Valore mancante o non valido.' };
      if (evaluated.error) issues.push(`${kpi.code}: ${evaluated.error}`);
      return { code: kpi.code, name: kpi.name, value, baseline: number(kpi.baseline), ...evaluated, annualAmount: evaluated.amount, periodAmount: evaluated.amount === null ? null : round(evaluated.amount * periodFactor) };
    });
    const collective = quotas.every((quota) => quota.amount !== null) ? round(quotas.reduce((sum, quota) => sum + quota.amount, 0)) : null;
    const individualAnnual = collective !== null && individual.amount !== null ? round(collective * individual.amount) : null;
    const proRata = months !== null && partTime !== null ? (months / periodMonths) * (partTime / 100) : null;
    const theoretical = individualAnnual !== null && proRata !== null ? round(individualAnnual * periodFactor * proRata) : null;
    const gateOpen = profitability === null ? null : profitability > 0;
    const eligible = data.inForce === true && data.excluded !== true;
    const termsValid = !issues.some((issue) => !/^(P1|P2|Qi1|Qi2|Qe1|Qe2|TAC|Ai):/.test(issue) && !issue.startsWith('Eventi di malattia'));
    const total = !eligible || (gateOpen === false && termsValid) ? 0 : issues.length ? null : theoretical;
    const converted = total === null || welfare === null ? null : round(total * welfare / 100);
    const welfareBonus = converted !== null && welfare === 100 ? round(converted * 0.12) : converted === null ? null : 0;
    const improved = quotas.filter((quota) => quota.value !== null && quota.baseline !== null && (quota.code.startsWith('P') ? quota.value > quota.baseline : quota.value < quota.baseline)).map((quota) => quota.code);
    return {
      year, periodMonths, periodFactor, referenceMaximum: 1600, profitability, gateOpen, eligible,
      quotas, individualCoefficient: individual.amount, individualRow: individual.index, annualCollective: collective,
      periodCollective: collective === null ? null : round(collective * periodFactor), annualIndividual: individualAnnual,
      proRata, theoretical, total, cash: total === null || converted === null ? null : round(total - converted),
      converted, welfareBonus, welfareTotal: converted === null || welfareBonus === null ? null : round(converted + welfareBonus),
      improved, issues,
    };
  }

  window.PdrCanegrate = { defaults: () => JSON.parse(JSON.stringify(defaults)), calculate, minimumScenario, match, number, round };
})();
