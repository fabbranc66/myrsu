<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class GdprConsentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $userId, string $type, string $version, bool $accepted, ?string $ipAddress): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gdpr_consents (user_id, consent_type, document_version, accepted, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $type, $version, $accepted ? 1 : 0, $ipAddress]);

        return (int)$this->pdo->lastInsertId();
    }

    public function latestForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM gdpr_consents
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function allForUser(int $userId): array
    {
        return $this->latestForUser($userId);
    }
}
