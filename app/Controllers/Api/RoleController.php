<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class RoleController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function roles(Request $request): Response
    {
        $this->app->auth->requirePermission($request, 'roles.manage');

        return Response::json(['data' => $this->app->roles->allRoles()]);
    }

    public function permissions(Request $request): Response
    {
        $this->app->auth->requirePermission($request, 'roles.manage');

        return Response::json(['data' => $this->app->roles->allPermissions()]);
    }

    public function replaceUserRoles(Request $request, array $params): Response
    {
        $actor = $this->app->auth->requirePermission($request, 'roles.manage');
        $data = $request->all();

        Validator::required($data, ['roles']);

        if (!is_array($data['roles'])) {
            throw new HttpException(422, 'Il campo roles deve essere un array.');
        }

        foreach ($data['roles'] as $roleName) {
            if (!$this->app->roles->roleExists((string)$roleName)) {
                throw new HttpException(422, 'Ruolo non valido.');
            }
        }

        $userId = (int)$params['id'];
        $this->app->roles->replaceUserRoles($userId, $data['roles']);
        $this->app->activityLogs->write((int)$actor['id'], 'roles.user_replaced', [
            'target_user_id' => $userId,
            'roles' => $data['roles'],
        ]);

        return Response::json([
            'data' => [
                'user_id' => $userId,
                'roles' => $this->app->roles->rolesForUser($userId),
            ],
        ]);
    }
}
