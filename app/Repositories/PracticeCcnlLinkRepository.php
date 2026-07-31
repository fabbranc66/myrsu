<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PracticeCcnlLinkRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $practiceId, array $data, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO practice_ccnl_links
             (practice_id, block_code, block_title, section_title, source_path, excerpt, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $practiceId,
            $data['block_code'],
            $data['block_title'],
            $data['section_title'],
            $data['source_path'],
            $data['excerpt'],
            $userId,
        ]);

        return $this->find((int)$this->pdo->lastInsertId());
    }

    public function find(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM practice_ccnl_links WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }

    public function forPractice(int $practiceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT 'ccnl' type, CAST(id AS CHAR) id, section_title title, excerpt summary,
                    created_at event_at, block_code status, NULL document_id
             FROM practice_ccnl_links WHERE practice_id = ?"
        );
        $stmt->execute([$practiceId]);
        return $stmt->fetchAll();
    }

    public function delete(int $practiceId, int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM practice_ccnl_links WHERE practice_id = ? AND id = ?');
        $stmt->execute([$practiceId, $id]);
        return $stmt->rowCount() > 0;
    }
}
