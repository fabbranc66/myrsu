<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RoomTimelineRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function page(int $roomId, int $limit, ?int $beforeId): array
    {
        $before = $beforeId === null ? '' : ' AND timeline_id < :before_id';
        $sql = "SELECT * FROM (
                  SELECT id AS timeline_id, 'message' AS timeline_type, message_type AS subtype,
                         author_id AS user_id, external_author_id, content, parent_id AS entity_id, created_at
                  FROM room_messages WHERE room_id = :room_messages AND deleted_at IS NULL
                  UNION ALL
                  SELECT id AS timeline_id, 'event' AS timeline_type, action AS subtype,
                         user_id, NULL AS external_author_id, metadata_json AS content, entity_id, created_at
                  FROM room_events WHERE room_id = :room_events
                  UNION ALL
                  SELECT rd.id AS timeline_id, 'document' AS timeline_type, 'shared' AS subtype,
                         rd.shared_by AS user_id, NULL AS external_author_id, d.original_name AS content, rd.document_id AS entity_id, rd.shared_at AS created_at
                  FROM room_documents rd JOIN documents d ON d.id = rd.document_id
                  WHERE rd.room_id = :room_documents AND rd.revoked_at IS NULL
                ) timeline WHERE 1 = 1{$before}
                ORDER BY created_at DESC, timeline_id DESC LIMIT :limit_rows";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':room_messages', $roomId, PDO::PARAM_INT);
        $stmt->bindValue(':room_events', $roomId, PDO::PARAM_INT);
        $stmt->bindValue(':room_documents', $roomId, PDO::PARAM_INT);
        if ($beforeId !== null) {
            $stmt->bindValue(':before_id', $beforeId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit_rows', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $names = $this->userNames(array_unique(array_filter(array_column($rows, 'user_id'))));
        $externalNames = $this->externalNames(array_unique(array_filter(array_column($rows, 'external_author_id'))));
        foreach ($rows as &$row) {
            $row['user_name'] = $names[(int)$row['user_id']] ?? $externalNames[(int)$row['external_author_id']] ?? null;
        }
        return array_reverse($rows);
    }

    public function createMessage(int $roomId, int $authorId, array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO room_messages
             (room_id, parent_id, author_id, message_type, content, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$roomId, $data['parent_id'], $authorId, $data['message_type'], $data['content']]);
        return $this->message((int)$this->pdo->lastInsertId());
    }

    public function createExternalMessage(int $roomId, int $externalAuthorId, array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO room_messages
             (room_id, parent_id, author_id, external_author_id, message_type, content, created_at, updated_at)
             VALUES (?, ?, NULL, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$roomId, $data['parent_id'], $externalAuthorId, $data['message_type'], $data['content']]);
        return $this->message((int)$this->pdo->lastInsertId());
    }

    public function message(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM room_messages WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function deleteMessage(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE room_messages SET deleted_at = NOW(), updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function event(int $roomId, ?int $userId, string $action, ?string $entityType, ?int $entityId, array $metadata = []): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO room_events (room_id, user_id, action, entity_type, entity_id, metadata_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$roomId, $userId, $action, $entityType, $entityId, json_encode($metadata, JSON_UNESCAPED_UNICODE)]);
    }

    private function userNames(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name FROM users WHERE id IN ({$placeholders})");
        $stmt->execute(array_values($ids));
        return array_column($stmt->fetchAll(), 'name', 'id');
    }

    private function externalNames(array $ids): array
    {
        if ($ids === []) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name FROM room_external_participants WHERE id IN ({$placeholders})");
        $stmt->execute(array_values($ids));
        return array_column($stmt->fetchAll(), 'name', 'id');
    }
}
