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

$sampleImage = $targetDir . '/sample-image.jpg';
$canvas = imagecreatetruecolor(1200, 780);
imagefill($canvas, 0, 0, imagecolorallocate($canvas, 230, 240, 255));
imagefilledrectangle($canvas, 60, 60, 1140, 720, imagecolorallocate($canvas, 180, 210, 245));
imagefilledellipse($canvas, 600, 390, 520, 360, imagecolorallocate($canvas, 55, 115, 180));
imagefilledrectangle($canvas, 120, 560, 1080, 640, imagecolorallocate($canvas, 250, 250, 250));
imagestring($canvas, 5, 470, 590, 'IMMAGINE FIT SOTTO WATERMARK', imagecolorallocate($canvas, 20, 30, 45));
imagejpeg($canvas, $sampleImage, 94);
imagedestroy($canvas);

$layout = new App\Services\PdfLayoutService();
$fit = new App\Services\PdfImageFitService();
$qr = new App\Services\PdfQrService();
$writer = new App\Services\PdfWriterService();
$verifyUrl = 'https://www.kr-solutions.it/myrsu/ui/document-verify.html?id=2&sig=TEST';
$body = $layout->text(42, 680, 15, App\Services\PdfLayoutService::FONT_BOLD, 'Test immagine');
$body .= $layout->text(42, 660, 10, App\Services\PdfLayoutService::FONT_REGULAR, 'Immagine adattata a pagina, watermark in overlay.');
$page = $layout->page($body, [
    'number' => 'IMG-2026-0001',
    'protocol' => 'RSU-TEST-IMG-2026-0001',
    'date' => date('Y-m-d H:i'),
    'verify_text' => 'Verifica autenticita copia digitale',
]);
$page['images'][] = $qr->image($verifyUrl, 'Qr1', 492, 724, 60);
$page['images'][] = $fit->image($sampleImage, 'Im1', 500, 430, 297.64, 390);
$page['links'][] = ['rect' => [260, 723, 430, 738], 'url' => $verifyUrl];
$page['links'][] = ['rect' => [492, 724, 552, 784], 'url' => $verifyUrl];
$writer->write($targetDir . '/step-1-image-watermark.pdf', [$page]);

echo $targetDir . '/step-1-image-watermark.pdf' . PHP_EOL;
