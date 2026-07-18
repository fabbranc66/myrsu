<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PracticeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allOpen(): array
    {
        return $this->pdo->query(
            "SELECT id, code, title, status
             FROM practices
             WHERE status NOT IN ('resolved', 'closed', 'archived', 'closed_positive', 'closed_negative')
             ORDER BY title ASC"
        )->fetchAll();
    }

    public function all(): array
    {
        return $this->pdo->query(
            "SELECT p.*, u.name assigned_user_name,
                    GREATEST(
                      p.updated_at,
                      COALESCE((SELECT MAX(pl.created_at) FROM practice_links pl WHERE pl.practice_id = p.id), p.updated_at),
                      COALESCE((SELECT MAX(pn.created_at) FROM practice_notes pn WHERE pn.practice_id = p.id), p.updated_at),
                      COALESCE((SELECT MAX(c.created_at) FROM calls_log c WHERE c.practice_id = p.id), p.updated_at)
                    ) last_activity_at
             FROM practices p
             LEFT JOIN users u ON u.id = p.assigned_user_id
             ORDER BY last_activity_at DESC, p.id DESC"
        )->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, u.name assigned_user_name, creator.name created_by_name
             FROM practices p
             LEFT JOIN users u ON u.id = p.assigned_user_id
             LEFT JOIN users creator ON creator.id = p.created_by
             WHERE p.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data, int $userId): array
    {
        $year = (int)date('Y');
        $this->pdo->beginTransaction();
        try {
            $sequenceQuery = $this->pdo->prepare(
                "SELECT COALESCE(MAX(CAST(RIGHT(code, 4) AS UNSIGNED)), 0) + 1
                 FROM practices WHERE code LIKE ? FOR UPDATE"
            );
            $sequenceQuery->execute(['PRA-' . $year . '-%']);
            $code = sprintf('PRA-%d-%04d', $year, (int)$sequenceQuery->fetchColumn());
            $stmt = $this->pdo->prepare(
                'INSERT INTO practices
                 (code, title, summary, type, status, priority, source_type, assigned_user_id,
                  visibility, due_date, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $code, $data['title'], $data['summary'], $data['type'], $data['status'],
                $data['priority'], $data['source_type'], $data['assigned_user_id'],
                $data['visibility'], $data['due_date'], $userId,
            ]);
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return $this->findById($id);
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function update(int $id, array $data): ?array
    {
        $closedAt = in_array($data['status'], ['resolved', 'closed', 'archived', 'closed_positive', 'closed_negative'], true)
            ? 'NOW()'
            : 'NULL';
        $stmt = $this->pdo->prepare(
            "UPDATE practices SET title = ?, summary = ?, type = ?, status = ?, priority = ?,
                    source_type = ?, assigned_user_id = ?, visibility = ?, due_date = ?,
                    closed_at = {$closedAt}, updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([
            $data['title'], $data['summary'], $data['type'], $data['status'], $data['priority'],
            $data['source_type'], $data['assigned_user_id'], $data['visibility'], $data['due_date'], $id,
        ]);

        return $this->findById($id);
    }
}
