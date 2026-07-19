<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class VotingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo->query('SELECT * FROM votings ORDER BY created_at DESC, id DESC')->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM votings WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function forAssembly(int $assemblyId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM votings WHERE assembly_id = ? ORDER BY session_id ASC, id ASC');
        $stmt->execute([$assemblyId]);
        return $stmt->fetchAll();
    }

    public function findByAssemblySession(int $assemblyId, int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM votings WHERE assembly_id = ? AND session_id = ? LIMIT 1');
        $stmt->execute([$assemblyId, $sessionId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO votings (title, description, status, anonymous, vote_mode, starts_at, ends_at, assembly_id, session_id, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([$data['title'], $data['description'], $data['status'], $data['anonymous'], $data['vote_mode'], $data['starts_at'], $data['ends_at'], $data['assembly_id'], $data['session_id'], $data['created_by']]);
        return $this->findById((int)$this->pdo->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE votings SET title = ?, description = ?, status = ?, anonymous = ?, vote_mode = ?, starts_at = ?, ends_at = ?, assembly_id = ?, session_id = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$data['title'], $data['description'], $data['status'], $data['anonymous'], $data['vote_mode'], $data['starts_at'], $data['ends_at'], $data['assembly_id'], $data['session_id'], $id]);
        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM votings WHERE id = ?');
        $stmt->execute([$id]);
    }
}
