<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class DocumentCommentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(string $status): array
    {
        $where = $status === 'all' ? '' : 'WHERE c.status = ?';
        $stmt = $this->pdo->prepare(
            "SELECT c.*, d.original_name AS document_name, d.category, u.name AS author_name
             FROM document_comments c
             INNER JOIN documents d ON d.id = c.document_id
             LEFT JOIN users u ON u.id = c.user_id
             {$where}
             ORDER BY c.id DESC"
        );
        $stmt->execute($status === 'all' ? [] : [$status]);

        return $stmt->fetchAll();
    }

    public function grouped(string $status): array
    {
        $comments = $this->all($status);
        $groups = [];

        foreach ($comments as $comment) {
            $documentId = (int)$comment['document_id'];
            if (!isset($groups[$documentId])) {
                $groups[$documentId] = [
                    'document_id' => $documentId,
                    'document_name' => $comment['document_name'],
                    'category' => $comment['category'],
                    'count' => 0,
                    'comments' => [],
                ];
            }
            $groups[$documentId]['count']++;
            $groups[$documentId]['comments'][] = $comment;
        }

        return array_values($groups);
    }

    public function publicForDocument(int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, subject, message, reply, origin, created_at
             FROM document_comments
             WHERE document_id = ? AND status = 'approved'
             ORDER BY id DESC"
        );
        $stmt->execute([$documentId]);

        return $stmt->fetchAll();
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM document_comments WHERE status = ?');
        $stmt->execute([$status]);

        return (int)$stmt->fetchColumn();
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_comments
             (document_id, subject, message, contact, user_id, origin, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['document_id'],
            $data['subject'],
            $data['message'],
            $data['contact'],
            $data['user_id'],
            $data['origin'],
            'pending',
        ]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_comments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function moderate(int $id, string $status, string $reply): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE document_comments SET status = ?, reply = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $reply, $id]);

        return $this->findById($id);
    }
}
