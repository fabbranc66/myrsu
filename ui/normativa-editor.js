const apiBase = '../api/v1';
const token = sessionStorage.getItem('token') || localStorage.getItem('token');
const form = document.querySelector('#normativaForm');
const loadButton = document.querySelector('#loadNormativa');
const message = document.querySelector('#message');
const jsonOutput = document.querySelector('#jsonOutput');

const normativaFiles = [
  ['../docs/representation_work/clean/01_TESTO_COORDINATO_OPERATIVO_RAPPRESENTANZA_LUGLIO_2026.md', 'T.U. Rappresentanza - testo coordinato'],
  ['../docs/representation_work/clean/02_ALLEGATO_3_RSU_METALMECCANICI_2017.md', 'T.U. Rappresentanza - Allegato 3 originale'],
  ['../docs/representation_work/clean/00_INDICE_RAPPRESENTANZA_LUGLIO_2026.md', 'T.U. Rappresentanza - indice'],
  ['../docs/ccnl_work/clean/ccnl_2021_testo_completo_ricercabile.md', 'CCNL 2021 - testo completo'],
  ['../docs/ccnl_work/clean/18_blocco_diritti_sindacali_assemblea_permessi_rsu_luglio_2026.md', 'CCNL - diritti sindacali e permessi RSU'],
  ['../docs/ccnl_work/clean/16_blocco_sospensioni_cig_provvedimenti_disciplinari_luglio_2026.md', 'CCNL - sospensioni, CIG, disciplina'],
  ['../docs/ccnl_work/clean/09_blocco_ferie_permessi_specifici_luglio_2026.md', 'CCNL - ferie e permessi'],
  ['../docs/safety_work/clean/00_INDICE_SICUREZZA_81_08.md', 'Sicurezza 81/08 - indice'],
  ['../docs/safety_work/clean/03_BLOCCO_OPERATIVO_RSU_RLS_SICUREZZA_81_08.md', 'Sicurezza 81/08 - blocco operativo RSU/RLS'],
  ['../docs/safety_work/clean/04_MAPPA_ARTICOLI_TEMI_SICUREZZA_81_08.md', 'Sicurezza 81/08 - mappa articoli e temi'],
  ['../docs/safety_work/clean/05_BLOCCO_RLS_CONSULTAZIONE_ACCESSO_DOCUMENTI.md', 'Sicurezza 81/08 - RLS, consultazione e accesso documenti'],
  ['../docs/safety_work/clean/06_BLOCCO_DVR_VALUTAZIONE_RISCHI.md', 'Sicurezza 81/08 - DVR e valutazione rischi'],
  ['../docs/safety_work/clean/07_BLOCCO_FORMAZIONE_INFORMAZIONE_ADDESTRAMENTO.md', 'Sicurezza 81/08 - formazione, informazione e addestramento'],
  ['../docs/safety_work/clean/08_BLOCCO_PREPOSTO_OBBLIGHI_RESPONSABILITA.md', 'Sicurezza 81/08 - preposto, obblighi e responsabilità'],
  ['../docs/safety_work/clean/09_BLOCCO_APPALTI_DUVRI_INTERFERENZE.md', 'Sicurezza 81/08 - appalti, DUVRI e interferenze'],
  ['../docs/safety_work/clean/10_BLOCCO_SORVEGLIANZA_SANITARIA_MEDICO_COMPETENTE.md', 'Sicurezza 81/08 - sorveglianza sanitaria'],
  ['../docs/safety_work/clean/11_BLOCCO_VIGILANZA_SOSPENSIONE_ORGANI_CONTROLLO.md', 'Sicurezza 81/08 - vigilanza e sospensione'],
  ['../docs/safety_work/clean/12_BLOCCO_EMERGENZE_ANTINCENDIO_PRIMO_SOCCORSO.md', 'Sicurezza 81/08 - emergenze, antincendio e primo soccorso'],
  ['../docs/safety_work/clean/14_CONTENUTI_COLLEGATI_81_08_ACCORDI_INTERPELLI_CIRCOLARI.md', 'Sicurezza 81/08 - contenuti collegati'],
  ['../docs/safety_work/clean/15_ISO_45001_SICUREZZA_LAVORO.md', 'Sicurezza 81/08 - ISO 45001'],
  ['../docs/safety_work/clean/16_ISO_14001_AMBIENTE_RSU.md', 'Sicurezza 81/08 - ISO 14001'],
  ['../docs/safety_work/clean/17_MAPPA_ISO_81_08.md', 'Sicurezza 81/08 - mappa ISO 81/08'],
  ['../docs/safety_work/clean/01_D_LGS_81_2008_TESTO_VIGENTE_PULITO.md', 'Sicurezza 81/08 - testo vigente pulito'],
];

if (!token) window.location.href = 'login.html';

form.source_path.innerHTML = normativaFiles.map(([path, label]) => `<option value="${escapeHtml(path)}">${escapeHtml(label)}</option>`).join('');

async function api(path, options = {}) {
  const headers = { ...(options.headers || {}), Authorization: `Bearer ${token}` };
  const response = await fetch(`${apiBase}${path}`, { ...options, headers });
  const payload = await response.json();
  jsonOutput.textContent = JSON.stringify(payload, null, 2);
  if (!response.ok) throw new Error(payload.error?.message || 'Operazione fallita');
  return payload.data;
}

async function loadFile() {
  const sourcePath = form.source_path.value;
  const data = await api(`/normativa/file?source_path=${encodeURIComponent(sourcePath)}`);
  form.content.value = data.content;
  message.textContent = 'Testo caricato';
}

loadButton.addEventListener('click', () => loadFile().catch(error => { message.textContent = error.message; }));

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  await api('/normativa/file', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.fromEntries(new FormData(form).entries())),
  });
  message.textContent = 'Testo normativa salvato';
});

function escapeHtml(value) {
  return String(value || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

loadFile().catch(error => { message.textContent = error.message; });
