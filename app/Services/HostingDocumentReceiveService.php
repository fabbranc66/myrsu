<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\DocumentRepository;

final class HostingDocumentReceiveService
{
    public function __construct(
        private readonly string $basePath,
        private readonly array $config,
        private readonly ?DocumentRepository $documents = null
    ) {
    }

    public function receive(array $file, string $category, string $checksum, array $metadata): array
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

        $result = [
            'path' => 'public/documents/' . $category . '/' . $fileName,
            'checksum_sha256' => $checksum,
        ];
        $this->writeMetadata($metadata, $result['path'], $checksum);

        return $result;
    }

    public function receivePendingComunicato(array $file, int $documentId, string $checksum, string $signature): array
    {
        $this->assertEnabled();
        $this->assertUpload($file);

        if ($this->documents === null) {
            throw new HttpException(500, 'Repository documenti non disponibile.');
        }

        $document = $this->documents->findById($documentId);
        if ($document === null || (string)$document['category'] !== 'comunicati') {
            throw new HttpException(404, 'Documento non trovato.');
        }

        $targetPath = $this->basePath . '/' . ltrim((string)$document['pdf_public_path'], '/');
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0775, true);
        }

        if (!move_uploaded_file((string)$file['tmp_name'], $targetPath)) {
            throw new HttpException(500, 'Salvataggio hosting fallito.');
        }

        if (hash_file('sha256', $targetPath) !== $checksum) {
            unlink($targetPath);
            throw new HttpException(422, 'Checksum non valido.');
        }

        $updated = $this->documents->completePendingComunicato(
            $documentId,
            $signature,
            filesize($targetPath),
            $checksum
        );

        $this->writeMetadata([
            'document_id' => (string)$documentId,
            'original_name' => (string)$document['original_name'],
            'signature' => $signature,
            'signed_at' => (string)($updated['signed_at'] ?? ''),
        ], (string)$document['pdf_public_path'], $checksum);

        return $updated ?? [];
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

    private function writeMetadata(array $metadata, string $pdfPath, string $checksum): void
    {
        $id = (int)($metadata['document_id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $dir = $this->basePath . '/public/documents/metadata';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($dir . '/' . $id . '.json', json_encode([
            'id' => $id,
            'original_name' => (string)($metadata['original_name'] ?? ''),
            'pdf_public_path' => $pdfPath,
            'pdf_checksum_sha256' => $checksum,
            'signature' => (string)($metadata['signature'] ?? ''),
            'signed_at' => (string)($metadata['signed_at'] ?? ''),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function token(): string
    {
        return trim((string)($this->config['documents_token'] ?? ''));
    }
}
