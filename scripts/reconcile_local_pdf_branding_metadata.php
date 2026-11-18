<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
$hosting = require $root . '/config/hosting.php';
if (!in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || $config['database'] !== 'myrsu' || trim((string)$hosting['documents_endpoint']) !== '' || trim((string)$hosting['documents_token']) !== '') throw new RuntimeException('Solo locale isolato.');
$directory = $root . '/storage/private/branding';
$applied = json_decode((string)file_get_contents($directory . '/applied.json'), true, 512, JSON_THROW_ON_ERROR);
$inventory = json_decode((string)file_get_contents($applied['inventory']), true, 512, JSON_THROW_ON_ERROR);
$manifest = json_decode((string)file_get_contents($directory . '/staged/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$before = json_decode((string)file_get_contents($directory . '/metadata-before.json'), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO('mysql:host=' . $config['host'] . ';dbname=myrsu;charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$updates = [];
foreach ($inventory['documents'] as $index => $entry) {
    $path = $entry['base'] . '/public/documents/metadata/' . (int)$entry['document']['id'] . '.json';
    if (!isset($before[$path])) continue;
    if (!preg_match('/^(?:cfg_[0-9]+_)?documents$/D', $entry['table'])) throw new RuntimeException('Tabella non valida.');
    $query = $pdo->prepare('SELECT * FROM `' . $entry['table'] . '` WHERE id = ?');
    $query->execute([$entry['document']['id']]);
    $row = $query->fetch();
    if ($row === false || $row['signature'] !== $entry['document']['signature'] || $row['pdf_public_path'] !== $entry['document']['pdf_public_path'] || $row['pdf_checksum_sha256'] !== $manifest['documents'][$index]['checksum'] || hash_file('sha256', $entry['pdf_path']) !== $row['pdf_checksum_sha256']) throw new RuntimeException('Documento modificato.');
    $current = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $legacy = json_decode($before[$path], true, 512, JSON_THROW_ON_ERROR);
    $legacy['pdf_checksum_sha256'] = $row['pdf_checksum_sha256'];
    if (isset($legacy['pdf_size_bytes'])) $legacy['pdf_size_bytes'] = (int)$row['pdf_size_bytes'];
    $correct = $legacy;
    foreach (['id', 'original_name', 'pdf_public_path', 'signature', 'signed_at'] as $field) $correct[$field] = $row[$field];
    if ($current == $correct) continue;
    if ($current != $legacy) throw new RuntimeException('Metadati cambiati durante verifica.');
    $updates[$path] = json_encode($correct, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
foreach ($updates as $path => $contents) {
    if (file_put_contents($path, $contents) !== strlen($contents)) throw new RuntimeException('Scrittura metadati fallita.');
}
echo 'Riallineati ' . count($updates) . ' metadati preesistenti al rispettivo documento. Nessuna firma o riga DB modificata.' . PHP_EOL;
