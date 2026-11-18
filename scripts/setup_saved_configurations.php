<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit('Solo CLI.');
$migration = $argv[1] ?? '20261024_saved_configurations.sql';
if (!in_array($migration, ['20261024_saved_configurations.sql', '20261024_archive_shares.sql', '20261025_practice_transfers.sql'], true)) throw new RuntimeException('Migration non consentita.');
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
if (!in_array($config['host'], ['localhost', '127.0.0.1'], true) || $config['database'] !== 'myrsu') throw new RuntimeException('Operazione consentita soltanto sul database locale myrsu.');
$pdo = new PDO('mysql:host=' . $config['host'] . ';dbname=' . $config['database'] . ';charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$directory = $root . '/storage/private/backups';
if (!is_dir($directory)) mkdir($directory, 0700, true);
$path = $directory . '/before_' . pathinfo($migration, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.sql';
$file = fopen($path, 'xb');
if ($file === false) throw new RuntimeException('Backup locale non disponibile.');
try {
    fwrite($file, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->beginTransaction();
    foreach ($tables as $table) {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) throw new RuntimeException('Nome tabella non valido.');
        $identifier = '`' . $table . '`';
        $create = $pdo->query('SHOW CREATE TABLE ' . $identifier)->fetch(PDO::FETCH_NUM);
        fwrite($file, 'DROP TABLE IF EXISTS ' . $identifier . ";\n" . $create[1] . ";\n");
        $columns = $pdo->query('SHOW COLUMNS FROM ' . $identifier)->fetchAll();
        $columns = array_column(array_filter($columns, static fn (array $column): bool => !preg_match('/(VIRTUAL|STORED) GENERATED/', $column['Extra'])), 'Field');
        $columnSql = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
        $rows = $pdo->query('SELECT ' . $columnSql . ' FROM ' . $identifier);
        while ($row = $rows->fetch(PDO::FETCH_NUM)) {
            $values = array_map(static fn (mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string)$value), $row);
            if (fwrite($file, 'INSERT INTO ' . $identifier . ' (' . $columnSql . ') VALUES (' . implode(', ', $values) . ");\n") === false) throw new RuntimeException('Scrittura backup fallita.');
        }
    }
    fwrite($file, "SET FOREIGN_KEY_CHECKS=1;\n");
    $pdo->commit();
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fclose($file);
}
$sql = file_get_contents($root . '/database/migrations/' . $migration);
$pdo->exec($sql);
echo 'Backup locale: ' . $path . PHP_EOL . 'Migration configurazioni applicata. Dati operativi esistenti invariati.' . PHP_EOL;
