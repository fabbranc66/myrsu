CREATE TABLE reminders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('call', 'email') NOT NULL,
  entity_id VARCHAR(36) NOT NULL,
  title VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  due_at DATETIME NOT NULL,
  status ENUM('pending', 'done', 'cancelled') NOT NULL DEFAULT 'pending',
  created_by BIGINT UNSIGNED NOT NULL,
  assigned_to BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  INDEX reminders_entity_idx (entity_type, entity_id),
  INDEX reminders_status_due_idx (status, due_at),
  INDEX reminders_assigned_to_idx (assigned_to),
  CONSTRAINT reminders_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT reminders_assigned_to_fk FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
