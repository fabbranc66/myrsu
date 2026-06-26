<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InstitutionalContactRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo
            ->query('SELECT * FROM institutional_contacts ORDER BY name ASC')
            ->fetchAll();
    }

    public function create(array $data, ?int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO institutional_contacts
             (type, name, role, organization, email, phone, notes, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['type'],
            $data['name'],
            $data['role'] ?: null,
            $data['organization'] ?: null,
            $data['email'] ?: null,
            $data['phone'] ?: null,
            $data['notes'] ?: null,
            $userId,
        ]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM institutional_contacts WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE institutional_contacts
             SET type = ?, name = ?, role = ?, organization = ?, email = ?, phone = ?, notes = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['type'],
            $data['name'],
            $data['role'] ?: null,
            $data['organization'] ?: null,
            $data['email'] ?: null,
            $data['phone'] ?: null,
            $data['notes'] ?: null,
            $id,
        ]);

        return $this->findById($id);
    }
}
