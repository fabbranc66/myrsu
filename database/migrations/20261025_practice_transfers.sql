CREATE TABLE IF NOT EXISTS practice_transfers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_configuration_id BIGINT UNSIGNED NOT NULL,
  source_practice_id BIGINT UNSIGNED NOT NULL,
  target_configuration_id BIGINT UNSIGNED NOT NULL,
  target_practice_id BIGINT UNSIGNED NOT NULL,
  source_code VARCHAR(30) NOT NULL,
  target_code VARCHAR(30) NOT NULL,
  moved_by BIGINT UNSIGNED NOT NULL,
  backup_name VARCHAR(160) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY practice_transfers_source (source_configuration_id, source_practice_id),
  CONSTRAINT practice_transfers_source_fk FOREIGN KEY (source_configuration_id) REFERENCES saved_configurations(id),
  CONSTRAINT practice_transfers_target_fk FOREIGN KEY (target_configuration_id) REFERENCES saved_configurations(id),
  CONSTRAINT practice_transfers_user_fk FOREIGN KEY (moved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS practice_archive_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  configuration_id BIGINT UNSIGNED NOT NULL,
  practice_id BIGINT UNSIGNED NOT NULL,
  source_configuration_id BIGINT UNSIGNED NOT NULL,
  source_practice_id BIGINT UNSIGNED NOT NULL,
  UNIQUE KEY practice_archive_links_unique (configuration_id, practice_id, source_configuration_id, source_practice_id),
  CONSTRAINT practice_archive_links_owner_fk FOREIGN KEY (configuration_id) REFERENCES saved_configurations(id) ON DELETE CASCADE,
  CONSTRAINT practice_archive_links_source_fk FOREIGN KEY (source_configuration_id) REFERENCES saved_configurations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
