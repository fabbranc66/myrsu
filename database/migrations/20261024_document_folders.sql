CREATE TABLE IF NOT EXISTS document_folders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id BIGINT UNSIGNED NULL,
  parent_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(parent_id, 0)) STORED,
  name VARCHAR(150) NOT NULL,
  inherit_permissions TINYINT(1) NOT NULL DEFAULT 1,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY document_folders_parent_name_unique (parent_key, name),
  CONSTRAINT document_folders_parent_fk FOREIGN KEY (parent_id) REFERENCES document_folders(id),
  CONSTRAINT document_folders_creator_fk FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_folder_items (
  document_id BIGINT UNSIGNED PRIMARY KEY,
  folder_id BIGINT UNSIGNED NOT NULL,
  placed_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  INDEX document_folder_items_folder_idx (folder_id),
  CONSTRAINT document_folder_items_document_fk FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT document_folder_items_folder_fk FOREIGN KEY (folder_id) REFERENCES document_folders(id),
  CONSTRAINT document_folder_items_user_fk FOREIGN KEY (placed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_folder_permissions (
  folder_id BIGINT UNSIGNED NOT NULL,
  role_id BIGINT UNSIGNED NOT NULL,
  access_level ENUM('read', 'manage') NOT NULL,
  PRIMARY KEY (folder_id, role_id),
  CONSTRAINT document_folder_permissions_folder_fk FOREIGN KEY (folder_id) REFERENCES document_folders(id) ON DELETE CASCADE,
  CONSTRAINT document_folder_permissions_role_fk FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO document_folders (parent_id, name, created_at, updated_at)
SELECT NULL, 'Sicurezza', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM document_folders WHERE parent_id IS NULL AND name = 'Sicurezza');
