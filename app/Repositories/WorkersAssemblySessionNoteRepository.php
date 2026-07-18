<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WorkersAssemblySessionNoteRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forAssembly(int $assemblyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, u.name AS author_name
             FROM workers_assembly_session_notes n
             JOIN users u ON u.id = n.created_by
             JOIN workers_assembly_sessions s ON s.id = n.session_id
             WHERE s.assembly_id = ?
             ORDER BY n.created_at DESC, n.id DESC'
        );
        $stmt->execute([$assemblyId]);

        return $stmt->fetchAll();
    }

    public function create(int $sessionId, string $noteType, string $body, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO workers_assembly_session_notes (session_id, note_type, body, created_by, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$sessionId, $noteType, $body, $userId]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    private function findById(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, u.name AS author_name
             FROM workers_assembly_session_notes n
             JOIN users u ON u.id = n.created_by
             WHERE n.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch();
    }
}
