<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\RsuElectionAnalysisService;

final class RsuElectionController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function analyze(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);
        if (!in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        return Response::json(['data' => (new RsuElectionAnalysisService())->analyze($request->all())]);
    }
}
