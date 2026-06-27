<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require BASE_PATH . '/vendor/autoload.php';

$targetDir = BASE_PATH . '/tmp/pdf-tests';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

$service = new App\Services\ComunicatoDirectPdfService(
    new App\Services\PdfLayoutService(),
    new App\Services\PdfWriterService(),
    new App\Services\PdfQrService()
);

$service->write(
    $targetDir . '/test-comunicato-direct.pdf',
    'Convocazione incontro sindacale',
    "Partecipanti:\nRSU, Direzione\n\nOrdine del giorno:\nMicroclima e sicurezza\n\nLuogo:\nSala Direzionale\n\nData e ora:\n2026-06-29 09:00",
    'RSU-OUT-COM-2026-0001',
    date('Y-m-d H:i'),
    '100',
    'https://www.kr-solutions.it/myrsu/ui/document-verify.html?id=100&sig=TEST'
);

echo $targetDir . '/test-comunicato-direct.pdf' . PHP_EOL;
