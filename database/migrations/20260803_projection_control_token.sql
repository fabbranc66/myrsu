ALTER TABLE meeting_projection_sessions
  ADD COLUMN control_token CHAR(64) NULL UNIQUE AFTER public_token;

UPDATE meeting_projection_sessions
SET control_token = SHA2(CONCAT(public_token, id, created_at), 256)
WHERE control_token IS NULL;

ALTER TABLE meeting_projection_sessions
  MODIFY control_token CHAR(64) NOT NULL;
