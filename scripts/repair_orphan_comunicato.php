<?php

declare(strict_types=1);

use App\Core\Application;

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require BASE_PATH . '/app/Core/helpers.php';
load_env(BASE_PATH . '/.env');
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

$protocolId = isset($argv[1]) ? (int)$argv[1] : 0;
$pdfName = $argv[2] ?? '';
$htmlName = $argv[3] ?? '';

if ($protocolId <= 0 || $pdfName === '' || $htmlName === '') {
    fwrite(STDERR, "Usage: php scripts/repair_orphan_comunicato.php <protocol_id> <pdf_name> <html_name>\n");
    exit(1);
}

$app = new Application(BASE_PATH);
$app->bootDatabase();

$protocol = $app->protocols->findById($protocolId);
if (!$protocol) {
    fwrite(STDERR, "Protocol not found\n");
    exit(1);
}

$pdfPath = BASE_PATH . '/public/documents/comunicati/' . basename($pdfName);
$htmlPath = BASE_PATH . '/storage/private/originals/' . basename($htmlName);

if (!is_file($pdfPath) || !is_file($htmlPath)) {
    fwrite(STDERR, "Missing files\n");
    exit(1);
}

$stored = [
    'original_name' => 'comunicato-' . date('Ymd-His', strtotime((string)$protocol['created_at'])) . '.html',
    'original_stored_name' => basename($htmlPath),
    'original_mime_type' => 'text/html',
    'original_size_bytes' => filesize($htmlPath),
    'original_checksum_sha256' => hash_file('sha256', $htmlPath),
    'category' => 'comunicati',
    'pdf_public_path' => 'public/documents/comunicati/' . basename($pdfPath),
    'pdf_size_bytes' => filesize($pdfPath),
    'pdf_checksum_sha256' => hash_file('sha256', $pdfPath),
    'conversion_status' => 'ready',
    'visibility' => 'public',
    'uploaded_by' => (int)$protocol['created_by'],
];

$document = $app->documents->create($stored);
$document = $app->documents->updateSignature((int)$document['id'], $app->documentSignature->sign($document));
$app->documentVerificationPage->append($pdfPath, $document, (string)$document['signature']);
$document = $app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
$protocol = $app->protocols->update($protocolId, (string)$protocol['subject'], (int)$document['id']);
$app->documentStorage->uploadPdfToHosting($document);

echo json_encode(['document' => $document, 'protocol' => $protocol], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
