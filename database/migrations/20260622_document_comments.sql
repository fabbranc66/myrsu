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

INSERT IGNORE INTO permissions (name, label)
VALUES ('comments.moderate', 'Moderare commenti');

INSERT IGNORE INTO permission_role (permission_id, role_id)
SELECT p.id, r.id
FROM permissions p
INNER JOIN roles r ON r.name IN ('admin', 'delegato')
WHERE p.name = 'comments.moderate';
