<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RolePermissionRepository;

final class DocumentVisibilityService
{
    public const VALUES = ['creator', 'rsu', 'members', 'public'];
    private array $userRoles = [];
    private ?array $roleDefinitions = null;

    public function __construct(private readonly RolePermissionRepository $roles)
    {
    }

    public function roles(?array $user): array
    {
        $id = (int)($user['id'] ?? 0);
        return $id > 0 ? ($this->userRoles[$id] ??= $this->roles->rolesForUser($id)) : [];
    }

    public function privileged(array $document, ?array $user): bool
    {
        $id = (int)($user['id'] ?? 0);
        return $id > 0 && (in_array('admin', $this->roles($user), true) || (int)($document['uploaded_by'] ?? 0) === $id);
    }

    public function allows(string $visibility, ?array $user): bool
    {
        return match ($visibility) {
            'public' => true,
            'members' => (int)($user['id'] ?? 0) > 0,
            'rsu' => in_array('delegato', $this->roles($user), true),
            default => false,
        };
    }

    public function roleDefinitions(): array
    {
        return $this->roleDefinitions ??= $this->roles->allRoles();
    }

    public function audience(string $visibility): array
    {
        $names = array_values(array_diff(array_column($this->roleDefinitions(), 'name'), ['admin']));
        return match ($visibility) {
            'public' => [...$names, '@authenticated', '@guest'],
            'members' => [...$names, '@authenticated'],
            'rsu' => ['delegato'],
            default => [],
        };
    }

    public function roleAudience(array $permissions, bool $manage = false): array
    {
        $definitions = array_column($this->roleDefinitions(), 'name', 'id');
        $names = [];
        foreach ($permissions as $permission) {
            $name = $definitions[(int)$permission['role_id']] ?? null;
            if ($name !== null && $name !== 'admin' && (!$manage || $permission['access_level'] === 'manage')) $names[] = $name;
        }
        return array_values(array_unique($names));
    }
}
