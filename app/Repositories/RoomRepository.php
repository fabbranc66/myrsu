<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RoomRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function categories(): array
    {
        return $this->pdo->query(
            'SELECT id, code, name FROM room_categories WHERE active = 1 ORDER BY sort_order, name'
        )->fetchAll();
    }

    public function allForUser(int $userId, bool $systemAdmin): array
    {
        $sql = "SELECT r.*, c.name AS category_name, u.name AS responsible_name,
                       ru.permission_level, ru.room_role,
                       ((SELECT COUNT(*) FROM room_users p WHERE p.room_id = r.id AND p.active = 1) +
                        (SELECT COUNT(*) FROM room_external_participants ep WHERE ep.room_id = r.id AND ep.active = 1)) AS participants_count,
                       (SELECT MAX(created_at) FROM room_events e WHERE e.room_id = r.id) AS last_activity_at
                FROM rooms r
                JOIN room_categories c ON c.id = r.category_id
                JOIN users u ON u.id = r.responsible_id
                LEFT JOIN room_users ru ON ru.room_id = r.id AND ru.user_id = ? AND ru.active = 1
                WHERE r.deleted_at IS NULL";
        if (!$systemAdmin) {
            $sql .= ' AND ru.id IS NOT NULL';
        }
        $sql .= ' ORDER BY COALESCE(last_activity_at, r.updated_at) DESC, r.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, c.name AS category_name, creator.name AS creator_name, responsible.name AS responsible_name
             FROM rooms r
             JOIN room_categories c ON c.id = r.category_id
             JOIN users creator ON creator.id = r.created_by
             JOIN users responsible ON responsible.id = r.responsible_id
             WHERE r.id = ? AND r.deleted_at IS NULL'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rooms
             (code, title, description, category_id, status, created_by, responsible_id, opened_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['code'], $data['title'], $data['description'], $data['category_id'], $data['status'],
            $data['created_by'], $data['responsible_id'], $data['opened_at'],
        ]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function update(int $id, array $data): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rooms SET title = ?, description = ?, category_id = ?, responsible_id = ?, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$data['title'], $data['description'], $data['category_id'], $data['responsible_id'], $id]);

        return $this->findById($id);
    }

    public function changeStatus(int $id, string $status): array
    {
        $stmt = $this->pdo->prepare(
            "UPDATE rooms SET status = ?, opened_at = CASE WHEN ? IN ('open','in_progress') AND opened_at IS NULL THEN NOW() ELSE opened_at END,
             closed_at = CASE WHEN ? IN ('closed','archived','cancelled') THEN NOW() ELSE NULL END, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL"
        );
        $stmt->execute([$status, $status, $status, $id]);

        return $this->findById($id);
    }

    public function nextCode(string $categoryCode): string
    {
        $year = date('Y');
        $prefix = 'ROOM-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $categoryCode), 0, 8)) . '-' . $year . '-';
        $stmt = $this->pdo->prepare('SELECT code FROM rooms WHERE code LIKE ? ORDER BY code DESC LIMIT 1');
        $stmt->execute([$prefix . '%']);
        $last = (string)($stmt->fetchColumn() ?: '');
        $number = $last === '' ? 1 : ((int)substr($last, -3) + 1);

        return $prefix . str_pad((string)$number, 3, '0', STR_PAD_LEFT);
    }

    public function categoryById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, code, name FROM room_categories WHERE id = ? AND active = 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
