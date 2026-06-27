<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class ReportPdfService
{
    private float $pageWidth = 595.28;
    private float $pageHeight = 841.89;
    private float $margin = 42.0;

    public function __construct(
        private readonly PdfLayoutService $layout,
        private readonly PdfQrService $qr
    ) {
    }

    public function write(
        string $targetPath,
        array $report,
        array $attachments,
        ?string $signature,
        ?string $verifyUrl,
        ?string $documentNumber = null,
        ?string $protocolNumber = null
    ): void
    {
        $documentNumber = pathinfo($targetPath, PATHINFO_FILENAME);
        $images = array_values(array_filter($attachments, fn (array $item): bool => ($item['kind'] ?? '') === 'image'));
        $videos = array_values(array_filter($attachments, fn (array $item): bool => ($item['kind'] ?? '') === 'video'));
        $pages = [$this->coverPage($report, $videos, $verifyUrl, $documentNumber, $protocolNumber, 1)];

        foreach ($images as $image) {
            $pages[] = $this->imagePage($image, $report, $verifyUrl, $documentNumber, $protocolNumber, count($pages) + 1);
        }

        if ($signature !== null && $signature !== '') {
            $pages[] = $this->verificationPage($report, $signature, $verifyUrl, $documentNumber, $protocolNumber, count($pages) + 1);
        }

        $this->writePdf($targetPath, $pages);
    }

    private function coverPage(
        array $report,
        array $videos,
        ?string $verifyUrl,
        ?string $documentNumber,
        ?string $protocolNumber,
        int $pageNumber
    ): array
    {
        $lines = [];
        $links = [];
        $images = [];
        $y = PdfLayoutService::BODY_TOP;

        $lines[] = $this->text(42, $y, 18, 'F2', 'Segnalazione approvata');
        $y -= 34;
        $lines[] = $this->text(42, $y, 11, 'F2', 'Codice');
        $lines[] = $this->text(120, $y, 11, 'F1', (string)$report['tracking_code']);
        $y -= 18;
        $lines[] = $this->text(42, $y, 11, 'F2', 'Oggetto');
        $y = $this->wrappedText($lines, 120, $y, 11, 78, (string)$report['subject']) - 8;
        $lines[] = $this->text(42, $y, 11, 'F2', 'Contatto');
        $lines[] = $this->text(120, $y, 11, 'F1', trim((string)($report['contact'] ?? '')) ?: '-');
        $y -= 28;
        $lines[] = $this->text(42, $y, 13, 'F2', 'Contenuto');
        $y -= 18;
        $y = $this->wrappedText($lines, 42, $y, 11, 92, (string)$report['message']) - 18;

        if ($videos !== []) {
            $lines[] = $this->text(42, $y, 13, 'F2', 'Video allegati');
            $y -= 18;
            foreach ($videos as $video) {
                $label = 'Apri video: ' . (string)($video['original_name'] ?? 'video');
                $url = (string)($video['shared_url'] ?? '');
                $lines[] = $this->text(54, $y, 11, 'F1', $label);
                if ($url !== '') {
                    $links[] = ['rect' => [54, $y - 3, 360, $y + 12], 'url' => $url];
                }
                $y -= 16;
            }
            $y -= 8;
        }

        $page = $this->layout->page(implode('', $lines), [
            'number' => ($documentNumber ?? '-') . ' / ' . $pageNumber,
            'protocol' => $protocolNumber ?? '-',
            'date' => (string)($report['created_at'] ?? date('Y-m-d H:i')),
            'verify_text' => $verifyUrl ? 'Verifica autenticita copia digitale' : '-',
        ]);

        if ($verifyUrl !== null && $verifyUrl !== '') {
            $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
            $page['links'][] = ['rect' => [260, 723, 430, 738], 'url' => $verifyUrl];
            $page['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];
        }

        $page['images'] = array_merge($page['images'], $images);
        $page['links'] = array_merge($page['links'], $links);
        return $page;
    }

    private function verificationPage(
        array $report,
        string $signature,
        ?string $verifyUrl,
        ?string $documentNumber,
        ?string $protocolNumber,
        int $pageNumber
    ): array {
        $content = $this->text(42, PdfLayoutService::BODY_TOP, 18, 'F2', 'Verifica documento');
        $verification = $this->layout->verificationBlock(PdfLayoutService::BODY_TOP - 48, $signature, $verifyUrl);
        $page = $this->layout->page($content . $verification['content'], [
            'number' => ($documentNumber ?? '-') . ' / ' . $pageNumber,
            'protocol' => $protocolNumber ?? '-',
            'date' => (string)($report['created_at'] ?? date('Y-m-d H:i')),
            'verify_text' => $verifyUrl ? 'Verifica autenticita copia digitale' : '-',
        ]);

        if ($verifyUrl !== null && $verifyUrl !== '') {
            $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
            $page['links'][] = ['rect' => [260, 723, 430, 738], 'url' => $verifyUrl];
            $page['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];
        }

        $page['links'] = array_merge($page['links'], $verification['links']);
        return $page;
    }

    private function imagePage(array $image, array $report, ?string $verifyUrl, ?string $documentNumber, ?string $protocolNumber, int $pageNumber): array
    {
        $jpeg = $this->jpegFromImage((string)$image['path']);
        $caption = (string)($image['original_name'] ?? 'immagine');
        $maxWidth = $this->pageWidth * 0.88;
        $maxHeight = $this->pageHeight * 0.74;
        $scale = min($maxWidth / $jpeg['width'], $maxHeight / $jpeg['height']);
        $drawWidth = $jpeg['width'] * $scale;
        $drawHeight = $jpeg['height'] * $scale;
        $x = ($this->pageWidth - $drawWidth) / 2;
        $y = ($this->pageHeight - $drawHeight) / 2 - 20;

        $page = $this->layout->page($this->text(42, PdfLayoutService::BODY_TOP, 15, 'F2', $caption), [
            'number' => ($documentNumber ?? '-') . ' / ' . $pageNumber,
            'protocol' => $protocolNumber ?? '-',
            'date' => (string)($report['created_at'] ?? date('Y-m-d H:i')),
            'verify_text' => $verifyUrl ? 'Verifica autenticita copia digitale' : '-',
        ]);
        $page['images'][] = [
                'name' => 'Im1',
                'data' => $jpeg['data'],
                'width' => $jpeg['width'],
                'height' => $jpeg['height'],
                'rect' => [$x, $y, $drawWidth, $drawHeight],
        ];
        if ($verifyUrl !== null && $verifyUrl !== '') {
            $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
            $page['links'][] = ['rect' => [260, 723, 430, 738], 'url' => $verifyUrl];
            $page['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];
        }

        return $page;
    }

    private function jpegFromImage(string $path): array
    {
        if (!is_file($path)) {
            throw new HttpException(404, 'Allegato immagine non trovato.');
        }

        $source = imagecreatefromstring((string)file_get_contents($path));
        if ($source === false) {
            throw new HttpException(422, 'Lettura immagine fallita.');
        }

        $source = $this->orientImage($source, $path);
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($source);
            throw new HttpException(422, 'Preparazione immagine fallita.');
        }

        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        ob_start();
        imagejpeg($canvas, null, 94);
        $data = (string)ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return ['data' => $data, 'width' => $width, 'height' => $height];
    }

    private function orientImage(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int)($exif['Orientation'] ?? 1);
        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };

        if ($rotated === false) {
            return $image;
        }

        imagedestroy($image);
        return $rotated;
    }

    private function wrappedText(array &$lines, float $x, float $y, int $size, int $maxChars, string $text): float
    {
        foreach (preg_split("/\R/", trim($text)) ?: [] as $paragraph) {
            foreach (explode("\n", wordwrap($paragraph, $maxChars, "\n", true)) as $line) {
                $lines[] = $this->text($x, $y, $size, 'F1', $line);
                $y -= $size + 5;
            }
            $y -= 4;
        }

        return $y;
    }

    private function text(float $x, float $y, int $size, string $font, string $text): string
    {
        return sprintf("BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, $x, $y, $this->pdfString($text));
    }

    private function writePdf(string $targetPath, array $pages): void
    {
        $objects = ["1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"];
        $pageRefs = [];
        $next = 3;
        $gstateObject = $next++;
        $fontRegular = 0;
        $fontBold = 0;

        foreach ($pages as $page) {
            $pageObject = $next++;
            $contentObject = $next++;
            $imageRefs = [];
            $annotRefs = [];
            foreach ($page['images'] as $image) {
                $imageObject = $next++;
                $imageRefs[$image['name']] = $imageObject;
                $objects[] = $this->imageObject($imageObject, $image);
                [$x, $y, $w, $h] = $image['rect'];
                $page['content'] .= sprintf("q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q\n", $w, $h, $x, $y, $image['name']);
            }
            foreach ($page['links'] as $link) {
                $annotObject = $next++;
                $annotRefs[] = $annotObject . ' 0 R';
                $objects[] = $this->linkObject($annotObject, $link);
            }

            $pageRefs[] = $pageObject . ' 0 R';
            $objects[] = "{$contentObject} 0 obj\n<< /Length " . strlen($page['content']) . " >>\nstream\n{$page['content']}endstream\nendobj\n";
            if ($fontRegular === 0) {
                $fontRegular = $next++;
                $fontBold = $next++;
            }
            $objects[] = $this->pageObject($pageObject, $contentObject, $imageRefs, $annotRefs, $fontRegular, $fontBold, $gstateObject);
        }

        $objects[] = "2 0 obj\n<< /Type /Pages /Kids [" . implode(' ', $pageRefs) . "] /Count " . count($pageRefs) . " >>\nendobj\n";
        $objects[] = "{$gstateObject} 0 obj\n<< /Type /ExtGState /ca 0.23 /CA 0.23 >>\nendobj\n";
        $objects[] = "{$fontRegular} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objects[] = "{$fontBold} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
        $this->writeObjects($objects, $targetPath);
    }

    private function pageObject(int $id, int $contentId, array $imageRefs, array $annotRefs, int $fontRegular, int $fontBold, int $gstateObject): string
    {
        $xobjects = '';
        foreach ($imageRefs as $name => $objectId) {
            $xobjects .= '/' . $name . ' ' . $objectId . ' 0 R ';
        }
        $annots = $annotRefs === [] ? '' : ' /Annots [' . implode(' ', $annotRefs) . ']';
        return "{$id} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}]{$annots} /Resources << /ExtGState << /GS1 {$gstateObject} 0 R >> /XObject << {$xobjects} >> /Font << /F1 {$fontRegular} 0 R /F2 {$fontBold} 0 R >> >> /Contents {$contentId} 0 R >>\nendobj\n";
    }

    private function imageObject(int $id, array $image): string
    {
        return "{$id} 0 obj\n<< /Type /XObject /Subtype /Image /Width {$image['width']} /Height {$image['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($image['data']) . " >>\nstream\n{$image['data']}\nendstream\nendobj\n";
    }

    private function linkObject(int $id, array $link): string
    {
        [$x1, $y1, $x2, $y2] = $link['rect'];
        return "{$id} 0 obj\n<< /Type /Annot /Subtype /Link /Rect [{$x1} {$y1} {$x2} {$y2}] /Border [0 0 0] /A << /S /URI /URI (" . $this->pdfString((string)$link['url']) . ") >> >>\nendobj\n";
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
        for ($i = 1; $i < $size; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        file_put_contents($targetPath, $pdf);
    }

    private function pdfString(string $value): string
    {
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
