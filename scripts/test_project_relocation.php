<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
$hosting = require $root . '/config/hosting.php';
if (!in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || $config['database'] !== 'myrsu'
    || trim((string)$hosting['documents_endpoint']) !== '' || trim((string)$hosting['documents_token']) !== ''
    || env_value('EMAIL_SMTP_HOST') !== '' || env_value('EMAIL_IMAP_HOST') !== '') throw new RuntimeException('Verifica consentita solo nel locale isolato.');
$mode = $argv[1] ?? '';
if (!in_array($mode, ['--capture', '--verify'], true)) throw new RuntimeException('Usare --capture oppure --verify riferimento.json.');
$pdo = new PDO('mysql:host=' . $config['host'] . ';dbname=myrsu;charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$snapshot = ['root' => $root, 'captured_at' => date('c'), 'files' => [], 'tables' => [], 'absolute_references' => []];
foreach (['app', 'config', 'routes', 'ui', 'scripts', 'vendor', 'public/documents', 'storage/private/originals', 'storage/private/configurations'] as $directory) {
    $path = $root . '/' . $directory;
    if (!is_dir($path)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || str_contains($file->getPathname(), '__pycache__')) continue;
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        $snapshot['files'][$relative] = hash_file('sha256', $file->getPathname());
    }
}
foreach (['.env', '.htaccess', 'index.php', 'composer.json', 'composer.lock', 'AGENTS.md', 'README.md', '.git/HEAD'] as $relative) {
    if (is_file($root . '/' . $relative)) $snapshot['files'][$relative] = hash_file('sha256', $root . '/' . $relative);
}
ksort($snapshot['files']);
$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'myrsu' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    if (!preg_match('/^[a-z0-9_]+$/D', $table)) throw new RuntimeException('Tabella non valida.');
    $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
    $encoded = array_map('serialize', $rows);
    sort($encoded);
    $snapshot['tables'][$table] = ['count' => count($rows), 'hash' => hash('sha256', implode("\n", $encoded))];
    foreach ($rows as $row) {
        foreach ($row as $field => $value) {
            if (is_string($value) && str_contains(strtolower(str_replace('\\', '/', $value)), 'c:/xampp2/htdocs/myrsu')) {
                $key = $table . '.' . $field;
                $snapshot['absolute_references'][$key] = ($snapshot['absolute_references'][$key] ?? 0) + 1;
            }
        }
    }
}
$directory = $root . '/storage/private/relocation';
if (!is_dir($directory)) mkdir($directory, 0770, true);
if ($mode === '--capture') {
    $path = $directory . '/before-' . date('Ymd-His') . '.json';
    if (file_exists($path)) throw new RuntimeException('Riferimento gia presente.');
    file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo json_encode(['reference' => $path, 'files' => count($snapshot['files']), 'tables' => count($tables), 'absolute_references' => $snapshot['absolute_references']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
    exit;
}
$referencePath = realpath($argv[2] ?? '');
if ($referencePath === false || !str_starts_with($referencePath, realpath($directory) . DIRECTORY_SEPARATOR)) throw new RuntimeException('Riferimento locale non valido.');
$before = json_decode((string)file_get_contents($referencePath), true, 512, JSON_THROW_ON_ERROR);
if ($before['files'] !== $snapshot['files']) throw new RuntimeException('File aggiunti, rimossi o modificati durante la rinomina.');
if (array_keys($before['tables']) !== array_keys($snapshot['tables'])) throw new RuntimeException('Schema cambiato durante la rinomina.');
$sessions = [];
foreach ($before['tables'] as $table => $value) {
    if ($value === $snapshot['tables'][$table]) continue;
    if (in_array($table, ['activity_logs', 'api_tokens'], true)) $sessions[] = $table;
    else throw new RuntimeException('Dati modificati durante la rinomina: ' . $table);
}
$path = $directory . '/after-' . date('Ymd-His') . '.json';
file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo json_encode(['verified_files' => count($snapshot['files']), 'business_tables_unchanged' => count($tables) - count($sessions), 'session_changes' => $sessions, 'absolute_references' => $snapshot['absolute_references'], 'report' => $path], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
