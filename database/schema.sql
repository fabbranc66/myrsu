CREATE DATABASE IF NOT EXISTS myrsu
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE myrsu;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(120) NULL,
  last_name VARCHAR(120) NULL,
  phone VARCHAR(40) NULL,
  mobile VARCHAR(40) NULL,
  city VARCHAR(120) NULL,
  country VARCHAR(120) NULL,
  status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  label VARCHAR(160) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_user (
  user_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  CONSTRAINT role_user_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT role_user_role_id_fk FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permission_role (
  permission_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (permission_id, role_id),
  CONSTRAINT permission_role_permission_id_fk FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
  CONSTRAINT permission_role_role_id_fk FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  device_name VARCHAR(120) NULL,
  last_used_at DATETIME NULL,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  INDEX api_tokens_user_id_idx (user_id),
  CONSTRAINT api_tokens_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gdpr_consents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  consent_type VARCHAR(80) NOT NULL,
  document_version VARCHAR(40) NOT NULL,
  accepted TINYINT(1) NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  INDEX gdpr_consents_user_type_idx (user_id, consent_type),
  CONSTRAINT gdpr_consents_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(80) NOT NULL UNIQUE,
  mime_type VARCHAR(120) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  original_stored_name VARCHAR(80) NULL,
  original_mime_type VARCHAR(120) NULL,
  original_size_bytes BIGINT UNSIGNED NULL,
  original_checksum_sha256 CHAR(64) NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'documenti',
  pdf_public_path VARCHAR(255) NULL,
  pdf_size_bytes BIGINT UNSIGNED NULL,
  pdf_checksum_sha256 CHAR(64) NULL,
  conversion_status ENUM('ready', 'pending', 'failed') NOT NULL DEFAULT 'ready',
  signature CHAR(24) NULL,
  signed_at DATETIME NULL,
  visibility ENUM('public', 'members', 'rsu') NOT NULL DEFAULT 'rsu',
  uploaded_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT documents_uploaded_by_fk FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(120) NOT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL,
  INDEX activity_logs_user_id_idx (user_id),
  INDEX activity_logs_action_idx (action),
  CONSTRAINT activity_logs_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE protocol_entries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  protocol_number VARCHAR(40) NOT NULL UNIQUE,
  direction ENUM('IN', 'OUT') NOT NULL,
  type_code VARCHAR(20) NOT NULL,
  year SMALLINT UNSIGNED NOT NULL,
  sequence INT UNSIGNED NOT NULL,
  subject VARCHAR(255) NOT NULL,
  document_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  canceled_at DATETIME NULL,
  canceled_by BIGINT UNSIGNED NULL,
  UNIQUE KEY protocol_sequence_unique (direction, type_code, year, sequence),
  CONSTRAINT protocol_entries_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT protocol_entries_canceled_by_fk FOREIGN KEY (canceled_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tracking_code VARCHAR(40) NOT NULL UNIQUE,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  contact VARCHAR(255) NULL,
  user_id BIGINT UNSIGNED NULL,
  origin ENUM('anonymous', 'member') NOT NULL DEFAULT 'anonymous',
  document_id BIGINT UNSIGNED NULL,
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  reply TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX reports_status_idx (status),
  CONSTRAINT reports_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT reports_document_id_fk FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles (name, label) VALUES
  ('membro', 'Membro'),
  ('delegato', 'Delegato'),
  ('rls', 'RLS'),
  ('admin', 'Amministratore');

INSERT INTO permissions (name, label) VALUES
  ('users.view', 'Vedere utenti'),
  ('users.create', 'Creare utenti'),
  ('users.update', 'Modificare utenti'),
  ('users.delete', 'Eliminare utenti'),
  ('roles.manage', 'Gestire ruoli e permessi'),
  ('gdpr.view_all', 'Vedere consensi GDPR di tutti'),
  ('activity.view', 'Vedere log attivita'),
  ('documents.view', 'Vedere documenti'),
  ('documents.upload', 'Caricare documenti'),
  ('documents.update', 'Modificare documenti'),
  ('documents.download', 'Scaricare documenti'),
  ('documents.delete', 'Eliminare documenti'),
  ('protocol.view', 'Vedere registro protocollo'),
  ('protocol.create', 'Creare protocollo'),
  ('protocol.update', 'Modificare protocollo'),
  ('protocol.cancel', 'Annullare protocollo'),
  ('reports.moderate', 'Moderare segnalazioni');

INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
JOIN roles r ON r.name = 'admin';

INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
JOIN roles r ON r.name = 'delegato'
WHERE p.name IN ('protocol.view', 'protocol.create', 'protocol.update', 'protocol.cancel');


INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
JOIN roles r ON r.name IN ('delegato', 'rls')
WHERE p.name IN ('users.view');

INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
JOIN roles r ON r.name IN ('delegato', 'rls')
WHERE p.name IN ('reports.moderate');

INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
VALUES (
  'Admin MyRSU',
  'admin@myrsu.local',
  '$2y$10$vaL5igBQUQfCumEhok6POOB02eYtnLgjW/Lw4cJ61HXCNf9S6n.BW',
  'active',
  NOW(),
  NOW()
);

INSERT INTO role_user (user_id, role_id)
SELECT u.id, r.id
FROM users u
JOIN roles r ON r.name = 'admin'
WHERE u.email = 'admin@myrsu.local';
