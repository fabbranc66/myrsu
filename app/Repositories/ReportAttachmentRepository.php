<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReportAttachmentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $reportId, array $file): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO report_attachments
             (report_id, original_name, stored_name, mime_type, size_bytes, checksum_sha256, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $reportId,
            $file['original_name'],
            $file['stored_name'],
            $file['mime_type'],
            $file['size_bytes'],
            $file['checksum_sha256'],
        ]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function forReport(int $reportId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, report_id, original_name, mime_type, size_bytes, created_at
             FROM report_attachments WHERE report_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$reportId]);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM report_attachments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }
}
