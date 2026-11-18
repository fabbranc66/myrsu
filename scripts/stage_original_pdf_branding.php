<?php

declare(strict_types=1);

use App\Services\FpdiUploadedPdfService;
use App\Services\PdfQrService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
$inventoryPath = realpath($argv[1] ?? '');
$private = realpath($root . '/storage/private/branding');
if ($inventoryPath === false || $private === false || !str_starts_with($inventoryPath, $private . DIRECTORY_SEPARATOR)) throw new RuntimeException('Inventario locale non valido.');
$inventory = json_decode((string)file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
$rebuilt = [];
foreach ($inventory['documents'] as $entry) {
    if ($entry['pdf_hash'] !== $entry['original_hash']) continue;
    if ($entry['document']['original_mime_type'] !== 'application/pdf' || $entry['document']['signature'] === null) throw new RuntimeException('Originale da verificare manualmente.');
    if (hash_file('sha256', $entry['original_path']) !== $entry['original_hash']) throw new RuntimeException('Originale modificato.');
    $directory = $private . '/staged/rebuilt/cfg-' . $entry['configuration_id'] . '-' . $entry['document']['id'];
    if (!is_dir($directory)) mkdir($directory, 0770, true);
    $target = $directory . '/' . basename($entry['pdf_path']);
    $url = 'http://localhost/miodel/ui/document-verify.html?id=' . (int)$entry['document']['id'] . '&sig=' . rawurlencode((string)$entry['document']['signature']);
    if ($entry['table'] !== 'documents') $url .= '&source=' . (int)$entry['configuration_id'];
    $source = $private . '/staged/normalized/cfg-' . $entry['configuration_id'] . '-' . $entry['document']['id'] . '.pdf';
    if (!is_file($source)) throw new RuntimeException('Preparare prima la copia compatibile in staging.');
    (new FpdiUploadedPdfService(new PdfQrService()))->write($source, $target, $entry['document'], $entry['protocol'], $url, $entry['document']['signature']);
    $rebuilt[$entry['configuration_id'] . ':' . $entry['document']['id']] = $target;
}
file_put_contents($private . '/staged/rebuilt.json', json_encode($rebuilt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo 'PDF precedentemente privi di intestazione: ' . count($rebuilt) . PHP_EOL;
