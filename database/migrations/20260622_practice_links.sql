CREATE TABLE practices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  status ENUM('open', 'closed', 'archived') NOT NULL DEFAULT 'open',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX practices_status_idx (status),
  CONSTRAINT practices_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE practice_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  practice_id BIGINT UNSIGNED NOT NULL,
  entity_type ENUM('document', 'report', 'comment', 'protocol', 'attachment') NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY practice_links_unique (practice_id, entity_type, entity_id),
  INDEX practice_links_entity_idx (entity_type, entity_id),
  CONSTRAINT practice_links_practice_id_fk FOREIGN KEY (practice_id) REFERENCES practices(id) ON DELETE CASCADE,
  CONSTRAINT practice_links_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
