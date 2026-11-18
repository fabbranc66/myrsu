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
        $singleton = $this->singleton();
        if ($singleton !== null) {
            $stmt = $this->pdo->prepare(
                "UPDATE meeting_projection_sessions
                 SET meeting_id = ?, status = 'active', active_slot = 1, current_document_id = NULL,
                     scroll_ratio = 0, pointer_page = 1, pointer_x = 0.5, pointer_y = 0.1,
                     highlight_x = NULL, highlight_y = NULL, highlight_page = NULL, highlight_end_x = NULL, highlight_end_y = NULL,
                     revision = revision + 1, created_by = ?, closed_at = NULL, updated_at = NOW()
                 WHERE id = ?"
            );
            $stmt->execute([$meetingId, $userId, $singleton['id']]);
            return $this->active();
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO meeting_projection_sessions
             (singleton_key, meeting_id, public_token, control_token, status, active_slot, created_by, created_at, updated_at)
             VALUES (1, ?, ?, ?, 'active', 1, ?, NOW(), NOW())"
        );
        $stmt->execute([$meetingId, $publicToken, $controlToken, $userId]);
        return $this->active();
    }

    public function close(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE meeting_projection_sessions
             SET status = 'inactive', active_slot = NULL, current_document_id = NULL,
                 highlight_x = NULL, highlight_y = NULL, highlight_page = NULL, highlight_end_x = NULL, highlight_end_y = NULL,
                 revision = revision + 1, closed_at = NOW(), updated_at = NOW()
             WHERE id = ? AND active_slot = 1"
        );
        $stmt->execute([$id]);
    }

    public function publish(int $id, int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE meeting_projection_sessions
             SET current_document_id = ?, scroll_ratio = 0, pointer_page = 1, pointer_x = 0.5, pointer_y = 0.1,
                 highlight_x = NULL, highlight_y = NULL, highlight_page = NULL, highlight_end_x = NULL, highlight_end_y = NULL,
                 revision = revision + 1, updated_at = NOW()
             WHERE id = ? AND active_slot = 1'
        );
        $stmt->execute([$documentId, $id]);
        return $this->active();
    }

    public function position(int $id, float $ratio, ?array $pointer = null, ?int $documentId = null): array
    {
        $assignments = 'scroll_ratio = ?';
        $values = [$ratio];
        if ($pointer !== null) {
            $assignments .= ', pointer_page = ?, pointer_x = ?, pointer_y = ?, highlight_x = ?, highlight_y = ?, highlight_page = ?, highlight_end_x = ?, highlight_end_y = ?';
            array_push($values, $pointer['pointer_page'], $pointer['pointer_x'], $pointer['pointer_y'],
                $pointer['highlight_x'] ?? null, $pointer['highlight_y'] ?? null,
                $pointer['highlight_page'] ?? null, $pointer['highlight_end_x'] ?? null, $pointer['highlight_end_y'] ?? null);
        }
        $condition = 'id = ? AND active_slot = 1';
        $values[] = $id;
        if ($documentId !== null) {
            $condition .= ' AND current_document_id = ?';
            $values[] = $documentId;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE meeting_projection_sessions SET ' . $assignments
            . ', revision = revision + 1, updated_at = NOW() WHERE ' . $condition
        );
        $stmt->execute($values);
        return $this->active();
    }

    private function selectSql(): string
    {
        return "SELECT s.*, m.title AS meeting_title, d.original_name AS document_name
                FROM meeting_projection_sessions s
                LEFT JOIN union_meetings m ON m.id = s.meeting_id
                LEFT JOIN documents d ON d.id = s.current_document_id";
    }

    private function singleton(): ?array
    {
        $row = $this->pdo->query($this->selectSql() . ' WHERE s.singleton_key = 1 LIMIT 1')->fetch();
        return $row ?: null;
    }
}
