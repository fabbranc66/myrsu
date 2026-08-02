ALTER TABLE room_external_participants
  ADD COLUMN matched_user_id BIGINT UNSIGNED NULL AFTER local_identifier,
  ADD COLUMN verification_sent_at DATETIME NULL AFTER matched_user_id,
  ADD CONSTRAINT room_external_participants_user_fk
    FOREIGN KEY (matched_user_id) REFERENCES users(id) ON DELETE SET NULL;
