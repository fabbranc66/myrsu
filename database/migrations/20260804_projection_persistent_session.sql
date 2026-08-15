SET @projection_keeper = (
  SELECT id
  FROM meeting_projection_sessions
  ORDER BY (status = 'active') DESC, id ASC
  LIMIT 1
);

DELETE FROM meeting_projection_sessions
WHERE @projection_keeper IS NOT NULL AND id <> @projection_keeper;

ALTER TABLE meeting_projection_sessions
  DROP FOREIGN KEY meeting_projection_meeting_fk;

ALTER TABLE meeting_projection_sessions
  MODIFY meeting_id BIGINT UNSIGNED NULL,
  MODIFY status ENUM('active', 'closed', 'inactive') NOT NULL DEFAULT 'inactive',
  ADD COLUMN singleton_key TINYINT UNSIGNED NOT NULL DEFAULT 1 UNIQUE AFTER id;

ALTER TABLE meeting_projection_sessions
  ADD CONSTRAINT meeting_projection_meeting_fk
  FOREIGN KEY (meeting_id) REFERENCES union_meetings(id) ON DELETE SET NULL;

UPDATE meeting_projection_sessions
SET status = 'inactive', active_slot = NULL, current_document_id = NULL, scroll_ratio = 0;

ALTER TABLE meeting_projection_sessions
  MODIFY status ENUM('active', 'inactive') NOT NULL DEFAULT 'inactive';
