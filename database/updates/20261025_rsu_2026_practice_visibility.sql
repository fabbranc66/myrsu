SET @rsu_practice_prefix = NULL;

SELECT table_prefix INTO @rsu_practice_prefix
FROM saved_configurations
WHERE name = 'RSU 2026' AND state = 'ready';

SET @rsu_practice_update = IF(
    @rsu_practice_prefix IS NOT NULL
    AND (@rsu_practice_prefix = '' OR @rsu_practice_prefix REGEXP '^cfg_[0-9]+_$'),
    CONCAT('UPDATE `', @rsu_practice_prefix, 'practices` SET visibility = ''authorized'''),
    'SELECT ''Configurazione RSU 2026 non trovata: nessun dato modificato.'' AS errore'
);

PREPARE rsu_practice_visibility FROM @rsu_practice_update;
EXECUTE rsu_practice_visibility;
DEALLOCATE PREPARE rsu_practice_visibility;
