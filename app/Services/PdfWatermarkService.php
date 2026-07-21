<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class PdfWatermarkService
{
    public function apply(string $sourcePath, string $targetPath, array $header = []): void
    {
        $tempDir = sys_get_temp_dir() . '/myrsu_watermark_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0775, true);

        try {
            $renderedPages = $this->renderPages($sourcePath, $tempDir);
            if ($renderedPages === []) {
                throw new HttpException(422, 'PDF senza contenuto utile.');
            }

            $jpegPages = [];
            foreach ($renderedPages as $index => $pagePath) {
                $jpegPath = $tempDir . '/final-' . ($index + 1) . '.jpg';
                $this->composePage($pagePath, $jpegPath, $header);
                $jpegPages[] = $jpegPath;
            }

            $this->writePdf($jpegPages, $targetPath);
        } finally {
            $this->deleteDirectory($tempDir);
        }

        if (!is_file($targetPath) || filesize($targetPath) === 0) {
            throw new HttpException(422, 'Watermark PDF fallito.');
        }
    }

    private function renderPages(string $sourcePath, string $tempDir): array
    {
        $pages = [];
        $pageCount = $this->pageCount($sourcePath);

        for ($page = 1; $page <= $pageCount; $page++) {
            $pngPath = $tempDir . '/page-' . $page . '.png';
            $command = escapeshellarg($this->ghostscriptPath())
                . ' -dBATCH -dNOPAUSE -q -sDEVICE=png16m -r150'
                . ' -dFirstPage=' . $page . ' -dLastPage=' . $page
                . ' -sOutputFile=' . escapeshellarg($pngPath)
                . ' ' . escapeshellarg($sourcePath);

            exec($command, $output, $exitCode);
            if ($exitCode === 0 && is_file($pngPath) && !$this->isBlankImage($pngPath)) {
                $pages[] = $pngPath;
            }
        }

        return $pages;
    }

    private function composePage(string $sourcePng, string $targetJpeg, array $header): void
    {
        $canvasWidth = 1240;
        $canvasHeight = 1754;
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        $source = imagecreatefrompng($sourcePng);

        if ($canvas === false || $source === false) {
            throw new HttpException(422, 'Composizione PDF fallita.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $maxWidth = (int)($canvasWidth * 0.94);
        $maxHeight = (int)($canvasHeight * 0.9);
        $scale = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $drawWidth = (int)($sourceWidth * $scale);
        $drawHeight = (int)($sourceHeight * $scale);
        $drawX = (int)(($canvasWidth - $drawWidth) / 2);
        $drawY = (int)(($canvasHeight - $drawHeight) / 2);

        imagecopyresampled($canvas, $source, $drawX, $drawY, 0, 0, $drawWidth, $drawHeight, $sourceWidth, $sourceHeight);
        $this->drawWatermark($canvas);
        $this->drawHeader($canvas, $header);
        imagejpeg($canvas, $targetJpeg, 92);

        imagedestroy($source);
        imagedestroy($canvas);
    }

    private function drawWatermark(\GdImage $canvas): void
    {
        $font = $this->fontPath(true);
        $gray = imagecolorallocatealpha($canvas, 150, 150, 150, 110);
        $this->drawWatermarkBlock($canvas, 360, 600, $gray, $font);
        $this->drawWatermarkBlock($canvas, 360, 1290, $gray, $font);
    }

    private function drawWatermarkBlock(\GdImage $canvas, int $x, int $y, int $color, string $font): void
    {
        imagettftext($canvas, 140, -28, $x, $y, $color, $font, 'RSU');
        imagettftext($canvas, 40, -28, $x - 10, $y + 95, $color, $font, 'Sitem Canegrate');
    }

    private function drawHeader(\GdImage $canvas, array $header): void
    {
        $title = substr((string)($header['title'] ?? 'Documento RSU'), 0, 100);
        $date = (string)($header['date'] ?? date('Y-m-d H:i'));
        $font = $this->fontPath();
        $bold = $this->fontPath(true);
        $black = imagecolorallocate($canvas, 0, 0, 0);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $left = 62;
        $top = 36;
        $width = 1116;
        $height = 92;

        imagefilledrectangle($canvas, $left, $top, $left + $width, $top + $height, $white);
        imagerectangle($canvas, $left, $top, $left + $width, $top + $height, $black);
        imagettftext($canvas, 32, 0, $left + 18, $top + 58, $black, $bold, 'RSU');
        imagettftext($canvas, 18, 0, $left + 150, $top + 34, $black, $font, 'Documento: ' . $title);
        imagettftext($canvas, 18, 0, $left + 150, $top + 68, $black, $font, 'Data: ' . $date);
    }

    private function writePdf(array $jpegPages, string $targetPath): void
    {
        $pageWidth = 595.28;
        $pageHeight = 841.89;
        $objects = ["1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"];
        $pageRefs = [];
        $nextObject = 3;

        foreach ($jpegPages as $jpegPath) {
            [$imageWidth, $imageHeight] = getimagesize($jpegPath) ?: [0, 0];
            $imageData = (string)file_get_contents($jpegPath);
            $pageObject = $nextObject++;
            $imageObject = $nextObject++;
            $contentObject = $nextObject++;
            $pageRefs[] = $pageObject . ' 0 R';
            $content = "q {$pageWidth} 0 0 {$pageHeight} 0 0 cm /Im1 Do Q\n";

            $objects[] = "{$pageObject} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << /XObject << /Im1 {$imageObject} 0 R >> >> /Contents {$contentObject} 0 R >>\nendobj\n";
            $objects[] = "{$imageObject} 0 obj\n<< /Type /XObject /Subtype /Image /Width {$imageWidth} /Height {$imageHeight} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($imageData) . " >>\nstream\n{$imageData}\nendstream\nendobj\n";
            $objects[] = "{$contentObject} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream\nendobj\n";
        }

        array_splice($objects, 1, 0, "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $pageRefs) . "] /Count " . count($pageRefs) . " >>\nendobj\n");
        $this->writeObjects($objects, $targetPath);
    }

    private function writeObjects(array $objects, string $targetPath): void
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $object) {
            preg_match('/^(\d+) 0 obj/', $object, $match);
            $offsets[(int)$match[1]] = strlen($pdf);
            $pdf .= $object;
        }

        $xref = strlen($pdf);
        $size = max(array_keys($offsets)) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($index = 1; $index < $size; $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index] ?? 0);
        }

        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        file_put_contents($targetPath, $pdf);
    }

    private function isBlankImage(string $pngPath): bool
    {
        $image = imagecreatefrompng($pngPath);
        if ($image === false) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $darkPixels = 0;
        $limit = max(8, (int)(($width / 8) * ($height / 8) * 0.0005));

        for ($y = 0; $y < $height; $y += 8) {
            for ($x = 0; $x < $width; $x += 8) {
                $rgb = imagecolorat($image, $x, $y);
                if ((($rgb >> 16) & 255) < 235 || (($rgb >> 8) & 255) < 235 || ($rgb & 255) < 235) {
                    $darkPixels++;
                    if ($darkPixels > $limit) {
                        imagedestroy($image);
                        return false;
                    }
                }
            }
        }

        imagedestroy($image);
        return true;
    }

    private function pageCount(string $path): int
    {
        $command = escapeshellarg($this->ghostscriptPath())
            . ' -q -dNOSAFER -dNODISPLAY -c '
            . escapeshellarg('(' . str_replace('\\', '/', $path) . ') (r) file runpdfbegin pdfpagecount = quit');

        exec($command, $output, $exitCode);
        return $exitCode === 0 ? max(1, (int)($output[0] ?? 1)) : 1;
    }

    private function fontPath(bool $bold = false): string
    {
        foreach ([
            $bold ? 'C:/Windows/Fonts/arialbd.ttf' : 'C:/Windows/Fonts/arial.ttf',
            $bold ? 'C:/Windows/Fonts/calibrib.ttf' : 'C:/Windows/Fonts/calibri.ttf',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new HttpException(500, 'Font PDF non trovato.');
    }

    private function ghostscriptPath(): string
    {
        foreach ([
            (string)getenv('GHOSTSCRIPT_PATH'),
            'C:/Program Files/gs/gs10.07.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.06.0/bin/gswin64c.exe',
            'C:/Program Files/gs/gs10.05.1/bin/gswin64c.exe',
        ] as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        throw new HttpException(500, 'Ghostscript non trovato.');
    }

    private function deleteDirectory(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        }

        if (is_dir($path)) {
            rmdir($path);
        }
    }
}
