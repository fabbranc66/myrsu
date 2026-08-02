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
  union_code VARCHAR(40) NULL,
  union_logo_stored_name VARCHAR(120) NULL,
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

CREATE TABLE practices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL,
  title VARCHAR(255) NOT NULL,
  summary TEXT NULL,
  type ENUM('collective', 'personal', 'personal_restricted') NOT NULL DEFAULT 'collective',
  status ENUM('new', 'under_review', 'assigned', 'company_discussion', 'awaiting_response', 'suspended', 'resolved', 'closed', 'archived', 'closed_positive', 'closed_negative') NOT NULL DEFAULT 'new',
  priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
  source_type ENUM('manual', 'anonymous_report', 'mail', 'member', 'delegate', 'document', 'communication', 'meeting') NOT NULL DEFAULT 'manual',
  assigned_user_id BIGINT UNSIGNED NULL,
  visibility ENUM('operators', 'authorized', 'public_summary') NOT NULL DEFAULT 'operators',
  due_date DATE NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  closed_at DATETIME NULL,
  UNIQUE KEY practices_code_unique (code),
  INDEX practices_status_idx (status),
  INDEX practices_priority_idx (priority),
  CONSTRAINT practices_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT practices_assigned_user_fk FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE practice_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  practice_id BIGINT UNSIGNED NOT NULL,
  entity_type ENUM('document', 'report', 'comment', 'protocol', 'attachment', 'meeting') NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY practice_links_unique (practice_id, entity_type, entity_id),
  INDEX practice_links_entity_idx (entity_type, entity_id),
  CONSTRAINT practice_links_practice_id_fk FOREIGN KEY (practice_id) REFERENCES practices(id) ON DELETE CASCADE,
  CONSTRAINT practice_links_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE practice_notes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  practice_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX practice_notes_practice_idx (practice_id),
  CONSTRAINT practice_notes_practice_fk FOREIGN KEY (practice_id) REFERENCES practices(id) ON DELETE CASCADE,
  CONSTRAINT practice_notes_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE practice_ccnl_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  practice_id BIGINT UNSIGNED NOT NULL,
  block_code VARCHAR(10) NOT NULL,
  block_title VARCHAR(255) NOT NULL,
  section_title VARCHAR(255) NOT NULL,
  source_path VARCHAR(255) NOT NULL,
  excerpt MEDIUMTEXT NOT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX practice_ccnl_links_practice_idx (practice_id),
  CONSTRAINT practice_ccnl_links_practice_fk FOREIGN KEY (practice_id) REFERENCES practices(id) ON DELETE CASCADE,
  CONSTRAINT practice_ccnl_links_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE union_meetings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  participants TEXT NOT NULL,
  agenda TEXT NOT NULL,
  meeting_date DATETIME NOT NULL,
  location VARCHAR(255) NOT NULL,
  status ENUM('scheduled', 'done', 'cancelled') NOT NULL DEFAULT 'scheduled',
  visibility ENUM('public', 'members', 'rsu') NOT NULL DEFAULT 'rsu',
  public_document_id BIGINT UNSIGNED NULL,
  minutes_document_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX union_meetings_date_idx (meeting_date),
  INDEX union_meetings_status_idx (status),
  CONSTRAINT union_meetings_public_document_fk FOREIGN KEY (public_document_id) REFERENCES documents(id) ON DELETE SET NULL,
  CONSTRAINT union_meetings_minutes_document_fk FOREIGN KEY (minutes_document_id) REFERENCES documents(id) ON DELETE SET NULL,
  CONSTRAINT union_meetings_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE union_meeting_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY union_meeting_documents_unique (meeting_id, document_id),
  INDEX union_meeting_documents_meeting_idx (meeting_id),
  INDEX union_meeting_documents_document_idx (document_id),
  CONSTRAINT union_meeting_documents_meeting_fk FOREIGN KEY (meeting_id) REFERENCES union_meetings(id) ON DELETE CASCADE,
  CONSTRAINT union_meeting_documents_document_fk FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT union_meeting_documents_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  voting_mode ENUM('online', 'manual', 'mixed') NOT NULL DEFAULT 'online',
  voting_subject VARCHAR(255) NULL,
  voting_options_json TEXT NULL,
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

CREATE TABLE votings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('draft', 'open', 'closed', 'cancelled') NOT NULL DEFAULT 'draft',
  anonymous TINYINT(1) NOT NULL DEFAULT 1,
  vote_mode ENUM('online', 'manual', 'mixed') NOT NULL DEFAULT 'online',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  assembly_id BIGINT UNSIGNED NULL,
  session_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX votings_status_idx (status),
  INDEX votings_assembly_idx (assembly_id),
  UNIQUE KEY votings_assembly_session_unique (assembly_id, session_id),
  CONSTRAINT votings_assembly_fk FOREIGN KEY (assembly_id) REFERENCES workers_assemblies(id) ON DELETE SET NULL,
  CONSTRAINT votings_session_fk FOREIGN KEY (session_id) REFERENCES workers_assembly_sessions(id) ON DELETE SET NULL,
  CONSTRAINT votings_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE voting_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voting_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(150) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX voting_options_voting_idx (voting_id),
  CONSTRAINT voting_options_voting_fk FOREIGN KEY (voting_id) REFERENCES votings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE voting_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voting_id BIGINT UNSIGNED NOT NULL,
  token VARCHAR(64) NOT NULL,
  status ENUM('unused', 'used', 'cancelled') NOT NULL DEFAULT 'unused',
  note VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  UNIQUE KEY voting_tokens_token_unique (token),
  INDEX voting_tokens_voting_idx (voting_id),
  CONSTRAINT voting_tokens_voting_fk FOREIGN KEY (voting_id) REFERENCES votings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE voting_ballots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voting_id BIGINT UNSIGNED NOT NULL,
  option_id BIGINT UNSIGNED NOT NULL,
  token_id BIGINT UNSIGNED NULL,
  voter_user_id BIGINT UNSIGNED NULL,
  ip_hash VARCHAR(64) NULL,
  local_identifier_hash VARCHAR(64) NULL,
  source ENUM('token', 'manual') NOT NULL DEFAULT 'token',
  recorded_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY voting_ballots_token_unique (token_id),
  UNIQUE KEY voting_ballots_local_identifier_unique (voting_id, local_identifier_hash),
  INDEX voting_ballots_voting_idx (voting_id),
  CONSTRAINT voting_ballots_voting_fk FOREIGN KEY (voting_id) REFERENCES votings(id) ON DELETE CASCADE,
  CONSTRAINT voting_ballots_option_fk FOREIGN KEY (option_id) REFERENCES voting_options(id) ON DELETE CASCADE,
  CONSTRAINT voting_ballots_token_fk FOREIGN KEY (token_id) REFERENCES voting_tokens(id) ON DELETE SET NULL,
  CONSTRAINT voting_ballots_user_fk FOREIGN KEY (voter_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT voting_ballots_recorded_by_fk FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
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

CREATE TABLE report_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  report_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(80) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX report_attachments_report_id_idx (report_id),
  CONSTRAINT report_attachments_report_id_fk FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_comments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  contact VARCHAR(255) NULL,
  user_id BIGINT UNSIGNED NULL,
  origin ENUM('anonymous', 'member') NOT NULL DEFAULT 'anonymous',
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  reply TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX document_comments_status_idx (status),
  INDEX document_comments_document_id_idx (document_id),
  CONSTRAINT document_comments_document_id_fk FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT document_comments_user_id_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reminders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('call', 'email') NOT NULL,
  entity_id VARCHAR(36) NOT NULL,
  title VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  due_at DATETIME NOT NULL,
  status ENUM('pending', 'done', 'cancelled') NOT NULL DEFAULT 'pending',
  created_by BIGINT UNSIGNED NOT NULL,
  assigned_to BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  INDEX reminders_entity_idx (entity_type, entity_id),
  INDEX reminders_status_due_idx (status, due_at),
  INDEX reminders_assigned_to_idx (assigned_to),
  CONSTRAINT reminders_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT reminders_assigned_to_fk FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
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
  ('reports.moderate', 'Moderare segnalazioni'),
  ('comments.moderate', 'Moderare commenti');

INSERT INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
JOIN roles r ON r.name IN ('admin', 'delegato', 'rls');

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


-- Normativa Rapida DB - CCNL

CREATE TABLE normative_documenti (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titolo VARCHAR(255) NOT NULL,
  titolo_breve VARCHAR(120) NOT NULL,
  tipo_documento VARCHAR(60) NOT NULL,
  ente_emittente VARCHAR(160) NULL,
  numero_atto VARCHAR(80) NULL,
  data_documento DATE NULL,
  data_pubblicazione DATE NULL,
  data_decorrenza DATE NULL,
  data_scadenza DATE NULL,
  stato_vigenza VARCHAR(40) NOT NULL DEFAULT 'da_verificare',
  versione VARCHAR(60) NOT NULL DEFAULT '1',
  descrizione TEXT NULL,
  file_originale VARCHAR(255) NULL,
  nome_file_originale VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  dimensione_file BIGINT UNSIGNED NULL,
  hash_sha256 CHAR(64) NULL,
  fonte_url VARCHAR(500) NULL,
  stato_importazione VARCHAR(40) NOT NULL DEFAULT 'completata',
  needs_review TINYINT(1) NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FULLTEXT normative_documenti_fulltext (titolo, titolo_breve, descrizione),
  INDEX normative_documenti_tipo_idx (tipo_documento),
  INDEX normative_documenti_vigenza_idx (stato_vigenza),
  CONSTRAINT normative_documenti_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE normative_nodi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  documento_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  tipo_nodo VARCHAR(40) NOT NULL,
  codice VARCHAR(40) NULL,
  titolo VARCHAR(255) NOT NULL,
  ordinamento INT UNSIGNED NOT NULL DEFAULT 0,
  livello TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pagina_inizio INT UNSIGNED NULL,
  pagina_fine INT UNSIGNED NULL,
  testo_introduttivo TEXT NULL,
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX normative_nodi_documento_idx (documento_id),
  INDEX normative_nodi_parent_idx (parent_id),
  INDEX normative_nodi_tipo_idx (tipo_nodo),
  CONSTRAINT normative_nodi_documento_fk FOREIGN KEY (documento_id) REFERENCES normative_documenti(id) ON DELETE CASCADE,
  CONSTRAINT normative_nodi_parent_fk FOREIGN KEY (parent_id) REFERENCES normative_nodi(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE normative_articoli (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  documento_base_id BIGINT UNSIGNED NOT NULL,
  nodo_id BIGINT UNSIGNED NULL,
  numero VARCHAR(40) NULL,
  numero_normalizzato VARCHAR(40) NULL,
  rubrica VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  ordinamento INT UNSIGNED NOT NULL DEFAULT 0,
  articolo_logico_key VARCHAR(255) NOT NULL,
  stato VARCHAR(40) NOT NULL DEFAULT 'vigente',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY normative_articoli_key_unique (articolo_logico_key),
  INDEX normative_articoli_documento_idx (documento_base_id),
  INDEX normative_articoli_nodo_idx (nodo_id),
  INDEX normative_articoli_numero_idx (numero_normalizzato),
  FULLTEXT normative_articoli_fulltext (rubrica),
  CONSTRAINT normative_articoli_documento_fk FOREIGN KEY (documento_base_id) REFERENCES normative_documenti(id) ON DELETE CASCADE,
  CONSTRAINT normative_articoli_nodo_fk FOREIGN KEY (nodo_id) REFERENCES normative_nodi(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE normative_articoli_versioni (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  articolo_id BIGINT UNSIGNED NOT NULL,
  documento_fonte_id BIGINT UNSIGNED NOT NULL,
  versione VARCHAR(60) NOT NULL DEFAULT '1',
  testo_integrale MEDIUMTEXT NOT NULL,
  testo_normalizzato MEDIUMTEXT NOT NULL,
  data_inizio_vigenza DATE NULL,
  data_fine_vigenza DATE NULL,
  stato_vigenza VARCHAR(40) NOT NULL DEFAULT 'da_verificare',
  tipo_modifica VARCHAR(40) NOT NULL DEFAULT 'consolidamento',
  fonte_modifica_testuale VARCHAR(255) NULL,
  pagina_inizio INT UNSIGNED NULL,
  pagina_fine INT UNSIGNED NULL,
  hash_testo CHAR(64) NOT NULL,
  needs_review TINYINT(1) NOT NULL DEFAULT 1,
  note_revisione TEXT NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX normative_versioni_articolo_idx (articolo_id),
  INDEX normative_versioni_fonte_idx (documento_fonte_id),
  INDEX normative_versioni_vigenza_idx (stato_vigenza, data_inizio_vigenza, data_fine_vigenza),
  FULLTEXT normative_versioni_fulltext (testo_normalizzato),
  CONSTRAINT normative_versioni_articolo_fk FOREIGN KEY (articolo_id) REFERENCES normative_articoli(id) ON DELETE CASCADE,
  CONSTRAINT normative_versioni_fonte_fk FOREIGN KEY (documento_fonte_id) REFERENCES normative_documenti(id) ON DELETE CASCADE,
  CONSTRAINT normative_versioni_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE normative_unita_testuali (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  versione_articolo_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  tipo_unita VARCHAR(40) NOT NULL DEFAULT 'capoverso',
  numero VARCHAR(40) NULL,
  etichetta VARCHAR(120) NULL,
  testo MEDIUMTEXT NOT NULL,
  testo_normalizzato MEDIUMTEXT NOT NULL,
  ordinamento INT UNSIGNED NOT NULL DEFAULT 0,
  livello TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pagina INT UNSIGNED NULL,
  anchor VARCHAR(120) NULL,
  hash_testo CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX normative_unita_versione_idx (versione_articolo_id),
  INDEX normative_unita_parent_idx (parent_id),
  INDEX normative_unita_tipo_idx (tipo_unita),
  FULLTEXT normative_unita_fulltext (testo_normalizzato),
  CONSTRAINT normative_unita_versione_fk FOREIGN KEY (versione_articolo_id) REFERENCES normative_articoli_versioni(id) ON DELETE CASCADE,
  CONSTRAINT normative_unita_parent_fk FOREIGN KEY (parent_id) REFERENCES normative_unita_testuali(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE normative_search_index (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(40) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  documento_id BIGINT UNSIGNED NOT NULL,
  titolo VARCHAR(255) NOT NULL,
  contenuto MEDIUMTEXT NOT NULL,
  contenuto_normalizzato MEDIUMTEXT NOT NULL,
  metadati_json JSON NULL,
  stato_vigenza VARCHAR(40) NOT NULL DEFAULT 'da_verificare',
  data_inizio DATE NULL,
  data_fine DATE NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY normative_search_entity_unique (entity_type, entity_id),
  INDEX normative_search_documento_idx (documento_id),
  INDEX normative_search_vigenza_idx (stato_vigenza, data_inizio, data_fine),
  FULLTEXT normative_search_fulltext (titolo, contenuto_normalizzato),
  CONSTRAINT normative_search_documento_fk FOREIGN KEY (documento_id) REFERENCES normative_documenti(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE normative_importazioni (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  documento_id BIGINT UNSIGNED NOT NULL,
  stato VARCHAR(40) NOT NULL DEFAULT 'caricata',
  fase VARCHAR(60) NOT NULL DEFAULT 'migrazione',
  avanzamento_percentuale TINYINT UNSIGNED NOT NULL DEFAULT 0,
  elementi_totali INT UNSIGNED NOT NULL DEFAULT 0,
  elementi_elaborati INT UNSIGNED NOT NULL DEFAULT 0,
  messaggio VARCHAR(500) NULL,
  errore TEXT NULL,
  configurazione_json JSON NULL,
  risultato_json JSON NULL,
  iniziata_il DATETIME NULL,
  completata_il DATETIME NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX normative_import_documento_idx (documento_id),
  INDEX normative_import_stato_idx (stato),
  CONSTRAINT normative_import_documento_fk FOREIGN KEY (documento_id) REFERENCES normative_documenti(id) ON DELETE CASCADE,
  CONSTRAINT normative_import_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
CREATE TABLE meeting_projection_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  meeting_id BIGINT UNSIGNED NOT NULL,
  public_token CHAR(64) NOT NULL UNIQUE,
  control_token CHAR(64) NOT NULL UNIQUE,
  status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
  active_slot TINYINT UNSIGNED NULL UNIQUE,
  current_document_id BIGINT UNSIGNED NULL,
  scroll_ratio DECIMAL(8,6) NOT NULL DEFAULT 0,
  revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  CONSTRAINT meeting_projection_meeting_fk FOREIGN KEY (meeting_id) REFERENCES union_meetings(id) ON DELETE CASCADE,
  CONSTRAINT meeting_projection_document_fk FOREIGN KEY (current_document_id) REFERENCES documents(id) ON DELETE SET NULL,
  CONSTRAINT meeting_projection_user_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);
