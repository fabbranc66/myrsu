const MyRsuNormativaVocabulary = (() => {
  const storageKey = 'myrsu_normativa_user_vocabulary';
  const scopeStorageKey = 'myrsu_normativa_scope_memory';
  let data = { entries: [] };
  let loading = null;

  function normalize(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9\s/.-]/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function unique(values) {
    return [...new Set(values.map(item => String(item || '').trim()).filter(item => {
      const normalized = normalize(item);
      if (normalized.includes(' ')) return item.length > 1;
      return item.length > 1 && !isStopWord(normalized);
    }))];
  }

  function load(path = '../docs/normativa_vocabulary.json') {
    if (!loading) {
      loading = fetch(`${path}?v=20260731-vocabulary-1`, { cache: 'no-store' })
        .then(response => response.ok ? response.json() : { entries: [] })
        .then(payload => { data = payload; return data; })
        .catch(() => data);
    }
    return loading;
  }

  function userEntries() {
    try {
      return JSON.parse(localStorage.getItem(storageKey) || '[]');
    } catch (_) {
      return [];
    }
  }

  function saveUserEntries(entries) {
    localStorage.setItem(storageKey, JSON.stringify(entries.slice(-300)));
  }

  function reset() {
    localStorage.removeItem(storageKey);
    localStorage.removeItem(scopeStorageKey);
  }

  function scopeMemory() {
    try {
      return JSON.parse(localStorage.getItem(scopeStorageKey) || '{}');
    } catch (_) {
      return {};
    }
  }

  function rememberScope(query, scope) {
    const key = normalize(query);
    if (key.length < 3 || !scope) return;
    const memory = scopeMemory();
    memory[key] = scope;
    localStorage.setItem(scopeStorageKey, JSON.stringify(memory));
  }

  function classifyScope(query, fallback = 'all') {
    const normalized = normalize(query);
    const memory = scopeMemory();
    if (memory[normalized]) return memory[normalized];
    const rules = [
      ['safety', ['iso', '45001', '14001', 'atex', 'dvr', 'duvri', 'rls', 'rspp', 'aspp', 'preposto', 'infortunio', 'malattia professionale', 'sorveglianza sanitaria', 'medico competente', 'sicurezza', 'rischio', 'near miss', 'audit sicurezza']],
      ['representation', ['elezioni', 'elezione', 'seggi', 'seggio', 'scrutinio', 'commissione elettorale', 'liste', 'candidati', 'testo unico rappresentanza', 'rappresentativita', 'rsu']],
      ['ccnl', ['ferie', 'par', 'rol', 'pdr', 'premio', 'preavviso', 'licenziamento', 'dimissioni', 'livello', 'inquadramento', 'mansione', 'turno', 'straordinario', 'banca ore', 'malattia', 'comporto', 'permesso sindacale', 'ccnl']],
    ];
    const scores = rules.map(([scopeName, terms]) => [
      scopeName,
      terms.reduce((score, term) => score + (normalized.includes(normalize(term)) ? 1 : 0), 0),
    ]).filter(([, score]) => score > 0).sort((a, b) => b[1] - a[1]);
    return scores[0]?.[0] || fallback;
  }

  function remember(scope, query) {
    const words = normalize(query).split(' ').filter(word => word.length > 2 && !isStopWord(word));
    if (!words.length) return;
    const entries = userEntries();
    const canonical = words.join(' ');
    if (entries.some(entry => entry.scope === scope && entry.canonical === canonical)) return;
    entries.push({ scope, canonical, variants: words, category: 'ricerca utente', definition: 'Termine inserito in ricerca normativa', synonyms: words, links: [] });
    saveUserEntries(entries);
  }

  function terms(scope, query, options = {}) {
    if (options.remember) remember(scope, query);
    const normalized = normalize(query);
    const base = unique([query, ...normalized.split(' ').filter(word => word.length > 2 && !isStopWord(word))]);
    const entries = [...(data.entries || []), ...userEntries()];
    const matched = entries.filter(entry => isAllowedCategory(scope, normalized, entry)).filter(entry => {
      const values = [entry.canonical, ...(entry.variants || []), ...(entry.synonyms || []), ...(entry.links || [])].map(normalize);
      return values.some(value => value && (normalized.includes(value) || value.includes(normalized) || base.some(term => value.includes(normalize(term)))));
    });
    return unique([...base, ...matched.flatMap(entry => [entry.canonical, ...(entry.variants || []), ...(entry.synonyms || []), ...(entry.links || [])])]);
  }

  function isAllowedCategory(scope, normalizedQuery, entry) {
    const category = normalize(entry.category);
    if (normalizedQuery.includes('iso') || normalizedQuery.includes('45001') || normalizedQuery.includes('14001')) {
      return ['salute e sicurezza', 'rischi specifici', 'gestione delle pratiche in myrsu'].includes(category);
    }
    if (scope === 'safety') {
      return ['salute e sicurezza', 'rischi specifici', 'infortuni e malattie professionali', 'gestione delle pratiche in myrsu'].includes(category);
    }
    return true;
  }

  function isStopWord(word) {
    return [
      'una', 'uno', 'del', 'della', 'dello', 'dei', 'degli', 'delle', 'nel', 'nella', 'nello', 'nei', 'negli', 'nelle',
      'con', 'per', 'tra', 'fra', 'che', 'chi', 'cui', 'non', 'sono', 'come', 'cosa', 'quando', 'dove',
      'lavoratore', 'lavoratori', 'azienda', 'dipendente', 'dipendenti'
    ].includes(word);
  }

  return { load, terms, remember, normalize, reset, classifyScope, rememberScope };
})();

window.MyRsuNormativaVocabulary = MyRsuNormativaVocabulary;
