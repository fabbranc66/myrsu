<?php

declare(strict_types=1);

namespace App\Services;

final class DocumentVerificationMetadataService
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function write(array $data): void
    {
        $id = (int)($data['document_id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $dir = $this->basePath . '/public/documents/metadata';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($dir . '/' . $id . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function find(int $id): ?array
    {
        $path = $this->basePath . '/public/documents/metadata/' . $id . '.json';
        if (!is_file($path)) {
            return null;
        }

        $data = json_decode((string)file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }
}
