<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class DocumentVerificationPageService
{
    public function append(string $pdfPath, array $document, string $signature, ?string $pdfChecksum = null): void
    {
        $tempDir = sys_get_temp_dir() . '/myrsu_verify_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0775, true);

        $url = $this->verifyUrl((int)$document['id'], $signature);
        $basePdf = $tempDir . '/base.pdf';
        $htmlPath = $tempDir . '/verify.html';
        $pagePdf = $tempDir . '/verify.pdf';
        $mergedPdf = $tempDir . '/merged.pdf';
        $finalHtmlPath = $tempDir . '/verify-final.html';
        $finalPagePdf = $tempDir . '/verify-final.pdf';
        $finalMergedPdf = $tempDir . '/merged-final.pdf';

        $qrPng = (new QRCode(new QROptions(['outputType' => QRCode::OUTPUT_IMAGE_PNG, 'scale' => 8])))->render($url);
        copy($pdfPath, $basePdf);

        file_put_contents($htmlPath, $this->html($document, $signature, $url, $qrPng, $pdfChecksum));
        $this->printHtml($htmlPath, $pagePdf);
        $this->merge([$basePdf, $pagePdf], $mergedPdf);

        $finalChecksum = $pdfChecksum ?? hash_file('sha256', $mergedPdf);
        file_put_contents($finalHtmlPath, $this->html($document, $signature, $url, $qrPng, $finalChecksum));
        $this->printHtml($finalHtmlPath, $finalPagePdf);
        $this->merge([$basePdf, $finalPagePdf], $finalMergedPdf);
        copy($finalMergedPdf, $pdfPath);
        $this->deleteDirectory($tempDir);
    }

    private function html(array $document, string $signature, string $url, string $qrPng, ?string $pdfChecksum): string
    {
        $qrUrl = str_starts_with($qrPng, 'data:image/')
            ? $qrPng
            : 'data:image/png;base64,' . base64_encode($qrPng);

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . 'body{font-family:Arial,sans-serif;padding:34px;color:#111}.box{border:1px solid #111;padding:22px}'
            . 'h1{font-size:22px;margin:0 0 14px}.row{margin:8px 0}.label{font-weight:700}'
            . 'a.verify-link{color:#084298;font-weight:700;text-decoration:underline}'
            . '.qr{display:inline-block;margin-top:16px}.qr img{width:180px;height:180px}'
            . '</style></head><body><div class="box">'
            . '<h1>Verifica documento RSU</h1>'
            . $this->row('Documento', (string)$document['original_name'])
            . $this->row('ID documento', (string)$document['id'])
            . $this->row('Firma digitale', $signature)
            . $this->row('Hash PDF', $pdfChecksum ?? (string)$document['pdf_checksum_sha256'])
            . $this->rowHtml(
                'URL verifica',
                '<a class="verify-link" href="' . $this->escape($url) . '">' . $this->escape($url) . '</a>'
            )
            . '<a class="qr" href="' . $this->escape($url) . '"><img src="' . $this->escape($qrUrl) . '" alt="QR verifica"></a>'
            . '</div></body></html>';
    }

    private function printHtml(string $htmlPath, string $pdfPath): void
    {
        $profileDir = sys_get_temp_dir() . '/myrsu_lo_verify_' . bin2hex(random_bytes(8));
        $profileUrl = 'file:///' . str_replace('\\', '/', $profileDir);
        $outDir = dirname($pdfPath);

        $command = escapeshellarg($this->sofficePath())
            . ' ' . escapeshellarg('-env:UserInstallation=' . $profileUrl)
            . ' --headless --nologo --nofirststartwizard --convert-to pdf --outdir '
            . escapeshellarg($outDir)
            . ' '
            . escapeshellarg($htmlPath);

        exec($command, $output, $exitCode);
        $createdPdf = $outDir . '/' . pathinfo($htmlPath, PATHINFO_FILENAME) . '.pdf';
        if ($createdPdf !== $pdfPath && is_file($createdPdf)) {
            rename($createdPdf, $pdfPath);
        }

        if (is_dir($profileDir)) {
            $this->deleteDirectory($profileDir);
        }

        if ($exitCode !== 0 || !is_file($pdfPath)) {
            throw new HttpException(422, 'Pagina verifica PDF fallita.');
        }
    }

    private function merge(array $pdfPaths, string $targetPath): void
    {
        $command = escapeshellarg($this->ghostscriptPath())
            . ' -dBATCH -dNOPAUSE -dPreserveAnnots=true -q -sDEVICE=pdfwrite -sOutputFile=' . escapeshellarg($targetPath)
            . ' ' . implode(' ', array_map('escapeshellarg', $pdfPaths));

        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($targetPath)) {
            throw new HttpException(422, 'Merge PDF verifica fallito.');
        }
    }

    private function verifyUrl(int $id, string $signature): string
    {
        $baseUrl = rtrim((string)(getenv('APP_URL') ?: 'http://localhost/myrsu'), '/');
        return $baseUrl . '/ui/document-verify.html?id=' . $id . '&sig=' . urlencode($signature);
    }

    private function row(string $label, string $value): string
    {
        return '<div class="row"><span class="label">' . $this->escape($label) . ':</span> ' . $this->escape($value) . '</div>';
    }

    private function rowHtml(string $label, string $html): string
    {
        return '<div class="row"><span class="label">' . $this->escape($label) . ':</span> ' . $html . '</div>';
    }

    private function sofficePath(): string
    {
        $path = 'C:/Program Files/LibreOffice/program/soffice.com';
        if (is_file($path)) {
            return $path;
        }

        throw new HttpException(500, 'LibreOffice non trovato.');
    }

    private function ghostscriptPath(): string
    {
        $path = 'C:/Program Files/gs/gs10.07.0/bin/gswin64c.exe';
        if (is_file($path)) {
            return $path;
        }

        throw new HttpException(500, 'Ghostscript non trovato.');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function deleteDirectory(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        }

        rmdir($path);
    }
}
