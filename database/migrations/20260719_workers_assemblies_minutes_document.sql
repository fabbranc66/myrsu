ALTER TABLE workers_assemblies
  ADD COLUMN minutes_document_id BIGINT UNSIGNED NULL AFTER public_document_id,
  ADD CONSTRAINT workers_assemblies_minutes_document_fk
    FOREIGN KEY (minutes_document_id) REFERENCES documents(id) ON DELETE SET NULL;
