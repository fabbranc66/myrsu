<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReportRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(string $status): array
    {
        $where = $status === 'all' ? '' : 'WHERE r.status = ?';
        $stmt = $this->pdo->prepare(
            "SELECT r.*, u.name AS author_name, d.original_name AS document_name, pe.protocol_number
             FROM reports r
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN documents d ON d.id = r.document_id
             LEFT JOIN protocol_entries pe ON pe.document_id = d.id AND pe.canceled_at IS NULL
             {$where}
             ORDER BY r.id DESC"
        );
        $stmt->execute($status === 'all' ? [] : [$status]);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reports WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM reports WHERE status = ?');
        $stmt->execute([$status]);

        return (int)$stmt->fetchColumn();
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reports
             (tracking_code, subject, message, contact, user_id, origin, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['tracking_code'],
            $data['subject'],
            $data['message'],
            $data['contact'],
            $data['user_id'],
            $data['origin'],
            'pending',
        ]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function attachDocument(int $id, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE reports SET document_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$documentId, $id]);

        return $this->findById($id);
    }

    public function moderate(int $id, string $status, string $reply): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE reports SET status = ?, reply = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $reply, $id]);

        return $this->findById($id);
    }
}
