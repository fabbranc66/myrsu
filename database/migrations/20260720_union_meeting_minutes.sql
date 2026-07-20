ALTER TABLE union_meetings
  ADD COLUMN minutes_document_id BIGINT UNSIGNED NULL AFTER public_document_id,
  ADD CONSTRAINT union_meetings_minutes_document_fk
    FOREIGN KEY (minutes_document_id) REFERENCES documents(id) ON DELETE SET NULL;
