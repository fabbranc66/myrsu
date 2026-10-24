SET @folder_system_sql = IF(
  EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'document_folders'
      AND COLUMN_NAME = 'system_key'
  ),
  'SELECT 1',
  'ALTER TABLE document_folders ADD COLUMN system_key VARCHAR(64) NULL, ADD UNIQUE KEY document_folders_system_key_unique (system_key)'
);
PREPARE folder_system_statement FROM @folder_system_sql;
EXECUTE folder_system_statement;
DEALLOCATE PREPARE folder_system_statement;

INSERT INTO document_folders (parent_id, name, system_key, created_at, updated_at)
SELECT NULL, 'Documenti pratiche', 'practices', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM document_folders WHERE system_key = 'practices')
ON DUPLICATE KEY UPDATE system_key = COALESCE(document_folders.system_key, VALUES(system_key));

INSERT INTO document_folders (parent_id, name, system_key, created_at, updated_at)
SELECT root.id, CONCAT('Pratica #', p.id), CONCAT('practice:', p.id), NOW(), NOW()
FROM practices p
JOIN document_folders root ON root.system_key = 'practices'
WHERE NOT EXISTS (SELECT 1 FROM document_folders existing WHERE existing.system_key = CONCAT('practice:', p.id))
ON DUPLICATE KEY UPDATE system_key = COALESCE(document_folders.system_key, VALUES(system_key));
