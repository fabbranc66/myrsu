const blocks = [
  ['00', 'Indice blocchi', '../docs/ccnl_work/clean/00_INDICE_BLOCCHI_CONSOLIDATI_LUGLIO_2026.md'],
  ['01', 'Economico, trasferta, reperibilit?', '../docs/ccnl_work/clean/01_blocco_economico_trasferta_reperibilita_luglio_2026.md'],
  ['02', 'Welfare, flexible benefits, PDR', '../docs/ccnl_work/clean/02_blocco_welfare_flexible_benefits_pdr_luglio_2026.md'],
  ['03', 'Salute, sicurezza, RLS', '../docs/ccnl_work/clean/03_blocco_salute_sicurezza_rls_luglio_2026.md'],
  ['04', 'Tipologie contrattuali', '../docs/ccnl_work/clean/04_blocco_tipologie_contrattuali_luglio_2026.md'],
  ['05', 'Formazione, MetApprendo, apprendistato', '../docs/ccnl_work/clean/05_blocco_formazione_metapprendo_apprendistato_luglio_2026.md'],
  ['06', 'Orario, PAR, conto ore, straordinario', '../docs/ccnl_work/clean/06_blocco_orario_par_conto_ore_straordinario_luglio_2026.md'],
  ['07', 'Malattia, infortunio, congedi', '../docs/ccnl_work/clean/07_blocco_malattia_infortunio_congedi_tutele_luglio_2026.md'],
  ['08', 'Classificazione e inquadramento', '../docs/ccnl_work/clean/08_blocco_classificazione_inquadramento_professionalita_luglio_2026.md'],
  ['09', 'Ferie e permessi specifici', '../docs/ccnl_work/clean/09_blocco_ferie_permessi_specifici_luglio_2026.md'],
  ['10', 'Pari opportunit? e molestie', '../docs/ccnl_work/clean/10_blocco_pari_opportunita_inclusivita_molestie_luglio_2026.md'],
  ['11', 'Informazione e consultazione', '../docs/ccnl_work/clean/11_blocco_informazione_consultazione_aziendale_luglio_2026.md'],
  ['12', 'Appalti e cambio appalto', '../docs/ccnl_work/clean/12_blocco_appalti_cambio_appalto_luglio_2026.md'],
  ['13', 'Contributi e deleghe sindacali', '../docs/ccnl_work/clean/13_blocco_contributi_deleghe_sindacali_luglio_2026.md'],
  ['14', 'Campo applicazione e partecipazione', '../docs/ccnl_work/clean/14_blocco_campo_applicazione_osservatorio_partecipazione_luglio_2026.md'],
  ['15', 'Decorrenza, una tantum, commissioni', '../docs/ccnl_work/clean/15_blocco_decorrenza_distribuzione_una_tantum_commissioni_luglio_2026.md'],
  ['16', 'Sospensioni, CIG, disciplina', '../docs/ccnl_work/clean/16_blocco_sospensioni_cig_provvedimenti_disciplinari_luglio_2026.md'],
  ['18', 'Diritti sindacali e permessi RSU', '../docs/ccnl_work/clean/18_blocco_diritti_sindacali_assemblea_permessi_rsu_luglio_2026.md'],
  ['19', 'Assunzione, prova, trasferimenti', '../docs/ccnl_work/clean/19_blocco_assunzione_prova_documenti_trasferimenti_luglio_2026.md'],
  ['20', 'Retribuzione e indennit?', '../docs/ccnl_work/clean/20_blocco_retribuzione_mensilizzazione_scatti_indennita_luglio_2026.md'],
  ['21', 'Previdenza, sanit?, welfare', '../docs/ccnl_work/clean/21_blocco_previdenza_sanita_welfare_elemento_perequativo_luglio_2026.md'],
  ['22', 'Assenze, studio, aspettative', '../docs/ccnl_work/clean/22_blocco_assenze_permessi_studio_aspettative_luglio_2026.md'],
  ['23', 'Rapporti azienda, reclami, privacy', '../docs/ccnl_work/clean/23_blocco_rapporti_azienda_reclami_privacy_controlli_luglio_2026.md'],
  ['24', 'Risoluzione, preavviso, TFR', '../docs/ccnl_work/clean/24_blocco_risoluzione_rapporto_preavviso_tfr_documenti_luglio_2026.md'],
  ['25', 'Banca ore solidale', '../docs/ccnl_work/clean/25_blocco_banca_ore_solidale_par_ferie_residue_luglio_2026.md'],
  ['26', 'Siderurgia e mansioni discontinue', '../docs/ccnl_work/clean/26_blocco_siderurgia_mansioni_discontinue_squadre_luglio_2026.md'],
  ['27', 'Allegati RSU, RLS, PDR', '../docs/ccnl_work/clean/27_blocco_allegati_rsu_rls_pdr_scioperi_luglio_2026.md'],
  ['28', 'Apprendistato completo', '../docs/ccnl_work/clean/28_blocco_apprendistato_completo_luglio_2026.md'],
  ['17', 'Mappatura completa articoli', '../docs/ccnl_work/clean/17_MAPPATURA_COMPLETA_ARTICOLI_CCNL_2021_2025_2026.md'],
  ['99', 'Testo completo CCNL 2021', '../docs/ccnl_work/clean/ccnl_2021_testo_completo_ricercabile.md'],
];

const blocksList = document.getElementById('blocksList');
const documentView = document.getElementById('documentView');
const jsonView = document.getElementById('jsonView');
const searchInput = document.getElementById('searchInput');
const resultModal = document.getElementById('resultModal');
const modalContent = document.getElementById('modalContent');
let currentText = '';
let currentBlock = null;
const cache = new Map();
const sectionCache = new Map();

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));
}

function renderMarkdown(text) {
  const lines = text.split('\n');
  let html = '';
  let inList = false;
  let inTable = false;

  lines.forEach(line => {
    if (line.startsWith('|')) {
      const cells = line.split('|').slice(1, -1).map(cell => escapeHtml(cell.trim()));
      if (cells.every(cell => /^-+$/.test(cell))) return;
      if (!inTable) html += '<table><tbody>';
      inTable = true;
      html += `<tr>${cells.map(cell => `<td>${cell}</td>`).join('')}</tr>`;
      return;
    }
    if (inTable) {
      html += '</tbody></table>';
      inTable = false;
    }
    if (line.startsWith('- ')) {
      if (!inList) html += '<ul>';
      inList = true;
      html += `<li>${escapeHtml(line.slice(2))}</li>`;
      return;
    }
    if (inList) {
      html += '</ul>';
      inList = false;
    }
    if (line.startsWith('# ')) html += `<h1>${escapeHtml(line.slice(2))}</h1>`;
    else if (line.startsWith('## ')) html += `<h2>${escapeHtml(line.slice(3))}</h2>`;
    else if (line.trim()) html += `<p>${escapeHtml(line)}</p>`;
  });

  if (inList) html += '</ul>';
  if (inTable) html += '</tbody></table>';
  return html;
}

function escapeRegExp(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function highlightHtml(html, term) {
  if (term.length < 2) return html;
  return html.replace(searchRegex(escapeHtml(term)), match => {
    const cleanMatch = match.trimStart();
    return `${match.slice(0, match.length - cleanMatch.length)}<mark>${cleanMatch}</mark>`;
  });
}

function splitSections(text, block) {
  if (sectionCache.has(block[2])) return sectionCache.get(block[2]);

  const lines = text.split('\n');
  const title = lines.find(line => line.startsWith('# ')) || `# ${block[1]}`;
  const sections = [];
  let current = null;

  lines.forEach(line => {
    const trimmed = line.trim();
    const isHeading = line.startsWith('## ');
    const isFullTextBoundary = block[0] === '99' && /^(Art\.|ART\.|TITOLO|SEZIONE|Allegato|ALLEGATO|Premessa|PREMESSA)\b/.test(trimmed);

    if (isHeading || isFullTextBoundary) {
      if (current && current.lines.length) sections.push(current);
      current = {
        title: isHeading ?line.replace(/^##\s+/, '').trim() : trimmed,
        lines: [title, '', isHeading ?line : `## ${trimmed}`],
      };
      return;
    }

    if (current) current.lines.push(line);
  });

  if (current && current.lines.length) sections.push(current);

  const normalized = (sections.length ?sections : [{ title: block[1], lines }]).map((section, index) => ({
    index,
    title: section.title || block[1],
    text: section.lines.join('\n').trim(),
  }));

  sectionCache.set(block[2], normalized);
  return normalized;
}

function countMatches(text, term) {
  return (text.match(searchRegex(term)) || []).length;
}

function searchRegex(term, flags = 'gi') {
  const escaped = escapeRegExp(term.trim());
  return term.trim().includes(' ') ?new RegExp(escaped, flags) : new RegExp(`(^|\\s)${escaped}(?=\\s|$)`, flags);
}

function applySearch() {
  const term = searchInput.value.trim();
  const html = highlightHtml(renderMarkdown(currentText), term);
  documentView.innerHTML = html;
}

async function loadBlock(block, button) {
  document.querySelectorAll('.block-button').forEach(item => item.classList.remove('active'));
  if (button) button.classList.add('active');
  currentBlock = block;
  currentText = await fetchBlock(block);
  applySearch();
  jsonView.textContent = JSON.stringify({ data: { code: block[0], title: block[1], path: block[2] } }, null, 2);
}

async function openSectionModal(block, sectionIndex) {
  currentBlock = block;
  const text = await fetchBlock(block);
  const section = splitSections(text, block)[sectionIndex];
  const term = searchInput.value.trim();
  modalContent.innerHTML = highlightHtml(renderMarkdown(section.text), term);
  resultModal.hidden = false;
}

async function fetchBlock(block) {
  if (cache.has(block[2])) return cache.get(block[2]);
  const response = await fetch(block[2]);
  const text = await response.text();
  cache.set(block[2], text);
  return text;
}

function getSnippet(text, term) {
  const lowerText = text.toLowerCase();
  const match = searchRegex(term).exec(text);
  const index = match ?match.index + (match[0].length - match[0].trimStart().length) : -1;
  if (index === -1) return '';
  const start = Math.max(0, index - 80);
  const end = Math.min(text.length, index + term.length + 120);
  return text
    .slice(start, end)
    .replace(/`[^`]+`/g, '')
    .replace(/[|#*_>-]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

async function globalSearch() {
  const term = searchInput.value.trim();
  if (term.length < 2) {
    if (currentBlock) applySearch();
    return;
  }

  const results = [];
  for (const block of blocks) {
    const text = await fetchBlock(block);
    splitSections(text, block).forEach(section => {
      const matches = countMatches(section.text, term);
      if (matches > 0) {
        results.push({ block, section, matches, snippet: getSnippet(section.text, term) });
      }
    });
  }

  results.sort((a, b) => b.matches - a.matches);

  documentView.innerHTML = `
    <h1>Risultati ricerca</h1>
    <p>${results.length} sezioni trovate per: <strong>${escapeHtml(term)}</strong></p>
    <div class="search-results">
      ${results.map(item => `
        <button class="result-card" type="button" data-code="${item.block[0]}" data-section="${item.section.index}">
          <strong>${escapeHtml(item.section.title)}</strong>
          <span>${escapeHtml(item.block[0])} - ${escapeHtml(item.block[1])} ? ${item.matches} occorrenze</span>
          <small>${escapeHtml(item.snippet)}</small>
        </button>
      `).join('') || '<p>Nessun risultato.</p>'}
    </div>
  `;
  document.querySelectorAll('.result-card').forEach(card => {
    card.addEventListener('click', () => {
      const block = blocks.find(item => item[0] === card.dataset.code);
      openSectionModal(block, Number(card.dataset.section));
    });
  });
  jsonView.textContent = JSON.stringify({
    data: {
      query: term,
      count: results.length,
      results: results.map(item => ({
        block: item.block[0],
        block_title: item.block[1],
        section: item.section.title,
        matches: item.matches,
      })),
    },
  }, null, 2);
}

blocks.forEach(block => {
  const button = document.createElement('button');
  button.className = 'block-button';
  button.type = 'button';
  button.textContent = `${block[0]} - ${block[1]}`;
  button.addEventListener('click', () => loadBlock(block, button));
  blocksList.appendChild(button);
});

searchInput.addEventListener('input', globalSearch);
document.getElementById('clearSearch').addEventListener('click', () => {
  searchInput.value = '';
  applySearch();
});

document.getElementById('closeModal').addEventListener('click', () => {
  resultModal.hidden = true;
});

resultModal.addEventListener('click', event => {
  if (event.target === resultModal) resultModal.hidden = true;
});

