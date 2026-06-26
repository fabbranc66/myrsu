<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UnionMeetingNoteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forMeeting(int $meetingId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, u.name AS author_name
             FROM union_meeting_notes n
             JOIN users u ON u.id = n.created_by
             WHERE n.meeting_id = ?
             ORDER BY n.created_at ASC, n.id ASC'
        );
        $stmt->execute([$meetingId]);

        return $stmt->fetchAll();
    }

    public function create(int $meetingId, string $type, string $body, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO union_meeting_notes (meeting_id, note_type, body, created_by, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$meetingId, $type, $body, $userId]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    private function findById(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, u.name AS author_name
             FROM union_meeting_notes n
             JOIN users u ON u.id = n.created_by
             WHERE n.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch();
    }
}
