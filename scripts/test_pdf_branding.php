<?php

declare(strict_types=1);

use App\Services\ComunicatoDirectPdfService;
use App\Services\FpdiUploadedPdfService;
use App\Services\PdfBrandingService;
use App\Services\PdfLayoutService;
use App\Services\PdfQrService;
use App\Services\PdfWriterService;
use App\Services\ReportPdfService;
use App\Services\UploadedDocumentPdfService;
use App\Services\UnionPermitPdfService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
$directory = $root . '/storage/private/branding/fixtures';
if (!is_dir($directory)) mkdir($directory, 0770, true);
$layout = new PdfLayoutService();
$writer = new PdfWriterService();
$qr = new PdfQrService();
$opaqueImage = imagecreatetruecolor(600, 800);
imagefill($opaqueImage, 0, 0, imagecolorallocate($opaqueImage, 140, 155, 170));
imagefilledrectangle($opaqueImage, 40, 80, 560, 180, imagecolorallocate($opaqueImage, 55, 85, 110));
imagepng($opaqueImage, $directory . '/opaque.png');
imagedestroy($opaqueImage);
$assets = PdfBrandingService::assets();
if ($assets['BrandSymbol']['width'] > $assets['BrandLogo']['width'] * 0.265) throw new RuntimeException('Simbolo non isolato.');
foreach ($assets as $image) {
    if (strlen(gzuncompress($image['data'])) !== $image['width'] * $image['height'] * 3) throw new RuntimeException('RGB non valido.');
    if (strlen(gzuncompress($image['alpha'])) !== $image['width'] * $image['height']) throw new RuntimeException('Trasparenza non valida.');
}
if ($assets['BrandSymbolBase']['alpha'] !== $assets['BrandSymbol']['alpha']) throw new RuntimeException('Contrasto non trasparente.');
if (gzuncompress($assets['BrandSymbolBase']['data']) !== str_repeat("\xFF", $assets['BrandSymbolBase']['width'] * $assets['BrandSymbolBase']['height'] * 3)) throw new RuntimeException('Sagoma di contrasto non valida.');
$writer->write($directory . '/overlay.pdf', [['content' => PdfBrandingService::watermark() . PdfBrandingService::header() . $layout->footer(PdfBrandingService::FOOTER)]]);
$writer->write($directory . '/watermark-only.pdf', [['content' => PdfBrandingService::watermark() . $layout->footer(PdfBrandingService::FOOTER)]]);
$writer->write($directory . '/source.pdf', [['content' => $layout->text(42, 660, 14, 'F2', 'Contenuto originale di prova: leggibile e invariato.')]]);
$url = 'http://localhost/miodel/ui/document-verify.html?id=2147483647&sig=' . str_repeat('A', 24);
$document = ['id' => 2147483647, 'original_name' => 'Prova.pdf', 'created_at' => '2026-11-14 18:00:00', 'creator_name' => 'Autore di prova', 'original_mime_type' => 'application/pdf', 'pdf_public_path' => 'public/documents/prova.pdf'];
$protocol = ['protocol_number' => 'TEST/2026/1', 'created_at' => $document['created_at']];
$fpdi = new FpdiUploadedPdfService($qr);
$fpdi->write($directory . '/source.pdf', $directory . '/uploaded.pdf', $document, $protocol, $url, str_repeat('A', 24));
(new UploadedDocumentPdfService($layout, $writer, $qr, $fpdi))->writeFromImage($directory . '/opaque.png', $directory . '/image.pdf', $document, $protocol, $url, str_repeat('A', 24));
(new ComunicatoDirectPdfService($layout, $writer, $qr))->write($directory . '/comunicato.pdf', 'Prova comunicato', 'Testo di prova senza modifiche al flusso documentale.', 'TEST/2026/1', $document['created_at'], null, $url, str_repeat('A', 24));
(new ReportPdfService($layout, $qr))->write($directory . '/report.pdf', ['tracking_code' => 'TEST', 'subject' => 'Verifica grafica', 'contact' => '-', 'message' => 'Testo della segnalazione.', 'created_at' => $document['created_at']], [['kind' => 'image', 'path' => $directory . '/opaque.png', 'original_name' => 'Immagine opaca di prova']], str_repeat('A', 24), $url);
$request = ['subject' => 'Prova permesso', 'union_name' => 'FIM-CISL', 'request_scope' => 'external', 'permit_type' => 'rsu', 'start_at' => '2026-11-14 09:00:00', 'end_at' => '2026-11-14 10:00:00', 'hours' => 1];
(new UnionPermitPdfService($layout, $writer, $qr))->write($directory . '/permit.pdf', $request, ['name' => 'Delegato di prova']);
echo json_encode(['fixtures' => $directory, 'symbol' => [$assets['BrandSymbol']['width'], $assets['BrandSymbol']['height']], 'checks' => 'Trasparenza, simbolo isolato e cinque generatori verificati.'], JSON_UNESCAPED_SLASHES), PHP_EOL;
