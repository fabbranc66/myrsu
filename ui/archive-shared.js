import { appRoot, archiveRequest, itemUrl, textElement, statusLabel } from './archive-client.js?v=20261025-transfer-1';

async function load(section) {
  const type = section.dataset.archiveShared;
  const output = section.querySelector('[data-archive-json]');
  const message = section.querySelector('[data-archive-message]');
  try {
    await window.MyRsuConfiguration?.ready;
    const me = await archiveRequest('/me');
    const admin = me.roles.includes('admin');
    const manage = section.querySelector('[data-archive-manage]');
    manage.hidden = !admin;
    manage.href = `${appRoot}/ui/archive.html?type=${type === 'protocol' ? 'document' : type}`;
    const data = await archiveRequest(type === 'protocol' ? '/archive/protocols' : `/archive/shared?type=${type}`, {}, output);
    const list = section.querySelector('[data-archive-list]');
    list.replaceChildren();
    section.querySelector('[data-archive-count]').textContent = String(data.items.length);
    if (!data.items.length) { message.textContent = 'Nessun contenuto condiviso da altri archivi.'; section.hidden = !admin; return; }
    const table = document.createElement('table');
    table.className = 'mobile-card-table archive-table';
    const headings = type === 'protocol' ? ['Protocollo originale', 'Origine', 'Oggetto', 'Stato', ''] : ['Codice / nome', 'Origine', 'Stato', ''];
    const head = document.createElement('thead');
    const headingRow = document.createElement('tr');
    headings.forEach((title) => headingRow.append(textElement('th', title)));
    head.append(headingRow); table.append(head);
    const body = document.createElement('tbody');
    data.items.forEach((item) => {
      const row = document.createElement('tr');
      const values = type === 'protocol'
        ? [item.protocol_number, item.source_configuration_name, item.subject, item.canceled_at ? 'Annullato nell’origine' : 'Registrato nell’origine']
        : [item.code ? `${item.code} · ${item.name}` : item.name, item.source_configuration_name, statusLabel(item.status)];
      values.forEach((value, index) => { const cell = textElement('td', value); cell.dataset.label = headings[index]; row.append(cell); });
      const actions = document.createElement('td');
      const link = textElement('a', 'Consulta'); link.href = itemUrl(item);
      actions.append(link); row.append(actions); body.append(row);
    });
    table.append(body); list.append(table);
    message.textContent = type === 'protocol' ? 'Riferimenti in sola consultazione: non fanno parte della numerazione attuale.' : 'Contenuti originali condivisi, senza copie. Le modifiche si effettuano nella configurazione d’origine.';
  } catch (error) { message.textContent = error.message; }
}
document.querySelectorAll('[data-archive-shared]').forEach(load);
