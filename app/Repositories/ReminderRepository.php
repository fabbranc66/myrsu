<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReminderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(array $filters = []): array
    {
        $where = [];
        $params = [];
        foreach (['status', 'entity_type', 'assigned_to'] as $field) {
            if (($filters[$field] ?? '') === '') {
                continue;
            }
            $where[] = "r.$field = ?";
            $params[] = $filters[$field];
        }
        $sql = "SELECT r.*, cu.name AS created_by_name, au.name AS assigned_to_name
                FROM reminders r
                JOIN users cu ON cu.id = r.created_by
                LEFT JOIN users au ON au.id = r.assigned_to"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY r.status = "pending" DESC, r.due_at ASC, r.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reminders
             (entity_type, entity_id, title, notes, due_at, status, created_by, assigned_to, created_at)
             VALUES (?, ?, ?, ?, ?, "pending", ?, ?, NOW())'
        );
        $stmt->execute([
            $data['entity_type'],
            $data['entity_id'],
            $data['title'],
            $data['notes'],
            $data['due_at'],
            $data['created_by'],
            $data['assigned_to'],
        ]);

        return $this->findById((int)$this->pdo->lastInsertId()) ?? [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reminders WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE reminders SET title = ?, notes = ?, due_at = ?, status = ?, assigned_to = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['notes'],
            $data['due_at'],
            $data['status'],
            $data['assigned_to'],
            $id,
        ]);

        return $this->findById($id);
    }

    public function done(int $id): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE reminders SET status = "done", completed_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM reminders WHERE id = ?');
        $stmt->execute([$id]);
    }
}
