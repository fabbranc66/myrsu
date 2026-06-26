CREATE TABLE union_meetings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  meeting_date DATETIME NOT NULL,
  location VARCHAR(255) NOT NULL,
  status ENUM('scheduled', 'done', 'cancelled') NOT NULL DEFAULT 'scheduled',
  visibility ENUM('public', 'members', 'rsu') NOT NULL DEFAULT 'rsu',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX union_meetings_date_idx (meeting_date),
  INDEX union_meetings_status_idx (status),
  CONSTRAINT union_meetings_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE practice_links
  MODIFY entity_type ENUM('document', 'report', 'comment', 'protocol', 'attachment', 'meeting') NOT NULL;
