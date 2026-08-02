CREATE TABLE IF NOT EXISTS room_external_participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(80) NULL,
  organization VARCHAR(255) NULL,
  room_role VARCHAR(120) NULL,
  permission_level ENUM('read','interact') NOT NULL DEFAULT 'read',
  access_token_hash CHAR(64) NOT NULL UNIQUE,
  token_expires_at DATETIME NULL,
  last_access_at DATETIME NULL,
  local_identifier VARCHAR(40) NULL UNIQUE,
  matched_user_id BIGINT UNSIGNED NULL,
  verification_sent_at DATETIME NULL,
  registered_at DATETIME NULL,
  added_by BIGINT UNSIGNED NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX room_external_participants_room_idx (room_id, active),
  CONSTRAINT room_external_participants_room_fk FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT room_external_participants_user_fk FOREIGN KEY (matched_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT room_external_participants_added_by_fk FOREIGN KEY (added_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE room_messages
  MODIFY author_id BIGINT UNSIGNED NULL,
  ADD COLUMN external_author_id BIGINT UNSIGNED NULL AFTER author_id,
  ADD CONSTRAINT room_messages_external_author_fk FOREIGN KEY (external_author_id) REFERENCES room_external_participants(id) ON DELETE SET NULL;
