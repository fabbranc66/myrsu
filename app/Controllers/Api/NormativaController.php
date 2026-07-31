<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class NormativaController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function show(Request $request): Response
    {
        $this->requireAdmin($request);
        $sourcePath = trim((string)$request->query('source_path', ''));
        if ($sourcePath === '') throw new HttpException(422, 'Percorso normativa obbligatorio.');

        return Response::json(['data' => $this->app->normativaFiles->read($sourcePath)]);
    }

    public function update(Request $request): Response
    {
        $user = $this->requireAdmin($request);
        $data = $request->all();
        Validator::required($data, ['source_path', 'content']);
        $file = $this->app->normativaFiles->write((string)$data['source_path'], (string)$data['content']);
        $this->app->activityLogs->write((int)$user['id'], 'normativa.update', [
            'section' => 'normativa',
            'source_path' => (string)$data['source_path'],
        ]);

        return Response::json(['data' => $file]);
    }

    private function requireAdmin(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)) {
            throw new HttpException(403, 'Solo admin.');
        }
        return $user;
    }
}
