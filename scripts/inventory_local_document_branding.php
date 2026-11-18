<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
$hosting = require $root . '/config/hosting.php';
if (!in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || $config['database'] !== 'myrsu') {
    throw new RuntimeException('Inventario consentito solo sul database myrsu locale.');
}
if (trim((string)($hosting['documents_endpoint'] ?? '')) !== '' || trim((string)($hosting['documents_token'] ?? '')) !== ''
    || env_value('EMAIL_SMTP_HOST', null) !== '' || env_value('EMAIL_IMAP_HOST', null) !== '') {
    throw new RuntimeException('Conservare isolamento hosting, SMTP e IMAP del locale.');
}
$pdo = new PDO('mysql:host=' . $config['host'] . ';dbname=myrsu;charset=utf8mb4', $config['username'], $config['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$snapshot = ['created_at' => date('c'), 'tables' => [], 'documents' => [], 'configurations' => []];
$tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'myrsu' AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    if (!preg_match('/^[a-z0-9_]+$/D', $table)) throw new RuntimeException('Nome tabella inatteso.');
    $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
    $encoded = array_map('serialize', $rows);
    sort($encoded);
    $snapshot['tables'][$table] = ['count' => count($rows), 'hash' => hash('sha256', implode("\n", $encoded))];
}
foreach ($pdo->query("SELECT id, table_prefix, operational_key FROM saved_configurations WHERE state = 'ready' ORDER BY id") as $configuration) {
    $prefix = (string)$configuration['table_prefix'];
    if ($prefix !== '' && !preg_match('/^cfg_[0-9]+_$/D', $prefix)) throw new RuntimeException('Prefisso inatteso.');
    $base = $prefix === '' ? $root : $root . '/storage/private/configurations/' . (int)$configuration['id'];
    $documents = $pdo->query('SELECT d.*, u.name AS creator_name FROM `' . $prefix . 'documents` d LEFT JOIN users u ON u.id = d.uploaded_by ORDER BY d.id')->fetchAll();
    $summary = ['id' => (int)$configuration['id'], 'prefix' => $prefix, 'operational' => (bool)$configuration['operational_key'], 'documents' => count($documents), 'ready' => 0, 'types' => [], 'missing_originals' => 0, 'missing_pdfs' => 0];
    foreach ($documents as $document) {
        $mime = (string)($document['original_mime_type'] ?? '');
        $summary['types'][$mime] = ($summary['types'][$mime] ?? 0) + 1;
        $original = $base . '/storage/private/originals/' . basename((string)$document['original_stored_name']);
        $relative = str_replace('\\', '/', (string)$document['pdf_public_path']);
        if (str_contains($relative, '..') || !str_starts_with($relative, 'public/documents/')) throw new RuntimeException('Percorso PDF inatteso.');
        $pdf = $base . '/' . $relative;
        if (!is_file($original)) $summary['missing_originals']++;
        if ($document['conversion_status'] === 'ready') {
            $summary['ready']++;
            if (!is_file($pdf)) $summary['missing_pdfs']++;
        }
        $protocol = $pdo->prepare('SELECT * FROM `' . $prefix . 'protocol_entries` WHERE document_id = ? ORDER BY id DESC LIMIT 1');
        $protocol->execute([(int)$document['id']]);
        $snapshot['documents'][] = [
            'configuration_id' => (int)$configuration['id'], 'table' => $prefix . 'documents', 'base' => $base,
            'document' => $document, 'protocol' => $protocol->fetch() ?: null,
            'original_path' => $original, 'pdf_path' => $pdf,
            'original_hash' => is_file($original) ? hash_file('sha256', $original) : null,
            'pdf_hash' => is_file($pdf) ? hash_file('sha256', $pdf) : null,
        ];
    }
    $snapshot['configurations'][] = $summary;
}
$directory = $root . '/storage/private/branding';
if (!is_dir($directory)) mkdir($directory, 0770, true);
$path = $directory . '/before-' . date('Ymd-His') . '.json';
if (is_file($path)) throw new RuntimeException('Inventario già presente.');
file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo json_encode(['inventory' => $path, 'tables' => count($tables), 'configurations' => $snapshot['configurations']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
