DELIMITER $$

DROP PROCEDURE IF EXISTS myrsu_document_visibility_20261025$$
CREATE PROCEDURE myrsu_document_visibility_20261025()
BEGIN
    DECLARE finished INT DEFAULT 0;
    DECLARE target_table VARCHAR(64);
    DECLARE present_column INT DEFAULT 0;
    DECLARE targets CURSOR FOR
        SELECT TABLE_NAME FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
          AND (TABLE_NAME IN ('documents', 'document_folders')
               OR TABLE_NAME REGEXP '^cfg_[0-9]+_(documents|document_folders)$')
        ORDER BY TABLE_NAME;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET finished = 1;

    OPEN targets;
    update_loop: LOOP
        FETCH targets INTO target_table;
        IF finished = 1 THEN LEAVE update_loop; END IF;
        IF target_table = 'documents' OR target_table REGEXP '^cfg_[0-9]+_documents$' THEN
            SELECT COUNT(*) INTO present_column FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = target_table
              AND COLUMN_NAME = 'visibility' AND COLUMN_TYPE LIKE '%''creator''%';
            IF present_column = 0 THEN
                SET @document_visibility_sql = CONCAT('ALTER TABLE `', target_table,
                    '` MODIFY COLUMN visibility ENUM(''public'',''members'',''rsu'',''creator'') NOT NULL DEFAULT ''rsu''');
                PREPARE document_visibility_statement FROM @document_visibility_sql;
                EXECUTE document_visibility_statement;
                DEALLOCATE PREPARE document_visibility_statement;
            END IF;
        ELSE
            SELECT COUNT(*) INTO present_column FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = target_table AND COLUMN_NAME = 'visibility';
            IF present_column = 0 THEN
                SET @document_visibility_sql = CONCAT('ALTER TABLE `', target_table,
                    '` ADD COLUMN visibility ENUM(''document'',''creator'',''rsu'',''members'',''public'') NULL AFTER inherit_permissions');
                PREPARE document_visibility_statement FROM @document_visibility_sql;
                EXECUTE document_visibility_statement;
                DEALLOCATE PREPARE document_visibility_statement;
            END IF;
        END IF;
    END LOOP;
    CLOSE targets;
END$$

CALL myrsu_document_visibility_20261025()$$
DROP PROCEDURE myrsu_document_visibility_20261025$$

DELIMITER ;
