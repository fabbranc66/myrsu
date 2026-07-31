const ccnlAiButton = document.querySelector('#ccnlAiSearch');
const ccnlAiHint = document.querySelector('#ccnlAiHint');

const normativeTerms = [
  'permessi', 'ferie', 'par', 'orario', 'straordinario', 'malattia', 'infortunio',
  'sicurezza', 'rls', 'rsu', 'assemblea', 'elezioni', 'disciplina', 'sospensione',
  'cig', 'appalto', 'trasferimento', 'mansione', 'inquadramento', 'formazione',
  'privacy', 'controlli', 'retribuzione', 'welfare', 'pdr', 'banca ore',
];

const aiSynonyms = new Map([
  ['problemi superiori', 'provvedimenti disciplinari rapporti azienda reclami'],
  ['permesso', 'permessi ferie par conto ore'],
  ['permessi', 'permessi ferie par conto ore'],
  ['sicurezza', 'salute sicurezza rls d.lgs. 81'],
  ['elezione', 'elezioni rsu liste seggi commissione elettorale'],
  ['elezioni', 'elezioni rsu liste seggi commissione elettorale'],
  ['assenza', 'assenze malattia infortunio ferie permessi'],
  ['turno', 'orario turni straordinario maggiorazioni'],
]);

async function practiceText() {
  const documentText = await linkedDocumentsText();
  return [
    document.querySelector('[name="title"]')?.value,
    document.querySelector('[name="summary"]')?.value,
    document.querySelector('#practiceTimeline')?.textContent,
    documentText,
  ].filter(Boolean).join(' ').toLowerCase();
}

async function linkedDocumentsText() {
  const ids = [...document.querySelectorAll('[data-document-id]')]
    .map(button => button.dataset.documentId)
    .filter(Boolean)
    .slice(0, 5);
  if (!ids.length) return '';
  const parts = [];
  for (const id of ids) {
    try {
      ccnlAiHint.textContent = `Lettura documento collegato ${id}...`;
      parts.push(await pdfText(id));
    } catch {
      parts.push(document.querySelector(`[data-document-id="${id}"]`)?.closest('.timeline-item')?.textContent || '');
    }
  }
  return parts.join(' ').slice(0, 18000);
}

async function pdfText(id) {
  const pdfjs = await import('./vendor/pdfjs/pdf.mjs');
  pdfjs.GlobalWorkerOptions.workerSrc = './vendor/pdfjs/pdf.worker.mjs';
  const pdf = await pdfjs.getDocument({
    url: `../api/v1/documents/${id}/preview?token=${encodeURIComponent(sessionStorage.getItem('token') || localStorage.getItem('token') || '')}`,
    cMapUrl: './vendor/pdfjs/cmaps/',
    cMapPacked: true,
    standardFontDataUrl: './vendor/pdfjs/standard_fonts/',
    wasmUrl: './vendor/pdfjs/wasm/',
  }).promise;
  const pages = Math.min(pdf.numPages, 8);
  const rows = [];
  for (let pageNumber = 1; pageNumber <= pages; pageNumber += 1) {
    const page = await pdf.getPage(pageNumber);
    const content = await page.getTextContent();
    rows.push(content.items.map(item => item.str).join(' '));
  }
  return rows.join(' ');
}

function normalizeText(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9\s]/gi, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function buildAiQuery(text) {
  const normalized = normalizeText(text);
  for (const [needle, query] of aiSynonyms.entries()) {
    if (normalized.includes(needle)) return query;
  }
  const found = normativeTerms.filter(term => normalized.includes(normalizeText(term)));
  if (found.length) return found.slice(0, 5).join(' ');
  return normalized.split(' ').filter(word => word.length > 4).slice(0, 6).join(' ');
}

ccnlAiButton?.addEventListener('click', async () => {
  ccnlAiHint.textContent = 'Analisi pratica e documenti collegati...';
  const query = buildAiQuery(await practiceText());
  if (!query) {
    ccnlAiHint.textContent = 'Nessun contenuto pratica da analizzare.';
    return;
  }
  window.MyRsuNormativaSearch.input.value = query;
  ccnlAiHint.textContent = `Query AI: ${query}`;
  await window.MyRsuNormativaSearch.search();
});
