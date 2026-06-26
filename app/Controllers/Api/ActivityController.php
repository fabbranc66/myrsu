<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;

final class ActivityController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function userIndex(Request $request, array $params): Response
    {
        $this->app->auth->requirePermission($request, 'activity.view');

        return Response::json([
            'data' => $this->app->activityLogs->allForUser((int)$params['id']),
        ]);
    }

    public function destroy(Request $request, array $params): Response
    {
        $this->app->auth->requirePermission($request, 'activity.view');
        $this->app->activityLogs->deleteById((int)$params['id']);

        return Response::json(['data' => ['deleted' => true]]);
    }
}
