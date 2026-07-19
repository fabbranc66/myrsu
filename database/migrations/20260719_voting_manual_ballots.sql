ALTER TABLE voting_ballots
  ADD COLUMN source ENUM('token', 'manual') NOT NULL DEFAULT 'token' AFTER local_identifier_hash,
  ADD COLUMN recorded_by BIGINT UNSIGNED NULL AFTER source,
  ADD CONSTRAINT voting_ballots_recorded_by_fk FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL;
