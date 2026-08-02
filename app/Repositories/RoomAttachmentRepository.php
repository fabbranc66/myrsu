<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RoomAttachmentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $roomId, int $messageId, array $file, ?int $userId, ?int $externalId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO room_attachments
             (room_id, message_id, original_name, stored_name, mime_type, attachment_type,
              size_bytes, checksum_sha256, uploaded_by_user, uploaded_by_external, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $roomId, $messageId, $file['original_name'], $file['stored_name'], $file['mime_type'],
            $file['attachment_type'], $file['size_bytes'], $file['checksum_sha256'], $userId, $externalId,
        ]);
        return $this->find((int)$this->pdo->lastInsertId()) ?? [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM room_attachments WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function forMessageIds(array $messageIds): array
    {
        if ($messageIds === []) return [];
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM room_attachments WHERE message_id IN ({$placeholders}) AND deleted_at IS NULL"
        );
        $stmt->execute(array_values($messageIds));
        return array_column($stmt->fetchAll(), null, 'message_id');
    }
}
