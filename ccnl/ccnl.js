const blocks = {
  ccnl: [
    ['01', 'Economico, trasferta, reperibilità', '../myrsu/docs/ccnl_work/clean/01_blocco_economico_trasferta_reperibilita_luglio_2026.md'],
    ['02', 'Welfare, flexible benefits, PDR', '../myrsu/docs/ccnl_work/clean/02_blocco_welfare_flexible_benefits_pdr_luglio_2026.md'],
    ['03', 'Salute, sicurezza, RLS', '../myrsu/docs/ccnl_work/clean/03_blocco_salute_sicurezza_rls_luglio_2026.md'],
    ['04', 'Tipologie contrattuali', '../myrsu/docs/ccnl_work/clean/04_blocco_tipologie_contrattuali_luglio_2026.md'],
    ['05', 'Formazione, MetApprendo, apprendistato', '../myrsu/docs/ccnl_work/clean/05_blocco_formazione_metapprendo_apprendistato_luglio_2026.md'],
    ['06', 'Orario, PAR, conto ore, straordinario', '../myrsu/docs/ccnl_work/clean/06_blocco_orario_par_conto_ore_straordinario_luglio_2026.md'],
    ['07', 'Malattia, infortunio, congedi', '../myrsu/docs/ccnl_work/clean/07_blocco_malattia_infortunio_congedi_tutele_luglio_2026.md'],
    ['08', 'Classificazione e inquadramento', '../myrsu/docs/ccnl_work/clean/08_blocco_classificazione_inquadramento_professionalita_luglio_2026.md'],
    ['09', 'Ferie e permessi specifici', '../myrsu/docs/ccnl_work/clean/09_blocco_ferie_permessi_specifici_luglio_2026.md'],
    ['10', 'Pari opportunità e molestie', '../myrsu/docs/ccnl_work/clean/10_blocco_pari_opportunita_inclusivita_molestie_luglio_2026.md'],
    ['11', 'Informazione e consultazione', '../myrsu/docs/ccnl_work/clean/11_blocco_informazione_consultazione_aziendale_luglio_2026.md'],
    ['12', 'Appalti e cambio appalto', '../myrsu/docs/ccnl_work/clean/12_blocco_appalti_cambio_appalto_luglio_2026.md'],
    ['13', 'Contributi e deleghe sindacali', '../myrsu/docs/ccnl_work/clean/13_blocco_contributi_deleghe_sindacali_luglio_2026.md'],
    ['14', 'Campo applicazione e partecipazione', '../myrsu/docs/ccnl_work/clean/14_blocco_campo_applicazione_osservatorio_partecipazione_luglio_2026.md'],
    ['15', 'Decorrenza, una tantum, commissioni', '../myrsu/docs/ccnl_work/clean/15_blocco_decorrenza_distribuzione_una_tantum_commissioni_luglio_2026.md'],
    ['16', 'Sospensioni, CIG, disciplina', '../myrsu/docs/ccnl_work/clean/16_blocco_sospensioni_cig_provvedimenti_disciplinari_luglio_2026.md'],
    ['18', 'Diritti sindacali e permessi RSU', '../myrsu/docs/ccnl_work/clean/18_blocco_diritti_sindacali_assemblea_permessi_rsu_luglio_2026.md'],
    ['19', 'Assunzione, prova, trasferimenti', '../myrsu/docs/ccnl_work/clean/19_blocco_assunzione_prova_documenti_trasferimenti_luglio_2026.md'],
    ['20', 'Retribuzione e indennità', '../myrsu/docs/ccnl_work/clean/20_blocco_retribuzione_mensilizzazione_scatti_indennita_luglio_2026.md'],
    ['21', 'Previdenza, sanità, welfare', '../myrsu/docs/ccnl_work/clean/21_blocco_previdenza_sanita_welfare_elemento_perequativo_luglio_2026.md'],
    ['22', 'Assenze, studio, aspettative', '../myrsu/docs/ccnl_work/clean/22_blocco_assenze_permessi_studio_aspettative_luglio_2026.md'],
    ['23', 'Rapporti azienda, reclami, privacy', '../myrsu/docs/ccnl_work/clean/23_blocco_rapporti_azienda_reclami_privacy_controlli_luglio_2026.md'],
    ['24', 'Risoluzione, preavviso, TFR', '../myrsu/docs/ccnl_work/clean/24_blocco_risoluzione_rapporto_preavviso_tfr_documenti_luglio_2026.md'],
    ['25', 'Banca ore solidale', '../myrsu/docs/ccnl_work/clean/25_blocco_banca_ore_solidale_par_ferie_residue_luglio_2026.md'],
    ['26', 'Siderurgia e mansioni discontinue', '../myrsu/docs/ccnl_work/clean/26_blocco_siderurgia_mansioni_discontinue_squadre_luglio_2026.md'],
    ['27', 'Allegati RSU, RLS, PDR', '../myrsu/docs/ccnl_work/clean/27_blocco_allegati_rsu_rls_pdr_scioperi_luglio_2026.md'],
    ['28', 'Apprendistato completo', '../myrsu/docs/ccnl_work/clean/28_blocco_apprendistato_completo_luglio_2026.md'],
    ['99', 'Testo completo CCNL 2021', '../myrsu/docs/ccnl_work/clean/ccnl_2021_testo_completo_ricercabile.md'],
  ],
  representation: [
    ['R02', 'Allegato 3 RSU Metalmeccanici 2017 - testo originale', '../myrsu/docs/representation_work/clean/02_ALLEGATO_3_RSU_METALMECCANICI_2017.md'],
    ['R01', 'Testo coordinato rappresentanza', '../myrsu/docs/representation_work/clean/01_TESTO_COORDINATO_OPERATIVO_RAPPRESENTANZA_LUGLIO_2026.md'],
  ],
  safety: [
    ['S00', 'Indice sicurezza 81/08', '../myrsu/docs/safety_work/clean/00_INDICE_SICUREZZA_81_08.md'],
    ['S03', 'Blocco operativo RSU/RLS sicurezza 81/08', '../myrsu/docs/safety_work/clean/03_BLOCCO_OPERATIVO_RSU_RLS_SICUREZZA_81_08.md'],
    ['S04', 'Mappa articoli e temi sicurezza 81/08', '../myrsu/docs/safety_work/clean/04_MAPPA_ARTICOLI_TEMI_SICUREZZA_81_08.md'],
    ['S05', 'RLS, consultazione e accesso documenti', '../myrsu/docs/safety_work/clean/05_BLOCCO_RLS_CONSULTAZIONE_ACCESSO_DOCUMENTI.md'],
    ['S06', 'DVR e valutazione dei rischi', '../myrsu/docs/safety_work/clean/06_BLOCCO_DVR_VALUTAZIONE_RISCHI.md'],
    ['S07', 'Formazione, informazione e addestramento', '../myrsu/docs/safety_work/clean/07_BLOCCO_FORMAZIONE_INFORMAZIONE_ADDESTRAMENTO.md'],
    ['S08', 'Preposto, obblighi e responsabilità', '../myrsu/docs/safety_work/clean/08_BLOCCO_PREPOSTO_OBBLIGHI_RESPONSABILITA.md'],
    ['S09', 'Appalti, DUVRI e interferenze', '../myrsu/docs/safety_work/clean/09_BLOCCO_APPALTI_DUVRI_INTERFERENZE.md'],
    ['S10', 'Sorveglianza sanitaria e medico competente', '../myrsu/docs/safety_work/clean/10_BLOCCO_SORVEGLIANZA_SANITARIA_MEDICO_COMPETENTE.md'],
    ['S11', 'Vigilanza, sospensione e organi di controllo', '../myrsu/docs/safety_work/clean/11_BLOCCO_VIGILANZA_SOSPENSIONE_ORGANI_CONTROLLO.md'],
    ['S12', 'Emergenze, antincendio e primo soccorso', '../myrsu/docs/safety_work/clean/12_BLOCCO_EMERGENZE_ANTINCENDIO_PRIMO_SOCCORSO.md'],
    ['S14', 'Contenuti collegati 81/08: accordi, interpelli, circolari', '../myrsu/docs/safety_work/clean/14_CONTENUTI_COLLEGATI_81_08_ACCORDI_INTERPELLI_CIRCOLARI.md'],
    ['S15', 'ISO 45001 - sicurezza lavoro', '../myrsu/docs/safety_work/clean/15_ISO_45001_SICUREZZA_LAVORO.md'],
    ['S16', 'ISO 14001 - ambiente RSU', '../myrsu/docs/safety_work/clean/16_ISO_14001_AMBIENTE_RSU.md'],
    ['S17', 'Mappa ISO - 81/08', '../myrsu/docs/safety_work/clean/17_MAPPA_ISO_81_08.md'],
    ['S01', 'D.Lgs. 81/2008 - testo vigente pulito', '../myrsu/docs/safety_work/clean/01_D_LGS_81_2008_TESTO_VIGENTE_PULITO.md'],
  ],
};

const cache = new Map();
const sectionCache = new Map();
const scope = document.querySelector('#scope');
const query = document.querySelector('#query');
const results = document.querySelector('#results');
const modal = document.querySelector('#modal');
const modalTitle = document.querySelector('#modalTitle');
const modalBody = document.querySelector('#modalBody');
const summarizeButton = document.querySelector('#summarize');
const codexPanel = document.querySelector('#codexPanel');
const codexQuestionForm = document.querySelector('#codexQuestionForm');
const codexAnswer = document.querySelector('#codexAnswer');
const jsonOutput = document.querySelector('#jsonOutput');
let searchTimer = null;
let vocabularyLoaded = false;
let searchToken = 0;
let currentNormativaUnit = null;

function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function showJson(payload) {
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
}

async function fetchBlock(block) {
  if (cache.has(block[2])) return cache.get(block[2]);
  const text = await fetch(`${block[2]}?v=20260730-representation-full`, { cache: 'no-store' }).then(response => response.text());
  cache.set(block[2], text);
  return text;
}

function splitSections(text, block) {
  if (sectionCache.has(block[2])) return sectionCache.get(block[2]);
  const rows = [];
  let current = null;
  text.split('\n').forEach(line => {
    const trimmed = line.trim();
    const title = line.startsWith('## ') || /^\d{1,2}\.\s+/.test(trimmed) || (block[0] === '99' && /^(Art\.|TITOLO|SEZIONE|Allegato|Premessa)\b/i.test(trimmed));
    if (title) {
      if (current) rows.push(current);
      current = { title: line.replace(/^##\s+/, '').trim(), lines: [line.startsWith('## ') ? line : `## ${trimmed}`] };
      return;
    }
    if (current) current.lines.push(line);
  });
  if (current) rows.push(current);
  if (!rows.length && text.trim()) {
    rows.push({ title: block[1], lines: [`# ${block[1]}`, '', text] });
  }
  const mapped = rows.map((item, index) => ({ ...item, index, text: item.lines.join('\n').trim() }));
  sectionCache.set(block[2], mapped);
  return mapped;
}

function terms() {
  if (window.MyRsuNormativaVocabulary) {
    return window.MyRsuNormativaVocabulary.terms(scope.value, query.value, { remember: true });
  }
  const items = expandedTerms(query.value);
  return items.length ? items : normalize(query.value).split(/\s+/).filter(word => word.length > 2);
}

function rawTerms() {
  return normalize(query.value).split(/\s+/).filter(word => word.length > 2 && !stopWords.has(word));
}

function expandedTerms(value) {
  const base = normalize(value).split(/\s+/).filter(word => word.length > 2 && !stopWords.has(word));
  const extra = [];
  const text = base.join(' ');
  const map = [
    [['superiori', 'capo', 'responsabile', 'azienda', 'direzione', 'richiamo', 'contestazione', 'sanzione'], ['disciplina', 'provvedimenti', 'disciplinari', 'reclami', 'rapporti', 'azienda', 'contestazione', 'sanzioni', 'ammonizione', 'multa', 'sospensione']],
    [['permesso', 'permessi', 'uscire', 'assenza', 'assenze'], ['ferie', 'par', 'rol', 'conto', 'ore', 'permessi', 'assenze', 'malattia', 'infortunio', 'aspettativa']],
    [['sicurezza', 'rls', 'infortunio', 'rischio', 'visita', 'malattia', 'professionale'], ['salute', 'sicurezza', 'prevenzione', 'rappresentante', 'lavoratori', 'rls', 'infortunio', 'malattia', 'professionale', 'd.lgs', '81']],
    [['dvr', 'valutazione', 'rischi'], ['dvr', 'documento', 'valutazione', 'rischi', 'articolo 28', 'articolo 29']],
    [['preposto', 'capo', 'responsabile'], ['preposto', 'obblighi', 'vigilanza', 'articolo 19']],
    [['duvri', 'interferenze'], ['duvri', 'appalto', 'interferenze', 'articolo 26']],
    [['medico', 'visita', 'idoneita'], ['sorveglianza', 'sanitaria', 'medico', 'competente', 'idoneita', 'articolo 41']],
    [['sospensione', 'ispettorato', 'vigilanza'], ['sospensione', 'organi', 'vigilanza', 'ispettorato', 'articolo 14']],
    [['elezione', 'elezioni', 'votare', 'voto', 'candidati', 'liste'], ['elezioni', 'rsu', 'liste', 'seggi', 'scrutinio', 'commissione', 'elettorale', 'quorum', 'candidati', 'preferenze']],
    [['turno', 'turni', 'notte', 'sabato', 'domenica'], ['orario', 'turni', 'straordinario', 'maggiorazioni', 'notturno', 'festivo', 'riposo']],
    [['appalto', 'cambio', 'cooperativa', 'fornitore'], ['appalti', 'cambio', 'appalto', 'anzianita', 'lavoratori', 'subentro']],
    [['mansione', 'livello', 'categoria', 'inquadramento'], ['classificazione', 'inquadramento', 'professionalita', 'mansioni', 'livelli']],
    [['formazione', 'corso', 'metapprendo'], ['formazione', 'apprendistato', 'metapprendo', 'diritto', 'studio']],
    [['premio', 'pdr', 'risultato', 'welfare'], ['pdr', 'premio', 'risultato', 'welfare', 'benefit', 'produttivita']],
    [['ubriaco', 'ubriachezza', 'alcol', 'alcool', 'droga', 'stupefacenti'], ['ubriachezza', 'alcol', 'alcool', 'stupefacenti', 'disciplina', 'licenziamento', 'sospensione', 'multa', 'sicurezza']],
  ];
  map.forEach(([needles, additions]) => {
    if (needles.some(needle => text.includes(needle))) extra.push(...additions);
  });
  return [...new Set([...base, ...extra])];
}

const stopWords = new Set([
  'con', 'per', 'tra', 'fra', 'che', 'chi', 'cui', 'come', 'quando', 'dove',
  'del', 'della', 'dello', 'dei', 'degli', 'delle', 'dal', 'dalla', 'dallo',
  'nel', 'nella', 'nello', 'nei', 'negli', 'nelle', 'sul', 'sulla', 'sullo',
  'una', 'uno', 'sono', 'essere', 'avere', 'fare', 'faccio', 'posso', 'puo', 'devo', 'deve',
]);

function normalize(value) {
  return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9\s]/g, ' ');
}

function count(text) {
  const normalized = normalize(text);
  return terms().reduce((sum, term) => {
    const exact = normalized.match(new RegExp(`(^|\\s)${escapeRegExp(term)}(?=\\s|$)`, 'gi')) || [];
    const partial = term.length > 5 && normalized.includes(term) ? 1 : 0;
    return sum + exact.length + partial;
  }, 0);
}

function score(section) {
  return count(section.text) + (count(section.title) * 5);
}

function rankScore(block, section, matches) {
  const currentQuery = query.value.toLowerCase();
  const exactBoost = `${block[0]} ${block[1]} ${section.title}`.toLowerCase().includes(currentQuery) ? 10000 : 0;
  const fullTextPenalty = ['S01', '99'].includes(block[0]) ? -1000 : 0;
  const mapPenalty = ['S00', 'S04'].includes(block[0]) ? -150 : 0;
  const specificBoost = !['S01', '99'].includes(block[0]) ? 250 : 0;
  const isIsoQuery = /\b(iso|45001|14001)\b/i.test(currentQuery);
  const isoBoost = isIsoQuery && ['S15', 'S16', 'S17'].includes(block[0]) ? 10000 : 0;
  const agreementPenalty = isIsoQuery && ['S14', 'S01'].includes(block[0]) ? -5000 : 0;
  return exactBoost + isoBoost + specificBoost + matches + fullTextPenalty + mapPenalty + agreementPenalty;
}

function activeBlocks() {
  if (scope.value !== 'all') return blocks[scope.value] || [];
  return [...blocks.ccnl, ...blocks.representation, ...blocks.safety];
}

function questionScope(question) {
  return scope.value;
}

function questionTerms(question) {
  return window.MyRsuNormativaVocabulary?.terms(questionScope(question), question, { remember: true }) || terms();
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function snippet(text) {
  const normalizedText = normalize(text);
  const term = terms().find(item => normalizedText.includes(item)) || terms()[0] || '';
  const index = normalizedText.indexOf(term);
  return text.slice(Math.max(0, index - 80), index + 180).replace(/[|#*_>-]/g, ' ').replace(/\s+/g, ' ').trim();
}

async function search() {
  const currentToken = ++searchToken;
  if (query.value.trim().length < 2) return;
  if (scope.value === 'all' && rawTerms().length < 4) {
    const message = 'Con una ricerca breve scegli un ambito specifico.';
    results.innerHTML = `<p class="search-status">${message}</p>`;
    showJson({ error: { message } });
    return;
  }
  results.innerHTML = '<p class="search-status">Ricerca in corso...</p>';
  if (['all', 'ccnl', 'representation', 'safety'].includes(scope.value)) {
    await searchDb(currentToken);
    return;
  }
  if (!vocabularyLoaded) {
    await window.MyRsuNormativaVocabulary?.load('../myrsu/docs/normativa_vocabulary.json');
    vocabularyLoaded = true;
  }
  const found = await findResults();
  if (currentToken !== searchToken) return;
  showJson({ data: { scope: scope.value, query: query.value.trim(), count: found.length, items: found.slice(0, 20) } });
  results.innerHTML = found.map(item => `<button class="result-card" data-path="${item.block[2]}" data-index="${item.section.index}">
    <strong>${highlight(escapeHtml(item.section.title))}</strong>
    <span>${escapeHtml(item.block[1])} · ${item.matches}</span>
  </button>`).join('') || '<p>Nessun risultato.</p>';
}

async function findResults() {
  const found = [];
  for (const block of activeBlocks()) {
    const text = await fetchBlock(block);
    const blockMatches = count(`${block[0]} ${block[1]} ${text}`);
    splitSections(text, block).forEach(section => {
      const matches = score(section);
      if (matches + blockMatches > 0) {
        const totalMatches = matches || blockMatches;
        found.push({ block, section, matches: totalMatches, score: rankScore(block, section, totalMatches) });
      }
    });
  }
  found.sort((a, b) => (b.score - a.score) || (b.matches - a.matches));
  return found;
}

async function api(path) {
  const token = sessionStorage.getItem('token') || localStorage.getItem('token');
  const response = await fetch(`../myrsu/api/v1${path}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });
  const payload = await response.json();
  showJson(payload);
  if (!response.ok) throw new Error(payload.error?.message || 'Errore API');
  return payload.data;
}

async function searchDb(currentToken) {
  try {
    const data = await api(`/normativa/ricerca?scope=${encodeURIComponent(scope.value)}&q=${encodeURIComponent(query.value.trim())}&limit=20`);
    if (currentToken !== searchToken) return;
    const items = data.items || [];
    results.innerHTML = groupedDbCards(items);
    return;
    results.innerHTML = items.map(item => `<button class="result-card" data-db-unit="${item.id}">
      <strong>${highlight(escapeHtml(item.section_title))}</strong>
      <span>${escapeHtml(item.block_title || item.document_title)} · ${escapeHtml(item.stato_vigenza)}</span>
      <small>${highlight(escapeHtml(item.excerpt || ''))}</small>
    </button>`).join('') || '<p>Nessun risultato. Verifica che il CCNL sia stato importato nel DB.</p>';
  } catch (error) {
    if (currentToken !== searchToken) return;
    showJson({ error: { message: error.message } });
    results.innerHTML = `<p class="error">${escapeHtml(error.message)}</p>`;
  }
}

function groupedDbCards(items) {
  return items.map(item => {
    const matches = item.matches || [{ id: item.id, excerpt: item.excerpt }];
    const context = item.context_label || item.block_title || item.document_title || '';
    const parentTitle = shortText(item.section_title || 'Riferimento normativa', 120);
    return `<article class="result-card">
      <strong>${highlight(escapeHtml(parentTitle))}</strong>
      <span>${escapeHtml(context)} - ${escapeHtml(item.stato_vigenza)} - ${item.match_count || matches.length} contenuti</span>
      <div class="nested-results">
        ${matches.slice(0, 8).map(match => `<button class="nested-result-card" data-db-unit="${match.id || item.article_unit_id || item.id}"><strong>${escapeHtml(parentTitle)}</strong><small>${highlight(escapeHtml(shortText(match.excerpt || item.excerpt || '', 220)))}</small></button>`).join('')}
      </div>
    </article>`;
  }).join('') || '<p>Nessun risultato. Verifica che il CCNL sia stato importato nel DB.</p>';
}

function shortText(value, limit = 160) {
  const text = String(value || '').replace(/\s+/g, ' ').trim();
  return text.length > limit ? `${text.slice(0, limit - 1)}…` : text;
}

function render(text) {
  let table = false;
  return compactMarkdownTables(cleanDisplayText(bodyText(text))).split('\n').map(line => {
    const salaryTable = salaryTableHtml(line);
    if (salaryTable) return salaryTable;
    const pairTable = flatPairTableHtml(line);
    if (pairTable) return pairTable;
    const categoryAmountTable = categoryAmountTableHtml(line);
    if (categoryAmountTable) return categoryAmountTable;
    const flattenedTable = flattenedTableHtml(line);
    if (flattenedTable) return flattenedTable;
    const genericTable = genericFlatTableHtml(line);
    if (genericTable) return genericTable;
    if (line.startsWith('|')) {
      const cells = line.split('|').slice(1, -1).map(cell => cell.trim());
      if (/^\|[\s:-]+\|/.test(line)) return '';
      const isHeader = !table;
      const prefix = isHeader ? '<table class="normativa-data-table"><thead>' : '';
      const suffix = isHeader ? '</thead><tbody>' : '';
      table = true;
      const tag = isHeader ? 'th' : 'td';
      return `${prefix}<tr>${cells.map(cell => `<${tag}>${escapeHtml(cell)}</${tag}>`).join('')}</tr>${suffix}`;
    }
    const close = table ? '</tbody></table>' : '';
    table = false;
    return line.trim() ? `${close}<p>${escapeHtml(line)}</p>` : close;
  }).join('') + (table ? '</tbody></table>' : '');
}

function compactMarkdownTables(text) {
  return String(text || '').replace(/(\|[^\n]+\|)\n\s*\n(?=\|)/g, '$1\n');
}

function cleanDisplayText(text) {
  return String(text || '')
    .replace(/Â°/g, '°')
    .replace(/à¹/g, 'ù')
    .replace(/à¨/g, 'è')
    .replace(/à /g, 'à');
}

function salaryTableHtml(line) {
  const clean = cleanDisplayText(line).replace(/\s+/g, ' ').trim();
  if (!/^L?ivello\s+/i.test(clean) || !/\b[A-D][1-3]\b/.test(clean)) return '';
  const years = [...clean.matchAll(/\b20\d{2}\b/g)].map(match => match[0]).slice(0, 6);
  const rows = [...clean.matchAll(/\b([A-D][1-3])\s+((?:(?:\d{1,2}\.\d{3},\d{2}|\d{1,3},\d{2}%)\s*){2,6})/g)]
    .map(match => [match[1], ...match[2].trim().split(/\s+/)]);
  if (!years.length || !rows.length) return '';
  const headers = ['Livello', ...years.map(year => `1° giugno ${year}`)];
  return `<table class="normativa-data-table"><thead><tr>${headers.map(header => `<th>${escapeHtml(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

function flatPairTableHtml(line) {
  const clean = cleanDisplayText(line).replace(/\s+/g, ' ').trim();
  const rows = [];
  const regex = /\b(Nessuna|da\s+\d+\s+a\s+\d+|fino\s+a\s+\d+(?:\s+dipendenti)?|oltre\s+\d+(?:\s+dipendenti)?|da\s+\d+\s+a\s+\d+(?:\s+dipendenti)?)\s+(\d+(?:,\d+)?\s*(?:%|ore|giorni)?)/gi;
  let match;
  while ((match = regex.exec(clean)) !== null) {
    rows.push([match[1], match[2].replace(/\s+/g, ' ').trim()]);
  }
  if (rows.length < 2) return '';
  const valueHeader = rows.some(row => row[1].includes('%')) ? 'Percentuale' : 'Valore';
  return `<table class="normativa-data-table"><thead><tr><th>Scaglione</th><th>${valueHeader}</th></tr></thead><tbody>${rows.map(row => `<tr><td>${escapeHtml(row[0])}</td><td>${escapeHtml(row[1])}</td></tr>`).join('')}</tbody></table>`;
}

function categoryAmountTableHtml(line) {
  const clean = cleanDisplayText(line).replace(/\s+/g, ' ').trim();
  if (!/\b(?:D1|D2|C2|C3|B1|B2|B3)\b/.test(clean) || !/\b\d{3},\d{2}\b/.test(clean)) return '';
  const rows = [];
  const regex = /\b(\d+\s*(?:a|ª)?(?:\s+Super)?)\s+(Eliminata\s+dal\s+1°\s+giugno\s+2021|D1|D2|C2|C3|B1|B2|B3)\s+(\d{3},\d{2})/gi;
  let match;
  while ((match = regex.exec(clean.replace(/\ba\s+([47])\s+/g, '$1a '))) !== null) {
    rows.push([
      match[1].replace(/\s*a\b/i, 'ª').replace(/\s+/g, ' ').trim(),
      match[2],
      match[3],
    ]);
  }
  if (rows.length < 2) return '';
  const headers = ['Vecchia categoria', 'Nuovo livello', 'Importo'];
  return `<table class="normativa-data-table"><thead><tr>${headers.map(header => `<th>${escapeHtml(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

function flattenedTableHtml(line) {
  const clean = cleanDisplayText(line).replace(/\s+/g, ' ').trim();
  if (!/\b\dS?\s+\d{1,2}\.\d{3},\d{2}\b/.test(clean) || !/\b[A-D][1-3]\b/.test(clean)) return '';
  const rows = [];
  const regex = /\b(\dS?)\s+(\d{1,2}\.\d{3},\d{2})(?:\s+([^0-9]*?))?\s+([A-D])?\s*([A-D][1-3])\s+(\d{1,2}\.\d{3},\d{2})/g;
  let match;
  while ((match = regex.exec(clean)) !== null) {
    rows.push([
      match[1],
      match[2],
      match[4] || '',
      match[5],
      match[6],
      (match[3] || '').trim(),
    ]);
  }
  if (!rows.length) return '';
  const headers = ['Vecchio livello', 'Vecchio minimo', 'Area', 'Nuovo livello', 'Nuovo minimo', 'Descrizione'];
  return `<table class="normativa-data-table"><thead><tr>${headers.map(header => `<th>${escapeHtml(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

function genericFlatTableHtml(line) {
  const clean = cleanDisplayText(line).replace(/\s+/g, ' ').trim();
  const valueCount = (clean.match(/\b(?:\d{1,3}(?:\.\d{3})*,\d{2}|\d+(?:,\d+)?%|\d+\s+ore|20\d{2})\b/g) || []).length;
  if (valueCount < 6 || /[.;:]\s+[A-ZÀ-Ú]/.test(clean)) return '';
  const rows = [...clean.matchAll(/\b([A-Z]?\dS?|[A-D][1-3])\s+((?:\d{1,3}(?:\.\d{3})*,\d{2}|\d+(?:,\d+)?%)(?:\s+(?:\d{1,3}(?:\.\d{3})*,\d{2}|\d+(?:,\d+)?%)){1,6})/g)]
    .map(match => [match[1], ...match[2].trim().split(/\s+/)]);
  if (rows.length < 2) return '';
  const maxCells = Math.max(...rows.map(row => row.length));
  const headers = Array.from({ length: maxCells }, (_, index) => index === 0 ? 'Voce' : `Valore ${index}`);
  return `<table class="normativa-data-table"><thead><tr>${headers.map(header => `<th>${escapeHtml(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${headers.map((_, index) => `<td>${escapeHtml(row[index] || '')}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

function bodyText(text) {
  return String(text || '')
    .split('\n')
    .filter(line => !line.startsWith('# '))
    .filter(line => !line.startsWith('## '))
    .join('\n')
    .trim();
}

async function openResult(event) {
  const nestedCard = event.target.closest('.nested-result-card');
  if (nestedCard) {
    await openDbUnit(Number(nestedCard.dataset.dbUnit));
    return;
  }
  const card = event.target.closest('.result-card');
  if (!card) return;
  if (card.dataset.dbUnit) {
    await openDbUnit(Number(card.dataset.dbUnit));
    return;
  }
  const block = activeBlocks().find(item => item[2] === card.dataset.path);
  const section = splitSections(await fetchBlock(block), block)[Number(card.dataset.index)];
  modalTitle.textContent = section.title;
  modalBody.innerHTML = highlight(render(section.text));
  modal.showModal();
}

async function openDbUnit(id) {
  const data = await api(`/normativa/unita/${id}`);
  showJson({ data });
  currentNormativaUnit = data;
  summarizeButton.hidden = false;
  modalTitle.textContent = data.rubrica || data.etichetta || 'Riferimento CCNL';
  const hierarchy = data.hierarchy_label ? `<p class="normativa-hierarchy">${escapeHtml(data.hierarchy_label)}</p>` : '';
  modalBody.innerHTML = `${hierarchy}${highlight(render(data.testo || ''))}`;
  modal.showModal();
}

function summarizeCurrentUnit() {
  if (!currentNormativaUnit) return;
  const text = String(currentNormativaUnit.testo || '')
    .replace(/^#+\s+/gm, '')
    .replace(/\s+/g, ' ')
    .trim();
  const protectedText = text
    .replace(/\bn\.\s+(?=\d)/gi, 'n\u00a0')
    .replace(/\b(art|dott|avv|ing)\.\s+/gi, '$1\u00a0');
  const queryTerms = rawTerms().map(term => normalize(term)).filter(term => term.length > 2);
  const sentences = protectedText.split(/(?<=[.!?;:])\s+/).map((sentence, index) => ({
    sentence: sentence.replace(/\u00a0/g, '. ').trim(),
    index,
  })).filter(item => item.sentence.length >= 35);
  const ranked = sentences.map(item => {
    const normalized = normalize(item.sentence);
    const queryScore = queryTerms.reduce((total, term) => total + (normalized.includes(term) ? 8 : 0), 0);
    const ruleScore = /\b(deve|devono|diritto|obbligo|obblighi|vietato|entro|puo|possono|responsabilita)\b/.test(normalized) ? 3 : 0;
    return { ...item, score: queryScore + ruleScore + Math.max(0, 3 - item.index) };
  }).sort((left, right) => right.score - left.score).slice(0, 5).sort((left, right) => left.index - right.index);
  const summary = ranked.length ? ranked : sentences.slice(0, 5);
  modalBody.querySelector('.ai-summary')?.remove();
  modalBody.insertAdjacentHTML('afterbegin', `<section class="ai-summary"><h3>Sintesi AI</h3><ul>${summary.map(item => `<li>${escapeHtml(item.sentence)}</li>`).join('')}</ul></section>`);
}

function highlight(html) {
  return rawTerms().sort((a, b) => b.length - a.length).reduce((value, term) => {
    if (term.length < 2) return value;
    return value.replace(new RegExp(`(^|\\s)(${escapeRegExp(term)})(?=\\s|$)`, 'gi'), match => {
      const clean = match.trimStart();
      return `${match.slice(0, match.length - clean.length)}<mark>${clean}</mark>`;
    });
  }, html);
}

async function isMyrsuAdmin() {
  const token = sessionStorage.getItem('token') || localStorage.getItem('token');
  if (!token) return false;
  try {
    const response = await fetch('../myrsu/api/v1/me', { headers: { Authorization: `Bearer ${token}` } });
    const payload = await response.json();
    showJson(payload);
    return response.ok && (payload.data?.roles || []).includes('admin');
  } catch (_) {
    return false;
  }
}

async function initCodexPanel() {
  if (await isMyrsuAdmin()) codexPanel.classList.remove('hidden');
}

async function askCodex(event) {
  event.preventDefault();
  const question = new FormData(codexQuestionForm).get('question').toString().trim();
  if (question.length < 3) return;
  query.value = question;
  await window.MyRsuNormativaVocabulary?.load('../myrsu/docs/normativa_vocabulary.json');
  const detectedScope = questionScope(question);
  const found = (await findResults()).slice(0, 6);
  showJson({ data: { scope: detectedScope, question, count: found.length, items: found } });
  codexAnswer.innerHTML = `
    <h3>Risposta Codex locale</h3>
    <p>Ambito selezionato: <strong>${escapeHtml(detectedScope)}</strong>. Concetti: <strong>${escapeHtml(questionTerms(question).slice(0, 10).join(', '))}</strong>.</p>
    <div class="results">${found.map(item => `<button class="result-card" data-path="${item.block[2]}" data-index="${item.section.index}"><strong>${highlight(escapeHtml(item.section.title))}</strong><span>${escapeHtml(item.block[1])} · ${item.matches}</span></button>`).join('') || '<p>Nessun risultato.</p>'}</div>
  `;
}

function scheduleSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(search, 900);
}

results.addEventListener('click', openResult);
codexAnswer.addEventListener('click', openResult);

document.querySelector('#close').addEventListener('click', () => modal.close());
summarizeButton.addEventListener('click', summarizeCurrentUnit);
document.querySelector('#clear').addEventListener('click', () => { query.value = ''; results.innerHTML = ''; });
document.querySelector('#resetVocabulary').addEventListener('click', () => {
  window.MyRsuNormativaVocabulary?.reset();
  results.innerHTML = '';
  query.value = '';
});
codexQuestionForm.addEventListener('submit', askCodex);
query.addEventListener('input', scheduleSearch);
scope.addEventListener('change', search);
initCodexPanel();

