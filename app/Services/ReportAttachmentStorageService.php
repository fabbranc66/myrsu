<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class ReportAttachmentStorageService
{
    private string $path;

    public function __construct(private readonly string $basePath)
    {
        $this->path = $this->basePath . '/storage/private/report-attachments';
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }
    }

    public function store(array $file): array
    {
        $this->assertUpload($file);
        $storedName = bin2hex(random_bytes(20));
        $target = $this->path . '/' . $storedName;

        if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
            throw new HttpException(500, 'Salvataggio allegato fallito.');
        }

        $mimeType = mime_content_type($target) ?: 'application/octet-stream';
        if (!str_starts_with($mimeType, 'image/') && !str_starts_with($mimeType, 'video/')) {
            unlink($target);
            throw new HttpException(422, 'Sono ammessi solo foto e video.');
        }

        return [
            'original_name' => basename((string)$file['name']),
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'size_bytes' => filesize($target),
            'checksum_sha256' => hash_file('sha256', $target),
        ];
    }

    public function path(string $storedName): string
    {
        return $this->path . '/' . basename($storedName);
    }

    private function assertUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, 'Allegato non valido.');
        }

        if ((int)$file['size'] <= 0 || (int)$file['size'] > 52428800) {
            throw new HttpException(422, 'Allegato non valido.');
        }
    }
}
