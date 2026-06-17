<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\RolePermissionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;

final class Auth
{
    private ?array $user = null;
    private bool $resolved = false;

    public function __construct(
        private readonly UserRepository $users,
        private readonly TokenRepository $tokens,
        private readonly RolePermissionRepository $roles
    ) {
    }

    public function user(Request $request): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }

        $this->resolved = true;
        $plainToken = $request->bearerToken();

        if ($plainToken === null) {
            return null;
        }

        $token = $this->tokens->findValidByHash(hash('sha256', $plainToken));

        if ($token === null) {
            return null;
        }

        $this->tokens->touch((int)$token['id']);
        $this->user = $this->users->findById((int)$token['user_id']);

        return $this->user;
    }

    public function requireUser(Request $request): array
    {
        $user = $this->user($request);

        if ($user === null) {
            throw new HttpException(401, 'Autenticazione richiesta.');
        }

        return $user;
    }

    public function requirePermission(Request $request, string $permission): array
    {
        $user = $this->requireUser($request);

        if (!$this->roles->userHasPermission((int)$user['id'], $permission)) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        return $user;
    }
}
