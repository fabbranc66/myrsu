<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PracticeLinkRepository
{
    private const TYPES = ['document', 'report', 'comment', 'protocol', 'attachment', 'meeting'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function link(int $practiceId, string $entityType, int $entityId, ?int $userId): void
    {
        if (!in_array($entityType, self::TYPES, true)) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO practice_links
             (practice_id, entity_type, entity_id, created_by, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$practiceId, $entityType, $entityId, $userId]);
    }

    public function forPractice(int $practiceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM practice_links WHERE practice_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$practiceId]);

        return $stmt->fetchAll();
    }

    public function forEntity(string $entityType, int $entityId): array
    {
        if (!in_array($entityType, self::TYPES, true)) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM practice_links WHERE entity_type = ? AND entity_id = ? ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$entityType, $entityId]);

        return $stmt->fetchAll();
    }
}
