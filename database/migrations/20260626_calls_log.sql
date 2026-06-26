CREATE TABLE calls_log (
  id CHAR(36) PRIMARY KEY,
  practice_id BIGINT UNSIGNED NULL,
  direction ENUM('incoming', 'outgoing') NOT NULL,
  interlocutor_name VARCHAR(255) NOT NULL,
  interlocutor_role VARCHAR(255) NULL,
  interlocutor_org VARCHAR(255) NULL,
  call_date DATE NOT NULL,
  call_time TIME NOT NULL,
  content TEXT NOT NULL,
  outcome VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX calls_log_practice_idx (practice_id),
  INDEX calls_log_datetime_idx (call_date, call_time),
  CONSTRAINT calls_log_practice_fk FOREIGN KEY (practice_id) REFERENCES practices(id) ON DELETE SET NULL,
  CONSTRAINT calls_log_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
