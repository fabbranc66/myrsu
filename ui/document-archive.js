import { buildDocumentArchive, folderTrail } from './document-archive-model.js?v=20261024-practice-permissions-1';

export function createDocumentArchive({ element, listing, listingTitle, emptyMessage, onRows }) {
  let archive = buildDocumentArchive([], []);
  let view = 'root';
  let practiceId = null;
  let customId = null;
  let manual = {};

  function escape(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
  }

  function folderRow({ title, documents, targetView, id = null, code = '', permission_folder_id = id, can_set_permissions = false }) {
    const custom = archive.customFolders.find((folder) => folder.id === id);
    const target = `data-archive-view="${targetView}"${id === null ? '' : ` data-${targetView === 'custom' ? 'folder' : 'practice'}-id="${id}"`}`;
    const drop = targetView === 'custom' && custom.can_manage ? ` data-drop-folder-id="${id}"` : '';
    const childCount = targetView === 'custom' ? archive.customFolders.filter((folder) => folder.parent_id === id).length : 0;
    return `<tr class="document-archive-folder-row"${drop}>
      <td data-label="Nome"><button type="button" class="document-archive-folder" ${target}>
        <span class="document-archive-icon" aria-hidden="true">${window.MyRsuIcons.get('folder')}</span>
        <span class="document-archive-folder-label"><strong>${escape(title)}</strong>${code ? `<small>${escape(code)}</small>` : ''}</span>
      </button></td>
      <td data-label="Categoria">Cartella</td>
      <td data-label="Visibilita">-</td>
      <td data-label="Stato">${documents.length} ${documents.length === 1 ? 'documento' : 'documenti'}${childCount ? ` · ${childCount} sottocartelle` : ''}</td>
      <td data-label="Dimensione">-</td>
      <td data-label="Azioni" class="actions-cell">${can_set_permissions ? `<button type="button" data-folder-permissions="${permission_folder_id}">Permessi</button>` : ''}</td>
    </tr>`;
  }

  function breadcrumb(label, targetView, active, identifier = null) {
    const folder = archive.customFolders.find((item) => item.id === identifier);
    const drop = manual.can_manage && (targetView === 'root' || (targetView === 'custom' && folder?.can_manage))
      ? ` data-drop-folder-id="${identifier ?? ''}"` : '';
    return active
      ? `<span aria-current="page" tabindex="-1"${drop}>${escape(label)}</span>`
      : `<button type="button" data-archive-view="${targetView}"${identifier === null ? '' : ` data-folder-id="${identifier}"`}${drop}>${escape(label)}</button>`;
  }

  function render() {
    const selected = archive.folders.find((folder) => folder.id === practiceId);
    const custom = archive.customFolders.find((folder) => folder.id === customId);
    if (['practice', 'practices'].includes(view) && !archive.practiceRoot) {
      view = 'root';
      practiceId = null;
    }
    if (view === 'practice' && !selected) {
      view = 'practices';
      practiceId = null;
    }
    if (view === 'custom' && !custom) {
      view = 'root';
      customId = null;
    }
    const crumbs = [breadcrumb('Archivio documenti', 'root', view === 'root')];
    if (['practices', 'practice'].includes(view)) crumbs.push(breadcrumb('Documenti pratiche', 'practices', view === 'practices'));
    if (view === 'practice') crumbs.push(breadcrumb(`${selected.code ? `${selected.code} · ` : ''}${selected.title}`, 'practice', true));
    if (view === 'custom') {
      for (const folder of folderTrail(archive.customFolders, customId)) {
        crumbs.push(breadcrumb(folder.title, 'custom', folder.id === customId, folder.id));
      }
    }
    let rows;
    let folders = [];
    let title;
    let note;
    if (view === 'root') {
      rows = archive.rootDocuments;
      folders = archive.practiceRoot ? [{
        title: 'Documenti pratiche', documents: archive.practiceDocuments, targetView: 'practices',
        permission_folder_id: archive.practiceRoot.id, can_set_permissions: archive.practiceRoot.can_set_permissions,
      }] : [];
      folders.push(...archive.customFolders.filter((folder) => folder.parent_id === null).map((folder) => ({ ...folder, targetView: 'custom' })));
      title = 'Archivio documenti';
      note = 'Trascina i documenti nelle cartelle create nell’archivio, oppure usa Sposta in cartella. I collegamenti alle pratiche restano invariati.';
    } else if (view === 'practices') {
      rows = [];
      folders = archive.folders.map((folder) => ({ ...folder, targetView: 'practice' }));
      title = 'Documenti pratiche';
      note = 'Sono incluse anche le pratiche chiuse e archiviate. Apri una sottocartella per vedere i documenti della singola pratica.';
    } else if (view === 'custom') {
      rows = custom.documents;
      folders = archive.customFolders.filter((folder) => folder.parent_id === customId).map((folder) => ({ ...folder, targetView: 'custom' }));
      title = custom.title;
      note = 'Per riportare un documento fuori cartella trascinalo su Archivio documenti nel percorso. I permessi delle cartelle superiori restano validi.';
    } else {
      rows = selected.documents;
      title = `Documenti · ${selected.code || selected.title}`;
      note = 'La cartella si aggiorna usando i collegamenti alla pratica. Per aggiungere o rimuovere documenti usa Collega a pratica o Scollega da pratica.';
    }
    const focused = element.contains(document.activeElement) || listing.contains(document.activeElement);
    const canCreate = manual.can_manage && (view === 'root' || (view === 'custom' && custom.can_manage));
    const permissionFolder = view === 'custom' ? custom : view === 'practices' ? archive.practiceRoot : view === 'practice' ? selected : null;
    const permissionButton = permissionFolder?.can_set_permissions
      ? `<button type="button" data-folder-permissions="${permissionFolder.permission_folder_id ?? permissionFolder.id}">Permessi cartella</button>` : '';
    element.innerHTML = `<nav class="document-archive-breadcrumb" aria-label="Percorso archivio">${crumbs.join('<span aria-hidden="true">/</span>')}</nav>
      ${manual.can_manage ? `<div class="document-archive-toolbar"><button type="button" data-new-folder ${canCreate ? '' : 'disabled title="Le cartelle delle pratiche sono gestite dai collegamenti esistenti."'}>${view === 'custom' ? 'Nuova sottocartella' : 'Nuova cartella'}</button>${permissionButton}</div>` : ''}
      <p class="document-archive-note">${escape(note)} I file non vengono spostati né duplicati.</p>`;
    element.hidden = false;
    const count = rows.length + folders.length;
    listingTitle.textContent = `${title} (${count} ${count === 1 ? 'elemento' : 'elementi'})`;
    emptyMessage.textContent = 'La cartella è vuota.';
    emptyMessage.hidden = count > 0;
    onRows(rows, folders.map(folderRow).join(''));
    if (focused) element.querySelector('[aria-current="page"]').focus();
  }

  function navigate(event) {
    const button = event.target.closest('button[data-archive-view]');
    if (!button || (!element.contains(button) && !listing.contains(button))) return;
    if (!['root', 'practices', 'practice', 'custom'].includes(button.dataset.archiveView)) return;
    const identifier = Number(button.dataset.practiceId);
    if (button.dataset.archiveView === 'practice' && !archive.folders.some((folder) => folder.id === identifier)) return;
    const folderId = Number(button.dataset.folderId);
    if (button.dataset.archiveView === 'custom' && !archive.customFolders.some((folder) => folder.id === folderId)) return;
    view = button.dataset.archiveView;
    practiceId = view === 'practice' ? identifier : null;
    customId = view === 'custom' ? folderId : null;
    render();
  }

  element.addEventListener('click', navigate);
  listing.addEventListener('click', navigate);

  return {
    setData(documents, practices, folderData = {}) {
      manual = folderData;
      archive = buildDocumentArchive(documents, practices, folderData);
      render();
    },
    getContext() {
      return { view, folderId: view === 'custom' ? customId : null };
    },
    openRoot() {
      view = 'root';
      practiceId = null;
      customId = null;
      render();
    },
  };
}
