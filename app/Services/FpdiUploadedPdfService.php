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

        try {
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
                $this->watermark($pdf);
                $this->footer($pdf);
            }

            $this->verificationPage($pdf, $document, $protocol, $documentName, $pageCount + 1, $verifyUrl, $signature, $qrPath);
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
        $pdf->SetFont('Helvetica', 'B', 30);
        $pdf->Text(42, 68, 'RSU');
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Text(44, 87, 'Canegrate');
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

    private function verificationPage(MyRsuFpdi $pdf, array $document, ?array $protocol, string $name, int $page, string $url, string $signature, string $qrPath): void
    {
        $pdf->AddPage('P', [PdfLayoutService::PAGE_WIDTH, PdfLayoutService::PAGE_HEIGHT]);
        $this->header($pdf, $document, $protocol, $name, $page, $url, $qrPath);
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
        $this->watermark($pdf);
        $this->footer($pdf);
    }

    private function watermark(MyRsuFpdi $pdf): void
    {
        $pdf->SetTextColor(195, 195, 195);
        $pdf->SetFont('Helvetica', 'B', 94);
        $pdf->rotatedText(205, 390, 'RSU', 28);
        $pdf->rotatedText(205, 665, 'RSU', 28);
        $pdf->SetFont('Helvetica', 'B', 24);
        $pdf->rotatedText(245, 448, 'Sitem Canegrate', 28);
        $pdf->rotatedText(245, 723, 'Sitem Canegrate', 28);
        $pdf->SetTextColor(0);
    }

    private function footer(MyRsuFpdi $pdf): void
    {
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Text(42, 806, 'RSU Sitem Canegrate');
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
