<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\RsuElectionAnalysisService;
use App\Services\RsuElectionDraftService;
use App\Repositories\RsuElectionDraftRepository;

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

    public function draft(Request $request): Response
    {
        $user = $this->app->auth->requireAdmin($request);
        return Response::json(['data' => $this->draftService()->load((int)$user['id'])]);
    }

    public function saveDraft(Request $request): Response
    {
        $user = $this->app->auth->requireAdmin($request);
        return Response::json(['data' => $this->draftService()->save((int)$user['id'], $request->all())]);
    }

    private function draftService(): RsuElectionDraftService
    {
        return new RsuElectionDraftService(new RsuElectionDraftRepository($this->app->database->pdo()));
    }
}
