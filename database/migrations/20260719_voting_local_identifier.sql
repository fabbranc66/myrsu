ALTER TABLE voting_ballots
  ADD COLUMN local_identifier_hash VARCHAR(64) NULL AFTER ip_hash,
  ADD UNIQUE KEY voting_ballots_local_identifier_unique (voting_id, local_identifier_hash);
