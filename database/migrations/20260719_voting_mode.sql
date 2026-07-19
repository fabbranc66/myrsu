ALTER TABLE votings
  ADD COLUMN vote_mode ENUM('online', 'manual') NOT NULL DEFAULT 'online' AFTER anonymous;
