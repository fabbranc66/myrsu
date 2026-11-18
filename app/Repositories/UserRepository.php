<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\HttpException;
use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, email, first_name, last_name, phone, mobile, city, country, union_code, union_logo_stored_name, status, created_at, updated_at
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
                        u.first_name, u.last_name, u.phone, u.mobile, u.city, u.country, u.union_code, u.union_logo_stored_name,
                        GROUP_CONCAT(r.name ORDER BY r.name) AS roles
                 FROM users u
                 LEFT JOIN role_user ru ON ru.user_id = u.id
                 LEFT JOIN roles r ON r.id = ru.role_id
                 GROUP BY u.id
                 ORDER BY u.name"
            )
            ->fetchAll();
    }

    public function operators(): array
    {
        return $this->pdo->query(
            "SELECT DISTINCT u.id, u.name
             FROM users u
             INNER JOIN role_user ru ON ru.user_id = u.id
             INNER JOIN roles r ON r.id = ru.role_id
             WHERE u.status = 'active' AND r.name IN ('admin', 'delegato', 'rls')
             ORDER BY u.name"
        )->fetchAll();
    }

    public function unionDelegates(): array
    {
        return $this->pdo->query(
            "SELECT u.id, u.name, u.email, u.first_name, u.last_name, u.phone, u.mobile, u.union_code, u.union_logo_stored_name,
                    GROUP_CONCAT(r.name ORDER BY r.name) AS roles
             FROM users u
             INNER JOIN role_user ru ON ru.user_id = u.id
             INNER JOIN roles r ON r.id = ru.role_id
             WHERE u.status = 'active' AND r.name IN ('delegato', 'rls')
              AND NOT EXISTS (
                  SELECT 1
                  FROM role_user admin_ru
                  INNER JOIN roles admin_role ON admin_role.id = admin_ru.role_id
                  WHERE admin_ru.user_id = u.id AND admin_role.name = 'admin'
              )
             GROUP BY u.id
             ORDER BY u.name"
        )->fetchAll();
    }

    public function create(array $data, string $status = 'active'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (
                name, email, password_hash, first_name, last_name, phone, mobile, city, country, union_code, status, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
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
            $data['union_code'] ?: null,
            $status,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): ?array
    {
        $fields = [];
        $values = [];

        foreach (['name', 'email', 'first_name', 'last_name', 'phone', 'mobile', 'city', 'country', 'union_code', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field] === '' ? null : $data[$field];
            }
        }

        $changesPassword = array_key_exists('password', $data) && trim((string)$data['password']) !== '';
        if ($changesPassword) {
            $fields[] = 'password_hash = ?';
            $values[] = password_hash((string)$data['password'], PASSWORD_DEFAULT);
        }

        if ($fields === []) {
            return $this->findById($id);
        }

        $fields[] = 'updated_at = NOW()';
        $values[] = $id;

        $operation = function () use ($id, $fields, $values, $data, $changesPassword): ?array {
            $revokeSessions = $changesPassword;
            if (array_key_exists('status', $data)) {
                $current = $this->pdo->prepare('SELECT status FROM users WHERE id = ? FOR UPDATE');
                $current->execute([$id]);
                $currentStatus = $current->fetchColumn();
                $revokeSessions = $revokeSessions || $data['status'] === 'suspended'
                    || ($data['status'] === 'active' && $currentStatus === 'suspended');
            }
            $stmt = $this->pdo->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?');
            $stmt->execute($values);
            if ($revokeSessions) {
                (new TokenRepository($this->pdo))->revokeForUser($id);
            }
            return $this->findById($id);
        };

        if (array_key_exists('status', $data) || $changesPassword) {
            return (new AdminAccountProtectionRepository($this->pdo))->run($id, array_key_exists('status', $data) && $data['status'] !== 'active', $operation);
        }
        return $operation();
    }

    public function delete(int $id): void
    {
        (new AdminAccountProtectionRepository($this->pdo))->run($id, true, function () use ($id): void {
            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
            try {
                $stmt->execute([$id]);
            } catch (PDOException $exception) {
                if ((int)($exception->errorInfo[1] ?? 0) === 1451) {
                    throw new HttpException(409, 'Utente collegato a dati o attività, anche negli archivi salvati: non può essere eliminato. Per impedirne l’accesso, imposta lo stato su sospeso.');
                }
                throw $exception;
            }
        });
    }

    public function clearUnionCode(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET union_code = NULL, union_logo_stored_name = NULL, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function updateUnionLogo(int $id, string $storedName): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE users SET union_logo_stored_name = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$storedName, $id]);
        return $this->findById($id);
    }
}
