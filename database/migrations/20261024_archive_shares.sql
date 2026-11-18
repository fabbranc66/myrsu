CREATE TABLE IF NOT EXISTS archive_shares (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  target_configuration_id BIGINT UNSIGNED NOT NULL,
  source_configuration_id BIGINT UNSIGNED NOT NULL,
  entity_type ENUM('practice', 'document') NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY archive_shares_unique (target_configuration_id, source_configuration_id, entity_type, entity_id),
  INDEX archive_shares_source_idx (source_configuration_id, entity_type, entity_id),
  CONSTRAINT archive_shares_target_fk FOREIGN KEY (target_configuration_id) REFERENCES saved_configurations(id) ON DELETE CASCADE,
  CONSTRAINT archive_shares_source_fk FOREIGN KEY (source_configuration_id) REFERENCES saved_configurations(id) ON DELETE CASCADE,
  CONSTRAINT archive_shares_creator_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
