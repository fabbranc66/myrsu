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

    public function publicReady(): array
    {
        return $this->pdo
            ->query(
                "SELECT d.id, d.original_name, d.original_stored_name, d.category, d.pdf_size_bytes, d.created_at
                 FROM documents d
                 WHERE visibility = 'public' AND conversion_status = 'ready'
                   AND category IN ('documenti', 'comunicati')
                   AND (
                     NOT EXISTS (
                       SELECT 1
                       FROM protocol_entries pe_any
                       WHERE pe_any.document_id = d.id
                     )
                     OR EXISTS (
                       SELECT 1
                       FROM protocol_entries pe_active
                       WHERE pe_active.document_id = d.id
                         AND pe_active.canceled_at IS NULL
                     )
                   )
                 ORDER BY d.category, d.created_at DESC, d.id DESC"
            )
            ->fetchAll();
    }

    public function pendingComunicati(): array
    {
        return $this->pdo
            ->query(
                "SELECT d.*, pe.protocol_number, pe.subject, pe.created_at AS protocol_created_at, u.name AS creator_name
                 FROM documents d
                 INNER JOIN protocol_entries pe ON pe.document_id = d.id AND pe.canceled_at IS NULL
                 LEFT JOIN users u ON u.id = d.uploaded_by
                 WHERE d.category = 'comunicati' AND d.conversion_status = 'pending'
                 ORDER BY d.id ASC"
            )
            ->fetchAll();
    }

    public function pendingOffice(): array
    {
        return $this->pdo->query(
            "SELECT d.*, u.name AS creator_name
             FROM documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.category = 'documenti' AND d.conversion_status = 'pending'
             ORDER BY d.id ASC"
        )->fetchAll();
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
            basename((string)$data['pdf_public_path']),
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
            "UPDATE documents
             SET size_bytes = ?, checksum_sha256 = ?, pdf_size_bytes = ?, pdf_checksum_sha256 = ?, conversion_status = 'ready'
             WHERE id = ?"
        );
        $stmt->execute([$size, $checksum, $size, $checksum, $id]);

        return $this->findById($id);
    }

    public function updatePublicPath(int $id, string $publicPath): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE documents SET stored_name = ?, pdf_public_path = ? WHERE id = ?');
        $stmt->execute([basename($publicPath), $publicPath, $id]);

        return $this->findById($id);
    }

    public function completePendingComunicato(int $id, string $signature, int $size, string $checksum): ?array
    {
        $stmt = $this->pdo->prepare(
            "UPDATE documents
             SET signature = ?, signed_at = NOW(), size_bytes = ?, checksum_sha256 = ?, pdf_size_bytes = ?, pdf_checksum_sha256 = ?,
                 conversion_status = 'ready'
             WHERE id = ? AND category = 'comunicati'"
        );
        $stmt->execute([$signature, $size, $checksum, $size, $checksum, $id]);

        return $this->findById($id);
    }

    public function completePendingDocument(int $id, string $signature, int $size, string $checksum): ?array
    {
        $stmt = $this->pdo->prepare(
            "UPDATE documents SET signature = ?, signed_at = NOW(), size_bytes = ?, checksum_sha256 = ?,
             pdf_size_bytes = ?, pdf_checksum_sha256 = ?, conversion_status = 'ready'
             WHERE id = ? AND category = 'documenti' AND conversion_status = 'pending'"
        );
        $stmt->execute([$signature, $size, $checksum, $size, $checksum, $id]);
        return $this->findById($id);
    }

    public function updateComunicato(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE documents
             SET original_name = ?, original_mime_type = ?, original_size_bytes = ?, original_checksum_sha256 = ?,
                 size_bytes = ?, checksum_sha256 = ?, pdf_size_bytes = ?, pdf_checksum_sha256 = ?, conversion_status = ?,
                 visibility = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['original_name'],
            $data['original_mime_type'],
            $data['original_size_bytes'],
            $data['original_checksum_sha256'],
            $data['pdf_size_bytes'],
            $data['pdf_checksum_sha256'],
            $data['pdf_size_bytes'],
            $data['pdf_checksum_sha256'],
            $data['conversion_status'],
            $data['visibility'],
            $id,
        ]);

        return $this->findById($id);
    }
}
