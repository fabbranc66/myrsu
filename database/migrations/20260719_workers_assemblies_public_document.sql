ALTER TABLE workers_assemblies
  ADD COLUMN public_document_id BIGINT UNSIGNED NULL AFTER voting_subject,
  ADD CONSTRAINT workers_assemblies_public_document_fk
    FOREIGN KEY (public_document_id) REFERENCES documents(id) ON DELETE SET NULL;
