CREATE TABLE workers_assemblies (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  practice_id BIGINT UNSIGNED NULL,
  title VARCHAR(255) NOT NULL,
  agenda TEXT NOT NULL,
  description TEXT NULL,
  final_statement TEXT NULL,
  status ENUM('draft', 'called', 'done', 'cancelled') NOT NULL DEFAULT 'draft',
  visibility ENUM('public', 'members', 'rsu') NOT NULL DEFAULT 'members',
  voting_enabled TINYINT(1) NOT NULL DEFAULT 0,
  voting_subject VARCHAR(255) NULL,
  public_document_id BIGINT UNSIGNED NULL,
  minutes_document_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX workers_assemblies_practice_idx (practice_id),
  INDEX workers_assemblies_status_idx (status),
  CONSTRAINT workers_assemblies_public_document_fk FOREIGN KEY (public_document_id) REFERENCES documents(id) ON DELETE SET NULL,
  CONSTRAINT workers_assemblies_minutes_document_fk FOREIGN KEY (minutes_document_id) REFERENCES documents(id) ON DELETE SET NULL,
  CONSTRAINT workers_assemblies_practice_fk FOREIGN KEY (practice_id) REFERENCES practices(id) ON DELETE SET NULL,
  CONSTRAINT workers_assemblies_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workers_assembly_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assembly_id BIGINT UNSIGNED NOT NULL,
  shift_label VARCHAR(120) NOT NULL,
  assembly_date DATE NOT NULL,
  time_start TIME NOT NULL,
  time_end TIME NULL,
  mode ENUM('in_person', 'online', 'mixed') NOT NULL DEFAULT 'in_person',
  place VARCHAR(255) NULL,
  status ENUM('scheduled', 'done', 'cancelled') NOT NULL DEFAULT 'scheduled',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX workers_assembly_sessions_assembly_idx (assembly_id),
  INDEX workers_assembly_sessions_date_idx (assembly_date, time_start),
  CONSTRAINT workers_assembly_sessions_assembly_fk FOREIGN KEY (assembly_id) REFERENCES workers_assemblies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workers_assembly_participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assembly_id BIGINT UNSIGNED NOT NULL,
  participant_type ENUM('user', 'institutional_contact') NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY workers_assembly_participants_unique (assembly_id, participant_type, participant_id),
  INDEX workers_assembly_participants_assembly_idx (assembly_id),
  CONSTRAINT workers_assembly_participants_assembly_fk FOREIGN KEY (assembly_id) REFERENCES workers_assemblies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workers_assembly_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assembly_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY workers_assembly_documents_unique (assembly_id, document_id),
  INDEX workers_assembly_documents_assembly_idx (assembly_id),
  INDEX workers_assembly_documents_document_idx (document_id),
  CONSTRAINT workers_assembly_documents_assembly_fk FOREIGN KEY (assembly_id) REFERENCES workers_assemblies(id) ON DELETE CASCADE,
  CONSTRAINT workers_assembly_documents_document_fk FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT workers_assembly_documents_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
