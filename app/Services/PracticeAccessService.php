<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\PracticeRepository;
use App\Repositories\RolePermissionRepository;

final class PracticeAccessService
{
    private array $userRoles = [];

    public function __construct(private readonly RolePermissionRepository $roles)
    {
    }

    public function allows(array $practice, ?array $user): bool
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) return false;
        $roles = $this->userRoles[$userId] ??= $this->roles->rolesForUser($userId);
        if (in_array('admin', $roles, true)) return true;
        return match ($practice['visibility'] ?? '') {
            'authorized' => (int)($practice['assigned_user_id'] ?? 0) === $userId,
            'operators' => in_array('delegato', $roles, true),
            default => false,
        };
    }

    public function filter(array $practices, array $user): array
    {
        return array_values(array_filter($practices, fn (array $practice): bool => $this->allows($practice, $user)));
    }

    public function assert(array $practice, array $user): void
    {
        if (!$this->allows($practice, $user)) {
            throw new HttpException(403, 'Accesso alla pratica non autorizzato.');
        }
    }

    public function assertLinked(array $item, array $user, PracticeRepository $practices): void
    {
        if (empty($item['practice_id'])) return;
        $practice = $practices->findById((int)$item['practice_id']);
        if ($practice === null) throw new HttpException(404, 'Pratica non trovata.');
        $this->assert($practice, $user);
    }

    public function filterLinked(array $items, array $user, PracticeRepository $practices): array
    {
        $visibleIds = array_fill_keys(array_column($this->filter($practices->all(), $user), 'id'), true);
        return array_values(array_filter($items, static fn (array $item): bool => empty($item['practice_id']) || isset($visibleIds[(int)$item['practice_id']])));
    }
}
