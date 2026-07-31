const ccnlQuestionToggle = document.querySelector('#ccnlQuestionToggle');
const ccnlQuestionForm = document.querySelector('#ccnlQuestionForm');
const ccnlQuestionAnswer = document.querySelector('#ccnlQuestionAnswer');

async function ccnlQuestionIsAdmin() {
  try {
    const data = await api('/me');
    return (data.roles || []).includes('admin');
  } catch (_) {
    return false;
  }
}

async function ccnlQuestionInit() {
  if (!await ccnlQuestionIsAdmin()) return;
  ccnlQuestionToggle.classList.remove('hidden');
}

function ccnlQuestionScore(section, question) {
  const search = window.MyRsuNormativaSearch;
  const terms = ccnlQuestionTerms(question);
  return terms.reduce((score, term) => score + search.countMatches(`${section.title}\n${section.text}`, term), 0);
}

function ccnlQuestionRank(block, section, question, score) {
  const normalized = question.toLowerCase();
  const title = `${block[0]} ${block[1]} ${section.title}`.toLowerCase();
  const isIsoQuery = /\b(iso|45001|14001)\b/i.test(normalized);
  const isoBoost = isIsoQuery && ['S15', 'S16', 'S17'].includes(block[0]) ? 10000 : 0;
  const exactBoost = title.includes(normalized) ? 5000 : 0;
  const agreementPenalty = isIsoQuery && ['S13', 'S14', 'S01'].includes(block[0]) ? -5000 : 0;
  return isoBoost + exactBoost + score + agreementPenalty;
}

function ccnlQuestionScope(question) {
  return document.querySelector('#ccnlScopeSelect').value;
}

function ccnlQuestionTerms(question) {
  return window.MyRsuNormativaVocabulary?.terms(ccnlQuestionScope(question), question, { remember: true }) || window.MyRsuNormativaSearch.queryTerms(question);
}

function ccnlQuestionSummary(results, question) {
  if (!results.length) return 'Nessun riferimento normativo trovato nei testi disponibili.';
  const terms = ccnlQuestionTerms(question);
  const mainTerms = terms.slice(0, 8).join(', ');
  return `Ambito selezionato: ${ccnlQuestionScope(question)}. Ho cercato questi concetti: ${mainTerms}. Le sezioni sotto sono i riferimenti più pertinenti.`;
}

async function ccnlQuestionSearch(event) {
  event.preventDefault();
  const question = new FormData(ccnlQuestionForm).get('question').toString().trim();
  if (question.length < 3) return;

  await window.MyRsuNormativaVocabulary?.load('../docs/normativa_vocabulary.json');
  const search = window.MyRsuNormativaSearch;
  const forcedScope = ccnlQuestionScope(question);
  ccnlQuestionAnswer.classList.remove('hidden');
  ccnlQuestionAnswer.innerHTML = '<p>Ricerca Codex in corso...</p>';
  const found = [];
  const blocks = (search.blocksForScope ? search.blocksForScope(forcedScope) : search.activeBlocks()).filter(ccnlQuestionSearchableBlock);
  try {
    for (const block of blocks) {
      const sections = search.split(await search.fetchBlock(block), block);
      sections.forEach(section => {
        const score = ccnlQuestionScore(section, question);
        if (score > 0) found.push({ block, section, score, rank: ccnlQuestionRank(block, section, question, score) });
      });
    }
  } catch (error) {
    ccnlQuestionAnswer.classList.remove('hidden');
    ccnlQuestionAnswer.innerHTML = `<p>Ricerca non completata: ${search.escape(error.message || 'errore lettura normativa')}</p>`;
    return;
  }
  found.sort((a, b) => (b.rank - a.rank) || (b.score - a.score));
  const top = found.slice(0, 4);
  ccnlQuestionAnswer.classList.remove('hidden');
  ccnlQuestionAnswer.innerHTML = `
    <h3>Risposta Codex locale</h3>
    <p>${search.escape(ccnlQuestionSummary(top, question))}</p>
    <div class="ccnl-results">
      ${top.map(item => `<button class="ccnl-result-card" type="button" data-block="${item.block[0]}" data-section="${item.section.index}" data-bookmark="${search.escape(question)}"><strong>${search.escape(item.section.title)}</strong><span>${search.escape(item.block[1])} &middot; ${item.score}</span></button>`).join('') || '<p>Nessun risultato.</p>'}
    </div>
  `;
}

function ccnlQuestionSearchableBlock(block) {
  if (['99', 'S01'].includes(block[0])) return false;
  if (window.matchMedia('(max-width: 760px)').matches && ['S13', 'S14'].includes(block[0])) return false;
  return true;
}

ccnlQuestionToggle?.addEventListener('click', () => {
  ccnlQuestionForm.classList.toggle('hidden');
  if (!ccnlQuestionForm.classList.contains('hidden')) {
    ccnlQuestionForm.scrollIntoView({ block: 'nearest' });
    ccnlQuestionForm.querySelector('textarea')?.focus();
  }
});
ccnlQuestionForm?.addEventListener('submit', ccnlQuestionSearch);
ccnlQuestionAnswer?.addEventListener('click', event => {
  const card = event.target.closest('.ccnl-result-card');
  if (card) window.MyRsuNormativaSearch.openSection(card.dataset.block, Number(card.dataset.section), card.dataset.bookmark || '');
});

ccnlQuestionInit();
