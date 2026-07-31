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
);
