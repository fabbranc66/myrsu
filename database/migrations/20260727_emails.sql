CREATE TABLE emails (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  direction ENUM('incoming', 'outgoing', 'draft') NOT NULL,
  read_status ENUM('unread', 'read') NOT NULL DEFAULT 'unread',
  handling_status ENUM('new', 'in_progress', 'managed', 'archived') NOT NULL DEFAULT 'new',
  from_name VARCHAR(255) NULL,
  from_email VARCHAR(255) NULL,
  to_emails TEXT NULL,
  cc_emails TEXT NULL,
  bcc_emails TEXT NULL,
  subject VARCHAR(255) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  message_at DATETIME NOT NULL,
  practice_id BIGINT UNSIGNED NULL,
  contact_id BIGINT UNSIGNED NULL,
  managed_by BIGINT UNSIGNED NULL,
  managed_at DATETIME NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX emails_direction_idx (direction),
  INDEX emails_status_idx (handling_status),
  INDEX emails_practice_idx (practice_id),
  INDEX emails_message_at_idx (message_at),
  CONSTRAINT emails_practice_fk FOREIGN KEY (practice_id) REFERENCES practices(id) ON DELETE SET NULL,
  CONSTRAINT emails_managed_by_fk FOREIGN KEY (managed_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT emails_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX email_notes_email_idx (email_id),
  CONSTRAINT email_notes_email_fk FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE,
  CONSTRAINT email_notes_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
