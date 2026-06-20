<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, first_name, last_name, phone, mobile, city, country, status, created_at, updated_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);

        return $stmt->fetch() ?: null;
    }

    public function all(): array
    {
        return $this->pdo
            ->query(
                "SELECT u.id, u.name, u.email, u.status, u.created_at, u.updated_at,
                        u.first_name, u.last_name, u.phone, u.mobile, u.city, u.country,
                        GROUP_CONCAT(r.name ORDER BY r.name) AS roles
                 FROM users u
                 LEFT JOIN role_user ru ON ru.user_id = u.id
                 LEFT JOIN roles r ON r.id = ru.role_id
                 GROUP BY u.id
                 ORDER BY u.name"
            )
            ->fetchAll();
    }

    public function create(array $data, string $status = 'active'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (
                name, email, password_hash, first_name, last_name, phone, mobile, city, country, status, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['name'],
            $data['email'],
            password_hash((string)$data['password'], PASSWORD_DEFAULT),
            $data['first_name'] ?: null,
            $data['last_name'] ?: null,
            $data['phone'] ?: null,
            $data['mobile'] ?: null,
            $data['city'] ?: null,
            $data['country'] ?: null,
            $status,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): ?array
    {
        $fields = [];
        $values = [];

        foreach (['name', 'email', 'first_name', 'last_name', 'phone', 'mobile', 'city', 'country', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field] === '' ? null : $data[$field];
            }
        }

        if (array_key_exists('password', $data) && trim((string)$data['password']) !== '') {
            $fields[] = 'password_hash = ?';
            $values[] = password_hash((string)$data['password'], PASSWORD_DEFAULT);
        }

        if ($fields === []) {
            return $this->findById($id);
        }

        $fields[] = 'updated_at = NOW()';
        $values[] = $id;

        $stmt = $this->pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($values);

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }
}
