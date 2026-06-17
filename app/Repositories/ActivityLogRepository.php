<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ActivityLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function write(?int $userId, string $action, array $metadata = []): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, metadata_json, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([
            $userId,
            $action,
            json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT al.id, al.action, al.metadata_json, al.created_at,
                    actor.name AS actor_name,
                    COALESCE(target.name, fallback_target.name) AS target_name,
                    COALESCE(target.email, fallback_target.email) AS target_email
             FROM activity_logs al
             LEFT JOIN users actor ON actor.id = al.user_id
             LEFT JOIN users target ON target.id = COALESCE(
                JSON_UNQUOTE(JSON_EXTRACT(al.metadata_json, "$.created_user_id")),
                JSON_UNQUOTE(JSON_EXTRACT(al.metadata_json, "$.updated_user_id")),
                JSON_UNQUOTE(JSON_EXTRACT(al.metadata_json, "$.deleted_user_id")),
                JSON_UNQUOTE(JSON_EXTRACT(al.metadata_json, "$.target_user_id"))
             )
             LEFT JOIN users fallback_target ON fallback_target.id = al.user_id
             WHERE user_id = ?
                OR JSON_UNQUOTE(JSON_EXTRACT(metadata_json, "$.created_user_id")) = ?
                OR JSON_UNQUOTE(JSON_EXTRACT(metadata_json, "$.updated_user_id")) = ?
                OR JSON_UNQUOTE(JSON_EXTRACT(metadata_json, "$.deleted_user_id")) = ?
                OR JSON_UNQUOTE(JSON_EXTRACT(metadata_json, "$.target_user_id")) = ?
             ORDER BY al.created_at DESC, al.id DESC'
        );
        $stmt->execute([$userId, $userId, $userId, $userId, $userId]);

        return $stmt->fetchAll();
    }
}
