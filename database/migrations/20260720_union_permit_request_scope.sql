ALTER TABLE union_permit_requests
  ADD COLUMN request_scope ENUM('internal', 'external') NOT NULL DEFAULT 'internal' AFTER document_id;
