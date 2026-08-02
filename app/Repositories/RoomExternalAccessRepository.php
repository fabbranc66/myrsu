<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RoomExternalAccessRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ep.*, r.code AS room_code, r.title AS room_title, r.description AS room_description,
                    r.status AS room_status, c.name AS category_name
             FROM room_external_participants ep
             JOIN rooms r ON r.id = ep.room_id AND r.deleted_at IS NULL
             JOIN room_categories c ON c.id = r.category_id
             WHERE ep.access_token_hash = ? AND ep.active = 1
               AND (ep.token_expires_at IS NULL OR ep.token_expires_at > NOW())'
        );
        $stmt->execute([$tokenHash]);
        $external = $stmt->fetch();
        if ($external) {
            $external['access_type'] = 'external';
            $external['participant_id'] = $external['id'];
            return $external;
        }
        $internal = $this->pdo->prepare(
            'SELECT rat.*, rat.id AS access_id, rat.user_id AS matched_user_id,
                    rat.created_at AS registered_at, NULL AS verification_sent_at,
                    NULL AS local_identifier, u.name, u.email, u.phone, NULL AS room_role,
                    r.code AS room_code, r.title AS room_title, r.description AS room_description,
                    r.status AS room_status, c.name AS category_name
             FROM room_access_tokens rat
             JOIN users u ON u.id = rat.user_id AND u.status = \'active\'
             JOIN rooms r ON r.id = rat.room_id AND r.deleted_at IS NULL
             JOIN room_categories c ON c.id = r.category_id
             LEFT JOIN room_users ru ON ru.room_id = rat.room_id AND ru.user_id = rat.user_id AND ru.active = 1
             WHERE rat.access_token_hash = ? AND rat.active = 1 AND rat.expires_at > NOW()
               AND (ru.id IS NOT NULL OR EXISTS (
                 SELECT 1 FROM role_user aru JOIN roles ar ON ar.id = aru.role_id
                 WHERE aru.user_id = rat.user_id AND ar.name = \'admin\'
               ))'
        );
        $internal->execute([$tokenHash]);
        $access = $internal->fetch();
        if (!$access) return null;
        $access['access_type'] = 'internal';
        $access['participant_id'] = null;
        return $access;
    }

    public function touch(int $id, string $accessType): void
    {
        $table = $accessType === 'internal' ? 'room_access_tokens' : 'room_external_participants';
        $stmt = $this->pdo->prepare("UPDATE {$table} SET last_access_at = NOW() WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function createInternalToken(int $roomId, int $userId, string $permission, string $tokenHash): void
    {
        $this->pdo->prepare('UPDATE room_access_tokens SET active = 0 WHERE room_id = ? AND user_id = ?')->execute([$roomId, $userId]);
        $stmt = $this->pdo->prepare(
            'INSERT INTO room_access_tokens
             (room_id, user_id, access_token_hash, permission_level, expires_at, active, created_at)
             VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 12 HOUR), 1, NOW())'
        );
        $stmt->execute([$roomId, $userId, $tokenHash, $permission]);
    }

    public function beginRegistration(
        int $id,
        string $name,
        string $email,
        ?string $phone,
        ?string $localIdentifier,
        ?int $matchedUserId,
        string $accessTokenHash
    ): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE room_external_participants
             SET name = ?, email = ?, phone = ?, local_identifier = ?, matched_user_id = ?,
                 access_token_hash = ?, verification_sent_at = NOW(), updated_at = NOW()
             WHERE id = ? AND active = 1 AND registered_at IS NULL AND verification_sent_at IS NULL'
        );
        $stmt->execute([$name, $email, $phone, $localIdentifier, $matchedUserId, $accessTokenHash, $id]);
        return $this->findById($id);
    }

    public function confirm(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE room_external_participants
             SET registered_at = NOW(), last_access_at = NOW(), updated_at = NOW()
             WHERE id = ? AND active = 1 AND verification_sent_at IS NOT NULL AND registered_at IS NULL'
        );
        $stmt->execute([$id]);
        return $this->findById($id);
    }

    private function findById(int $id): array
    {
        $find = $this->pdo->prepare('SELECT * FROM room_external_participants WHERE id = ? AND active = 1');
        $find->execute([$id]);
        return $find->fetch() ?: [];
    }
}
