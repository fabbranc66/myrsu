<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class PdfConversionService
{
    public function __construct(private readonly PdfWatermarkService $watermark)
    {
    }

    public function available(): bool
    {
        try {
            $this->sofficePath();
            return true;
        } catch (HttpException) {
            return false;
        }
    }

    public function convert(string $sourcePath, string $originalName, string $targetPath, string $mimeType): void
    {
        if ($mimeType === 'application/pdf') {
            $this->watermark->apply($sourcePath, $targetPath, ['title' => $originalName]);
            return;
        }

        if (str_starts_with($mimeType, 'image/')) {
            $this->convertImage($sourcePath, $originalName, $targetPath, $mimeType);
            return;
        }

        $tempDir = sys_get_temp_dir() . '/myrsu_pdf_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0775, true);

        $inputPath = $tempDir . '/' . $this->safeFileName($originalName);
        copy($sourcePath, $inputPath);

        $profileDir = sys_get_temp_dir() . '/myrsu_lo_profile_' . bin2hex(random_bytes(8));
        $profileUrl = 'file:///' . str_replace('\\', '/', $profileDir);

        $command = escapeshellarg($this->sofficePath())
            . ' ' . escapeshellarg('-env:UserInstallation=' . $profileUrl)
            . ' --headless --nologo --nofirststartwizard --convert-to pdf --outdir '
            . escapeshellarg($tempDir)
            . ' '
            . escapeshellarg($inputPath);

        exec($command, $output, $exitCode);
        $pdfFiles = glob($tempDir . '/*.pdf') ?: [];

        if ($exitCode !== 0 || count($pdfFiles) === 0) {
            $this->deleteDirectory($tempDir);
            if (is_dir($profileDir)) {
                $this->deleteDirectory($profileDir);
            }
            throw new HttpException(422, 'Conversione PDF fallita.');
        }

        $this->watermark->apply($pdfFiles[0], $targetPath, ['title' => $originalName]);
        $this->deleteDirectory($tempDir);
        if (is_dir($profileDir)) {
            $this->deleteDirectory($profileDir);
        }
    }

    private function convertImage(string $sourcePath, string $originalName, string $targetPath, string $mimeType): void
    {
        $tempDir = sys_get_temp_dir() . '/myrsu_imgpdf_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0775, true);

        [$width, $height] = getimagesize($sourcePath) ?: [0, 0];
        if ($width <= 0 || $height <= 0) {
            $this->deleteDirectory($tempDir);
            throw new HttpException(422, 'Immagine non valida.');
        }

        $jpegPath = $tempDir . '/image.jpg';
        $pdfPath = $tempDir . '/image.pdf';
        $this->writeJpegCopy($sourcePath, $jpegPath);
        $this->writeImagePdf($jpegPath, $pdfPath, $width, $height, $originalName);
        copy($pdfPath, $targetPath);
        $this->deleteDirectory($tempDir);
    }

    private function writeJpegCopy(string $sourcePath, string $targetPath): void
    {
        $image = imagecreatefromstring((string)file_get_contents($sourcePath));
        if ($image === false) {
            throw new HttpException(422, 'Lettura immagine fallita.');
        }

        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        if ($canvas === false) {
            imagedestroy($image);
            throw new HttpException(422, 'Preparazione immagine fallita.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        imagejpeg($canvas, $targetPath, 95);
        imagedestroy($image);
        imagedestroy($canvas);
    }

    private function writeImagePdf(string $jpegPath, string $pdfPath, int $imageWidth, int $imageHeight, string $originalName): void
    {
        $pageWidth = 595.28;
        $pageHeight = 841.89;
        $ratio = $imageWidth / $imageHeight;

        if ($imageWidth > $imageHeight) {
            $drawWidth = $pageWidth * 0.9;
            $drawHeight = $drawWidth / $ratio;
            if ($drawHeight > $pageHeight * 0.9) {
                $drawHeight = $pageHeight * 0.9;
                $drawWidth = $drawHeight * $ratio;
            }
        } else {
            $drawHeight = $pageHeight * 0.9;
            $drawWidth = $drawHeight * $ratio;
            if ($drawWidth > $pageWidth * 0.9) {
                $drawWidth = $pageWidth * 0.9;
                $drawHeight = $drawWidth / $ratio;
            }
        }

        $x = ($pageWidth - $drawWidth) / 2;
        $y = ($pageHeight - $drawHeight) / 2;
        $imageData = (string)file_get_contents($jpegPath);
        $title = $this->pdfText($originalName);
        $date = $this->pdfText(date('Y-m-d H:i'));
        $watermarkTop = "q /GS1 gs 0.85 g 0.8829 0.4695 -0.4695 0.8829 297.64 610 cm "
            . "BT /F2 120 Tf -115 18 Td (RSU) Tj ET BT /F2 34 Tf -145 -42 Td (Sitem Canegrate) Tj ET Q\n";
        $watermarkBottom = "q /GS1 gs 0.85 g 0.8829 0.4695 -0.4695 0.8829 297.64 250 cm "
            . "BT /F2 120 Tf -115 18 Td (RSU) Tj ET BT /F2 34 Tf -145 -42 Td (Sitem Canegrate) Tj ET Q\n";
        $content = sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /Im1 Do Q\n", $drawWidth, $drawHeight, $x, $y)
            . $watermarkTop
            . $watermarkBottom
            . "q 1 g 28 799.89 540 30 re f 0 G 0.8 w 28 799.89 540 30 re S "
            . "0 g BT /F2 15 Tf 42 809.89 Td (RSU) Tj ET "
            . "0 g BT /F1 8 Tf 96 817.89 Td (Documento: {$title}) Tj ET "
            . "0 g BT /F1 8 Tf 96 806.89 Td (Data: {$date}) Tj ET Q\n";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /XObject << /Im1 4 0 R >> /Font << /F1 6 0 R /F2 7 0 R >> /ExtGState << /GS1 8 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /XObject /Subtype /Image /Width {$imageWidth} /Height {$imageHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($imageData) . " >>\nstream\n" . $imageData . "\nendstream\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream\nendobj\n",
            "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n",
            "8 0 obj\n<< /Type /ExtGState /ca 0.5 /CA 0.5 >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        file_put_contents($pdfPath, $pdf);
    }

    private function pdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function sofficePath(): string
    {
        foreach ([
            (string)getenv('SOFFICE_PATH'),
            'C:/Program Files/LibreOffice/program/soffice.com',
            'C:/Program Files/LibreOffice/program/soffice.exe',
            'C:/Program Files (x86)/LibreOffice/program/soffice.com',
            'C:/Program Files (x86)/LibreOffice/program/soffice.exe',
            'C:/Program Files/OpenOffice 4/program/soffice.exe',
            'C:/Program Files (x86)/OpenOffice 4/program/soffice.exe',
        ] as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        throw new HttpException(500, 'LibreOffice/OpenOffice non trovato.');
    }

    private function safeFileName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name)) ?: 'document';
    }

    private function deleteDirectory(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        }

        rmdir($path);
    }
}
