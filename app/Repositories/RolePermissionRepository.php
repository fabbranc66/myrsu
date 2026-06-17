<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RolePermissionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function rolesForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.name
             FROM roles r
             INNER JOIN role_user ru ON ru.role_id = r.id
             WHERE ru.user_id = ?
             ORDER BY r.name'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function allRoles(): array
    {
        return $this->pdo->query('SELECT id, name, label FROM roles ORDER BY id')->fetchAll();
    }

    public function allPermissions(): array
    {
        return $this->pdo->query('SELECT id, name, label FROM permissions ORDER BY name')->fetchAll();
    }

    public function roleExists(string $roleName): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM roles WHERE name = ?');
        $stmt->execute([$roleName]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function permissionsForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT p.name
             FROM permissions p
             INNER JOIN permission_role pr ON pr.permission_id = p.id
             INNER JOIN role_user ru ON ru.role_id = pr.role_id
             WHERE ru.user_id = ?
             ORDER BY p.name'
        );
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function userHasPermission(int $userId, string $permission): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM permissions p
             INNER JOIN permission_role pr ON pr.permission_id = p.id
             INNER JOIN role_user ru ON ru.role_id = pr.role_id
             WHERE ru.user_id = ? AND p.name = ?'
        );
        $stmt->execute([$userId, $permission]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function assignRole(int $userId, string $roleName): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO role_user (user_id, role_id)
             SELECT ?, id FROM roles WHERE name = ?'
        );
        $stmt->execute([$userId, $roleName]);
    }

    public function replaceUserRoles(int $userId, array $roleNames): void
    {
        $this->pdo->beginTransaction();

        $delete = $this->pdo->prepare('DELETE FROM role_user WHERE user_id = ?');
        $delete->execute([$userId]);

        foreach ($roleNames as $roleName) {
            $this->assignRole($userId, (string)$roleName);
        }

        $this->pdo->commit();
    }
}
