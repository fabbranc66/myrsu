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
