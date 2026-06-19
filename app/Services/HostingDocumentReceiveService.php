<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class HostingDocumentReceiveService
{
    public function __construct(
        private readonly string $basePath,
        private readonly array $config
    ) {
    }

    public function receive(array $file, string $category, string $checksum): array
    {
        $this->assertEnabled();
        $this->assertCategory($category);
        $this->assertUpload($file);

        $targetDir = $this->basePath . '/public/documents/' . $category;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        $fileName = $this->safePdfName((string)$file['name']);
        $targetPath = $targetDir . '/' . $fileName;

        if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
            throw new HttpException(500, 'Salvataggio hosting fallito.');
        }

        if (hash_file('sha256', $targetPath) !== $checksum) {
            unlink($targetPath);
            throw new HttpException(422, 'Checksum non valido.');
        }

        return [
            'path' => 'public/documents/' . $category . '/' . $fileName,
            'checksum_sha256' => $checksum,
        ];
    }

    public function assertToken(?string $token): void
    {
        if ($token === null || !hash_equals($this->token(), $token)) {
            throw new HttpException(401, 'Token hosting non valido.');
        }
    }

    private function assertEnabled(): void
    {
        if ($this->token() === '') {
            throw new HttpException(403, 'Upload hosting non configurato.');
        }
    }

    private function assertUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, 'Upload non valido.');
        }

        if ((string)($file['type'] ?? '') !== 'application/pdf') {
            throw new HttpException(422, 'Solo PDF ammessi.');
        }
    }

    private function assertCategory(string $category): void
    {
        if (!in_array($category, ['comunicati', 'documenti', 'segnalazioni'], true)) {
            throw new HttpException(422, 'Category non valida.');
        }
    }

    private function safePdfName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name)) ?: 'document.pdf';
        return str_ends_with(strtolower($name), '.pdf') ? $name : $name . '.pdf';
    }

    private function token(): string
    {
        return trim((string)($this->config['documents_token'] ?? ''));
    }
}
