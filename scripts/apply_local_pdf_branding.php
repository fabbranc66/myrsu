<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
$hosting = require $root . '/config/hosting.php';
if (!in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || $config['database'] !== 'myrsu'
    || trim((string)($hosting['documents_endpoint'] ?? '')) !== '' || trim((string)($hosting['documents_token'] ?? '')) !== ''
    || env_value('EMAIL_SMTP_HOST') !== '' || env_value('EMAIL_IMAP_HOST') !== '') {
    throw new RuntimeException('Operazione consentita solo sul locale isolato.');
}
$private = realpath($root . '/storage/private/branding');
$inventoryPath = realpath($argv[1] ?? '');
if ($private === false || $inventoryPath === false || !str_starts_with($inventoryPath, $private . DIRECTORY_SEPARATOR)) throw new RuntimeException('Inventario non valido.');
$inventory = json_decode((string)file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
$manifestPath = realpath($argv[3] ?? ($private . '/staged/manifest.json'));
if ($manifestPath === false || !str_starts_with($manifestPath, $private . DIRECTORY_SEPARATOR . 'staged' . DIRECTORY_SEPARATOR)) throw new RuntimeException('Manifest locale non valido.');
$runDirectory = isset($argv[3]) ? dirname($manifestPath) : $private;
$manifest = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
if (realpath($manifest['inventory']) !== $inventoryPath || count($manifest['documents']) !== count($inventory['documents'])) throw new RuntimeException('Manifest non coerente.');
$mode = $argv[2] ?? '--check';
if (!in_array($mode, ['--check', '--apply', '--verify'], true)) throw new RuntimeException('Modalita non valida.');
$pdo = new PDO('mysql:host=' . $config['host'] . ';dbname=myrsu;charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$documentTables = [];
$jobs = [];
foreach ($inventory['documents'] as $index => $entry) {
    $target = $manifest['documents'][$index];
    $table = $entry['table'];
    if (!preg_match('/^(?:cfg_[0-9]+_)?documents$/D', $table) || $target['id'] !== $entry['document']['id'] || $target['configuration_id'] !== $entry['configuration_id']) throw new RuntimeException('Documento non coerente.');
    $documentTables[$table] = true;
    $pdf = realpath($entry['pdf_path']);
    $allowed = realpath($entry['base'] . '/public/documents');
    if ($pdf === false || $allowed === false || !str_starts_with($pdf, $allowed . DIRECTORY_SEPARATOR) || $entry['base'] !== $root && !str_starts_with(realpath($entry['base']) ?: '', realpath($root . '/storage/private/configurations') . DIRECTORY_SEPARATOR)) throw new RuntimeException('Percorso PDF non valido.');
    if (hash_file('sha256', $entry['original_path']) !== $entry['original_hash']) throw new RuntimeException('Originale modificato.');
    $expectedHash = $mode === '--verify' ? $target['checksum'] : $entry['pdf_hash'];
    if (hash_file('sha256', $pdf) !== $expectedHash) throw new RuntimeException('PDF modificato rispetto al riferimento.');
    if ($mode !== '--verify') {
        $staged = realpath($target['staged_path']);
        if ($staged === false || !str_starts_with($staged, $private . DIRECTORY_SEPARATOR . 'staged' . DIRECTORY_SEPARATOR) || hash_file('sha256', $staged) !== $target['checksum'] || filesize($staged) !== $target['size']) throw new RuntimeException('PDF preparato non valido.');
    }
    $query = $pdo->prepare('SELECT * FROM `' . $table . '` WHERE id = ?');
    $query->execute([$entry['document']['id']]);
    $expected = $entry['document'];
    unset($expected['creator_name']);
    if ($mode === '--verify') {
        $expected['size_bytes'] = $expected['pdf_size_bytes'] = $target['size'];
        $expected['checksum_sha256'] = $expected['pdf_checksum_sha256'] = $target['checksum'];
    }
    if ($query->fetch() != $expected) throw new RuntimeException('Dati documento modificati: ' . $table . '/' . $entry['document']['id']);
    $jobs[] = ['entry' => $entry, 'target' => $target, 'pdf' => $pdf, 'expected' => $expected];
}
foreach ($inventory['tables'] as $table => $reference) {
    if (isset($documentTables[$table])) continue;
    if (!preg_match('/^[a-z0-9_]+$/D', $table)) throw new RuntimeException('Tabella non valida.');
    $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
    $encoded = array_map('serialize', $rows);
    sort($encoded);
    if (count($rows) !== $reference['count'] || hash('sha256', implode("\n", $encoded)) !== $reference['hash']) throw new RuntimeException('Tabella modificata: ' . $table);
}
$metadataPath = $runDirectory . '/metadata-before.json';
if (!is_file($metadataPath) && $mode !== '--verify') {
    $metadata = [];
    foreach ($jobs as $job) {
        $path = $job['entry']['base'] . '/public/documents/metadata/' . (int)$job['entry']['document']['id'] . '.json';
        if (is_file($path)) $metadata[$path] = (string)file_get_contents($path);
    }
    file_put_contents($metadataPath, json_encode($metadata, JSON_THROW_ON_ERROR));
}
$metadata = json_decode((string)file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
foreach ($jobs as $job) {
    $path = $job['entry']['base'] . '/public/documents/metadata/' . (int)$job['entry']['document']['id'] . '.json';
    if (!isset($metadata[$path])) {
        if (is_file($path)) throw new RuntimeException('Metadati aggiunti durante la preparazione.');
        continue;
    }
    $expected = json_decode($metadata[$path], true, 512, JSON_THROW_ON_ERROR);
    $actual = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if ($mode === '--verify') {
        $expected['pdf_checksum_sha256'] = $job['target']['checksum'];
        if (isset($expected['pdf_size_bytes'])) $expected['pdf_size_bytes'] = $job['target']['size'];
        foreach (['id', 'original_name', 'pdf_public_path', 'signature', 'signed_at'] as $field) $expected[$field] = $job['entry']['document'][$field];
    }
    if ($actual != $expected) throw new RuntimeException('Metadati di verifica modificati.');
}
if ($mode !== '--apply') {
    echo 'OK ' . $mode . ': ' . count($jobs) . ' documenti, originali, dati e metadati coerenti; altre ' . (count($inventory['tables']) - count($documentTables)) . ' tabelle invariate.' . PHP_EOL;
    exit;
}
$replacements = [];
$pdo->beginTransaction();
try {
    foreach ($jobs as $job) {
        $entry = $job['entry'];
        $target = $job['target'];
        $lock = $pdo->prepare('SELECT * FROM `' . $entry['table'] . '` WHERE id = ? FOR UPDATE');
        $lock->execute([$entry['document']['id']]);
        if ($lock->fetch() != $job['expected'] || hash_file('sha256', $job['pdf']) !== $entry['pdf_hash']) throw new RuntimeException('Documento cambiato durante operazione.');
        $paths = [$job['pdf'] => (string)file_get_contents($target['staged_path'])];
        $meta = $entry['base'] . '/public/documents/metadata/' . (int)$entry['document']['id'] . '.json';
        if (isset($metadata[$meta])) {
            $data = json_decode($metadata[$meta], true, 512, JSON_THROW_ON_ERROR);
            $data['pdf_checksum_sha256'] = $target['checksum'];
            if (isset($data['pdf_size_bytes'])) $data['pdf_size_bytes'] = $target['size'];
            foreach (['id', 'original_name', 'pdf_public_path', 'signature', 'signed_at'] as $field) $data[$field] = $entry['document'][$field];
            $paths[$meta] = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        foreach ($paths as $path => $bytes) {
            $backup = $path . '.branding-rollback';
            $temporary = $path . '.branding-new';
            if (file_exists($backup) || file_exists($temporary)) throw new RuntimeException('Sostituzione precedente da controllare.');
            if (file_put_contents($temporary, $bytes) !== strlen($bytes)) throw new RuntimeException('Scrittura temporanea fallita.');
            if (!rename($path, $backup)) { unlink($temporary); throw new RuntimeException('Preparazione sostituzione fallita.'); }
            $replacements[$path] = $backup;
            if (!rename($temporary, $path)) throw new RuntimeException('Sostituzione fallita.');
        }
        $update = $pdo->prepare('UPDATE `' . $entry['table'] . '` SET size_bytes = ?, checksum_sha256 = ?, pdf_size_bytes = ?, pdf_checksum_sha256 = ? WHERE id = ?');
        $update->execute([$target['size'], $target['checksum'], $target['size'], $target['checksum'], $entry['document']['id']]);
        if (hash_file('sha256', $job['pdf']) !== $target['checksum']) throw new RuntimeException('Checksum dopo sostituzione non valido.');
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach (array_reverse($replacements, true) as $path => $backup) {
        if (is_file($path)) unlink($path);
        if (!rename($backup, $path)) throw new RuntimeException('Ripristino da completare manualmente: ' . $path, 0, $error);
        if (is_file($path . '.branding-new')) unlink($path . '.branding-new');
    }
    throw $error;
}
foreach ($replacements as $backup) {
    if (!unlink($backup)) throw new RuntimeException('Rimuovere copia transitoria: ' . $backup);
}
file_put_contents($runDirectory . '/applied.json', json_encode(['applied_at' => date('c'), 'inventory' => $inventoryPath, 'documents' => count($jobs), 'pdf_versions_kept' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Aggiornati ' . count($jobs) . ' PDF locali e ' . count($metadata) . ' metadati di verifica. Nessuna versione PDF precedente conservata.' . PHP_EOL;
