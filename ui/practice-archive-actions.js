import { appRoot, token, archiveRequest, itemUrl, textElement } from './archive-client.js?v=20261025-transfer-1';

const pages = { document: 'document-edit.html', report: 'reports-moderation.html', comment: 'comments-moderation.html', protocol: 'protocol.html', meeting: 'union-meetings.html', assembly: 'workers-assemblies.html', call: 'calls.html', email: 'emails.html' };

export function renderArchiveHistory(container, items) {
  if (!items.length) return;
  container.append(textElement('h3', 'Fascicolo precedente collegato agli originali'));
  items.forEach((item) => {
    const article = document.createElement('article');
    article.append(textElement('strong', item.title), textElement('small', ` · ${item.archive_source_configuration_name} · ${item.event_at}`), textElement('p', item.summary, 'archive-item-summary'));
    if (item.document_id) { const link = textElement('a', 'Consulta documento originale'); link.href = itemUrl({ id: item.document_id, type: 'document', source_configuration_id: item.archive_source_configuration_id }); article.append(link); }
    item.archive_attachments.forEach((attachment) => {
      const button = textElement('button', `Scarica ${attachment.original_name}`);
      button.type = 'button'; button.dataset.archiveDownload = String(attachment.id); button.dataset.archiveSource = String(item.archive_source_configuration_id); button.dataset.archiveFilename = attachment.original_name; article.append(button);
    });
    container.append(article);
  });
}

document.addEventListener('click', async (event) => {
  const origin = event.target.closest('[data-archive-origin]');
  const download = event.target.closest('[data-archive-download]');
  if (!origin && !download) return;
  event.preventDefault();
  try {
    if (origin) {
      const page = pages[origin.dataset.archiveType];
      if (!page || !confirm('Aprire la configurazione originale? Cambia soltanto la tua sessione admin.')) return;
      const documentId = origin.dataset.archiveType === 'document' ? Number(origin.dataset.archiveId) : null;
      if (origin.dataset.archiveType === 'document' && (!Number.isSafeInteger(documentId) || documentId <= 0)) throw new Error('Documento non valido.');
      await archiveRequest('/admin/configurations/selection', { method: 'POST', body: JSON.stringify({ configuration_id: Number(origin.dataset.archiveOrigin) }) });
      window.location.href = `${appRoot}/ui/${page}${documentId ? `?id=${documentId}` : ''}`;
      return;
    }
    const response = await fetch(`${appRoot}/api/v1/archive/sources/${encodeURIComponent(download.dataset.archiveSource)}/attachments/${encodeURIComponent(download.dataset.archiveDownload)}/file`, { headers: { Authorization: `Bearer ${token()}` }, cache: 'no-store' });
    if (!response.ok) { const payload = await response.json(); throw new Error(payload.error?.message || 'Allegato non disponibile.'); }
    const url = URL.createObjectURL(await response.blob()); const link = document.createElement('a'); link.href = url; link.download = download.dataset.archiveFilename; document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) { alert(error.message); }
});
