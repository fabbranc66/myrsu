<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use PDOException;

final class TokenRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $userId, string $plainToken, ?string $deviceName = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO api_tokens (user_id, token_hash, device_name, created_at)
                 VALUES (?, ?, ?, NOW())'
            );
            $stmt->execute([$userId, hash('sha256', $plainToken), $deviceName]);
        } catch (PDOException) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO api_tokens (user_id, token_hash, created_at)
                 VALUES (?, ?, NOW())'
            );
            $stmt->execute([$userId, hash('sha256', $plainToken)]);
        }
    }

    public function findValidByHash(string $hash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute([$hash]);

        return $stmt->fetch() ?: null;
    }

    public function touch(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function revokeByPlainToken(string $plainToken): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_tokens SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL'
        );
        $stmt->execute([hash('sha256', $plainToken)]);
    }
}
