ALTER TABLE `cfg_2_meeting_projection_sessions`
  ADD COLUMN `highlight_page` SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `highlight_y`,
  ADD COLUMN `highlight_end_x` DECIMAL(8,6) NULL DEFAULT NULL AFTER `highlight_page`,
  ADD COLUMN `highlight_end_y` DECIMAL(8,6) NULL DEFAULT NULL AFTER `highlight_end_x`;
