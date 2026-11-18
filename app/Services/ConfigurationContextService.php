<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\RolePermissionRepository;
use App\Repositories\SavedConfigurationRepository;

final class ConfigurationContextService
{
    public readonly ?array $current;
    public readonly bool $operational;

    public function __construct(public readonly SavedConfigurationRepository $repository, ?Request $request, Auth $auth, RolePermissionRepository $roles)
    {
        if (!$repository->installed()) {
            $this->current = null;
            $this->operational = true;
            return;
        }
        $operational = $repository->operational();
        $selected = $request?->bearerToken() ? $repository->selected($request->bearerToken()) : null;
        if ($selected !== null) {
            $user = $auth->requireUser($request);
            if (!in_array('admin', $roles->rolesForUser((int)$user['id']), true)) {
                $repository->select($request->bearerToken(), null);
                $selected = null;
            }
        }
        $this->current = $selected ?? $operational;
        $this->operational = (int)$this->current['id'] === (int)$operational['id'];
        $expected = $request?->header('x-configuration-id');
        if ($expected !== null && $expected !== (string)$this->current['id'] && !in_array($request->path(), ['/api/v1/admin/configurations/selection', '/api/v1/me', '/api/v1/auth/logout', '/api/v1/auth/login'], true)) {
            throw new HttpException(409, 'La configurazione è cambiata in un’altra scheda. Ricarica la pagina prima di continuare.');
        }
    }

    public function summary(): ?array
    {
        return $this->current === null ? null : ['id' => (int)$this->current['id'], 'name' => $this->current['name'], 'is_operational' => $this->operational];
    }

    public function assertLocalAction(Request $request): void
    {
        if ($this->operational) return;
        if ($request->method() !== 'GET' && preg_match('~^/api/v1/(users|roles|permissions|profile|gdpr|contacts|institutional-contacts|normativa)(/|$)~', $request->path())) {
            throw new HttpException(409, 'Le anagrafiche e le impostazioni comuni si modificano solo dall’Operativo.');
        }
        if (in_array($request->path(), ['/api/v1/emails/send', '/api/v1/emails/sync', '/api/v1/room-access/register'], true) || str_starts_with($request->path(), '/api/v1/hosting/') || (preg_match('~/rooms/[0-9]+/(access-link|external-participants)$~', $request->path()) && $request->method() !== 'GET')) {
            throw new HttpException(409, 'Invii e sincronizzazioni esterne non disponibili nelle configurazioni salvate.');
        }
    }
}
