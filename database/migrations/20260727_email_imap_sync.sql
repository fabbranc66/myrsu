ALTER TABLE emails
  ADD COLUMN external_id VARCHAR(255) NULL AFTER id,
  ADD COLUMN import_source VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER external_id,
  ADD UNIQUE KEY emails_external_unique (external_id);

CREATE TABLE email_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX email_attachments_email_idx (email_id),
  CONSTRAINT email_attachments_email_fk FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
