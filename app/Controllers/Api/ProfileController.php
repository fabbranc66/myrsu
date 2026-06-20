<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class ProfileController
{
    private const PROFILE_FIELDS = ['name', 'email', 'first_name', 'last_name', 'phone', 'mobile', 'city', 'country'];

    public function __construct(private readonly Application $app)
    {
    }

    public function show(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);

        return Response::json(['data' => $user]);
    }

    public function update(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);
        $data = $request->all();

        Validator::required($data, ['name', 'email']);
        Validator::email((string)$data['email']);

        $payload = [];

        foreach (self::PROFILE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $payload[$field] = trim((string)$data[$field]);
            }
        }

        $updated = $this->app->users->update((int)$user['id'], $payload);

        $this->app->activityLogs->write((int)$user['id'], 'profile.update', [
            'section' => 'registry',
            'updated_user_id' => (int)$user['id'],
        ]);

        return Response::json(['data' => $updated]);
    }

    public function password(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);
        $data = $request->all();

        Validator::required($data, ['password']);

        if (strlen((string)$data['password']) < 8) {
            throw new HttpException(422, 'Password min 8 chars.');
        }

        $this->app->users->update((int)$user['id'], [
            'password' => (string)$data['password'],
        ]);

        $this->app->activityLogs->write((int)$user['id'], 'profile.password', [
            'section' => 'registry',
            'updated_user_id' => (int)$user['id'],
            'changes' => ['password' => 'changed'],
        ]);

        return Response::json(['data' => ['password_changed' => true]]);
    }
}
