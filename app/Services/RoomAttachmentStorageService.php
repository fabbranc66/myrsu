<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class RoomAttachmentStorageService
{
    private string $root;

    public function __construct(string $basePath, private readonly MediaDurationService $duration)
    {
        $this->root = $basePath . '/storage/private/room-attachments';
    }

    public function store(int $roomId, array $file): array
    {
        $this->assertUpload($file);
        $directory = $this->root . '/' . $roomId;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new HttpException(500, 'Creazione archivio allegati fallita.');
        }
        $storedName = bin2hex(random_bytes(20));
        $path = $directory . '/' . $storedName;
        if (!move_uploaded_file((string)$file['tmp_name'], $path)) {
            throw new HttpException(500, 'Salvataggio allegato fallito.');
        }
        $mime = mime_content_type($path) ?: 'application/octet-stream';
        $clientMime = strtolower(trim((string)($file['type'] ?? '')));
        $type = $this->attachmentType($mime, (string)$file['name'], $clientMime);
        if ($type === null) {
            unlink($path);
            throw new HttpException(422, 'Formato allegato non ammesso.');
        }
        if ($type === 'video') {
            $seconds = $this->duration->seconds($path, $mime);
            if ($seconds === null || $seconds > 30.0) {
                unlink($path);
                throw new HttpException(422, 'Il video deve essere MP4, MOV o WebM e durare massimo 30 secondi.');
            }
        }
        return [
            'original_name' => basename((string)$file['name']),
            'stored_name' => $storedName,
            'mime_type' => $type === 'audio' && str_starts_with($clientMime, 'audio/') ? $clientMime : $mime,
            'attachment_type' => $type,
            'size_bytes' => filesize($path),
            'checksum_sha256' => hash_file('sha256', $path),
        ];
    }

    public function path(int $roomId, string $storedName): string
    {
        return $this->root . '/' . $roomId . '/' . basename($storedName);
    }

    public function delete(int $roomId, string $storedName): void
    {
        $path = $this->path($roomId, $storedName);
        if (is_file($path)) unlink($path);
    }

    private function assertUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, 'Allegato non valido.');
        }
        if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > 52428800) {
            throw new HttpException(422, 'Allegato massimo 50 MB.');
        }
    }

    private function attachmentType(string $mime, string $name, string $clientMime): ?string
    {
        if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') return 'image';
        if (str_starts_with($clientMime, 'audio/')) return 'audio';
        if (str_starts_with($mime, 'audio/')) return 'audio';
        if (str_starts_with($mime, 'video/')) return 'video';
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['pdf', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods'];
        return in_array($extension, $allowed, true) ? 'document' : null;
    }
}
