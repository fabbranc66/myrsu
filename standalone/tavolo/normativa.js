import { api, escapeHtml, refreshRoomTimeline } from './app.js?v=20260802-room-1';

const modal = document.querySelector('#normativaModal');
const body = document.querySelector('.normativa-body');
const form = document.querySelector('#normativaSearch');
const results = document.querySelector('#normativaResults');
const reader = document.querySelector('#normativaReader');
const content = document.querySelector('#normativaContent');
const shareButton = document.querySelector('#shareNormativa');
const selectionLabel = document.querySelector('#normativaSelection');
const closeReaderButton = document.querySelector('#closeNormativaReader');
let currentUnitId = null;
let savedSelection = '';
let query = '';
let dragStartRange = null;

document.querySelector('#openNormativa').addEventListener('click', () => modal.showModal());
document.querySelector('#closeNormativa').addEventListener('click', () => modal.close());
closeReaderButton.addEventListener('click', closeReader);
document.querySelector('#clearNormativa').addEventListener('click', () => {
  form.reset();
  results.innerHTML = '';
  closeReader();
});

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  const data = new FormData(form);
  query = String(data.get('q') || '').trim();
  results.innerHTML = '<p>Ricerca in corso…</p>';
  closeReader();
  try {
    const response = await api(`/room-access/normativa/ricerca?scope=${encodeURIComponent(data.get('scope'))}&q=${encodeURIComponent(query)}`);
    results.innerHTML = response.items.map(resultCard).join('') || '<p>Nessun risultato.</p>';
  } catch (error) {
    results.innerHTML = `<p>${escapeHtml(error.message)}</p>`;
  }
});

function resultCard(item) {
  const matches = item.matches?.length ? item.matches : [{id: item.article_unit_id || item.id, excerpt: item.excerpt}];
  const title = shortText(item.section_title || 'Riferimento normativa', 120);
  return `<article class="normativa-result-card"><strong>${highlight(escapeHtml(title))}</strong><span>${escapeHtml(item.context_label || item.document_title || '')} · ${escapeHtml(item.stato_vigenza || '')} · ${item.match_count || matches.length} contenuti</span><div class="normativa-nested-results">${matches.slice(0, 8).map((match) => `<button class="normativa-nested-result" type="button" data-unit="${match.id || item.article_unit_id || item.id}"><strong>${escapeHtml(title)}</strong><small>${highlight(escapeHtml(shortText(match.excerpt || item.excerpt || '', 220)))}</small></button>`).join('')}</div></article>`;
}

results.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-unit]');
  if (!button) return;
  try {
    const unit = await api(`/room-access/normativa/unita/${button.dataset.unit}`);
    currentUnitId = Number(button.dataset.unit);
    savedSelection = '';
    const title = unit.rubrica || unit.etichetta || 'Riferimento normativa';
    document.querySelector('#normativaTitle').textContent = title;
    document.querySelector('#normativaHierarchy').textContent = unit.hierarchy_label || unit.document_title || '';
    content.innerHTML = highlight(renderText(bodyText(unit.testo || '', title)));
    selectionLabel.textContent = '0 caratteri';
    selectionLabel.hidden = false;
    closeReaderButton.hidden = false;
    reader.classList.remove('hidden');
    body.classList.add('reading');
    shareButton.hidden = false;
    reader.scrollIntoView({block: 'start'});
  } catch (error) {
    results.insertAdjacentHTML('afterbegin', `<p>${escapeHtml(error.message)}</p>`);
  }
});

function rememberSelection() {
  const selected = selectedText();
  if (!selected) return;
  savedSelection = selected;
  selectionLabel.textContent = `${selected.length} caratteri`;
}

document.addEventListener('selectionchange', rememberSelection);
document.addEventListener('mouseup', () => setTimeout(rememberSelection, 0));
document.addEventListener('pointerup', () => setTimeout(rememberSelection, 0));
document.addEventListener('touchend', () => setTimeout(rememberSelection, 100));
content.addEventListener('pointerdown', startCustomSelection);
content.addEventListener('pointerup', endCustomSelection);
content.addEventListener('touchstart', startCustomSelection);
content.addEventListener('touchend', endCustomSelection);

shareButton.addEventListener('pointerdown', prepareShare);
shareButton.addEventListener('mousedown', prepareShare);
shareButton.addEventListener('touchstart', prepareShare);
shareButton.addEventListener('click', async () => {
  if (!currentUnitId) return;
  rememberSelection();
  shareButton.disabled = true;
  try {
    await api(`/room-access/normativa/unita/${currentUnitId}/share`, {method: 'POST', body: JSON.stringify({selection: savedSelection})});
    await refreshRoomTimeline();
    modal.close();
  } catch (error) {
    window.alert(error.message);
  } finally {
    shareButton.disabled = false;
  }
});

function selectedText() {
  if (!modal.open) return '';
  const selection = window.getSelection();
  if (!selection || selection.isCollapsed || selection.rangeCount === 0) return '';
  const range = selection.getRangeAt(0).cloneRange();
  const contentRange = document.createRange();
  contentRange.selectNodeContents(content);
  if (!range.intersectsNode(content)) return '';
  if (range.compareBoundaryPoints(Range.START_TO_START, contentRange) < 0) {
    range.setStart(contentRange.startContainer, contentRange.startOffset);
  }
  if (range.compareBoundaryPoints(Range.END_TO_END, contentRange) > 0) {
    range.setEnd(contentRange.endContainer, contentRange.endOffset);
  }
  return range.toString().trim();
}

function prepareShare() {
  rememberSelection();
}

function startCustomSelection(event) {
  dragStartRange = rangeFromPoint(event);
}

function endCustomSelection(event) {
  if (!dragStartRange) return;
  const endRange = rangeFromPoint(event);
  if (!endRange) return;
  const range = document.createRange();
  try {
    range.setStart(dragStartRange.startContainer, dragStartRange.startOffset);
    range.setEnd(endRange.startContainer, endRange.startOffset);
  } catch (_) {
    dragStartRange = null;
    return;
  }
  if (range.collapsed) {
    range.setStart(endRange.startContainer, endRange.startOffset);
    range.setEnd(dragStartRange.startContainer, dragStartRange.startOffset);
  }
  const selected = range.toString().trim();
  if (selected) {
    savedSelection = selected;
    selectionLabel.textContent = `${selected.length} caratteri`;
  }
  dragStartRange = null;
}

function rangeFromPoint(event) {
  const point = event.changedTouches ? event.changedTouches[0] : event;
  if (document.caretRangeFromPoint) return document.caretRangeFromPoint(point.clientX, point.clientY);
  if (!document.caretPositionFromPoint) return null;
  const position = document.caretPositionFromPoint(point.clientX, point.clientY);
  if (!position) return null;
  const range = document.createRange();
  range.setStart(position.offsetNode, position.offset);
  range.collapse(true);
  return range;
}

function closeReader() {
  reader.classList.add('hidden');
  body.classList.remove('reading');
  shareButton.hidden = true;
  selectionLabel.hidden = true;
  closeReaderButton.hidden = true;
  currentUnitId = null;
  savedSelection = '';
}

function renderText(text) {
  const lines = String(text).split('\n');
  const html = [];
  for (let index = 0; index < lines.length; index++) {
    if (!lines[index].trim().startsWith('|')) {
      if (lines[index].trim()) html.push(`<p>${escapeHtml(lines[index].trim())}</p>`);
      continue;
    }
    const rows = [];
    while (index < lines.length && lines[index].trim().startsWith('|')) {
      rows.push(lines[index].trim().split('|').slice(1, -1).map((cell) => cell.trim()));
      index++;
    }
    index--;
    const dataRows = rows.filter((row) => !row.every((cell) => /^:?-+:?$/.test(cell)));
    const header = dataRows.shift() || [];
    html.push(`<div class="normativa-data-table"><table><thead><tr>${header.map((cell) => `<th>${escapeHtml(cell)}</th>`).join('')}</tr></thead><tbody>${dataRows.map((row) => `<tr>${row.map((cell) => `<td>${escapeHtml(cell)}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`);
  }
  return html.join('');
}

function bodyText(text, title) {
  const lines = String(text).split('\n');
  const firstContent = lines.findIndex((line) => line.trim());
  if (firstContent >= 0) {
    const heading = lines[firstContent].trim().replace(/^#{1,6}\s*/, '').trim().toLowerCase();
    if (heading === String(title).trim().toLowerCase()) lines.splice(firstContent, 1);
  }
  return lines.join('\n').trim();
}

function highlight(html) {
  return query.split(/\s+/).filter((term) => term.length > 2).reduce((value, term) => value.replace(new RegExp(`(${escapeRegex(term)})`, 'giu'), '<mark>$1</mark>'), html);
}

function shortText(value, limit) {
  const text = String(value || '').replace(/\s+/g, ' ').trim();
  return text.length > limit ? `${text.slice(0, limit - 1)}…` : text;
}

function escapeRegex(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
