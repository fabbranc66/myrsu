(() => {
  const accountLink = document.querySelector('#pdrAccountLink');
  const financialsLink = document.querySelector('#pdrFinancialsLink');
  const authenticated = Boolean(sessionStorage.getItem('token') || localStorage.getItem('token'));
  const pdrStorage = authenticated ? localStorage : sessionStorage;
  document.querySelector('#pdrEligibilityFields').hidden = !authenticated;
  const configuration = document.querySelector('#pdrAgreementConfiguration');
  configuration.hidden = !authenticated;
  configuration.disabled = !authenticated;
  for (const selector of ['.pdr-agreement-notes', '.pdr-agreement-json']) {
    document.querySelector(selector).hidden = !authenticated;
  }
  if (authenticated) {
    accountLink.href = 'app/index.html';
    accountLink.textContent = 'Area riservata MyRSU';
    financialsLink.hidden = false;
  }
  const model = window.PdrCanegrate;
  const form = document.querySelector('#pdrAgreementForm');
  if (!authenticated) document.querySelector('#pdrAgreementReset').textContent = 'Ripristina dati';
  const profilePicker = document.querySelector('#pdrCalculatorProfile');
  const welfareButtons = document.querySelectorAll('[data-guest-welfare]');
  document.querySelector('#pdrWelfareField').hidden = !authenticated;
  document.querySelector('#pdrWelfareGuest').hidden = authenticated;
  form.elements.namedItem('welfare').disabled = !authenticated;
  welfareButtons.forEach((button) => { button.disabled = authenticated; });
  document.querySelector('#pdrAnnualPrizeField').hidden = !authenticated;
  document.querySelector('#pdrAnnualPrizeGuest').hidden = authenticated;
  form.elements.namedItem('annualPrize').disabled = !authenticated;
  document.querySelector('#pdrProfileField').hidden = !authenticated;
  profilePicker.disabled = !authenticated;
  for (const [field, selector] of [['profitability', '#pdrProfitabilityField'], ['months', '#pdrMonthsField'], ['partTime', '#pdrPartTimeField']]) {
    document.querySelector(selector).hidden = !authenticated;
    form.elements.namedItem(field).disabled = !authenticated;
  }
  const profileMessage = document.querySelector('#pdrProfileMessage');
  const kpisContainer = document.querySelector('#pdrAgreementKpis');
  const tablesContainer = document.querySelector('#pdrAgreementTables');
  const resultContainer = document.querySelector('#pdrAgreementResult');
  const message = document.querySelector('#pdrAgreementMessage');
  const storageKey = 'myrsu.pdr.calculator.canegrate.20261008';
  const profileKey = 'myrsu.pdr.calculator.activeProfile';
  const currency = new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' });
  let state = load();
  if (!authenticated) state.welfare = Number(state.welfare) === 100 ? 100 : 0;
  let legacyLoading = false;
  let legacyLoaded = false;

  function escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
  }

  function decimal(value) {
    return value === null || value === '' || value === undefined ? '' : Number(value).toFixed(2);
  }

  function money(value) {
    return value === null ? 'Da determinare' : currency.format(value);
  }

  function load() {
    const initial = model.defaults();
    try {
      const saved = JSON.parse(pdrStorage.getItem(storageKey) || 'null');
      if (!saved || !Array.isArray(saved.kpis) || !Array.isArray(saved.individualRows)) return initial;
      return {
        ...initial, ...saved,
        kpis: initial.kpis.map((kpi) => {
          const stored = saved.kpis.find((item) => item.code === kpi.code);
          return stored && Array.isArray(stored.rows) && stored.rows.length ? { ...kpi, value: stored.value, baseline: stored.baseline, rows: stored.rows } : kpi;
        }),
        individualRows: saved.individualRows.length ? saved.individualRows : initial.individualRows,
      };
    } catch {
      return initial;
    }
  }

  function persist() {
    try {
      pdrStorage.setItem(storageKey, JSON.stringify(state));
      profileMessage.textContent = '';
    } catch {
      profileMessage.textContent = 'Salvataggio locale non disponibile in questo browser.';
    }
  }

  function unit(kpi) {
    return kpi.code === 'P2' && state.year !== 2026 ? 'kg/h uomo' : kpi.unit;
  }

  function renderForm() {
    for (const field of ['year', 'profitability', 'months', 'partTime', 'events', 'welfare']) form.elements.namedItem(field).value = state[field] ?? '';
    for (const field of ['inForce', 'excluded']) form.elements.namedItem(field).checked = state[field];
    kpisContainer.innerHTML = state.kpis.map((kpi) => `<section class="pdr-agreement-card" data-kpi="${kpi.code}">
      <h2>${kpi.code} · ${escape(kpi.name)}</h2>
      <div class="pdr-agreement-card-fields">
        <label>Risultato <span data-unit="${kpi.code}">${escape(unit(kpi))}</span><input data-kpi-field="value" type="number" min="0" ${['Qi1', 'Qi2', 'TAC'].includes(kpi.code) ? 'max="100"' : ''} step="${kpi.code.startsWith('Qe') ? '1' : '0.01'}" value="${decimal(kpi.value)}" required></label>
        <label>Riferimento confronto<input data-kpi-field="baseline" type="number" min="0" step="${kpi.code.startsWith('Qe') ? '1' : '0.01'}" value="${decimal(kpi.baseline)}"></label>
      </div>
      <p class="pdr-agreement-quota"><span data-band="${kpi.code}"></span><strong data-quota="${kpi.code}"></strong></p>
      <p class="pdr-agreement-quota"><span>Peso sul premio base annuo</span><strong data-base-share="${kpi.code}"></strong></p>
      ${kpi.code === 'TAC' ? '<p class="muted"><strong>6% delle ore teoriche annue 2026: 19.306,8 ore.</strong><br>Monte ore: 321.780 ore (155 dipendenti a tempo pieno &times; 173 ore mensili &times; 12 mesi).</p>' : ''}
    </section>`).join('');
    tablesContainer.innerHTML = state.kpis.map((kpi) => tableHtml(kpi.code, `${kpi.code} · ${kpi.name}`, kpi.rows, 'Quota annua €')).join('') + tableHtml('Ai', 'Ai · Assiduità individuale', state.individualRows, 'Coefficiente');
    updatePeriod();
    renderResult();
  }

  function boundHtml(code, index, side, value, inclusive) {
    const field = side === 'min' ? 'minInclusive' : 'maxInclusive';
    const operators = side === 'min' ? [['true', '≥'], ['false', '>']] : [['true', '≤'], ['false', '<']];
    return `<div class="pdr-agreement-bound"><select data-table="${code}" data-row="${index}" data-field="${field}" aria-label="${code} scaglione ${index + 1}: inclusione ${side === 'min' ? 'minimo' : 'massimo'}">${operators.map(([choice, label]) => `<option value="${choice}"${String(inclusive) === choice ? ' selected' : ''}>${label}</option>`).join('')}</select><input data-table="${code}" data-row="${index}" data-field="${side}" type="number" step="${code === 'Ai' || code.startsWith('Qe') ? '1' : '0.01'}" value="${decimal(value)}" placeholder="${side === 'min' ? '−∞' : '+∞'}" aria-label="${code} scaglione ${index + 1}: ${side === 'min' ? 'minimo' : 'massimo'}"></div>`;
  }

  function tableHtml(code, title, rows, amountTitle) {
    return `<section class="pdr-agreement-table"><h2>${escape(title)}</h2><table><thead><tr><th>Da</th><th>A</th><th>${amountTitle}</th></tr></thead><tbody>${rows.map((row, index) => `<tr data-table-band="${code}-${index}">
      <td>${boundHtml(code, index, 'min', row.min, row.minInclusive)}</td><td>${boundHtml(code, index, 'max', row.max, row.maxInclusive)}</td>
      <td><input data-table="${code}" data-row="${index}" data-field="amount" type="number" min="0" step="0.01" value="${decimal(row.amount)}" aria-label="${code} scaglione ${index + 1}: ${amountTitle}"></td>
    </tr>`).join('')}</tbody></table></section>`;
  }

  function updatePeriod() {
    const periodMonths = state.year === 2026 ? 6 : 12;
    form.elements.namedItem('months').max = periodMonths;
    document.querySelector('#pdrMonthsLabel').textContent = state.year === 2026 ? 'Mesi utili luglio–dicembre' : 'Mesi utili gennaio–dicembre';
    document.querySelector('#pdrAgreementPeriod').textContent = `${state.year === 2026 ? '2026: quote annue dimezzate (solo secondo semestre).' : `${state.year}: quote annue intere. P2 richiede la tabella aggiornata prevista dall’art. 2.2.`} Pagamento: maggio ${state.year + 1}.`;
    for (const kpi of state.kpis) document.querySelector(`[data-unit="${kpi.code}"]`).textContent = unit(kpi);
  }

  function summaryRow(label, value, total = false) {
    return `<div class="pdr-row${total ? ' pdr-total' : ''}"><span>${escape(label)}</span><strong>${escape(value)}</strong></div>`;
  }

  function collectiveSummaryRow(calculation, amount) {
    if (authenticated) return summaryRow('Somma quote annue (P1 + P2 + Qi1 + Qi2 + Qe1 + Qe2 + TAC)', money(amount));
    const kpis = calculation.quotas.map((quota) => {
      const status = quota.amount === null ? 'pending' : quota.amount > 0 ? 'achieved' : 'not-achieved';
      const description = status === 'pending' ? 'Da determinare' : status === 'achieved' ? 'Quota maturata' : 'Quota non maturata';
      return `<span class="pdr-kpi-status" data-status="${status}" title="${description}" aria-label="${escape(quota.code)}: ${description}">${escape(quota.code)}</span>`;
    }).join(' + ');
    return `<div class="pdr-row"><span>Somma quote annue (${kpis})</span><strong>${escape(money(amount))}</strong></div>`;
  }

  function renderResult() {
    const calculation = model.calculate(state);
    welfareButtons.forEach((button) => {
      button.setAttribute('aria-pressed', String(Number(button.dataset.guestWelfare) === Number(state.welfare)));
    });
    document.querySelector('#pdrAnnualPrizeAmount').textContent = money(calculation.referenceMaximum);
    for (const quota of calculation.quotas) {
      document.querySelector(`[data-band="${quota.code}"]`).textContent = quota.error || `Scaglione ${quota.index + 1} · quota annua`;
      document.querySelector(`[data-quota="${quota.code}"]`).textContent = money(quota.annualAmount);
      document.querySelector(`[data-base-share="${quota.code}"]`).textContent = quota.annualAmount === null || !(calculation.referenceMaximum > 0)
        ? 'Da determinare'
        : `${(quota.annualAmount / calculation.referenceMaximum * 100).toFixed(3).replace('.', ',')}%`;
    }
    let status = calculation.issues.length ? 'error' : 'open';
    let statusText = calculation.gateOpen === null ? 'Inserire la redditività per verificare il cancello.' : calculation.gateOpen ? 'Cancello aperto: redditività positiva.' : 'Cancello chiuso: redditività non positiva. PDR = 0 €.';
    const pendingGuestScenario = !authenticated && calculation.gateOpen === null;
    if (pendingGuestScenario) statusText = 'Scegli una simulazione per impostare il cancello e calcolare il premio.';
    if (!calculation.eligible) {
      status = 'closed';
      statusText = 'Condizioni individuali di accesso non soddisfatte. PDR = 0 €.';
    } else if (calculation.gateOpen === false) status = 'closed';
    message.dataset.status = status;
    const visibleIssues = pendingGuestScenario ? calculation.issues.filter((issue) => !issue.includes('redditività')) : calculation.issues;
    message.textContent = [statusText, ...visibleIssues].join('\n');
    const awardedCollective = calculation.gateOpen === false || !calculation.eligible ? 0 : calculation.annualCollective;
    resultContainer.innerHTML = summaryRow('Premio annuo', money(calculation.referenceMaximum))
      + collectiveSummaryRow(calculation, awardedCollective)
      + summaryRow('Coefficiente Ai', calculation.individualCoefficient === null ? 'Da determinare' : decimal(calculation.individualCoefficient).replace('.', ','))
      + summaryRow(`Competenza ${state.year}${state.year === 2026 ? ' · solo luglio–dicembre' : ''}`, `${calculation.periodFactor * 100}%`)
      + summaryRow('Riproporzionamento mesi × orario contrattuale', calculation.proRata === null ? 'Da determinare' : `${decimal(calculation.proRata * 100).replace('.', ',')}%`)
      + summaryRow('PDR individuale lordo · formula della bozza', money(calculation.total), true)
      + (Number(state.welfare) > 0 ? summaryRow('Quota in denaro (lorda)', money(calculation.cash)) + summaryRow('Quota convertita in welfare', money(calculation.converted)) + summaryRow('Incentivo welfare (12% solo al 100%)', money(calculation.welfareBonus)) + summaryRow('Disponibile in piattaforma welfare', money(calculation.welfareTotal), true) : '')
      + summaryRow('Indicatori migliorati rispetto ai riferimenti inseriti', calculation.improved.join(', ') || 'Nessuno');
    document.querySelector('#pdrAgreementJson').textContent = JSON.stringify({ profile: 'Bozza Canegrate 8 ottobre 2026', input: state, calculation }, null, 2);
  }

  function updateInput(target) {
    if (target.dataset.table) {
      const rows = target.dataset.table === 'Ai' ? state.individualRows : state.kpis.find((kpi) => kpi.code === target.dataset.table).rows;
      rows[Number(target.dataset.row)][target.dataset.field] = target.tagName === 'SELECT' ? target.value === 'true' : model.number(target.value);
    } else if (target.dataset.kpiField) {
      state.kpis.find((kpi) => kpi.code === target.closest('[data-kpi]').dataset.kpi)[target.dataset.kpiField] = model.number(target.value);
    } else if (target.name === 'year') {
      const previousMonths = state.year === 2026 ? 6 : 12;
      state.year = Number(target.value);
      const nextMonths = state.year === 2026 ? 6 : 12;
      if (state.months === previousMonths || state.months > nextMonths) state.months = nextMonths;
      form.elements.namedItem('months').value = state.months;
      updatePeriod();
    } else if (['inForce', 'excluded'].includes(target.name)) {
      state[target.name] = target.checked;
    } else if (['profitability', 'events', 'months', 'partTime', 'welfare'].includes(target.name)) {
      state[target.name] = model.number(target.value);
    } else return;
    persist();
    renderResult();
  }

  function demoValue(row, count) {
    if (row.min !== null && row.max !== null) return model.round((Number(row.min) + Number(row.max)) / 2);
    if (row.min !== null) return model.round(Number(row.min) + (row.minInclusive ? 0 : count ? 1 : 0.01));
    return model.round(Number(row.max) - (row.maxInclusive ? 0 : count ? 1 : 0.01));
  }

  function preset(choice) {
    if (choice === 'minimum' || choice.startsWith('intermediate-')) {
      const objectiveCount = choice === 'minimum' ? 1 : Number(choice.slice('intermediate-'.length));
      const minimum = model.minimumScenario(state, objectiveCount);
      if (!minimum) {
        message.textContent = `Le tabelle non consentono una simulazione con ${objectiveCount} obiettivi maturati: verifica soglie e importi.`;
        return;
      }
      state = minimum;
      persist();
      renderForm();
      return;
    }
    state.profitability = choice === 'closed' ? 0 : 1;
    state.events = 0;
    state.months = state.year === 2026 ? 6 : 12;
    state.partTime = 100;
    state.inForce = true;
    state.excluded = false;
    for (const kpi of state.kpis) {
      if (choice === 'baseline') {
        kpi.value = kpi.baseline;
      } else {
        const rows = [...kpi.rows].sort((left, right) => Number(right.amount) - Number(left.amount));
        const selected = rows[0];
        const raw = demoValue(selected, kpi.code.startsWith('Qe'));
        kpi.value = kpi.code.startsWith('Qe') ? Math.round(raw) : raw;
      }
    }
    persist();
    renderForm();
  }

  function switchProfile() {
    if (!authenticated) profilePicker.value = 'canegrate';
    const custom = profilePicker.value === 'custom';
    document.querySelector('#pdrCustomProfile').hidden = !custom;
    document.querySelector('#pdrAgreementProfile').hidden = custom;
    try { pdrStorage.setItem(profileKey, profilePicker.value); } catch { profileMessage.textContent = 'Salvataggio locale non disponibile in questo browser.'; }
    if (!custom || legacyLoaded || legacyLoading) return;
    legacyLoading = true;
    const script = document.createElement('script');
    script.src = 'pdr-calculator.js?v=20261016-guest-session-1';
    script.onload = () => { legacyLoaded = true; legacyLoading = false; };
    script.onerror = () => { legacyLoading = false; script.remove(); profileMessage.textContent = 'Impossibile caricare il calcolatore precedente. Riprova a selezionare il profilo.'; };
    document.body.append(script);
  }

  form.addEventListener('input', (event) => updateInput(event.target));
  form.addEventListener('change', (event) => updateInput(event.target));
  form.addEventListener('submit', (event) => { event.preventDefault(); persist(); renderResult(); });
  welfareButtons.forEach((button) => button.addEventListener('click', () => {
    if (authenticated) return;
    const input = form.elements.namedItem('welfare');
    input.value = button.dataset.guestWelfare;
    updateInput(input);
  }));
  document.querySelectorAll('[data-agreement-preset]').forEach((button) => button.addEventListener('click', () => preset(button.dataset.agreementPreset)));
  document.querySelector('#pdrAgreementReset').addEventListener('click', () => { state = model.defaults(); persist(); renderForm(); });
  profilePicker.addEventListener('change', switchProfile);
  try { profilePicker.value = authenticated && pdrStorage.getItem(profileKey) === 'custom' ? 'custom' : 'canegrate'; } catch { profilePicker.value = 'canegrate'; }
  renderForm();
  switchProfile();
})();
