<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RsuElectionDraftRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findForUser(int $userId): ?array
    {
        $statement = $this->pdo->prepare('SELECT payload_json, updated_at FROM rsu_election_drafts WHERE user_id = ?');
        $statement->execute([$userId]);
        $row = $statement->fetch();
        return $row ? ['form' => json_decode($row['payload_json'], true, 512, JSON_THROW_ON_ERROR), 'updated_at' => $row['updated_at']] : null;
    }

    public function saveForUser(int $userId, array $form): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO rsu_election_drafts (user_id, payload_json, created_at, updated_at) VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), updated_at = NOW()'
        );
        $statement->execute([$userId, json_encode($form, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        return $this->findForUser($userId) ?? [];
    }
}
