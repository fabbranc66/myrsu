ALTER TABLE `cfg_2_meeting_projection_sessions`
  ADD COLUMN `pointer_page` SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `scroll_ratio`,
  ADD COLUMN `pointer_x` DECIMAL(8,6) NOT NULL DEFAULT 0.500000 AFTER `pointer_page`,
  ADD COLUMN `pointer_y` DECIMAL(8,6) NOT NULL DEFAULT 0.100000 AFTER `pointer_x`;
