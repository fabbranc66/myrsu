CREATE TABLE room_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rooms (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  status ENUM('draft','open','in_progress','suspended','closed','archived','cancelled') NOT NULL DEFAULT 'draft',
  created_by BIGINT UNSIGNED NOT NULL,
  responsible_id BIGINT UNSIGNED NOT NULL,
  opened_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX rooms_status_idx (status),
  INDEX rooms_category_idx (category_id),
  CONSTRAINT rooms_category_fk FOREIGN KEY (category_id) REFERENCES room_categories(id),
  CONSTRAINT rooms_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT rooms_responsible_fk FOREIGN KEY (responsible_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  permission_level ENUM('read','interact','manage') NOT NULL DEFAULT 'read',
  room_role VARCHAR(120) NULL,
  invited_by BIGINT UNSIGNED NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY room_users_unique (room_id, user_id),
  INDEX room_users_user_idx (user_id, active),
  CONSTRAINT room_users_room_fk FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT room_users_user_fk FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT room_users_invited_by_fk FOREIGN KEY (invited_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_external_participants (
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

CREATE TABLE room_access_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  access_token_hash CHAR(64) NOT NULL UNIQUE,
  permission_level ENUM('read','interact','manage') NOT NULL,
  expires_at DATETIME NOT NULL,
  last_access_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  INDEX room_access_tokens_room_user_idx (room_id, user_id, active),
  CONSTRAINT room_access_tokens_room_fk FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT room_access_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  author_id BIGINT UNSIGNED NULL,
  external_author_id BIGINT UNSIGNED NULL,
  message_type ENUM('message','update','request','reply','proposal','decision','notice') NOT NULL DEFAULT 'message',
  content TEXT NOT NULL,
  edited_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX room_messages_timeline_idx (room_id, created_at, id),
  CONSTRAINT room_messages_room_fk FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT room_messages_parent_fk FOREIGN KEY (parent_id) REFERENCES room_messages(id) ON DELETE SET NULL,
  CONSTRAINT room_messages_author_fk FOREIGN KEY (author_id) REFERENCES users(id),
  CONSTRAINT room_messages_external_author_fk FOREIGN KEY (external_author_id) REFERENCES room_external_participants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  message_id BIGINT UNSIGNED NOT NULL UNIQUE,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(80) NOT NULL UNIQUE,
  mime_type VARCHAR(120) NOT NULL,
  attachment_type ENUM('document','image','video','audio') NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  uploaded_by_user BIGINT UNSIGNED NULL,
  uploaded_by_external BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  INDEX room_attachments_room_idx (room_id, deleted_at),
  CONSTRAINT room_attachments_room_fk FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT room_attachments_message_fk FOREIGN KEY (message_id) REFERENCES room_messages(id) ON DELETE CASCADE,
  CONSTRAINT room_attachments_user_fk FOREIGN KEY (uploaded_by_user) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT room_attachments_external_fk FOREIGN KEY (uploaded_by_external) REFERENCES room_external_participants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  shared_by BIGINT UNSIGNED NOT NULL,
  shared_at DATETIME NOT NULL,
  revoked_by BIGINT UNSIGNED NULL,
  revoked_at DATETIME NULL,
  UNIQUE KEY room_documents_unique (room_id, document_id),
  INDEX room_documents_document_idx (document_id),
  CONSTRAINT room_documents_room_fk FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT room_documents_document_fk FOREIGN KEY (document_id) REFERENCES documents(id),
  CONSTRAINT room_documents_shared_by_fk FOREIGN KEY (shared_by) REFERENCES users(id),
  CONSTRAINT room_documents_revoked_by_fk FOREIGN KEY (revoked_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id BIGINT UNSIGNED NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX room_events_timeline_idx (room_id, created_at, id),
  CONSTRAINT room_events_room_fk FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT room_events_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO room_categories (code, name, active, sort_order, created_at, updated_at) VALUES
('confronto', 'Confronto sindacale', 1, 10, NOW(), NOW()),
('sicurezza', 'Sicurezza / RLS', 1, 20, NOW(), NOW()),
('trattativa', 'Trattativa', 1, 30, NOW(), NOW()),
('pdr', 'Premio di risultato', 1, 40, NOW(), NOW()),
('gruppo_lavoro', 'Gruppo di lavoro', 1, 50, NOW(), NOW()),
('altro', 'Altro', 1, 100, NOW(), NOW());
