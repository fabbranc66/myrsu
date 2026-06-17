<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class AuthController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function login(Request $request): Response
    {
        $data = $request->all();
        Validator::required($data, ['email', 'password']);
        Validator::email((string)$data['email']);

        return Response::json([
            'data' => $this->app->authService->login(
                (string)$data['email'],
                (string)$data['password'],
                isset($data['device_name']) ? (string)$data['device_name'] : null
            ),
        ]);
    }

    public function logout(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);
        $token = $request->bearerToken();

        if ($token !== null) {
            $this->app->authService->logout($token, (int)$user['id']);
        }

        return Response::json(['data' => ['logged_out' => true]]);
    }

    public function me(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);

        return Response::json([
            'data' => [
                'user' => $user,
                'roles' => $this->app->roles->rolesForUser((int)$user['id']),
                'permissions' => $this->app->roles->permissionsForUser((int)$user['id']),
            ],
        ]);
    }
}
