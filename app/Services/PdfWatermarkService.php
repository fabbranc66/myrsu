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

        $postScriptPath = $tempDir . '/watermark.ps';
        $trimmedPath = $sourcePath;
        file_put_contents($postScriptPath, $this->postScript($header));
        $runWatermark = '(' . str_replace('\\', '/', $postScriptPath) . ') run';

        $command = escapeshellarg($this->ghostscriptPath())
            . ' -dNOSAFER -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($targetPath)
            . ' -c ' . escapeshellarg($runWatermark)
            . ' -f'
            . ' ' . escapeshellarg($trimmedPath);

        exec($command, $output, $exitCode);
        $this->deleteDirectory($tempDir);

        if ($exitCode !== 0 || !is_file($targetPath)) {
            throw new HttpException(422, 'Watermark PDF fallito.');
        }

        $this->keepContentPages($targetPath);
    }

    private function postScript(array $header): string
    {
        $title = $this->postScriptText((string)($header['title'] ?? 'Documento RSU'));
        $date = $this->postScriptText((string)($header['date'] ?? date('Y-m-d H:i')));

        return <<<PS
<< /BeginPage {
  pop
  gsave
  currentpagedevice /PageSize get aload pop /pageHeight exch def /pageWidth exch def
  pageWidth 2 div pageHeight 2 div translate
  -28 rotate
  0.92 setgray
  4 setlinewidth
  -260 -90 520 180 rectstroke
  /Helvetica-Bold findfont 120 scalefont setfont
  (RSU) dup stringwidth pop -0.5 mul 18 moveto show
  /Helvetica-Bold findfont 34 scalefont setfont
  (Sitem Canegrate) dup stringwidth pop -0.5 mul -42 moveto show
  grestore
} bind /EndPage {
  pop pop
  gsave
  currentpagedevice /PageSize get aload pop /pageHeight exch def /pageWidth exch def
  0 setgray
  0.8 setlinewidth
  1 setgray 28 pageHeight 42 sub 540 30 rectfill
  0 setgray 28 pageHeight 42 sub 540 30 rectstroke
  /Helvetica-Bold findfont 15 scalefont setfont
  42 pageHeight 32 sub moveto (RSU) show
  /Helvetica findfont 8 scalefont setfont
  96 pageHeight 24 sub moveto (Documento: {$title}) show
  96 pageHeight 35 sub moveto (Data: {$date}) show
  grestore
  true
} bind >> setpagedevice
PS;
    }

    private function postScriptText(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
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

    private function trimLeadingBlankPages(string $sourcePath, string $tempDir): string
    {
        $pageCount = $this->pageCount($sourcePath);
        $firstPage = 1;

        for ($page = 1; $page <= $pageCount; $page++) {
            if (!$this->isBlankPage($sourcePath, $page)) {
                $firstPage = $page;
                break;
            }
        }

        if ($firstPage <= 1) {
            return $sourcePath;
        }

        $targetPath = $tempDir . '/trimmed.pdf';
        $command = escapeshellarg($this->ghostscriptPath())
            . ' -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dFirstPage=' . $firstPage
            . ' -sOutputFile=' . escapeshellarg($targetPath)
            . ' ' . escapeshellarg($sourcePath);

        exec($command, $output, $exitCode);
        return $exitCode === 0 && is_file($targetPath) ? $targetPath : $sourcePath;
    }

    private function pageCount(string $path): int
    {
        $command = escapeshellarg($this->ghostscriptPath())
            . ' -q -dNOSAFER -dNODISPLAY -c '
            . escapeshellarg('(' . str_replace('\\', '/', $path) . ') (r) file runpdfbegin pdfpagecount = quit');

        exec($command, $output, $exitCode);
        return $exitCode === 0 ? max(1, (int)($output[0] ?? 1)) : 1;
    }

    private function keepContentPages(string $path): void
    {
        $pageCount = $this->pageCount($path);
        $contentPages = $this->contentPages($path, $pageCount);

        if (count($contentPages) === 0 || count($contentPages) === $pageCount) {
            return;
        }

        $pageFiles = [];
        foreach ($contentPages as $page) {
            $pagePath = $path . '.page-' . $page . '.pdf';
            $command = escapeshellarg($this->ghostscriptPath())
                . ' -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -dFirstPage=' . $page
                . ' -dLastPage=' . $page
                . ' -sOutputFile=' . escapeshellarg($pagePath)
                . ' ' . escapeshellarg($path);
            exec($command, $output, $exitCode);
            if ($exitCode === 0 && is_file($pagePath)) {
                $pageFiles[] = $pagePath;
            }
        }

        if (count($pageFiles) === 0) {
            return;
        }

        $trimmedPath = $path . '.content.pdf';
        $command = escapeshellarg($this->ghostscriptPath())
            . ' -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($trimmedPath)
            . ' ' . implode(' ', array_map('escapeshellarg', $pageFiles));

        exec($command, $output, $exitCode);
        if ($exitCode === 0 && is_file($trimmedPath)) {
            copy($trimmedPath, $path);
            unlink($trimmedPath);
        }

        foreach ($pageFiles as $pageFile) {
            if (is_file($pageFile)) {
                unlink($pageFile);
            }
        }
    }

    private function contentPages(string $path, int $pageCount): array
    {
        $pages = [];
        for ($page = 1; $page <= $pageCount; $page++) {
            $text = $this->pageText($path, $page);
            $clean = preg_replace('/\\b(RSU|Sitem Canegrate|Documento:.*|Data:.*)\\b/u', '', $text);
            if (trim((string)$clean) !== '') {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    private function isBlankPage(string $path, int $page): bool
    {
        $text = $this->pageText($path, $page);
        if (trim($text) === '') {
            return true;
        }

        $bboxCommand = escapeshellarg($this->ghostscriptPath())
            . ' -dBATCH -dNOPAUSE -q -sDEVICE=bbox -dFirstPage=' . $page . ' -dLastPage=' . $page
            . ' ' . escapeshellarg($path) . ' 2>&1';

        exec($bboxCommand, $output);
        $bbox = implode("\n", $output);

        return str_contains($bbox, '%%BoundingBox: 0 0 0 0');
    }

    private function pageText(string $path, int $page): string
    {
        $textPath = sys_get_temp_dir() . '/myrsu_page_text_' . bin2hex(random_bytes(6)) . '.txt';
        $textCommand = escapeshellarg($this->ghostscriptPath())
            . ' -dBATCH -dNOPAUSE -q -sDEVICE=txtwrite -dFirstPage=' . $page . ' -dLastPage=' . $page
            . ' -sOutputFile=' . escapeshellarg($textPath)
            . ' ' . escapeshellarg($path);

        exec($textCommand);
        $text = is_file($textPath) ? trim((string)file_get_contents($textPath)) : '';
        if (is_file($textPath)) {
            unlink($textPath);
        }

        return $text;
    }

    private function deleteDirectory(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        }

        rmdir($path);
    }
}
