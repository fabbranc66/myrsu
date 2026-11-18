ALTER TABLE `meeting_projection_sessions`
  ADD COLUMN `highlight_x` DECIMAL(8,6) NULL DEFAULT NULL AFTER `pointer_y`,
  ADD COLUMN `highlight_y` DECIMAL(8,6) NULL DEFAULT NULL AFTER `highlight_x`;
