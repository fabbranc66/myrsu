<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\RolePermissionRepository;
use App\Repositories\RoomParticipantRepository;

final class RoomPermissionService
{
    public function __construct(
        private readonly RoomParticipantRepository $participants,
        private readonly RolePermissionRepository $roles
    ) {
    }

    public function isSystemAdmin(int $userId): bool
    {
        return in_array('admin', $this->roles->rolesForUser($userId), true);
    }

    public function requireView(int $roomId, int $userId): array
    {
        if ($this->isSystemAdmin($userId)) {
            return ['permission_level' => 'manage', 'system_admin' => true];
        }
        $membership = $this->participants->membership($roomId, $userId);
        if ($membership === null) {
            throw new HttpException(403, 'Accesso al Tavolo non autorizzato.');
        }
        if ((int)($membership['is_responsible'] ?? 0) === 1) {
            $membership['permission_level'] = 'manage';
        }
        return $membership;
    }

    public function requireInteract(int $roomId, int $userId): array
    {
        $membership = $this->requireView($roomId, $userId);
        if (!in_array((string)$membership['permission_level'], ['interact', 'manage'], true)) {
            throw new HttpException(403, 'Interazione non autorizzata.');
        }
        return $membership;
    }

    public function requireManage(int $roomId, int $userId): array
    {
        $membership = $this->requireView($roomId, $userId);
        if ((string)$membership['permission_level'] !== 'manage') {
            throw new HttpException(403, 'Gestione del Tavolo non autorizzata.');
        }
        return $membership;
    }
}
