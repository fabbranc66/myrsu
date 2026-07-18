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
