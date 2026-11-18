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
        ?string $protocolNumber = null,
        ?string $creator = null
    ): void
    {
        $documentNumber = pathinfo($targetPath, PATHINFO_FILENAME);
        $images = array_values(array_filter($attachments, fn (array $item): bool => ($item['kind'] ?? '') === 'image'));
        $videos = array_values(array_filter($attachments, fn (array $item): bool => ($item['kind'] ?? '') === 'video'));
        $pages = [$this->coverPage($report, $videos, $verifyUrl, $documentNumber, $protocolNumber, $creator, 1)];

        foreach ($images as $image) {
            $pages[] = $this->imagePage($image, $report, $verifyUrl, $documentNumber, $protocolNumber, $creator, count($pages) + 1);
        }

        if ($signature !== null && $signature !== '') {
            $pages[] = $this->verificationPage($report, $signature, $verifyUrl, $documentNumber, $protocolNumber, $creator, count($pages) + 1);
        }

        $this->writePdf($targetPath, $pages);
    }

    private function coverPage(
        array $report,
        array $videos,
        ?string $verifyUrl,
        ?string $documentNumber,
        ?string $protocolNumber,
        ?string $creator,
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
            'creator' => $creator,
            'verify_text' => $verifyUrl ? 'Verifica autenticita copia digitale' : '-',
        ]);

        if ($verifyUrl !== null && $verifyUrl !== '') {
            $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
            $page['links'][] = ['rect' => PdfLayoutService::VERIFY_LINK_RECT, 'url' => $verifyUrl];
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
        ?string $creator,
        int $pageNumber
    ): array {
        $content = $this->text(42, PdfLayoutService::BODY_TOP, 18, 'F2', 'Verifica documento');
        $verification = $this->layout->verificationBlock(PdfLayoutService::BODY_TOP - 48, $signature, $verifyUrl);
        $page = $this->layout->page($content . $verification['content'], [
            'number' => ($documentNumber ?? '-') . ' / ' . $pageNumber,
            'protocol' => $protocolNumber ?? '-',
            'date' => (string)($report['created_at'] ?? date('Y-m-d H:i')),
            'creator' => $creator,
            'verify_text' => $verifyUrl ? 'Verifica autenticita copia digitale' : '-',
        ]);

        if ($verifyUrl !== null && $verifyUrl !== '') {
            $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
            $page['links'][] = ['rect' => PdfLayoutService::VERIFY_LINK_RECT, 'url' => $verifyUrl];
            $page['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];
        }

        $page['links'] = array_merge($page['links'], $verification['links']);
        return $page;
    }

    private function imagePage(array $image, array $report, ?string $verifyUrl, ?string $documentNumber, ?string $protocolNumber, ?string $creator, int $pageNumber): array
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
            'creator' => $creator,
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
            $page['links'][] = ['rect' => PdfLayoutService::VERIFY_LINK_RECT, 'url' => $verifyUrl];
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
        (new PdfWriterService())->write($targetPath, $pages);
    }

    private function pdfString(string $value): string
    {
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
