export function documentPracticeIds(document) {
  const values = Array.isArray(document?.practice_ids) ? document.practice_ids : String(document?.practice_ids || '').split(',');
  return [...new Set(values.map(Number).filter((identifier) => Number.isSafeInteger(identifier) && identifier > 0))];
}

export function isOpenPractice(practice) {
  return !['resolved', 'closed', 'archived', 'closed_positive', 'closed_negative'].includes(practice.status);
}

export function folderTrail(folders, identifier) {
  const byId = new Map(folders.map((folder) => [Number(folder.id), folder]));
  const trail = [];
  const visited = new Set();
  while (identifier !== null) {
    if (visited.has(identifier) || !byId.has(identifier)) return [];
    visited.add(identifier);
    const folder = byId.get(identifier);
    trail.unshift(folder);
    identifier = folder.parent_id === null ? null : Number(folder.parent_id);
  }
  return trail;
}

export function buildDocumentArchive(documents, practices, manual = {}) {
  const practiceMap = new Map(practices.map((practice) => [Number(practice.id), practice]));
  const practiceRoot = (manual.folders || []).find((folder) => folder.system_key === 'practices') || null;
  const customFolders = (manual.folders || []).filter((folder) => !folder.system_key).map((folder) => ({ ...folder, id: Number(folder.id), parent_id: folder.parent_id === null ? null : Number(folder.parent_id), title: folder.name, documents: [] }));
  const customById = new Map(customFolders.map((folder) => [folder.id, folder]));
  const placements = new Map((manual.placements || []).map((item) => [Number(item.document_id), Number(item.folder_id)]));
  const rootDocuments = [];
  const practiceDocuments = [];
  const folders = new Map((manual.folders || []).filter((folder) => folder.system_key?.startsWith('practice:') && folder.practice_id !== null).map((folder) => {
    const identifier = Number(folder.practice_id);
    const practice = practiceMap.get(identifier);
    return [identifier, {
      id: identifier, permission_folder_id: Number(folder.id), can_set_permissions: folder.can_set_permissions,
      code: String(practice?.code || folder.practice_code || ''),
      title: String(practice?.title || folder.practice_title || `Pratica #${identifier}`),
      documents: [],
    }];
  }));
  const seenDocuments = new Set();
  for (const document of documents) {
    if (seenDocuments.has(String(document.id))) continue;
    seenDocuments.add(String(document.id));
    const destination = customById.get(placements.get(Number(document.id)));
    if (destination) destination.documents.push(document);
    const identifiers = documentPracticeIds(document);
    if (!identifiers.length) {
      if (!destination && document.category !== 'comunicati') rootDocuments.push(document);
      continue;
    }
    if (identifiers.some((identifier) => folders.has(identifier))) practiceDocuments.push(document);
    for (const identifier of identifiers) {
      if (folders.has(identifier)) folders.get(identifier).documents.push(document);
    }
  }
  return {
    rootDocuments,
    practiceDocuments,
    practiceRoot,
    customFolders: customFolders.sort((left, right) => left.title.localeCompare(right.title, 'it', { numeric: true })),
    folders: [...folders.values()].sort((left, right) => `${left.code} ${left.title}`.localeCompare(`${right.code} ${right.title}`, 'it', { numeric: true })),
  };
}
