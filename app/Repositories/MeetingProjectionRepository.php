<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class MeetingProjectionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function active(): ?array
    {
        $row = $this->pdo->query($this->selectSql() . ' WHERE s.active_slot = 1 LIMIT 1')->fetch();
        return $row ?: null;
    }

    public function activeByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare($this->selectSql() . ' WHERE s.active_slot = 1 AND s.public_token = ? LIMIT 1');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function activeByControlToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare($this->selectSql() . ' WHERE s.active_slot = 1 AND s.control_token = ? LIMIT 1');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $meetingId, int $userId, string $publicToken, string $controlToken): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO meeting_projection_sessions
             (meeting_id, public_token, control_token, status, active_slot, created_by, created_at, updated_at)
             VALUES (?, ?, ?, 'active', 1, ?, NOW(), NOW())"
        );
        $stmt->execute([$meetingId, $publicToken, $controlToken, $userId]);
        return $this->active();
    }

    public function close(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE meeting_projection_sessions
             SET status = 'closed', active_slot = NULL, current_document_id = NULL,
                 revision = revision + 1, closed_at = NOW(), updated_at = NOW()
             WHERE id = ? AND active_slot = 1"
        );
        $stmt->execute([$id]);
    }

    public function publish(int $id, int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE meeting_projection_sessions
             SET current_document_id = ?, scroll_ratio = 0, revision = revision + 1, updated_at = NOW()
             WHERE id = ? AND active_slot = 1'
        );
        $stmt->execute([$documentId, $id]);
        return $this->active();
    }

    public function position(int $id, float $ratio): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE meeting_projection_sessions
             SET scroll_ratio = ?, revision = revision + 1, updated_at = NOW()
             WHERE id = ? AND active_slot = 1'
        );
        $stmt->execute([$ratio, $id]);
        return $this->active();
    }

    private function selectSql(): string
    {
        return "SELECT s.*, m.title AS meeting_title, d.original_name AS document_name
                FROM meeting_projection_sessions s
                JOIN union_meetings m ON m.id = s.meeting_id
                LEFT JOIN documents d ON d.id = s.current_document_id";
    }
}
