<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use Throwable;

final class FpdiUploadedPdfService
{
    public function __construct(private readonly PdfQrService $qr)
    {
    }

    public function write(
        string $sourcePath,
        string $targetPath,
        array $document,
        ?array $protocol,
        string $verifyUrl,
        string $signature
    ): void {
        $outputPath = realpath($sourcePath) === realpath($targetPath)
            ? $targetPath . '.tmp.pdf'
            : $targetPath;
        $qrPath = $this->qrFile($verifyUrl);
        $symbolPath = tempnam(sys_get_temp_dir(), 'miodelegato_symbol_');
        $basePath = tempnam(sys_get_temp_dir(), 'miodelegato_base_');

        try {
            if ($symbolPath === false || file_put_contents($symbolPath, PdfBrandingService::assets()['BrandSymbol']['png']) === false) {
                throw new HttpException(500, 'Preparazione filigrana fallita.');
            }
            if ($basePath === false || file_put_contents($basePath, PdfBrandingService::assets()['BrandSymbolBase']['png']) === false) {
                throw new HttpException(500, 'Preparazione contrasto filigrana fallita.');
            }
            $pdf = new MyRsuFpdi('P', 'pt', [PdfLayoutService::PAGE_WIDTH, PdfLayoutService::PAGE_HEIGHT]);
            $pdf->SetAutoPageBreak(false);
            $pageCount = $pdf->setSourceFile($sourcePath);
            $documentName = pathinfo($targetPath, PATHINFO_FILENAME);

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $template = $pdf->importPage($pageNumber);
                $templateSize = $pdf->getTemplateSize($template);
                $pdf->AddPage('P', [PdfLayoutService::PAGE_WIDTH, PdfLayoutService::PAGE_HEIGHT]);
                $this->header($pdf, $document, $protocol, $documentName, $pageNumber, $verifyUrl, $qrPath);
                $this->sourcePage($pdf, $template, $templateSize, $pageNumber === 1 ? (string)$document['original_name'] : '');
                $this->watermark($pdf, $symbolPath, $basePath);
                $this->footer($pdf);
            }

            $this->verificationPage($pdf, $document, $protocol, $documentName, $pageCount + 1, $verifyUrl, $signature, $qrPath, $symbolPath, $basePath);
            $pdf->Output('F', $outputPath);
            unset($pdf);

            if ($outputPath !== $targetPath) {
                if (is_file($targetPath)) unlink($targetPath);
                if (!rename($outputPath, $targetPath)) {
                    throw new HttpException(500, 'Sostituzione PDF fallita.');
                }
            }
        } catch (Throwable $exception) {
            if (is_file($outputPath) && $outputPath !== $targetPath) unlink($outputPath);
            throw $exception;
        } finally {
            if (is_file($qrPath)) unlink($qrPath);
            if (is_string($symbolPath) && is_file($symbolPath)) unlink($symbolPath);
            if (is_string($basePath) && is_file($basePath)) unlink($basePath);
        }
    }

    private function header(MyRsuFpdi $pdf, array $document, ?array $protocol, string $name, int $page, string $url, string $qrPath): void
    {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(0, 0, PdfLayoutService::PAGE_WIDTH, PdfLayoutService::PAGE_HEIGHT, 'F');
        $creator = trim((string)($document['creator_name'] ?? '')) ?: '-';
        $date = (string)($protocol['created_at'] ?? $document['created_at'] ?? date('Y-m-d H:i'));
        $rows = [
            ['Doc/n pag', $name . ' / ' . $page],
            ['Prot', (string)($protocol['protocol_number'] ?? '-')],
            ['Data', $date . ' - ' . $creator],
            ['Rev', '-'],
            ['Verifica', 'Verifica autenticita copia digitale'],
        ];

        $pdf->SetTextColor(0);
        $pdf->Image(PdfBrandingService::logoPath(), 42, PdfLayoutService::PAGE_HEIGHT - 786, 96, 32, 'PNG');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Text(44, PdfLayoutService::PAGE_HEIGHT - 741, PdfBrandingService::CAPTION);
        foreach ($rows as $index => [$label, $value]) {
            $y = 62 + ($index * 17);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Text(150, $y, $this->encode($label));
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Text(260, $y, $this->encode($value));
        }
        $pdf->Image($qrPath, 502, 62, 52, 52, 'JPEG');
        $pdf->Link(260, 121, 230, 14, $url);
        $pdf->Link(502, 62, 52, 52, $url);
        $pdf->SetDrawColor(0);
        $pdf->SetLineWidth(0.8);
        $pdf->Line(42, 154, 553, 154);
    }

    private function sourcePage(MyRsuFpdi $pdf, mixed $template, array $size, string $caption): void
    {
        if ($caption !== '') {
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Text(42, 174, $this->encode($caption));
        }
        $maxWidth = 511.0;
        $maxHeight = 590.0;
        $scale = min($maxWidth / (float)$size['width'], $maxHeight / (float)$size['height']);
        $width = (float)$size['width'] * $scale;
        $height = (float)$size['height'] * $scale;
        $x = (PdfLayoutService::PAGE_WIDTH - $width) / 2;
        $y = 182 + (($maxHeight - $height) / 2);
        $pdf->useTemplate($template, $x, $y, $width, $height);
    }

    private function verificationPage(MyRsuFpdi $pdf, array $document, ?array $protocol, string $name, int $page, string $url, string $signature, string $qrPath, string $symbolPath, string $basePath): void
    {
        $pdf->AddPage('P', [PdfLayoutService::PAGE_WIDTH, PdfLayoutService::PAGE_HEIGHT]);
        $this->header($pdf, $document, $protocol, $name, $page, $url, $qrPath);
        $this->watermark($pdf, $symbolPath, $basePath);
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->Text(42, 182, 'Verifica documento');
        $pdf->SetLineWidth(0.45);
        $pdf->Line(42, 230, 553, 230);
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Text(42, 252, 'Verifica autenticita copia digitale');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Text(42, 272, $this->encode('Firma documento: ' . $signature));
        $pdf->Text(42, 289, 'Checksum verificato sul file PDF conservato dal sistema.');
        $pdf->Text(42, 306, $this->encode('Link verifica: ' . $url));
        $pdf->Link(42, 294, 511, 16, $url);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(42, 327, 'Copia diffusa dal delegato RSU FIM-CISL, non dall\'intera RSU.');
        $this->footer($pdf);
    }

    private function watermark(MyRsuFpdi $pdf, string $symbolPath, string $basePath): void
    {
        $symbol = PdfBrandingService::assets()['BrandSymbol'];
        $height = 170 * $symbol['height'] / $symbol['width'];
        foreach (PdfBrandingService::CENTERS as $center) $pdf->brandingWatermark($symbolPath, $height, $center, $basePath);
    }

    private function footer(MyRsuFpdi $pdf): void
    {
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(42, 806, PdfBrandingService::FOOTER);
    }

    private function qrFile(string $url): string
    {
        $image = $this->qr->image($url, 'Qr1', 0, 0, 52);
        $path = sys_get_temp_dir() . '/myrsu_qr_' . bin2hex(random_bytes(8)) . '.jpg';
        if (file_put_contents($path, $image['data']) === false) {
            throw new HttpException(500, 'QR non salvato.');
        }
        return $path;
    }

    private function encode(string $text): string
    {
        return iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
    }
}
