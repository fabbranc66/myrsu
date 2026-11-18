import { appRoot, archiveRequest, itemUrl, textElement, statusLabel } from './archive-client.js?v=20261025-transfer-1';

const form = document.querySelector('#archiveSearch');
const message = document.querySelector('#archiveMessage');
const output = document.querySelector('#archiveJson');
const rows = document.querySelector('#archiveRows');
let page = 1;
let loading = false;
let moving = false;
const initialType = new URLSearchParams(window.location.search).get('type');
if (['practice', 'document'].includes(initialType)) form.elements.type.value = initialType;
async function load() {
  if (loading) return;
  loading = true;
  try {
    const params = new URLSearchParams(new FormData(form)); params.set('page', String(page));
    const data = await archiveRequest(`/admin/archive?${params}`, {}, output);
    document.querySelector('#archivePanel').hidden = false;
    document.querySelector('#archiveTarget').textContent = data.target.name;
    document.querySelector('#archiveWarnings').textContent = data.warnings.join(' ');
    const selected = form.elements.source.value;
    form.elements.source.replaceChildren();
    const all = textElement('option', 'Tutti gli archivi'); all.value = '0'; form.elements.source.append(all);
    data.configurations.forEach((source) => { const option = textElement('option', source.name); option.value = String(source.id); form.elements.source.append(option); });
    form.elements.source.value = selected;
    rows.replaceChildren();
    data.items.forEach((item) => {
      const row = document.createElement('tr');
      const flagCell = document.createElement('td');
      if (item.is_current) flagCell.textContent = 'Già in questa configurazione';
      else {
        const label = document.createElement('label'); label.className = 'archive-flag';
        const checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.checked = item.explicitly_shared;
        checkbox.setAttribute('aria-label', `Rendi disponibile ${item.name} nella configurazione ${data.target.name}`);
        checkbox.addEventListener('change', async () => {
          const enabled = checkbox.checked; checkbox.disabled = true;
          try {
            await archiveRequest('/admin/archive/shares', { method: 'POST', body: JSON.stringify({ source_configuration_id: item.source_configuration_id, entity_type: item.type, entity_id: item.id, enabled }) }, output);
            message.textContent = enabled ? 'Condivisione attivata. Nessun contenuto copiato.' : 'Condivisione diretta rimossa. Il contenuto originale non è stato modificato.';
            await load();
          } catch (error) { checkbox.checked = !enabled; message.textContent = error.message; }
          finally { checkbox.disabled = false; }
        });
        label.append(checkbox, textElement('span', 'Condividi qui')); flagCell.append(label);
        if (item.via_practice) flagCell.append(textElement('small', 'Disponibile anche tramite una pratica condivisa.'));
      }
      row.append(flagCell, textElement('td', item.code ? `${item.code} · ${item.name}` : item.name), textElement('td', item.source_configuration_name), textElement('td', statusLabel(item.status)));
      const actions = document.createElement('td'); const link = textElement('a', 'Consulta'); link.href = itemUrl(item); actions.append(link);
      if (item.type === 'practice' && !item.is_current) {
        const move = textElement('button', 'Sposta qui');
        move.type = 'button'; move.className = 'archive-move-button';
        move.setAttribute('aria-label', `Sposta ${item.code} nella configurazione ${data.target.name}`);
        move.addEventListener('click', () => movePractice(item, move));
        actions.append(move);
      }
      row.append(actions); rows.append(row);
    });
    if (!data.items.length) { const row = document.createElement('tr'); const cell = textElement('td', 'Nessun contenuto trovato.'); cell.colSpan = 5; row.append(cell); rows.append(row); }
    const pages = Math.max(1, Math.ceil(data.total / data.limit));
    document.querySelector('#archivePage').textContent = `Pagina ${page} di ${pages} · ${data.total} contenuti`;
    document.querySelector('#archivePrevious').disabled = page <= 1;
    document.querySelector('#archiveNext').disabled = page >= pages;
  } catch (error) { message.textContent = error.message; }
  finally { loading = false; }
}
async function movePractice(item, button) {
  if (moving) return;
  moving = true; button.disabled = true;
  try {
    const path = `/admin/archive/practices/${item.source_configuration_id}/${item.id}/transfer`;
    const preview = await archiveRequest(path, {}, output);
    const confirmation = prompt(`Spostare ${preview.practice.code} da “${preview.source.name}” a “${preview.target.name}”?\n\n${preview.notice}\n\nSarà creato un backup preventivo. Se il codice è occupato, ne sarà assegnato uno nuovo.\n\nScrivi ${preview.practice.code} per confermare:`);
    if (confirmation === null) return;
    message.textContent = 'Backup preventivo e spostamento in corso…';
    const result = await archiveRequest(path, { method: 'POST', body: JSON.stringify({ confirm: confirmation }) }, output);
    await load();
    message.textContent = `Pratica ${result.code} spostata in ${preview.target.name}. Documenti e fascicolo originale restano collegati. `;
    const link = textElement('a', 'Apri pratica');
    link.href = `${appRoot}/ui/practice-view.html?id=${result.practice_id}`;
    message.append(link);
  } catch (error) { message.textContent = error.message; }
  finally { moving = false; button.disabled = false; }
}
form.addEventListener('submit', (event) => { event.preventDefault(); page = 1; load(); });
document.querySelector('#archivePrevious').addEventListener('click', () => { page = Math.max(1, page - 1); load(); });
document.querySelector('#archiveNext').addEventListener('click', () => { page += 1; load(); });
window.MyRsuConfiguration.ready.then(load).catch((error) => { message.textContent = error.message; });
