import { appRoot, token, archiveRequest, itemUrl, textElement, statusLabel } from './archive-client.js?v=20261025-transfer-1';
import { renderArchiveHistory } from './practice-archive-actions.js?v=20261025-1';

const params = new URLSearchParams(window.location.search);
const source = params.get('source');
const type = params.get('type');
const id = params.get('id');
const message = document.querySelector('#archiveItemMessage');
const output = document.querySelector('#archiveItemJson');
const filePath = `/archive/sources/${encodeURIComponent(source)}/documents/${encodeURIComponent(id)}`;
let data;
let pdfBlob;
async function verify() {
  const result = document.querySelector('#archiveVerification');
  try {
    const value = await archiveRequest(`${filePath}/verify?sig=${encodeURIComponent(data.document.signature || '')}`, {}, output);
    result.textContent = value.valid ? 'Originale verificato nella configurazione d’origine.' : 'Verifica originale non riuscita.';
    result.className = value.valid ? 'alert success' : 'alert danger';
  } catch (error) { result.textContent = error.message; result.className = 'alert danger'; }
}
async function loadPdf() {
  const viewer = document.querySelector('#archivePdfViewer');
  viewer.hidden = false; viewer.textContent = 'Caricamento PDF originale…';
  const response = await fetch(`${appRoot}/api/v1${filePath}/file`, { headers: { Authorization: `Bearer ${token()}` } });
  if (!response.ok) { const error = await response.json(); throw new Error(error.error?.message || 'PDF non disponibile.'); }
  pdfBlob = await response.blob();
  const pdfjs = await import('./vendor/pdfjs/pdf.mjs');
  pdfjs.GlobalWorkerOptions.workerSrc = './vendor/pdfjs/pdf.worker.mjs';
  const pdf = await pdfjs.getDocument({ data: await pdfBlob.arrayBuffer(), cMapUrl: './vendor/pdfjs/cmaps/', cMapPacked: true, standardFontDataUrl: './vendor/pdfjs/standard_fonts/', wasmUrl: './vendor/pdfjs/wasm/' }).promise;
  viewer.replaceChildren();
  for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber += 1) {
    const page = await pdf.getPage(pageNumber);
    const base = page.getViewport({ scale: 1 });
    const viewport = page.getViewport({ scale: Math.min(2, Math.max(260, viewer.clientWidth - 20) / base.width) });
    const wrap = document.createElement('div'); wrap.className = 'archive-pdf-page';
    wrap.style.width = `${viewport.width}px`; wrap.style.height = `${viewport.height}px`;
    const canvas = document.createElement('canvas'); canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height); wrap.append(canvas); viewer.append(wrap);
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    function addLink(rect, url) {
      const verification = url.includes('document-verify.html');
      const link = document.createElement(verification ? 'button' : 'a');
      if (verification) { link.type = 'button'; link.setAttribute('aria-label', 'Verifica originale'); link.addEventListener('click', verify); }
      else {
        const target = new URL(url, window.location.href);
        if (!['https:', 'http:', 'mailto:', 'tel:'].includes(target.protocol)) return;
        link.href = target.href; link.target = '_blank'; link.rel = 'noopener';
      }
      const [left, top, right, bottom] = viewport.convertToViewportRectangle(rect);
      link.className = 'archive-pdf-link'; link.style.left = `${Math.min(left, right)}px`; link.style.top = `${Math.min(top, bottom)}px`; link.style.width = `${Math.abs(right - left)}px`; link.style.height = `${Math.abs(bottom - top)}px`; wrap.append(link);
    }
    const annotations = await page.getAnnotations({ intent: 'display' });
    annotations.filter((annotation) => annotation.rect && (annotation.url || annotation.unsafeUrl)).forEach((annotation) => addLink(annotation.rect, annotation.url || annotation.unsafeUrl));
    addLink([502, 728, 554, 780], 'document-verify.html');
  }
}
async function load() {
  if (!['practice', 'document'].includes(type) || !/^[1-9][0-9]*$/.test(source || '') || !/^[1-9][0-9]*$/.test(id || '')) throw new Error('Riferimento archivio non valido.');
  await window.MyRsuConfiguration.ready;
  data = await archiveRequest(`/archive/sources/${source}/${type}/${id}`, {}, output);
  const me = await archiveRequest('/me');
  document.querySelector('#archiveItemPanel').hidden = false;
  document.querySelector('#archiveItemTitle').textContent = data.item.name;
  document.querySelector('#archiveItemOrigin').textContent = `Origine: ${data.item.source_configuration_name} · ${data.item.code || `Documento #${id}`}`;
  document.querySelector('#archiveItemStatus').textContent = `Stato originale: ${statusLabel(data.item.status)}`;
  const origin = document.querySelector('#archiveOpenOrigin'); origin.hidden = !me.roles.includes('admin');
  const move = document.querySelector('#archiveMovePractice');
  move.hidden = type !== 'practice' || !me.roles.includes('admin') || Number(me.configuration?.id) === Number(source);
  move.addEventListener('click', async () => {
    move.disabled = true;
    try {
      const path = `/admin/archive/practices/${source}/${id}/transfer`;
      const preview = await archiveRequest(path, {}, output);
      const confirmation = prompt(`Spostare ${preview.practice.code} da “${preview.source.name}” a “${preview.target.name}”?\n\n${preview.notice}\n\nSarà creato un backup preventivo. Se il codice è già occupato, ne sarà assegnato uno nuovo.\n\nScrivi ${preview.practice.code} per confermare:`);
      if (confirmation === null) return;
      message.textContent = 'Backup preventivo e spostamento in corso…';
      const result = await archiveRequest(path, { method: 'POST', body: JSON.stringify({ confirm: confirmation }) }, output);
      window.location.href = `${appRoot}/ui/practice-view.html?id=${result.practice_id}`;
    } catch (error) { message.textContent = error.message; }
    finally { move.disabled = false; }
  });
  origin.addEventListener('click', async () => {
    if (!confirm(`Aprire l’originale in “${data.item.source_configuration_name}”? Cambia soltanto la tua sessione. Le modifiche saranno visibili ovunque sia condiviso.`)) return;
    try {
      await archiveRequest('/admin/configurations/selection', { method: 'POST', body: JSON.stringify({ configuration_id: Number(source) }) });
      window.location.href = `${appRoot}/ui/${type === 'practice' ? 'practice-view' : 'document-edit'}.html?id=${id}`;
    } catch (error) { message.textContent = error.message; }
  });
  if (type === 'practice') {
    document.querySelector('#archiveItemSummary').textContent = data.practice.summary || '';
    const documents = document.querySelector('#archiveItemDocuments'); documents.append(textElement('h3', 'Documenti collegati disponibili'));
    data.documents.forEach((document) => { const paragraph = document.createElement('p'); const link = textElement('a', document.name); link.href = itemUrl(document); paragraph.append(link); documents.append(paragraph); });
    if (!data.documents.length) documents.append(textElement('p', 'Nessun documento collegato accessibile.'));
    const notes = document.querySelector('#archiveItemNotes'); notes.append(textElement('h3', 'Note originali'));
    data.notes.forEach((note) => { const article = document.createElement('article'); article.append(textElement('strong', `${note.author} · ${note.created_at}`), textElement('p', note.body, 'archive-item-summary')); notes.append(article); });
    renderArchiveHistory(document.querySelector('#archiveItemHistory'), data.timeline || []);
  }
  const protocols = document.querySelector('#archiveItemProtocols');
  if (data.protocols.length) {
    protocols.append(textElement('h3', 'Protocolli originali · sola consultazione'));
    data.protocols.forEach((protocol) => protocols.append(textElement('p', `${protocol.protocol_number} · ${protocol.source_configuration_name} · ${protocol.subject}${protocol.canceled_at ? ' · annullato' : ''}`)));
  }
  if (type === 'document') {
    const comments = document.querySelector('#archiveItemHistory');
    if (data.comments?.length) comments.append(textElement('h3', 'Commenti del documento originale'));
    (data.comments || []).forEach((comment) => {
      const article = document.createElement('article');
      article.append(textElement('strong', comment.subject), textElement('p', comment.message, 'archive-item-summary'));
      if (comment.reply) article.append(textElement('p', `Risposta: ${comment.reply}`, 'archive-item-summary'));
      comments.append(article);
    });
    if (data.document.conversion_status === 'ready') { document.querySelector('#archivePdfActions').hidden = false; await loadPdf(); }
  }
}
document.querySelector('#archiveVerify').addEventListener('click', verify);
document.querySelector('#archiveDownload').addEventListener('click', async () => {
  try {
    const response = await fetch(`${appRoot}/api/v1${filePath}/file?download=1`, { headers: { Authorization: `Bearer ${token()}` } });
    if (!response.ok) { const error = await response.json(); throw new Error(error.error?.message || 'Download non autorizzato.'); }
    const url = URL.createObjectURL(await response.blob()); const link = document.createElement('a'); link.href = url; link.download = data.document.original_name.replace(/\.[^.]+$/, '') + '.pdf'; document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (error) { message.textContent = error.message; }
});
load().catch((error) => { message.textContent = error.message; });
