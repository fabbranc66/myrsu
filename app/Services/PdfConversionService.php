<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class PdfConversionService
{
    public function __construct(private readonly PdfWatermarkService $watermark)
    {
    }

    public function convert(string $sourcePath, string $originalName, string $targetPath, string $mimeType): void
    {
        if ($mimeType === 'application/pdf') {
            $this->watermark->apply($sourcePath, $targetPath, ['title' => $originalName]);
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
