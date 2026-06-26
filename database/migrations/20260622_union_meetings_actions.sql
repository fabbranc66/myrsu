ALTER TABLE union_meetings
  ADD participants TEXT NOT NULL AFTER description,
  ADD agenda TEXT NOT NULL AFTER participants,
  ADD public_document_id BIGINT UNSIGNED NULL AFTER visibility,
  ADD CONSTRAINT union_meetings_public_document_fk
    FOREIGN KEY (public_document_id) REFERENCES documents(id) ON DELETE SET NULL;

CREATE TABLE union_meeting_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id BIGINT UNSIGNED NOT NULL,
  note_type ENUM('content', 'answer', 'idea', 'proposal') NOT NULL DEFAULT 'content',
  body TEXT NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX union_meeting_notes_meeting_idx (meeting_id),
  CONSTRAINT union_meeting_notes_meeting_fk FOREIGN KEY (meeting_id) REFERENCES union_meetings(id) ON DELETE CASCADE,
  CONSTRAINT union_meeting_notes_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
