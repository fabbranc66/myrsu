<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RoomParticipantRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function membership(int $roomId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ru.*, CASE WHEN r.responsible_id = ru.user_id THEN 1 ELSE 0 END AS is_responsible
             FROM room_users ru
             JOIN rooms r ON r.id = ru.room_id AND r.deleted_at IS NULL
             WHERE ru.room_id = ? AND ru.user_id = ? AND ru.active = 1'
        );
        $stmt->execute([$roomId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function forRoom(int $roomId): array
    {
        $internal = $this->pdo->prepare(
            "SELECT ru.id, ru.user_id, ru.permission_level, ru.room_role, ru.active, ru.created_at,
                    u.name, u.email, NULL AS organization, NULL AS local_identifier,
                    NULL AS verification_sent_at, ru.created_at AS registered_at, 'internal' AS participant_type
             FROM room_users ru JOIN users u ON u.id = ru.user_id
             WHERE ru.room_id = ? AND ru.active = 1"
        );
        $internal->execute([$roomId]);
        $external = $this->pdo->prepare(
            "SELECT ep.id, NULL AS user_id, ep.permission_level, ep.room_role, ep.active, ep.created_at,
                    ep.name, ep.email, ep.organization, ep.local_identifier,
                    ep.verification_sent_at, ep.registered_at, 'external' AS participant_type
             FROM room_external_participants ep
             WHERE ep.room_id = ? AND ep.active = 1"
        );
        $external->execute([$roomId]);
        $participants = array_merge($internal->fetchAll(), $external->fetchAll());
        usort($participants, static fn (array $left, array $right): int => strcasecmp(
            (string)($left['name'] ?? ''),
            (string)($right['name'] ?? '')
        ));
        return $participants;
    }

    public function save(int $roomId, int $userId, string $permission, string $role, int $invitedBy): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO room_users
             (room_id, user_id, permission_level, room_role, invited_by, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE permission_level = VALUES(permission_level), room_role = VALUES(room_role),
             invited_by = VALUES(invited_by), active = 1, updated_at = NOW()'
        );
        $stmt->execute([$roomId, $userId, $permission, $role ?: null, $invitedBy]);

        return $this->membership($roomId, $userId);
    }

    public function revoke(int $roomId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE room_users SET active = 0, updated_at = NOW() WHERE room_id = ? AND user_id = ?'
        );
        $stmt->execute([$roomId, $userId]);
    }

    public function createExternal(int $roomId, array $data, int $addedBy): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO room_external_participants
             (room_id, name, email, organization, room_role, permission_level, access_token_hash,
              token_expires_at, added_by, active, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
        );
        $stmt->execute([
            $roomId, $data['name'], $data['email'], $data['organization'], $data['room_role'],
            $data['permission_level'], $data['access_token_hash'], $data['token_expires_at'], $addedBy,
        ]);
        return $this->externalById((int)$this->pdo->lastInsertId());
    }

    public function externalById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ep.*, 'external' AS participant_type FROM room_external_participants ep WHERE ep.id = ? AND ep.active = 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function revokeExternal(int $roomId, int $externalId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE room_external_participants SET active = 0, updated_at = NOW() WHERE room_id = ? AND id = ?'
        );
        $stmt->execute([$roomId, $externalId]);
    }
}
