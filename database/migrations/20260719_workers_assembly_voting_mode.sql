ALTER TABLE workers_assemblies
  ADD COLUMN voting_mode ENUM('online', 'manual') NOT NULL DEFAULT 'online' AFTER voting_enabled;
