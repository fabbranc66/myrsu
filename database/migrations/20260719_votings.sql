CREATE TABLE IF NOT EXISTS votings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('draft', 'open', 'closed', 'cancelled') NOT NULL DEFAULT 'draft',
  anonymous TINYINT(1) NOT NULL DEFAULT 1,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  assembly_id BIGINT UNSIGNED NULL,
  session_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX votings_status_idx (status),
  INDEX votings_assembly_idx (assembly_id),
  CONSTRAINT votings_assembly_fk FOREIGN KEY (assembly_id) REFERENCES workers_assemblies(id) ON DELETE SET NULL,
  CONSTRAINT votings_session_fk FOREIGN KEY (session_id) REFERENCES workers_assembly_sessions(id) ON DELETE SET NULL,
  CONSTRAINT votings_created_by_fk FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voting_options (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voting_id BIGINT UNSIGNED NOT NULL,
  label VARCHAR(150) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  INDEX voting_options_voting_idx (voting_id),
  CONSTRAINT voting_options_voting_fk FOREIGN KEY (voting_id) REFERENCES votings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voting_tokens (
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

CREATE TABLE IF NOT EXISTS voting_ballots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voting_id BIGINT UNSIGNED NOT NULL,
  option_id BIGINT UNSIGNED NOT NULL,
  token_id BIGINT UNSIGNED NULL,
  voter_user_id BIGINT UNSIGNED NULL,
  ip_hash VARCHAR(64) NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY voting_ballots_token_unique (token_id),
  INDEX voting_ballots_voting_idx (voting_id),
  CONSTRAINT voting_ballots_voting_fk FOREIGN KEY (voting_id) REFERENCES votings(id) ON DELETE CASCADE,
  CONSTRAINT voting_ballots_option_fk FOREIGN KEY (option_id) REFERENCES voting_options(id) ON DELETE CASCADE,
  CONSTRAINT voting_ballots_token_fk FOREIGN KEY (token_id) REFERENCES voting_tokens(id) ON DELETE SET NULL,
  CONSTRAINT voting_ballots_user_fk FOREIGN KEY (voter_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

