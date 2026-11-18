SELECT CONCAT('ALTER TABLE `', TABLE_NAME,
    '` MODIFY COLUMN visibility ENUM(''public'',''members'',''rsu'',''creator'') NOT NULL DEFAULT ''rsu'';') AS sql_da_eseguire
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'visibility'
  AND (TABLE_NAME = 'documents' OR TABLE_NAME REGEXP '^cfg_[0-9]+_documents$')
  AND COLUMN_TYPE NOT LIKE '%''creator''%'
UNION ALL
SELECT CONCAT('ALTER TABLE `', target.TABLE_NAME,
    '` ADD COLUMN visibility ENUM(''document'',''creator'',''rsu'',''members'',''public'') NULL AFTER inherit_permissions;')
FROM information_schema.TABLES target
WHERE target.TABLE_SCHEMA = DATABASE() AND target.TABLE_TYPE = 'BASE TABLE'
  AND (target.TABLE_NAME = 'document_folders' OR target.TABLE_NAME REGEXP '^cfg_[0-9]+_document_folders$')
  AND NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS existing
      WHERE existing.TABLE_SCHEMA = target.TABLE_SCHEMA AND existing.TABLE_NAME = target.TABLE_NAME AND existing.COLUMN_NAME = 'visibility');
