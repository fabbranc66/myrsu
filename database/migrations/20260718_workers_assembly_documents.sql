CREATE TABLE workers_assembly_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assembly_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY workers_assembly_documents_unique (assembly_id, document_id),
  INDEX workers_assembly_documents_assembly_idx (assembly_id),
  INDEX workers_assembly_documents_document_idx (document_id),
  CONSTRAINT workers_assembly_documents_assembly_fk FOREIGN KEY (assembly_id) REFERENCES workers_assemblies(id) ON DELETE CASCADE,
  CONSTRAINT workers_assembly_documents_document_fk FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT workers_assembly_documents_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
