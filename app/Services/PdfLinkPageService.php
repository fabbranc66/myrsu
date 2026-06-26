<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class PdfLinkPageService
{
    public function append(string $pdfPath, array $links, string $qrUrl): void
    {
        $pdf = (string)file_get_contents($pdfPath);
        if (!preg_match('/(\d+)\s+0\s+obj\s*<<\s*\/Type\s*\/Pages\s*\/Kids\s*\[(.*?)\]\s*\/Count\s*(\d+)/s', $pdf, $pagesMatch)) {
            throw new HttpException(422, 'Albero pagine PDF non trovato.');
        }

        $pagesObject = (int)$pagesMatch[1];
        $kids = trim(preg_replace('/\s+/', ' ', $pagesMatch[2]) ?? '');
        $count = (int)$pagesMatch[3];
        $maxObject = $this->maxObjectNumber($pdf);
        $prevXref = $this->previousXref($pdf);
        $newPageObject = $maxObject + 1;
        $fontObject = $maxObject + 2;
        $qrObject = $maxObject + 3;
        $contentObject = $maxObject + 4;
        $firstAnnotObject = $maxObject + 5;
        $page = $this->pageObjects($newPageObject, $fontObject, $qrObject, $contentObject, $firstAnnotObject, $links, $qrUrl);
        $newKids = trim($kids . ' ' . $newPageObject . ' 0 R');
        $objects = [
            $pagesObject => "{$pagesObject} 0 obj\n<< /Type /Pages /Kids [{$newKids}] /Count " . ($count + 1) . " >>\nendobj\n",
        ] + $page;

        $this->appendObjects($pdfPath, $objects, $prevXref);
    }

    public function write(string $targetPath, array $links, string $qrUrl): void
    {
        $pageWidth = 595.28;
        $pageHeight = 841.89;
        $content = "BT /F1 20 Tf 50 790 Td (Link documento) Tj ET\n";
        $annotations = [];
        $qrJpeg = $this->qrJpeg($qrUrl);
        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        ];
        $qrObject = 5;
        $nextObject = 6;
        $y = 735;

        $content .= "q 130 0 0 130 415 635 cm /Im1 Do Q\n";
        $annotations[] = $nextObject . ' 0 R';
        $objects[] = "{$nextObject} 0 obj\n<< /Type /Annot /Subtype /Link /Rect [415 635 545 765] /Border [0 0 0] /A << /S /URI /URI (" . $this->pdfText($qrUrl) . ") >> >>\nendobj\n";
        $nextObject++;

        foreach ($links as $link) {
            $label = $this->pdfText((string)$link['label']);
            $url = $this->pdfText((string)$link['url']);
            $content .= "BT /F1 12 Tf 50 {$y} Td ({$label}) Tj ET\n";
            $content .= "0 0 1 rg BT /F1 10 Tf 50 " . ($y - 18) . " Td ({$url}) Tj ET 0 0 0 rg\n";
            $annotObject = $nextObject++;
            $annotations[] = $annotObject . ' 0 R';
            $objects[] = "{$annotObject} 0 obj\n<< /Type /Annot /Subtype /Link /Rect [45 " . ($y - 23) . " 550 " . ($y + 14) . "] /Border [0 0 0] /A << /S /URI /URI ({$url}) >> >>\nendobj\n";
            $y -= 72;
        }

        $annots = $annotations === [] ? '' : '/Annots [' . implode(' ', $annotations) . ']';
        array_splice($objects, 2, 0, [
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] {$annots} /Resources << /Font << /F1 4 0 R >> /XObject << /Im1 {$qrObject} 0 R >> >> /Contents " . $nextObject . " 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "{$qrObject} 0 obj\n<< /Type /XObject /Subtype /Image /Width 360 /Height 360 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($qrJpeg) . " >>\nstream\n{$qrJpeg}\nendstream\nendobj\n",
        ]);
        $objects[] = "{$nextObject} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream\nendobj\n";

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

    private function pdfText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function pageObjects(int $pageObject, int $fontObject, int $qrObject, int $contentObject, int $firstAnnotObject, array $links, string $qrUrl): array
    {
        $pageWidth = 595.28;
        $pageHeight = 841.89;
        $qrJpeg = $this->qrJpeg($qrUrl);
        $content = "BT /F1 20 Tf 50 790 Td (Link documento) Tj ET\nq 130 0 0 130 415 635 cm /Im1 Do Q\n";
        $objects = [];
        $annots = [];
        $annotObject = $firstAnnotObject;
        $y = 735;

        $annots[] = $annotObject . ' 0 R';
        $objects[$annotObject] = "{$annotObject} 0 obj\n<< /Type /Annot /Subtype /Link /Rect [415 635 545 765] /Border [0 0 0] /A << /S /URI /URI (" . $this->pdfText($qrUrl) . ") >> >>\nendobj\n";
        $annotObject++;

        foreach ($links as $link) {
            $label = $this->pdfText((string)$link['label']);
            $url = $this->pdfText((string)$link['url']);
            $content .= "BT /F1 12 Tf 50 {$y} Td ({$label}) Tj ET\n";
            $content .= "0 0 1 rg BT /F1 10 Tf 50 " . ($y - 18) . " Td ({$url}) Tj ET 0 0 0 rg\n";
            $annots[] = $annotObject . ' 0 R';
            $objects[$annotObject] = "{$annotObject} 0 obj\n<< /Type /Annot /Subtype /Link /Rect [45 " . ($y - 23) . " 550 " . ($y + 14) . "] /Border [0 0 0] /A << /S /URI /URI ({$url}) >> >>\nendobj\n";
            $annotObject++;
            $y -= 72;
        }

        return [
            $pageObject => "{$pageObject} 0 obj\n<< /Type /Page /Parent 3 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Annots [" . implode(' ', $annots) . "] /Resources << /Font << /F1 {$fontObject} 0 R >> /XObject << /Im1 {$qrObject} 0 R >> >> /Contents {$contentObject} 0 R >>\nendobj\n",
            $fontObject => "{$fontObject} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            $qrObject => "{$qrObject} 0 obj\n<< /Type /XObject /Subtype /Image /Width 360 /Height 360 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($qrJpeg) . " >>\nstream\n{$qrJpeg}\nendstream\nendobj\n",
            $contentObject => "{$contentObject} 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream\nendobj\n",
        ] + $objects;
    }

    private function appendObjects(string $pdfPath, array $objects, int $prevXref): void
    {
        $base = (string)file_get_contents($pdfPath);
        $append = "\n";
        $offsets = [];
        $baseLength = strlen($base);

        ksort($objects);
        foreach ($objects as $number => $object) {
            $offsets[(int)$number] = $baseLength + strlen($append);
            $append .= $object;
        }

        $xref = $baseLength + strlen($append);
        $min = min(array_keys($offsets));
        $max = max(array_keys($offsets));
        $append .= "xref\n{$min} " . ($max - $min + 1) . "\n";
        for ($index = $min; $index <= $max; $index++) {
            $append .= isset($offsets[$index])
                ? sprintf("%010d 00000 n \n", $offsets[$index])
                : "0000000000 65535 f \n";
        }
        $append .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Prev {$prevXref} >>\nstartxref\n{$xref}\n%%EOF";
        file_put_contents($pdfPath, $base . $append);
    }

    private function maxObjectNumber(string $pdf): int
    {
        preg_match_all('/(\d+)\s+0\s+obj/', $pdf, $matches);
        return max(array_map('intval', $matches[1] ?? [0]));
    }

    private function previousXref(string $pdf): int
    {
        preg_match_all('/startxref\s+(\d+)/', $pdf, $matches);
        return (int)end($matches[1]);
    }

    private function qrJpeg(string $url): string
    {
        $qr = (new QRCode(new QROptions(['outputType' => QRCode::OUTPUT_IMAGE_PNG, 'scale' => 8])))->render($url);
        $data = str_starts_with($qr, 'data:image/png;base64,') ? base64_decode(substr($qr, 22)) : $qr;
        $image = imagecreatefromstring((string)$data);
        $canvas = imagecreatetruecolor(360, 360);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, 360, 360, imagesx($image), imagesy($image));
        ob_start();
        imagejpeg($canvas, null, 95);
        $jpeg = (string)ob_get_clean();
        imagedestroy($image);
        imagedestroy($canvas);

        return $jpeg;
    }

    private function merge(array $pdfPaths, string $targetPath): void
    {
        $command = escapeshellarg($this->ghostscriptPath())
            . ' -dBATCH -dNOPAUSE -dPreserveAnnots=true -q -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($targetPath)
            . ' ' . implode(' ', array_map('escapeshellarg', $pdfPaths));

        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($targetPath)) {
            throw new HttpException(422, 'Merge link PDF fallito.');
        }
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
