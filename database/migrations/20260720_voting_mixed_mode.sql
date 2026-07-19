ALTER TABLE workers_assemblies
  MODIFY COLUMN voting_mode ENUM('online', 'manual', 'mixed') NOT NULL DEFAULT 'online';

ALTER TABLE votings
  MODIFY COLUMN vote_mode ENUM('online', 'manual', 'mixed') NOT NULL DEFAULT 'online';
