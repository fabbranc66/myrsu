CREATE TABLE institutional_contacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('aziendale', 'sindacale', 'esterno') NOT NULL,
  name VARCHAR(255) NOT NULL,
  role VARCHAR(255) NULL,
  organization VARCHAR(255) NULL,
  email VARCHAR(255) NULL,
  phone VARCHAR(80) NULL,
  notes TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX institutional_contacts_type_idx (type),
  CONSTRAINT institutional_contacts_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE union_meeting_participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id BIGINT UNSIGNED NOT NULL,
  participant_type ENUM('user', 'institutional_contact') NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY union_meeting_participants_unique (meeting_id, participant_type, participant_id),
  INDEX union_meeting_participants_meeting_idx (meeting_id),
  CONSTRAINT union_meeting_participants_meeting_fk FOREIGN KEY (meeting_id) REFERENCES union_meetings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
