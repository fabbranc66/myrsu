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

    public function search(Request $request): Response
    {
        $this->app->auth->requireUser($request);
        $query = trim((string)$request->query('q', ''));
        $scope = trim((string)$request->query('scope', 'ccnl')) ?: 'ccnl';
        $limit = (int)$request->query('limit', 20);
        $offset = (int)$request->query('offset', 0);

        if ($query === '') {
            throw new HttpException(422, 'Testo ricerca obbligatorio.');
        }

        if (!in_array($scope, ['all', 'ccnl', 'representation', 'safety'], true)) {
            throw new HttpException(422, 'Ambito non disponibile su database.');
        }
        if ($scope === 'all' && $this->app->normativa->meaningfulTermCount($query) < 4) {
            throw new HttpException(422, 'Con una ricerca breve scegli un ambito specifico.');
        }

        return Response::json(['data' => [
            'items' => $this->app->normativa->search($query, $scope, $limit, $offset),
            'limit' => max(1, min(50, $limit)),
            'offset' => max(0, $offset),
        ]]);
    }

    public function unit(Request $request, array $params): Response
    {
        $this->app->auth->requireUser($request);
        $unit = $this->app->normativa->unit((int)$params['id']);
        if ($unit === []) {
            throw new HttpException(404, 'Riferimento normativa non trovato.');
        }

        return Response::json(['data' => $unit]);
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
