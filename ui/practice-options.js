const MyRsuPracticeOptions = (() => {
  const statuses = [
    ['new', 'nuova', 10], ['under_review', 'in istruttoria', 30], ['assigned', 'assegnata', 45],
    ['company_discussion', 'in confronto azienda', 65], ['awaiting_response', 'in attesa risposta', 80],
    ['suspended', 'sospesa', 55], ['resolved', 'risolta', 100], ['closed', 'chiusa', 100],
    ['archived', 'archiviata', 100], ['closed_positive', 'chiusa positiva', 100],
    ['closed_negative', 'chiusa negativa', 100],
  ];
  const types = [['collective', 'collettiva RSU'], ['personal', 'personale'], ['personal_restricted', 'personale riservata']];
  const priorities = [['low', 'bassa'], ['medium', 'media'], ['high', 'alta'], ['urgent', 'urgente']];
  const sources = [['manual', 'manuale'], ['anonymous_report', 'segnalazione anonima'], ['mail', 'mail'], ['member', 'membro'], ['delegate', 'delegato'], ['document', 'documento'], ['communication', 'comunicato'], ['meeting', 'incontro']];
  const visibilities = [['operators', 'solo operatori'], ['authorized', 'interni autorizzati'], ['public_summary', 'sintesi pubblicabile']];

  function options(items, selected = '') {
    return items.map(([value, label]) => `<option value="${value}"${value === selected ? ' selected' : ''}>${label}</option>`).join('');
  }

  function label(items, value) {
    return items.find(([key]) => key === value)?.[1] || value || '-';
  }

  function progress(status) {
    return statuses.find(([key]) => key === status)?.[2] || 0;
  }

  return { statuses, types, priorities, sources, visibilities, options, label, progress };
})();
