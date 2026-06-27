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

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

$targetDir = BASE_PATH . '/tmp/pdf-tests';
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0775, true);
}

$layout = new App\Services\PdfLayoutService();
$qr = new App\Services\PdfQrService();
$writer = new App\Services\PdfWriterService();
$verifyUrl = 'https://www.kr-solutions.it/myrsu/ui/document-verify.html?id=1&sig=TEST';
$text = $layout->text(42, 680, 15, App\Services\PdfLayoutService::FONT_BOLD, 'Test standard PDF unico');
$text .= $layout->wrappedText(
    42,
    650,
    11,
    88,
    'Questo PDF serve solo per validare formato A4, margini coerenti, font leggibile, header comune, footer comune e watermark uniforme.'
)['content'];

$page = $layout->page($text, [
    'number' => 'DOC-2026-0001',
    'protocol' => 'RSU-TEST-2026-0001',
    'date' => date('Y-m-d H:i'),
    'verify_text' => 'Verifica autenticita copia digitale',
]);
$page['images'][] = $qr->image($verifyUrl, 'Qr1', 492, 724, 60);
$page['links'][] = ['rect' => [260, 723, 430, 738], 'url' => $verifyUrl];
$page['links'][] = ['rect' => [492, 724, 552, 784], 'url' => $verifyUrl];
$writer->write($targetDir . '/step-1-layout-standard.pdf', [$page]);

echo $targetDir . '/step-1-layout-standard.pdf' . PHP_EOL;
