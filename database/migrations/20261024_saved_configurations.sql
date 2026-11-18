CREATE TABLE IF NOT EXISTS saved_configurations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  table_prefix VARCHAR(24) NOT NULL UNIQUE,
  tables_json LONGTEXT NULL,
  state ENUM('building', 'ready') NOT NULL DEFAULT 'ready',
  operational_key TINYINT UNSIGNED NULL UNIQUE,
  created_by BIGINT UNSIGNED NULL,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT saved_configurations_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT saved_configurations_updater_fk FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO saved_configurations (name, table_prefix, operational_key)
VALUES ('Situazione iniziale', '', 1);

CREATE TABLE IF NOT EXISTS configuration_sessions (
  token_id BIGINT UNSIGNED PRIMARY KEY,
  configuration_id BIGINT UNSIGNED NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT configuration_sessions_token_fk FOREIGN KEY (token_id) REFERENCES api_tokens(id) ON DELETE CASCADE,
  CONSTRAINT configuration_sessions_configuration_fk FOREIGN KEY (configuration_id) REFERENCES saved_configurations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
