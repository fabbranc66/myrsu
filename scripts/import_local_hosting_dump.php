<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
$host = (string)($config['host'] ?? '');
if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true) || ($config['database'] ?? '') !== 'myrsu') {
    throw new RuntimeException('Importazione consentita solo nel database myrsu locale.');
}
if (count($argv) !== 3 || !in_array($argv[2], ['--prepare', '--import'], true)) {
    throw new RuntimeException('Uso: php scripts/import_local_hosting_dump.php percorso.sql --prepare oppure --import');
}
$source = realpath($argv[1]);
if ($source === false || !is_file($source) || strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'sql') {
    throw new RuntimeException('Dump SQL non trovato.');
}
$client = dirname(PHP_BINARY, 2) . '/mysql/bin/mysql.exe';
if (!is_file($client)) throw new RuntimeException('Client MySQL locale non trovato.');
$originalHash = hash_file('sha256', $source);
$sql = file_get_contents($source);
if ($sql === false) throw new RuntimeException('Lettura del dump fallita.');
if (preg_match('/^\s*(?:(?:CREATE|ALTER|DROP)\s+(?:DATABASE|SCHEMA)\b|USE\b)/mi', $sql)) {
    throw new RuntimeException('Il dump contiene direttive di selezione o modifica del database: verificare prima di importare.');
}
$converted = preg_replace('/(\bCOLLATE\s*(?:=\s*)?)utf8mb4_0900_ai_ci\b/i', '${1}utf8mb4_unicode_ci', $sql, -1, $replacements);
if ($converted === null || stripos($converted, 'utf8mb4_0900_ai_ci') !== false) {
    throw new RuntimeException('Collation non convertita completamente: importazione annullata.');
}
$directory = $root . '/storage/private/imports';
if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Cartella privata di importazione non disponibile.');
$name = preg_replace('/[^A-Za-z0-9_.-]/', '_', pathinfo($source, PATHINFO_FILENAME));
$destination = $directory . '/' . $name . '_locale_' . date('Ymd_His') . '.sql';
$handle = fopen($destination, 'xb');
if ($handle === false) throw new RuntimeException('Creazione della copia SQL fallita.');
try {
    if (fwrite($handle, $converted) !== strlen($converted)) throw new RuntimeException('Copia SQL incompleta.');
} finally {
    fclose($handle);
}
if (hash_file('sha256', $destination) !== hash('sha256', $converted) || hash_file('sha256', $source) !== $originalHash) {
    throw new RuntimeException('Verifica della copia SQL fallita.');
}
unset($sql, $converted);
echo json_encode(['target' => $host . '/myrsu', 'collations_replaced' => $replacements, 'compatible_copy' => $destination], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
if ($argv[2] === '--prepare') exit;
$pdo = new PDO('mysql:host=' . $host . ';dbname=myrsu;charset=utf8mb4', (string)$config['username'], (string)$config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$before = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'myrsu'")->fetchColumn();
if ($before !== 0) throw new RuntimeException('Il database locale non è vuoto: nessuna importazione eseguita.');
if (!(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLLATIONS WHERE COLLATION_NAME = 'utf8mb4_unicode_ci'")->fetchColumn()) {
    throw new RuntimeException('Collation locale utf8mb4_unicode_ci non disponibile.');
}
$environment = getenv();
$environment['MYSQL_PWD'] = (string)$config['password'];
$process = proc_open([$client, '--protocol=TCP', '--host=' . $host, '--user=' . (string)$config['username'], '--default-character-set=utf8mb4', '--max_allowed_packet=64M', '--database=myrsu'], [0 => ['file', $destination, 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
if (!is_resource($process)) throw new RuntimeException('Avvio del client MySQL fallito.');
$output = stream_get_contents($pipes[1]);
$error = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
if ($exitCode !== 0) {
    $errorPath = $destination . '.error.txt';
    file_put_contents($errorPath, $error . "\n" . $output);
    throw new RuntimeException('Importazione interrotta. Non ripeterla sul database parziale. Dettagli nel file privato: ' . $errorPath);
}
$after = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'myrsu'")->fetchColumn();
$users = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$configurations = $pdo->query("SELECT id, table_prefix FROM saved_configurations WHERE operational_key = 1 AND state = 'ready'")->fetchAll(PDO::FETCH_ASSOC);
$documents = [];
foreach ($configurations as $configuration) {
    $prefix = (string)$configuration['table_prefix'];
    if ($prefix !== '' && !preg_match('/^cfg_[0-9]+_$/D', $prefix)) throw new RuntimeException('Prefisso configurazione inatteso dopo importazione.');
    $documents[] = ['configuration_id' => (int)$configuration['id'], 'documents' => (int)$pdo->query('SELECT COUNT(*) FROM `' . $prefix . 'documents`')->fetchColumn()];
}
echo json_encode(['import' => 'ok', 'tables_after' => $after, 'users' => $users, 'operational_documents' => $documents, 'original_unchanged' => hash_file('sha256', $source) === $originalHash], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
