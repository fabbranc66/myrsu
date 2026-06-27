<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class UploadedDocumentPdfService
{
    public function __construct(
        private readonly PdfLayoutService $layout,
        private readonly PdfWriterService $writer,
        private readonly PdfQrService $qr
    ) {
    }

    public function write(
        string $originalPath,
        string $convertedPdfPath,
        array $document,
        ?array $protocol,
        string $verifyUrl,
        string $signature
    ): void {
        $document['public_name'] = pathinfo($convertedPdfPath, PATHINFO_FILENAME);
        $mimeType = (string)($document['original_mime_type'] ?? '');

        if ($mimeType === 'application/pdf') {
            $this->writeFromPdf($originalPath, $convertedPdfPath, $document, $protocol, $verifyUrl, $signature);
            return;
        }

        if (str_starts_with($mimeType, 'image/')) {
            $this->writeFromImage($originalPath, $convertedPdfPath, $document, $protocol, $verifyUrl, $signature);
            return;
        }

        if ($mimeType === 'text/plain') {
            $this->writeFromText($originalPath, $convertedPdfPath, $document, $protocol, $verifyUrl, $signature);
            return;
        }

        $this->writeFromPdf($convertedPdfPath, $convertedPdfPath, $document, $protocol, $verifyUrl, $signature);
    }

    public function writeFromPdf(
        string $sourcePath,
        string $targetPath,
        array $document,
        ?array $protocol,
        string $verifyUrl,
        string $signature
    ): void {
        $tempDir = sys_get_temp_dir() . '/myrsu_upload_pdf_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0775, true);

        try {
            $pages = [];
            foreach ($this->renderPages($sourcePath, $tempDir) as $index => $imagePath) {
                $pages[] = $this->documentPage($imagePath, $index, $document, $protocol, $verifyUrl);
            }
            $pages[] = $this->verificationPage($document, $protocol, $verifyUrl, $signature, count($pages) + 1);
            $this->writer->write($targetPath, $pages);
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    public function writeFromImage(
        string $sourcePath,
        string $targetPath,
        array $document,
        ?array $protocol,
        string $verifyUrl,
        string $signature
    ): void {
        $jpegPath = $this->jpegCopy($sourcePath);
        try {
            $page = $this->documentPage($jpegPath, 0, $document, $protocol, $verifyUrl);
            $this->writer->write($targetPath, [
                $page,
                $this->verificationPage($document, $protocol, $verifyUrl, $signature, 2),
            ]);
        } finally {
            if (is_file($jpegPath)) {
                unlink($jpegPath);
            }
        }
    }

    public function writeFromText(
        string $sourcePath,
        string $targetPath,
        array $document,
        ?array $protocol,
        string $verifyUrl,
        string $signature
    ): void {
        $pages = [];
        $lines = $this->textLines((string)file_get_contents($sourcePath));
        $content = $this->title($document);
        $y = 644.0;

        foreach ($lines as $line) {
            if ($y < 72) {
                $pages[] = $this->pageWithHeader($content, $document, $protocol, $verifyUrl, count($pages) + 1);
                $content = '';
                $y = PdfLayoutService::BODY_TOP;
            }
            $content .= $this->layout->text(42, $y, 9, PdfLayoutService::FONT_REGULAR, $line);
            $y -= 13;
        }

        $pages[] = $this->pageWithHeader($content, $document, $protocol, $verifyUrl, count($pages) + 1);
        $pages[] = $this->verificationPage($document, $protocol, $verifyUrl, $signature, count($pages) + 1);
        $this->writer->write($targetPath, $pages);
    }

    private function documentPage(string $imagePath, int $index, array $document, ?array $protocol, string $verifyUrl): array
    {
        [$width, $height] = getimagesize($imagePath) ?: [0, 0];
        if ($width <= 0 || $height <= 0) {
            throw new HttpException(422, 'Pagina PDF non valida.');
        }

        $maxWidth = PdfLayoutService::PAGE_WIDTH - PdfLayoutService::MARGIN_LEFT - PdfLayoutService::MARGIN_RIGHT;
        $maxHeight = 590.0;
        $scale = min($maxWidth / $width, $maxHeight / $height);
        $drawWidth = $width * $scale;
        $drawHeight = $height * $scale;
        $x = (PdfLayoutService::PAGE_WIDTH - $drawWidth) / 2;
        $y = 70 + (($maxHeight - $drawHeight) / 2);

        $page = $this->layout->page($index === 0 ? $this->title($document) : '', [
            'number' => $this->pageReference($document, $index + 1),
            'protocol' => (string)($protocol['protocol_number'] ?? '-'),
            'date' => (string)($protocol['created_at'] ?? $document['created_at'] ?? date('Y-m-d H:i')),
            'verify_text' => 'Verifica autenticita copia digitale',
        ]);
        $page['images'][] = [
            'name' => 'Page' . ($index + 1),
            'data' => (string)file_get_contents($imagePath),
            'width' => $width,
            'height' => $height,
            'rect' => [$x, $y, $drawWidth, $drawHeight],
        ];
        $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
        $page['links'][] = ['rect' => [260, 723, 430, 738], 'url' => $verifyUrl];
        $page['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];

        return $page;
    }

    private function verificationPage(array $document, ?array $protocol, string $verifyUrl, string $signature, int $pageNumber): array
    {
        $content = $this->layout->text(42, PdfLayoutService::BODY_TOP, 18, PdfLayoutService::FONT_BOLD, 'Verifica documento');
        $verification = $this->layout->verificationBlock(PdfLayoutService::BODY_TOP - 48, $signature, $verifyUrl);
        $page = $this->layout->page($content . $verification['content'], [
            'number' => $this->pageReference($document, $pageNumber),
            'protocol' => (string)($protocol['protocol_number'] ?? '-'),
            'date' => (string)($protocol['created_at'] ?? $document['created_at'] ?? date('Y-m-d H:i')),
            'verify_text' => 'Verifica autenticita copia digitale',
        ]);
        $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
        $page['links'][] = ['rect' => [260, 723, 430, 738], 'url' => $verifyUrl];
        $page['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];
        $page['links'] = array_merge($page['links'], $verification['links']);

        return $page;
    }

    private function pageWithHeader(string $content, array $document, ?array $protocol, string $verifyUrl, int $pageNumber): array
    {
        $page = $this->layout->page($content, [
            'number' => $this->pageReference($document, $pageNumber),
            'protocol' => (string)($protocol['protocol_number'] ?? '-'),
            'date' => (string)($protocol['created_at'] ?? $document['created_at'] ?? date('Y-m-d H:i')),
            'verify_text' => 'Verifica autenticita copia digitale',
        ]);
        $page['images'][] = $this->qr->image($verifyUrl, 'Qr1', 502, 728, 52);
        $page['links'][] = ['rect' => [260, 723, 430, 738], 'url' => $verifyUrl];
        $page['links'][] = ['rect' => [502, 728, 554, 780], 'url' => $verifyUrl];

        return $page;
    }

    private function pageReference(array $document, int $pageNumber): string
    {
        return (string)($document['public_name'] ?? pathinfo((string)$document['pdf_public_path'], PATHINFO_FILENAME))
            . ' / ' . $pageNumber;
    }

    private function textLines(string $text): array
    {
        $lines = [];
        foreach (preg_split("/\R/", trim($text)) ?: [] as $paragraph) {
            foreach (explode("\n", wordwrap($paragraph, 100, "\n", true)) as $line) {
                $lines[] = $line;
            }
            $lines[] = '';
        }

        return $lines;
    }

    private function title(array $document): string
    {
        return $this->layout->text(42, 674, 10, PdfLayoutService::FONT_REGULAR, (string)$document['original_name']);
    }

    private function jpegCopy(string $sourcePath): string
    {
        $source = imagecreatefromstring((string)file_get_contents($sourcePath));
        if ($source === false) {
            throw new HttpException(422, 'Immagine non valida.');
        }

        $targetPath = sys_get_temp_dir() . '/myrsu_image_' . bin2hex(random_bytes(8)) . '.jpg';
        imagejpeg($source, $targetPath, 94);
        imagedestroy($source);

        return $targetPath;
    }

    private function renderPages(string $sourcePath, string $tempDir): array
    {
        $pages = [];
        for ($page = 1; $page <= $this->pageCount($sourcePath); $page++) {
            $jpegPath = $tempDir . '/page-' . $page . '.jpg';
            $command = escapeshellarg($this->ghostscriptPath())
                . ' -dBATCH -dNOPAUSE -q -sDEVICE=jpeg -r144 -dJPEGQ=92'
                . ' -dFirstPage=' . $page . ' -dLastPage=' . $page
                . ' -sOutputFile=' . escapeshellarg($jpegPath)
                . ' ' . escapeshellarg($sourcePath);
            exec($command, $output, $exitCode);
            if ($exitCode === 0 && is_file($jpegPath)) {
                $pages[] = $jpegPath;
            }
        }

        return $pages;
    }

    private function pageCount(string $path): int
    {
        $command = escapeshellarg($this->ghostscriptPath())
            . ' -q -dNOSAFER -dNODISPLAY -c '
            . escapeshellarg('(' . str_replace('\\', '/', $path) . ') (r) file runpdfbegin pdfpagecount = quit');
        exec($command, $output, $exitCode);

        return $exitCode === 0 ? max(1, (int)($output[0] ?? 1)) : 1;
    }

    private function ghostscriptPath(): string
    {
        foreach ([(string)getenv('GHOSTSCRIPT_PATH'), 'C:/Program Files/gs/gs10.07.0/bin/gswin64c.exe'] as $path) {
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
