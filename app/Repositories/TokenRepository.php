<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\HttpException;
use PDO;
use PDOException;

final class TokenRepository
{
    private const ACTIVE_CONDITION = "revoked_at IS NULL
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND COALESCE(last_used_at, created_at) > DATE_SUB(NOW(), INTERVAL 2 HOUR)";

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $userId, string $plainToken, ?string $deviceName = null, ?string $expectedPasswordHash = null): void
    {
        $passwordCheck = $expectedPasswordHash === null ? '' : ' AND BINARY password_hash = ?';
        $parameters = [hash('sha256', $plainToken), $deviceName, $userId];
        if ($expectedPasswordHash !== null) $parameters[] = $expectedPasswordHash;
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO api_tokens (user_id, token_hash, device_name, created_at)
                 SELECT id, ?, ?, NOW() FROM users WHERE id = ? AND status = \'active\'' . $passwordCheck . ' FOR UPDATE'
            );
            $stmt->execute($parameters);
        } catch (PDOException) {
            $parameters = [hash('sha256', $plainToken), $userId];
            if ($expectedPasswordHash !== null) $parameters[] = $expectedPasswordHash;
            $stmt = $this->pdo->prepare(
                'INSERT INTO api_tokens (user_id, token_hash, created_at)
                 SELECT id, ?, NOW() FROM users WHERE id = ? AND status = \'active\'' . $passwordCheck . ' FOR UPDATE'
            );
            $stmt->execute($parameters);
        }

        if ($stmt->rowCount() !== 1) {
            throw new HttpException(403, $expectedPasswordHash === null ? 'Utente non attivo.' : 'Accesso non piu valido. Ripetere il login.');
        }
    }

    public function findValidByHash(string $hash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM api_tokens WHERE token_hash = ? AND ' . self::ACTIVE_CONDITION . ' LIMIT 1'
        );
        $stmt->execute([$hash]);

        return $stmt->fetch() ?: null;
    }

    public function touch(int $id): bool
    {
        $stmt = $this->pdo->prepare('UPDATE api_tokens SET last_used_at = NOW() WHERE id = ? AND ' . self::ACTIVE_CONDITION);
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) return true;

        $check = $this->pdo->prepare('SELECT id FROM api_tokens WHERE id = ? AND ' . self::ACTIVE_CONDITION . ' LIMIT 1');
        $check->execute([$id]);
        return $check->fetchColumn() !== false;
    }

    public function revokeByPlainToken(string $plainToken): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_tokens SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL'
        );
        $stmt->execute([hash('sha256', $plainToken)]);
    }

    public function revokeForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$userId]);
    }
}
