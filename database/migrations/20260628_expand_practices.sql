ALTER TABLE practices
  MODIFY status VARCHAR(40) NOT NULL DEFAULT 'new',
  ADD COLUMN code VARCHAR(30) NULL AFTER id,
  ADD COLUMN summary TEXT NULL AFTER title,
  ADD COLUMN type ENUM('collective', 'personal', 'personal_restricted') NOT NULL DEFAULT 'collective' AFTER summary,
  ADD COLUMN priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium' AFTER status,
  ADD COLUMN source_type ENUM('manual', 'anonymous_report', 'mail', 'member', 'delegate', 'document', 'communication', 'meeting') NOT NULL DEFAULT 'manual' AFTER priority,
  ADD COLUMN assigned_user_id BIGINT UNSIGNED NULL AFTER source_type,
  ADD COLUMN visibility ENUM('operators', 'authorized', 'public_summary') NOT NULL DEFAULT 'operators' AFTER assigned_user_id,
  ADD COLUMN due_date DATE NULL AFTER visibility,
  ADD COLUMN closed_at DATETIME NULL AFTER updated_at;

UPDATE practices SET status = 'under_review' WHERE status = 'open';
UPDATE practices SET code = CONCAT('PRA-', YEAR(created_at), '-', LPAD(id, 4, '0')) WHERE code IS NULL;

ALTER TABLE practices
  MODIFY code VARCHAR(30) NOT NULL,
  MODIFY status ENUM('new', 'under_review', 'assigned', 'company_discussion', 'awaiting_response', 'suspended', 'resolved', 'closed', 'archived', 'closed_positive', 'closed_negative') NOT NULL DEFAULT 'new',
  ADD UNIQUE KEY practices_code_unique (code),
  ADD INDEX practices_priority_idx (priority),
  ADD CONSTRAINT practices_assigned_user_fk FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE practice_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  practice_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX practice_notes_practice_idx (practice_id),
  CONSTRAINT practice_notes_practice_fk FOREIGN KEY (practice_id) REFERENCES practices(id) ON DELETE CASCADE,
  CONSTRAINT practice_notes_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
