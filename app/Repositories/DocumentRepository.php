<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM documents ORDER BY id DESC')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO documents
             (original_name, stored_name, mime_type, size_bytes, checksum_sha256,
              original_stored_name, original_mime_type, original_size_bytes, original_checksum_sha256,
              category, pdf_public_path, pdf_size_bytes, pdf_checksum_sha256, conversion_status,
              visibility, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['original_name'],
            $data['pdf_public_path'],
            'application/pdf',
            $data['pdf_size_bytes'],
            $data['pdf_checksum_sha256'],
            $data['original_stored_name'],
            $data['original_mime_type'],
            $data['original_size_bytes'],
            $data['original_checksum_sha256'],
            $data['category'],
            $data['pdf_public_path'],
            $data['pdf_size_bytes'],
            $data['pdf_checksum_sha256'],
            $data['conversion_status'],
            $data['visibility'],
            $data['uploaded_by'],
        ]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM documents WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function update(int $id, string $visibility): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE documents SET visibility = ? WHERE id = ?');
        $stmt->execute([$visibility, $id]);

        return $this->findById($id);
    }

    public function updateSignature(int $id, string $signature): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE documents SET signature = ?, signed_at = NOW() WHERE id = ?');
        $stmt->execute([$signature, $id]);

        return $this->findById($id);
    }

    public function updatePdfMetadata(int $id, int $size, string $checksum): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE documents SET size_bytes = ?, checksum_sha256 = ?, pdf_size_bytes = ?, pdf_checksum_sha256 = ? WHERE id = ?'
        );
        $stmt->execute([$size, $checksum, $size, $checksum, $id]);

        return $this->findById($id);
    }
}
