ALTER TABLE votings
  ADD UNIQUE KEY votings_assembly_session_unique (assembly_id, session_id);
