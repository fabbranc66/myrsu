<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class UserController
{
    private const OPTIONAL_FIELDS = ['first_name', 'last_name', 'phone', 'mobile', 'city', 'country', 'union_code'];

    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireRegistryViewer($request);

        return Response::json(['data' => $this->app->users->all()]);
    }

    public function show(Request $request, array $params): Response
    {
        $this->requireRegistryViewer($request);

        $user = $this->app->users->findById((int)$params['id']);

        if ($user === null) {
            throw new HttpException(404, 'Utente non trovato.');
        }

        return Response::json([
            'data' => [
                'user' => $user,
                'roles' => $this->app->roles->rolesForUser((int)$user['id']),
                'permissions' => $this->app->roles->permissionsForUser((int)$user['id']),
            ],
        ]);
    }

    private function requireRegistryViewer(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!array_intersect($roles, ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        return $user;
    }

    public function store(Request $request): Response
    {
        $actor = $this->app->auth->requirePermission($request, 'users.create');
        $data = $request->all();

        Validator::required($data, ['name', 'email', 'password', 'role']);
        Validator::email((string)$data['email']);

        if (!$this->app->roles->roleExists((string)$data['role'])) {
            throw new HttpException(422, 'Ruolo non valido.');
        }

        $payload = [
            'name' => (string)$data['name'],
            'email' => (string)$data['email'],
            'password' => (string)$data['password'],
        ];

        foreach (self::OPTIONAL_FIELDS as $field) {
            $payload[$field] = trim((string)($data[$field] ?? ''));
        }
        if (!in_array((string)$data['role'], ['delegato', 'rls'], true)) {
            $payload['union_code'] = '';
        }

        $userId = $this->app->users->create($payload);
        $this->app->roles->assignRole($userId, (string)$data['role']);
        $this->app->activityLogs->write((int)$actor['id'], 'users.create', [
            'section' => 'registry',
            'created_user_id' => $userId,
        ]);

        return Response::json(['data' => $this->app->users->findById($userId)], 201);
    }

    public function update(Request $request, array $params): Response
    {
        $actor = $this->app->auth->requirePermission($request, 'users.update');
        $userId = (int)$params['id'];
        $data = $request->all();

        if (isset($data['email'])) {
            Validator::email((string)$data['email']);
        }

        if (isset($data['status']) && !in_array($data['status'], ['active', 'suspended'], true)) {
            throw new HttpException(422, 'Stato utente non valido.');
        }

        $before = $this->app->users->findById($userId);

        if ($before === null) {
            throw new HttpException(404, 'Utente non trovato.');
        }

        $roles = $this->app->roles->rolesForUser($userId);
        if (array_key_exists('union_code', $data) && !array_intersect($roles, ['delegato', 'rls'])) {
            $data['union_code'] = '';
        }
        $updated = $this->app->users->update($userId, $data);
        $changes = [];

        foreach (array_merge(['name', 'email', 'status'], self::OPTIONAL_FIELDS) as $field) {
            if (array_key_exists($field, $data) && (string)$before[$field] !== (string)$updated[$field]) {
                $changes[$field] = [
                    'from' => $before[$field],
                    'to' => $updated[$field],
                ];
            }
        }

        if (array_key_exists('password', $data) && trim((string)$data['password']) !== '') {
            $changes['password'] = 'changed';
        }

        $this->app->activityLogs->write((int)$actor['id'], 'users.update', [
            'section' => 'registry',
            'updated_user_id' => $userId,
            'changes' => $changes,
        ]);

        return Response::json(['data' => $updated]);
    }

    public function destroy(Request $request, array $params): Response
    {
        $actor = $this->app->auth->requirePermission($request, 'users.delete');
        $userId = (int)$params['id'];

        if ((int)$actor['id'] === $userId) {
            throw new HttpException(422, 'Non puoi eliminare il tuo utente.');
        }

        if ($this->app->users->findById($userId) === null) {
            throw new HttpException(404, 'Utente non trovato.');
        }

        $this->app->users->delete($userId);
        $this->app->activityLogs->write((int)$actor['id'], 'users.delete', [
            'section' => 'registry',
            'deleted_user_id' => $userId,
        ]);

        return Response::json(['data' => ['deleted' => true]]);
    }

    public function unionLogo(Request $request, array $params): Response
    {
        $actor = $this->app->auth->requirePermission($request, 'users.update');
        $userId = (int)$params['id'];
        $user = $this->app->users->findById($userId);
        if ($user === null) {
            throw new HttpException(404, 'Utente non trovato.');
        }
        $roles = $this->app->roles->rolesForUser($userId);
        if (!array_intersect($roles, ['delegato', 'rls'])) {
            throw new HttpException(422, 'Logo sigla consentito solo per delegati/RLS.');
        }
        $updated = $this->app->users->updateUnionLogo($userId, $this->app->unionLogoStorage->store($_FILES['logo'] ?? []));
        $this->app->activityLogs->write((int)$actor['id'], 'users.union_logo_update', [
            'section' => 'registry',
            'updated_user_id' => $userId,
        ]);

        return Response::json(['data' => $updated]);
    }
}
