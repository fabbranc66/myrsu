<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PracticeNoteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $practiceId, string $body, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO practice_notes (practice_id, body, created_by, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$practiceId, $body, $userId]);
        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function findById(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pn.*, u.name created_by_name
             FROM practice_notes pn JOIN users u ON u.id = pn.created_by WHERE pn.id = ?'
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
