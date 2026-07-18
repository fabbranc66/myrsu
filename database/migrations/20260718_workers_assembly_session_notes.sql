CREATE TABLE workers_assembly_session_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  note_type ENUM('discussion', 'question', 'answer', 'proposal', 'decision', 'note') NOT NULL DEFAULT 'discussion',
  body TEXT NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX workers_assembly_session_notes_session_idx (session_id),
  CONSTRAINT workers_assembly_session_notes_session_fk FOREIGN KEY (session_id) REFERENCES workers_assembly_sessions(id) ON DELETE CASCADE,
  CONSTRAINT workers_assembly_session_notes_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
