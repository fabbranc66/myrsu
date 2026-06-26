<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class PracticeController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireLinkRole($request);

        return Response::json(['data' => $this->app->practices->allOpen()]);
    }

    public function link(Request $request): Response
    {
        $user = $this->requireLinkRole($request);
        $data = $request->all();
        Validator::required($data, ['practice_id', 'entity_type', 'entity_id']);

        $this->app->practiceLinks->link(
            (int)$data['practice_id'],
            (string)$data['entity_type'],
            (int)$data['entity_id'],
            (int)$user['id']
        );
        $this->app->activityLogs->write((int)$user['id'], 'practices.link', [
            'section' => 'practices',
            'practice_id' => (int)$data['practice_id'],
            'entity_type' => (string)$data['entity_type'],
            'entity_id' => (int)$data['entity_id'],
        ]);

        return Response::json(['data' => ['linked' => true]]);
    }

    private function requireLinkRole(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!array_intersect($roles, ['admin', 'delegato'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        return $user;
    }
}
