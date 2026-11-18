<?php

declare(strict_types=1);

use App\Services\FpdiUploadedPdfService;
use App\Services\PdfLayoutService;
use App\Services\PdfQrService;
use App\Services\PdfWriterService;
use App\Services\UploadedDocumentPdfService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
$inventoryPath = realpath($argv[1] ?? '');
$private = realpath($root . '/storage/private/branding/staged');
if ($inventoryPath === false || $private === false || !str_starts_with($inventoryPath, $private . DIRECTORY_SEPARATOR)) throw new RuntimeException('Inventario locale non valido.');
$inventory = json_decode((string)file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
$qr = new PdfQrService();
$service = new UploadedDocumentPdfService(new PdfLayoutService(), new PdfWriterService(), $qr, new FpdiUploadedPdfService($qr));
$manifest = ['inventory' => $inventoryPath, 'documents' => []];
foreach ($inventory['documents'] as $entry) {
    if (!str_starts_with((string)$entry['document']['original_mime_type'], 'image/') || hash_file('sha256', $entry['original_path']) !== $entry['original_hash'] || hash_file('sha256', $entry['pdf_path']) !== $entry['pdf_hash']) throw new RuntimeException('Immagine o PDF modificati.');
    $directory = dirname($inventoryPath) . '/cfg-' . (int)$entry['configuration_id'] . '-' . (int)$entry['document']['id'];
    if (!is_dir($directory)) mkdir($directory, 0770, true);
    $path = $directory . '/' . basename($entry['pdf_path']);
    $service->write($entry['original_path'], $path, $entry['document'], $entry['protocol'], $entry['verify_url'], $entry['document']['signature']);
    $manifest['documents'][] = ['configuration_id' => $entry['configuration_id'], 'id' => $entry['document']['id'], 'staged_path' => $path, 'checksum' => hash_file('sha256', $path), 'size' => filesize($path)];
}
file_put_contents(dirname($inventoryPath) . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo 'Copie da immagini preparate: ' . count($manifest['documents']) . PHP_EOL;
