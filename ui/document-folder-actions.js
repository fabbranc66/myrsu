import { folderTrail } from './document-archive-model.js?v=20261024-practice-permissions-1';

export function createDocumentFolderActions({ element, listing, api, reload, message, archive }) {
  const dialog = document.querySelector('#documentFolderDialog');
  const form = document.querySelector('#documentFolderForm');
  const title = document.querySelector('#documentFolderTitle');
  const context = document.querySelector('#documentFolderContext');
  const nameField = document.querySelector('#documentFolderNameField');
  const destinationField = document.querySelector('#documentFolderDestinationField');
  const submit = form.querySelector('button[type="submit"]');
  let data = { folders: [], placements: [] };
  let documents = [];
  let mode = 'create';
  let parentId = null;
  let documentId = null;
  let draggedId = null;
  let highlighted = null;
  let busy = false;

  function escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
  }

  function canMove(identifier) {
    if (!data.can_manage || !documents.some((item) => Number(item.id) === Number(identifier))) return false;
    const placement = data.placements.find((item) => Number(item.document_id) === Number(identifier));
    return !placement || Boolean(data.folders.find((folder) => Number(folder.id) === Number(placement.folder_id))?.can_manage);
  }

  function folderPath(identifier) {
    return ['Archivio documenti', ...folderTrail(data.folders, identifier).map((folder) => folder.name)].join(' / ');
  }

  function jsonOptions(body) {
    return { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
  }

  async function move(identifier, destination) {
    if (busy || !canMove(identifier)) return;
    if (destination !== null && !data.folders.some((folder) => Number(folder.id) === destination && folder.can_manage && !folder.system_key)) {
      throw new Error('Cartella di destinazione non autorizzata.');
    }
    const current = data.placements.find((item) => Number(item.document_id) === identifier);
    if ((current ? Number(current.folder_id) : null) === destination) {
      message.textContent = 'Il documento si trova già in questa posizione.';
      return;
    }
    busy = true;
    try {
      await api(`/documents/${identifier}/folder`, { ...jsonOptions({ folder_id: destination }), method: 'PATCH' });
      await reload();
      message.textContent = 'Destinazione aggiornata. File originale e collegamenti alle pratiche invariati.';
    } finally {
      busy = false;
    }
  }

  function openCreate() {
    const current = archive.getContext();
    if (!data.can_manage || !['root', 'custom'].includes(current.view)) return;
    if (current.folderId !== null && !data.folders.find((folder) => Number(folder.id) === current.folderId)?.can_manage) return;
    mode = 'create';
    parentId = current.folderId;
    title.textContent = parentId === null ? 'Nuova cartella' : 'Nuova sottocartella';
    context.textContent = `Posizione: ${folderPath(parentId)}`;
    nameField.hidden = false;
    destinationField.hidden = true;
    form.elements.name.disabled = false;
    form.elements.name.value = '';
    form.elements.destination.disabled = true;
    dialog.showModal();
    form.elements.name.focus();
  }

  function openMove(identifier) {
    if (!canMove(identifier)) return;
    mode = 'move';
    documentId = identifier;
    title.textContent = 'Sposta in cartella';
    context.textContent = documents.find((item) => Number(item.id) === identifier).original_name;
    nameField.hidden = true;
    destinationField.hidden = false;
    form.elements.name.disabled = true;
    form.elements.destination.disabled = false;
    const placement = data.placements.find((item) => Number(item.document_id) === identifier);
    form.elements.destination.innerHTML = '<option value="">Archivio documenti (fuori cartella)</option>'
      + data.folders.filter((folder) => folder.can_manage && !folder.system_key)
        .sort((left, right) => folderPath(Number(left.id)).localeCompare(folderPath(Number(right.id)), 'it'))
        .map((folder) => `<option value="${Number(folder.id)}">${escape(folderPath(Number(folder.id)))}</option>`).join('');
    form.elements.destination.value = placement ? String(placement.folder_id) : '';
    dialog.showModal();
    form.elements.destination.focus();
  }

  function click(event) {
    const button = event.target.closest('button');
    if (!button || busy) return;
    if (button.hasAttribute('data-new-folder')) openCreate();
    if (button.dataset.folderMove) openMove(Number(button.dataset.folderMove));
  }

  element.addEventListener('click', click);
  listing.addEventListener('click', click);
  document.querySelector('#cancelDocumentFolder').addEventListener('click', () => dialog.close());
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (busy || submit.disabled) return;
    submit.disabled = true;
    try {
      if (mode === 'create') {
        await api('/document-folders', jsonOptions({ name: form.elements.name.value, parent_id: parentId }));
        await reload();
        message.textContent = 'Cartella creata.';
      } else {
        await move(documentId, form.elements.destination.value === '' ? null : Number(form.elements.destination.value));
      }
      dialog.close();
    } catch (error) {
      message.textContent = error.message;
      context.textContent = error.message;
    } finally {
      submit.disabled = false;
    }
  });

  function clearHighlight() {
    highlighted?.classList.remove('document-drop-target');
    highlighted = null;
  }

  listing.addEventListener('dragstart', (event) => {
    const row = event.target.closest('tr[data-document-id]');
    if (!row || event.target.closest('button, a, input, select, textarea') || !canMove(Number(row.dataset.documentId)) || busy) {
      event.preventDefault();
      return;
    }
    draggedId = Number(row.dataset.documentId);
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('application/x-myrsu-document', String(draggedId));
    event.dataTransfer.setData('text/plain', row.innerText);
  });

  function dropTarget(event) {
    const target = event.target.closest('[data-drop-folder-id]');
    if (!target || (!element.contains(target) && !listing.contains(target))) return null;
    return target;
  }

  for (const surface of [element, listing]) {
    surface.addEventListener('dragover', (event) => {
      const target = dropTarget(event);
      if (draggedId === null || !target || busy) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'move';
      if (highlighted !== target) {
        clearHighlight();
        highlighted = target;
        target.classList.add('document-drop-target');
      }
    });
    surface.addEventListener('dragleave', (event) => {
      if (highlighted && !highlighted.contains(event.relatedTarget)) clearHighlight();
    });
    surface.addEventListener('drop', async (event) => {
      const target = dropTarget(event);
      if (draggedId === null || !target) return;
      event.preventDefault();
      const identifier = draggedId;
      draggedId = null;
      clearHighlight();
      try {
        await move(identifier, target.dataset.dropFolderId === '' ? null : Number(target.dataset.dropFolderId));
      } catch (error) {
        message.textContent = error.message;
      }
    });
  }
  listing.addEventListener('dragend', () => {
    draggedId = null;
    clearHighlight();
  });

  return {
    setData(folderData, documentRows) {
      data = folderData;
      documents = documentRows;
    },
    canMove,
    uploadDestination() {
      const current = archive.getContext();
      if (!['root', 'custom'].includes(current.view)) {
        throw new Error('Cartella automatica delle pratiche: carica in Archivio documenti o in una cartella manuale e usa il collegamento alla pratica.');
      }
      if (current.folderId !== null && (!data.can_manage || !data.folders.find((folder) => Number(folder.id) === current.folderId)?.can_manage)) {
        throw new Error('Questa cartella è in sola lettura: caricamento non consentito.');
      }
      return { folderId: current.folderId, label: folderPath(current.folderId) };
    },
    moveButton(identifier) {
      return canMove(identifier) ? `<button type="button" class="icon-action" data-folder-move="${identifier}" title="Sposta in cartella" aria-label="Sposta in cartella">${window.MyRsuIcons.get('folder')}</button>` : '';
    },
  };
}
