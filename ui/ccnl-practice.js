const ccnlBlocks = [
  ['01', 'Economico, trasferta, reperibilità', '../docs/ccnl_work/clean/01_blocco_economico_trasferta_reperibilita_luglio_2026.md'],
  ['02', 'Welfare, flexible benefits, PDR', '../docs/ccnl_work/clean/02_blocco_welfare_flexible_benefits_pdr_luglio_2026.md'],
  ['03', 'Salute, sicurezza, RLS', '../docs/ccnl_work/clean/03_blocco_salute_sicurezza_rls_luglio_2026.md'],
  ['04', 'Tipologie contrattuali', '../docs/ccnl_work/clean/04_blocco_tipologie_contrattuali_luglio_2026.md'],
  ['05', 'Formazione, MetApprendo, apprendistato', '../docs/ccnl_work/clean/05_blocco_formazione_metapprendo_apprendistato_luglio_2026.md'],
  ['06', 'Orario, PAR, conto ore, straordinario', '../docs/ccnl_work/clean/06_blocco_orario_par_conto_ore_straordinario_luglio_2026.md'],
  ['07', 'Malattia, infortunio, congedi', '../docs/ccnl_work/clean/07_blocco_malattia_infortunio_congedi_tutele_luglio_2026.md'],
  ['08', 'Classificazione e inquadramento', '../docs/ccnl_work/clean/08_blocco_classificazione_inquadramento_professionalita_luglio_2026.md'],
  ['09', 'Ferie e permessi specifici', '../docs/ccnl_work/clean/09_blocco_ferie_permessi_specifici_luglio_2026.md'],
  ['10', 'Pari opportunità e molestie', '../docs/ccnl_work/clean/10_blocco_pari_opportunita_inclusivita_molestie_luglio_2026.md'],
  ['11', 'Informazione e consultazione', '../docs/ccnl_work/clean/11_blocco_informazione_consultazione_aziendale_luglio_2026.md'],
  ['12', 'Appalti e cambio appalto', '../docs/ccnl_work/clean/12_blocco_appalti_cambio_appalto_luglio_2026.md'],
  ['13', 'Contributi e deleghe sindacali', '../docs/ccnl_work/clean/13_blocco_contributi_deleghe_sindacali_luglio_2026.md'],
  ['14', 'Campo applicazione e partecipazione', '../docs/ccnl_work/clean/14_blocco_campo_applicazione_osservatorio_partecipazione_luglio_2026.md'],
  ['15', 'Decorrenza, una tantum, commissioni', '../docs/ccnl_work/clean/15_blocco_decorrenza_distribuzione_una_tantum_commissioni_luglio_2026.md'],
  ['16', 'Sospensioni, CIG, disciplina', '../docs/ccnl_work/clean/16_blocco_sospensioni_cig_provvedimenti_disciplinari_luglio_2026.md'],
  ['18', 'Diritti sindacali e permessi RSU', '../docs/ccnl_work/clean/18_blocco_diritti_sindacali_assemblea_permessi_rsu_luglio_2026.md'],
  ['19', 'Assunzione, prova, trasferimenti', '../docs/ccnl_work/clean/19_blocco_assunzione_prova_documenti_trasferimenti_luglio_2026.md'],
  ['20', 'Retribuzione e indennità', '../docs/ccnl_work/clean/20_blocco_retribuzione_mensilizzazione_scatti_indennita_luglio_2026.md'],
  ['21', 'Previdenza, sanità, welfare', '../docs/ccnl_work/clean/21_blocco_previdenza_sanita_welfare_elemento_perequativo_luglio_2026.md'],
  ['22', 'Assenze, studio, aspettative', '../docs/ccnl_work/clean/22_blocco_assenze_permessi_studio_aspettative_luglio_2026.md'],
  ['23', 'Rapporti azienda, reclami, privacy', '../docs/ccnl_work/clean/23_blocco_rapporti_azienda_reclami_privacy_controlli_luglio_2026.md'],
  ['24', 'Risoluzione, preavviso, TFR', '../docs/ccnl_work/clean/24_blocco_risoluzione_rapporto_preavviso_tfr_documenti_luglio_2026.md'],
  ['25', 'Banca ore solidale', '../docs/ccnl_work/clean/25_blocco_banca_ore_solidale_par_ferie_residue_luglio_2026.md'],
  ['26', 'Siderurgia e mansioni discontinue', '../docs/ccnl_work/clean/26_blocco_siderurgia_mansioni_discontinue_squadre_luglio_2026.md'],
  ['27', 'Allegati RSU, RLS, PDR', '../docs/ccnl_work/clean/27_blocco_allegati_rsu_rls_pdr_scioperi_luglio_2026.md'],
  ['28', 'Apprendistato completo', '../docs/ccnl_work/clean/28_blocco_apprendistato_completo_luglio_2026.md'],
  ['99', 'Testo completo CCNL 2021', '../docs/ccnl_work/clean/ccnl_2021_testo_completo_ricercabile.md'],
];


const representationBlocks = [
  ['R02', 'Allegato 3 RSU Metalmeccanici 2017 - testo originale', '../docs/representation_work/clean/02_ALLEGATO_3_RSU_METALMECCANICI_2017.md'],
  ['R01', 'Testo coordinato rappresentanza', '../docs/representation_work/clean/01_TESTO_COORDINATO_OPERATIVO_RAPPRESENTANZA_LUGLIO_2026.md'],
];

const safetyBlocks = [
  ['S00', 'Indice sicurezza 81/08', '../docs/safety_work/clean/00_INDICE_SICUREZZA_81_08.md'],
  ['S03', 'Blocco operativo RSU/RLS sicurezza 81/08', '../docs/safety_work/clean/03_BLOCCO_OPERATIVO_RSU_RLS_SICUREZZA_81_08.md'],
  ['S04', 'Mappa articoli e temi sicurezza 81/08', '../docs/safety_work/clean/04_MAPPA_ARTICOLI_TEMI_SICUREZZA_81_08.md'],
  ['S05', 'RLS, consultazione e accesso documenti', '../docs/safety_work/clean/05_BLOCCO_RLS_CONSULTAZIONE_ACCESSO_DOCUMENTI.md'],
  ['S06', 'DVR e valutazione dei rischi', '../docs/safety_work/clean/06_BLOCCO_DVR_VALUTAZIONE_RISCHI.md'],
  ['S07', 'Formazione, informazione e addestramento', '../docs/safety_work/clean/07_BLOCCO_FORMAZIONE_INFORMAZIONE_ADDESTRAMENTO.md'],
  ['S08', 'Preposto, obblighi e responsabilità', '../docs/safety_work/clean/08_BLOCCO_PREPOSTO_OBBLIGHI_RESPONSABILITA.md'],
  ['S09', 'Appalti, DUVRI e interferenze', '../docs/safety_work/clean/09_BLOCCO_APPALTI_DUVRI_INTERFERENZE.md'],
  ['S10', 'Sorveglianza sanitaria e medico competente', '../docs/safety_work/clean/10_BLOCCO_SORVEGLIANZA_SANITARIA_MEDICO_COMPETENTE.md'],
  ['S11', 'Vigilanza, sospensione e organi di controllo', '../docs/safety_work/clean/11_BLOCCO_VIGILANZA_SOSPENSIONE_ORGANI_CONTROLLO.md'],
  ['S12', 'Emergenze, antincendio e primo soccorso', '../docs/safety_work/clean/12_BLOCCO_EMERGENZE_ANTINCENDIO_PRIMO_SOCCORSO.md'],
  ['S14', 'Contenuti collegati 81/08: accordi, interpelli, circolari', '../docs/safety_work/clean/14_CONTENUTI_COLLEGATI_81_08_ACCORDI_INTERPELLI_CIRCOLARI.md'],
  ['S15', 'ISO 45001 - sicurezza lavoro', '../docs/safety_work/clean/15_ISO_45001_SICUREZZA_LAVORO.md'],
  ['S16', 'ISO 14001 - ambiente RSU', '../docs/safety_work/clean/16_ISO_14001_AMBIENTE_RSU.md'],
  ['S17', 'Mappa ISO - 81/08', '../docs/safety_work/clean/17_MAPPA_ISO_81_08.md'],
  ['S01', 'D.Lgs. 81/2008 - testo vigente pulito', '../docs/safety_work/clean/01_D_LGS_81_2008_TESTO_VIGENTE_PULITO.md'],
];

const ccnlModal = document.querySelector('#ccnlModal');
const ccnlScopeSelect = document.querySelector('#ccnlScopeSelect');
const ccnlInput = document.querySelector('#ccnlSearchInput');
const ccnlResults = document.querySelector('#ccnlResults');
const ccnlSectionView = document.querySelector('#ccnlSectionView');
const ccnlHeaderLink = document.querySelector('#ccnlHeaderLink');
const ccnlEditForm = document.querySelector('#ccnlEditForm');
const ccnlCancelEdit = document.querySelector('#ccnlCancelEdit');
const ccnlResetVocabulary = document.querySelector('#ccnlResetVocabulary');
const ccnlCache = new Map();
const ccnlSections = new Map();
const ccnlVocabularyKey = 'myrsu_normativa_vocabulary';
let ccnlLastSelectedText = '';
let ccnlActiveSection = null;
let ccnlActiveBlock = null;
let ccnlDragStartRange = null;
let ccnlSearchTimer = null;
let ccnlVocabularyLoaded = false;

function ccnlEscape(value) {
  return String(value || '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
}

function ccnlEscapeRegExp(value) {
  return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function ccnlRender(text) {
  let table = false;
  return ccnlCompactMarkdownTables(ccnlCleanDisplayText(text)).split('\n').map(line => {
    const salaryTable = ccnlSalaryTable(line);
    if (salaryTable) return `${table ? '</tbody></table>' : ''}${salaryTable}`;
    const pairTable = ccnlFlatPairTable(line);
    if (pairTable) return `${table ? '</tbody></table>' : ''}${pairTable}`;
    const categoryAmountTable = ccnlCategoryAmountTable(line);
    if (categoryAmountTable) return `${table ? '</tbody></table>' : ''}${categoryAmountTable}`;
    const flattenedTable = ccnlFlattenedTable(line);
    if (flattenedTable) return `${table ? '</tbody></table>' : ''}${flattenedTable}`;
    const genericTable = ccnlGenericFlatTable(line);
    if (genericTable) return `${table ? '</tbody></table>' : ''}${genericTable}`;
    if (line.startsWith('|')) {
      const cells = line.split('|').slice(1, -1).map(cell => cell.trim());
      if (/^\|[\s:-]+\|/.test(line)) return '';
      const isHeader = !table;
      const prefix = isHeader ? '<table class="normativa-data-table"><thead>' : '';
      const suffix = isHeader ? '</thead><tbody>' : '';
      table = true;
      const tag = isHeader ? 'th' : 'td';
      return `${prefix}<tr>${cells.map(cell => `<${tag}>${ccnlEscape(cell)}</${tag}>`).join('')}</tr>${suffix}`;
    }
    const close = table ?'</tbody></table>' : '';
    table = false;
    if (line.startsWith('# ')) return `${close}<h1>${ccnlEscape(line.slice(2))}</h1>`;
    if (line.startsWith('## ')) return `${close}<h2>${ccnlEscape(line.slice(3))}</h2>`;
    if (line.startsWith('- ')) return `${close}<p>&bull; ${ccnlEscape(line.slice(2))}</p>`;
    return line.trim() ?`${close}<p>${ccnlEscape(line)}</p>` : close;
  }).join('') + (table ?'</tbody></table>' : '');
}

function ccnlCompactMarkdownTables(text) {
  return String(text || '').replace(/(\|[^\n]+\|)\n\s*\n(?=\|)/g, '$1\n');
}

function ccnlCleanDisplayText(text) {
  return String(text || '')
    .replace(/Â°/g, '°')
    .replace(/à¹/g, 'ù')
    .replace(/à¨/g, 'è')
    .replace(/à /g, 'à');
}

function ccnlSalaryTable(line) {
  const clean = ccnlCleanDisplayText(line).replace(/\s+/g, ' ').trim();
  if (!/^L?ivello\s+/i.test(clean) || !/\b[A-D][1-3]\b/.test(clean)) return '';
  const years = [...clean.matchAll(/\b20\d{2}\b/g)].map(match => match[0]).slice(0, 6);
  const rows = [...clean.matchAll(/\b([A-D][1-3])\s+((?:(?:\d{1,2}\.\d{3},\d{2}|\d{1,3},\d{2}%)\s*){2,6})/g)]
    .map(match => [match[1], ...match[2].trim().split(/\s+/)]);
  if (!years.length || !rows.length) return '';
  const headers = ['Livello', ...years.map(year => `1° giugno ${year}`)];
  return `<table class="normativa-data-table"><thead><tr>${headers.map(header => `<th>${ccnlEscape(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${ccnlEscape(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

function ccnlFlatPairTable(line) {
  const clean = ccnlCleanDisplayText(line).replace(/\s+/g, ' ').trim();
  const rows = [];
  const regex = /\b(Nessuna|da\s+\d+\s+a\s+\d+|fino\s+a\s+\d+(?:\s+dipendenti)?|oltre\s+\d+(?:\s+dipendenti)?|da\s+\d+\s+a\s+\d+(?:\s+dipendenti)?)\s+(\d+(?:,\d+)?\s*(?:%|ore|giorni)?)/gi;
  let match;
  while ((match = regex.exec(clean)) !== null) {
    rows.push([match[1], match[2].replace(/\s+/g, ' ').trim()]);
  }
  if (rows.length < 2) return '';
  const valueHeader = rows.some(row => row[1].includes('%')) ? 'Percentuale' : 'Valore';
  return `<table class="normativa-data-table"><thead><tr><th>Scaglione</th><th>${valueHeader}</th></tr></thead><tbody>${rows.map(row => `<tr><td>${ccnlEscape(row[0])}</td><td>${ccnlEscape(row[1])}</td></tr>`).join('')}</tbody></table>`;
}

function ccnlCategoryAmountTable(line) {
  const clean = ccnlCleanDisplayText(line).replace(/\s+/g, ' ').trim();
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
  return `<table class="normativa-data-table"><thead><tr>${headers.map(header => `<th>${ccnlEscape(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${ccnlEscape(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

function ccnlFlattenedTable(line) {
  const clean = ccnlCleanDisplayText(line).replace(/\s+/g, ' ').trim();
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
  return `<table class="normativa-data-table"><thead><tr>${headers.map(header => `<th>${ccnlEscape(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${ccnlEscape(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

function ccnlGenericFlatTable(line) {
  const clean = ccnlCleanDisplayText(line).replace(/\s+/g, ' ').trim();
  const valueCount = (clean.match(/\b(?:\d{1,3}(?:\.\d{3})*,\d{2}|\d+(?:,\d+)?%|\d+\s+ore|20\d{2})\b/g) || []).length;
  if (valueCount < 6 || /[.;:]\s+[A-ZÀ-Ú]/.test(clean)) return '';
  const rows = [...clean.matchAll(/\b([A-Z]?\dS?|[A-D][1-3])\s+((?:\d{1,3}(?:\.\d{3})*,\d{2}|\d+(?:,\d+)?%)(?:\s+(?:\d{1,3}(?:\.\d{3})*,\d{2}|\d+(?:,\d+)?%)){1,6})/g)]
    .map(match => [match[1], ...match[2].trim().split(/\s+/)]);
  if (rows.length < 2) return '';
  const maxCells = Math.max(...rows.map(row => row.length));
  const headers = Array.from({ length: maxCells }, (_, index) => index === 0 ? 'Voce' : `Valore ${index}`);
  return `<table class="normativa-data-table"><thead><tr>${headers.map(header => `<th>${ccnlEscape(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${headers.map((_, index) => `<td>${ccnlEscape(row[index] || '')}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
}

window.MyRsuCcnlRender = ccnlRender;

async function ccnlFetch(block) {
  if (ccnlCache.has(block[2])) return ccnlCache.get(block[2]);
  const text = await fetch(`${block[2]}?v=20260730-representation-full`, { cache: 'no-store' }).then(response => response.text());
  ccnlCache.set(block[2], text);
  return text;
}

function ccnlSplit(text, block) {
  if (ccnlSections.has(block[2])) return ccnlSections.get(block[2]);
  const title = text.split('\n').find(line => line.startsWith('# ')) || `# ${block[1]}`;
  const rows = [];
  let current = null;
  const lines = text.split('\n');
  lines.forEach((line, index) => {
    const trimmed = line.trim();
    const numberedTitle = /^\d{1,2}\.\s+/.test(trimmed);
    const boundary = line.startsWith('## ') || numberedTitle || (block[0] === '99' && /^(Art\.|TITOLO|SEZIONE|Allegato|Premessa)\b/i.test(trimmed));
    if (boundary) {
      if (current) rows.push(current);
      let sectionTitle = line.replace(/^##\s+/, '').trim();
      if (numberedTitle && lines[index + 1] && /^[a-z??????]/.test(lines[index + 1].trim()) && sectionTitle.length < 45) {
        sectionTitle = `${sectionTitle} ${lines[index + 1].trim()}`.replace(/\s+/g, ' ');
      }
      current = { title: sectionTitle, lines: [title, '', line.startsWith('## ') ? line : `## ${sectionTitle}`] };
      return;
    }
    if (current) current.lines.push(line);
  });
  if (current) rows.push(current);
  if (!rows.length && text.trim()) {
    rows.push({ title: block[1], lines: [title, '', text] });
  }
  const mapped = rows.map((item, index) => ({ ...item, index, text: item.lines.join('\n').trim() }));
  ccnlSections.set(block[2], mapped);
  return mapped;
}

function ccnlSnippet(text, term) {
  const matchTerm = ccnlFirstMatchTerm(text, term);
  const index = ccnlMatchIndex(text, matchTerm);
  return text.slice(Math.max(0, index - 70), index + matchTerm.length + 110).replace(/[|#*_>-]/g, ' ').replace(/\s+/g, ' ').trim();
}

function ccnlSearchRegex(term, flags = 'gi') {
  const escaped = ccnlEscapeRegExp(term.trim());
  return term.trim().includes(' ') ?new RegExp(escaped, flags) : new RegExp(`(^|\\s)(${escaped})(?=\\s|$)`, flags);
}

function ccnlCountMatches(text, term) {
  return ccnlQueryTerms(term).reduce((count, queryTerm) => count + (text.match(ccnlSearchRegex(queryTerm)) || []).length, 0);
}

function ccnlRankScore(block, section, term, matches, titleMatches) {
  const query = term.toLowerCase();
  const blockTitle = `${block[0]} ${block[1]}`.toLowerCase();
  const sectionTitle = section.title.toLowerCase();
  const exactBoost = blockTitle.includes(query) || sectionTitle.includes(query) ? 10000 : 0;
  const fullTextPenalty = ['S01', '99'].includes(block[0]) ? -1000 : 0;
  const mapPenalty = ['S00', 'S04'].includes(block[0]) ? -150 : 0;
  const specificBoost = !['S01', '99'].includes(block[0]) ? 250 : 0;
  return exactBoost + specificBoost + (titleMatches * 50) + matches + fullTextPenalty + mapPenalty;
}

function ccnlMatchIndex(text, term) {
  const match = ccnlSearchRegex(term).exec(text);
  if (!match) return -1;
  return match.index + (match[1] ?match[1].length : 0);
}

function ccnlQueryTerms(term) {
  const query = term.trim().toLowerCase();
  const learned = ccnlLearnedTerms(query);
  if (ccnlScopeSelect.value === 'representation' && ['elezione', 'elezioni', 'elettorale', 'elettorali'].includes(query)) {
    return ccnlUniqueTerms(['elezioni', 'elezione', 'elettorale', 'elettorali', 'votazione', 'votazioni', 'voto', 'liste', 'seggi', 'seggio', 'scrutinio', 'quorum', 'commissione elettorale', 'indire', 'indizione', 'rinnovo', ...learned]);
  }
  if (ccnlScopeSelect.value === 'safety') {
    const safetyMap = [
      [['dvr', 'valutazione', 'rischi'], ['dvr', 'documento di valutazione dei rischi', 'valutazione dei rischi', 'articolo 28', 'articolo 29']],
      [['rls', 'rappresentante'], ['rls', 'rappresentante dei lavoratori per la sicurezza', 'consultazione', 'attribuzioni', 'articolo 50']],
      [['preposto', 'capo', 'responsabile'], ['preposto', 'obblighi del preposto', 'vigilanza', 'articolo 19']],
      [['formazione', 'corso', 'addestramento'], ['formazione', 'informazione', 'addestramento', 'articolo 36', 'articolo 37']],
      [['appalto', 'duvri', 'interferenze'], ['appalto', 'duvri', 'interferenze', 'articolo 26']],
      [['visita', 'medico', 'malattia'], ['sorveglianza sanitaria', 'medico competente', 'visita medica', 'idoneità', 'articolo 41']],
      [['sospensione', 'ispettorato', 'vigilanza'], ['sospensione', 'organi di vigilanza', 'ispettorato nazionale del lavoro', 'articolo 14']],
    ];
    const found = safetyMap.find(([needles]) => needles.some(needle => query.includes(needle)));
    if (found) return ccnlUniqueTerms([...found[1], ...learned]);
  }
  const words = term.trim().split(/\s+/).filter(word => word.length > 2);
  return ccnlUniqueTerms([...(words.length > 1 ? words : [term.trim()]), ...learned]);
}

function ccnlRawQueryTerms(term) {
  return term.trim().split(/\s+/).map(word => word.replace(/[^\p{L}\p{N}]/gu, '')).filter(word => word.length > 2);
}

function ccnlUniqueTerms(terms) {
  return [...new Set(terms.map(term => term.trim()).filter(term => term.length > 1))];
}

function ccnlVocabulary() {
  try {
    return JSON.parse(localStorage.getItem(ccnlVocabularyKey) || '{}');
  } catch (_) {
    return {};
  }
}

function ccnlSaveVocabulary(vocabulary) {
  localStorage.setItem(ccnlVocabularyKey, JSON.stringify(vocabulary));
}

function ccnlMeaningfulTerms(value) {
  const stopWords = ['una', 'uno', 'del', 'della', 'dello', 'dei', 'degli', 'delle', 'dal', 'dalla', 'dallo', 'nel', 'nella', 'nello', 'nei', 'negli', 'nelle', 'sul', 'sulla', 'sullo', 'con', 'per', 'tra', 'fra', 'che', 'chi', 'cui', 'come', 'quando', 'dove', 'sono', 'essere', 'avere', 'fare', 'faccio', 'posso', 'puo', 'devo', 'deve'];
  return value.toLowerCase().split(/\s+/).map(term => term.replace(/[^\p{L}\p{N}]/gu, '')).filter(term => term.length > 2 && !stopWords.includes(term));
}

function ccnlRememberQuery(term) {
  window.MyRsuNormativaVocabulary?.remember(ccnlScopeSelect.value, term);
  const words = ccnlMeaningfulTerms(term);
  if (!words.length) return;
  const scope = ccnlScopeSelect.value;
  const vocabulary = ccnlVocabulary();
  vocabulary[scope] = vocabulary[scope] || [];
  const group = ccnlUniqueTerms([term.trim().toLowerCase(), ...words]);
  const exists = vocabulary[scope].some(item => item.join('|') === group.join('|'));
  if (!exists) vocabulary[scope].push(group);
  ccnlSaveVocabulary(vocabulary);
}

function ccnlLearnedTerms(query) {
  if (window.MyRsuNormativaVocabulary) {
    return window.MyRsuNormativaVocabulary.terms(ccnlScopeSelect.value, query);
  }
  const groups = ccnlVocabulary()[ccnlScopeSelect.value] || [];
  return groups.filter(group => group.some(term => query.includes(term) || term.includes(query))).flat();
}

function ccnlFirstMatchTerm(text, term) {
  return ccnlQueryTerms(term).find(queryTerm => ccnlSearchRegex(queryTerm).test(text)) || term;
}

async function ccnlSearch() {
  const term = ccnlInput.value.trim();
  if (term.length < 2) return;
  if (ccnlScopeSelect.value === 'all' && ccnlMeaningfulTerms(term).length < 4) {
    ccnlResults.innerHTML = '<p>Con una ricerca breve scegli un ambito specifico.</p>';
    return;
  }
  await ccnlSearchDb(term);
}

async function ccnlSearchDb(term) {
  const data = await api(`/normativa/ricerca?scope=${encodeURIComponent(ccnlScopeSelect.value)}&q=${encodeURIComponent(term)}&limit=20`);
  const items = data.items || [];
  ccnlResults.innerHTML = items.map(item => {
    const matches = item.matches || [{ id: item.id, excerpt: item.excerpt }];
    const context = item.context_label || item.block_title || item.document_title || '';
    const parentTitle = ccnlShortText(item.section_title || 'Riferimento normativa', 120);
    return `
      <article class="ccnl-result-card">
          <strong>${ccnlHighlightTerms(ccnlEscape(parentTitle), ccnlRawQueryTerms(term))}</strong>
        <span>${ccnlEscape(context)} &middot; ${ccnlEscape(item.stato_vigenza)} &middot; ${item.match_count || matches.length} contenuti</span>
        <div class="ccnl-nested-results">
          ${matches.slice(0, 8).map(match => `<button class="ccnl-nested-result-card" type="button" data-db-unit="${match.id || item.article_unit_id || item.id}" data-bookmark="${ccnlEscape(term)}"><strong>${ccnlEscape(parentTitle)}</strong><small>${ccnlHighlightTerms(ccnlEscape(ccnlShortText(match.excerpt || item.excerpt || '', 220)), ccnlRawQueryTerms(term))}</small></button>`).join('')}
        </div>
      </article>
    `;
  }).join('') || '<p>Nessun risultato.</p>';
}

function ccnlShortText(value, limit = 160) {
  const text = String(value || '').replace(/\s+/g, ' ').trim();
  return text.length > limit ? `${text.slice(0, limit - 1)}…` : text;
}

function ccnlScheduleSearch() {
  clearTimeout(ccnlSearchTimer);
  ccnlSearchTimer = setTimeout(ccnlSearch, 350);
}

async function ccnlOpenSection(blockCode, sectionIndex, bookmark = '') {
  const block = ccnlActiveBlocks().find(item => item[0] === blockCode);
  const section = ccnlSplit(await ccnlFetch(block), block)[sectionIndex];
  ccnlLastSelectedText = '';
  ccnlActiveBlock = block;
  ccnlActiveSection = section;
  ccnlHeaderLink.hidden = false;
  ccnlSectionView.innerHTML = `
    <div class="ccnl-link-bar">
      <button class="ccnl-link-action" type="button" data-ccnl-edit>Modifica testo</button>
      <button class="ccnl-close-reading" type="button" data-ccnl-close-reading>Chiudi lettura</button>
      <small id="ccnlSelectionStatus">Nessuna selezione: verrà collegata tutta la sezione.</small>
    </div>
    <div id="ccnlReadableText">${ccnlHighlight(ccnlRender(ccnlBodyText(section.text)), bookmark || ccnlInput.value.trim())}</div>
  `;
  ccnlSectionView.querySelector('[data-ccnl-close-reading]').addEventListener('click', () => { ccnlSectionView.innerHTML = ''; });
  ccnlSectionView.querySelector('[data-ccnl-edit]').addEventListener('click', () => ccnlOpenEdit(block));
  setTimeout(ccnlScrollToBookmark, 0);
}

async function ccnlOpenDbUnit(unitId, bookmark = '') {
  const data = await api(`/normativa/unita/${unitId}`);
  const block = [
    data.block_code || 'CCNL',
    data.block_title || data.document_title || 'CCNL',
    `normativa:unita:${unitId}`,
  ];
  const section = {
    title: data.rubrica || data.etichetta || 'Riferimento CCNL',
    text: data.testo || '',
  };
  const hierarchy = data.hierarchy_label ? `<p class="normativa-hierarchy">${ccnlEscape(data.hierarchy_label)}</p>` : '';
  ccnlLastSelectedText = '';
  ccnlActiveBlock = block;
  ccnlActiveSection = section;
  ccnlHeaderLink.hidden = false;
  ccnlSectionView.innerHTML = `
    <div class="ccnl-link-bar">
      <button class="ccnl-close-reading" type="button" data-ccnl-close-reading>Chiudi lettura</button>
      <small id="ccnlSelectionStatus">Nessuna selezione: verrà collegato tutto il riferimento.</small>
    </div>
    <div id="ccnlReadableText">${hierarchy}${ccnlHighlight(ccnlRender(ccnlBodyText(section.text)), bookmark || ccnlInput.value.trim())}</div>
  `;
  ccnlSectionView.querySelector('[data-ccnl-close-reading]').addEventListener('click', () => { ccnlSectionView.innerHTML = ''; });
  setTimeout(ccnlScrollToBookmark, 0);
}

async function ccnlOpenEdit(block) {
  const data = await api(`/normativa/file?source_path=${encodeURIComponent(block[2])}`);
  ccnlEditForm.source_path.value = block[2];
  ccnlEditForm.content.value = data.content;
  ccnlSectionView.classList.add('hidden');
  ccnlEditForm.classList.remove('hidden');
}

ccnlEditForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  await api('/normativa/file', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.fromEntries(new FormData(ccnlEditForm).entries())),
  });
  ccnlCache.delete(ccnlEditForm.source_path.value);
  ccnlSections.delete(ccnlEditForm.source_path.value);
  ccnlEditForm.classList.add('hidden');
  ccnlSectionView.classList.remove('hidden');
  ccnlSectionView.innerHTML = '';
  ccnlResults.innerHTML = '';
  message.textContent = 'Testo normativa aggiornato';
});

ccnlCancelEdit.addEventListener('click', () => {
  ccnlEditForm.classList.add('hidden');
  ccnlSectionView.classList.remove('hidden');
});

async function ccnlLink(block, section) {
  const selectedText = ccnlCleanSelection(ccnlLastSelectedText || ccnlSelectedText());
  const excerpt = selectedText || ccnlBodyText(section.text);
  await api(`/practices/${practiceId}/ccnl-links`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ block_code: block[0], block_title: block[1], section_title: section.title, source_path: block[2], excerpt }),
  });
  message.textContent = selectedText ?'Selezione CCNL collegata' : 'Riferimento CCNL collegato';
  ccnlModal.close();
  await load();
}

function ccnlSelectedText() {
  if (!ccnlModal.open) return '';
  const selection = window.getSelection();
  if (!selection || selection.isCollapsed) return '';
  const selected = selection.toString().trim();
  if (!selected) return '';
  return selected;
}

function ccnlBodyText(text) {
  return text
    .split('\n')
    .filter(line => !line.startsWith('# '))
    .filter(line => !line.startsWith('## '))
    .join('\n')
    .trim();
}

function ccnlCleanSelection(text) {
  return String(text || '')
    .split('\n')
    .filter(line => !/^Collega alla pratica$/i.test(line.trim()))
    .filter(line => !/^Nessuna selezione:/i.test(line.trim()))
    .filter(line => !/^Selezione pronta:/i.test(line.trim()))
    .filter(line => !/^CCNL\s+2021\s+-\s+Testo completo ricercabile$/i.test(line.trim()))
    .filter(line => !/^CCNL Metalmeccanici Industria\b/i.test(line.trim()))
    .join('\n')
    .trim();
}

function ccnlRememberSelection() {
  const selected = ccnlSelectedText();
  if (!selected) return;
  ccnlSetSelectedText(selected);
}

function ccnlPrepareLinkClick(event) {
  ccnlRememberSelection();
}

function ccnlStartCustomSelection(event) {
  const readable = document.querySelector('#ccnlReadableText');
  if (!readable || !readable.contains(event.target)) return;
  ccnlDragStartRange = ccnlRangeFromPoint(event);
}

function ccnlEndCustomSelection(event) {
  const readable = document.querySelector('#ccnlReadableText');
  if (!readable || !ccnlDragStartRange) return;
  const endRange = ccnlRangeFromPoint(event);
  if (!endRange) return;
  const range = document.createRange();
  range.setStart(ccnlDragStartRange.startContainer, ccnlDragStartRange.startOffset);
  range.setEnd(endRange.startContainer, endRange.startOffset);
  if (range.collapsed) {
    range.setStart(endRange.startContainer, endRange.startOffset);
    range.setEnd(ccnlDragStartRange.startContainer, ccnlDragStartRange.startOffset);
  }
  const text = range.toString().trim();
  if (text) ccnlSetSelectedText(text);
  ccnlDragStartRange = null;
}

function ccnlRangeFromPoint(event) {
  const point = event.changedTouches ?event.changedTouches[0] : event;
  if (document.caretRangeFromPoint) return document.caretRangeFromPoint(point.clientX, point.clientY);
  if (!document.caretPositionFromPoint) return null;
  const position = document.caretPositionFromPoint(point.clientX, point.clientY);
  if (!position) return null;
  const range = document.createRange();
  range.setStart(position.offsetNode, position.offset);
  range.collapse(true);
  return range;
}

function ccnlSetSelectedText(text) {
  ccnlLastSelectedText = ccnlCleanSelection(text);
  const status = document.querySelector('#ccnlSelectionStatus');
  if (status) status.textContent = `Selezione pronta: ${ccnlLastSelectedText.length} caratteri.`;
}

function ccnlHighlight(html, term) {
  const terms = ccnlUniqueTerms([term, ...ccnlRawQueryTerms(ccnlInput.value.trim())]).sort((a, b) => b.length - a.length);
  if (!terms.length) return html;
  let first = true;
  return terms.reduce((value, item) => value.replace(ccnlSearchRegex(item), match => {
    const cleanMatch = match.trimStart();
    const prefix = match.slice(0, match.length - cleanMatch.length);
    if (!first) return `${prefix}<mark>${cleanMatch}</mark>`;
    first = false;
    return `${prefix}<mark id="ccnlBookmark">${cleanMatch}</mark>`;
  }), html);
}

function ccnlHighlightTerms(html, terms) {
  return ccnlUniqueTerms(terms).sort((a, b) => b.length - a.length).reduce((value, term) => {
    if (term.length < 2) return value;
    return value.replace(ccnlSearchRegex(term), match => {
      const cleanMatch = match.trimStart();
      const prefix = match.slice(0, match.length - cleanMatch.length);
      return `${prefix}<mark>${cleanMatch}</mark>`;
    });
  }, html);
}

function ccnlScrollToBookmark() {
  const mark = document.querySelector('#ccnlBookmark');
  if (mark) mark.scrollIntoView({ block: 'center' });
}

document.querySelector('#openCcnlModal').addEventListener('click', () => ccnlModal.showModal());
document.querySelector('#closeCcnlModal').addEventListener('click', () => ccnlModal.close());
ccnlHeaderLink.addEventListener('pointerdown', ccnlPrepareLinkClick);
ccnlHeaderLink.addEventListener('mousedown', ccnlPrepareLinkClick);
ccnlHeaderLink.addEventListener('touchstart', ccnlPrepareLinkClick);
ccnlHeaderLink.addEventListener('click', () => ccnlLink(ccnlActiveBlock, ccnlActiveSection));
ccnlModal.addEventListener('close', () => { ccnlHeaderLink.hidden = true; });
document.querySelector('#ccnlClearSearch').addEventListener('click', () => { ccnlInput.value = ''; ccnlResults.innerHTML = ''; ccnlSectionView.innerHTML = ''; });
ccnlResetVocabulary?.addEventListener('click', () => {
  window.MyRsuNormativaVocabulary?.reset();
  localStorage.removeItem(ccnlVocabularyKey);
  ccnlResults.innerHTML = '';
  ccnlSectionView.innerHTML = '';
  message.textContent = 'Termini locali azzerati';
});
ccnlScopeSelect.addEventListener('change', () => {
  const placeholders = {
    representation: 'Cerca nel Testo Unico Rappresentanza',
    safety: 'Cerca in Sicurezza 81/08',
    all: 'Cerca in tutte le normative',
    ccnl: 'Cerca nel CCNL',
  };
  ccnlInput.placeholder = placeholders[ccnlScopeSelect.value] || placeholders.ccnl;
  ccnlResults.innerHTML = '';
  ccnlSectionView.innerHTML = '';
  ccnlSearch();
});
ccnlInput.addEventListener('input', ccnlScheduleSearch);
document.addEventListener('selectionchange', ccnlRememberSelection);
document.addEventListener('mouseup', () => setTimeout(ccnlRememberSelection, 0));
document.addEventListener('pointerup', () => setTimeout(ccnlRememberSelection, 0));
document.addEventListener('touchend', () => setTimeout(ccnlRememberSelection, 120));
ccnlSectionView.addEventListener('pointerdown', ccnlStartCustomSelection);
ccnlSectionView.addEventListener('pointerup', ccnlEndCustomSelection);
ccnlSectionView.addEventListener('touchstart', ccnlStartCustomSelection);
ccnlSectionView.addEventListener('touchend', ccnlEndCustomSelection);
ccnlSectionView.addEventListener('mouseup', ccnlRememberSelection);
ccnlSectionView.addEventListener('pointerup', ccnlRememberSelection);
ccnlSectionView.addEventListener('touchend', () => setTimeout(ccnlRememberSelection, 50));
ccnlSectionView.addEventListener('keyup', ccnlRememberSelection);
ccnlResults.addEventListener('click', event => {
  const nestedCard = event.target.closest('.ccnl-nested-result-card');
  if (nestedCard) {
    ccnlOpenDbUnit(nestedCard.dataset.dbUnit, nestedCard.dataset.bookmark || ccnlInput.value.trim());
    return;
  }
  const card = event.target.closest('.ccnl-result-card');
  if (!card) return;
  if (card.dataset.dbUnit) {
    ccnlOpenDbUnit(card.dataset.dbUnit, card.dataset.bookmark || ccnlInput.value.trim());
    return;
  }
  ccnlOpenSection(card.dataset.block, Number(card.dataset.section), card.dataset.bookmark || ccnlInput.value.trim());
});


function ccnlActiveBlocks() {
  if (ccnlScopeSelect.value === 'all') return [...ccnlBlocks, ...representationBlocks, ...safetyBlocks];
  if (ccnlScopeSelect.value === 'representation') return representationBlocks;
  if (ccnlScopeSelect.value === 'safety') return safetyBlocks;
  return ccnlBlocks;
}

function ccnlBlocksForScope(scope) {
  if (scope === 'all') return [...ccnlBlocks, ...representationBlocks, ...safetyBlocks];
  if (scope === 'representation') return representationBlocks;
  if (scope === 'safety') return safetyBlocks;
  if (scope === 'ccnl') return ccnlBlocks;
  return ccnlActiveBlocks();
}

window.MyRsuNormativaSearch = {
  search: ccnlSearch,
  input: ccnlInput,
  results: ccnlResults,
  sectionView: ccnlSectionView,
  activeBlocks: ccnlActiveBlocks,
  blocksForScope: ccnlBlocksForScope,
  fetchBlock: ccnlFetch,
  split: ccnlSplit,
  countMatches: ccnlCountMatches,
  queryTerms: ccnlQueryTerms,
  openSection: ccnlOpenSection,
  escape: ccnlEscape,
};
