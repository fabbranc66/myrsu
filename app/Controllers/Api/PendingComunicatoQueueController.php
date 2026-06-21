<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class PendingComunicatoQueueController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireAdmin($request);

        $items = $this->app->pendingComunicatoQueue->pending();

        return Response::json([
            'data' => [
                'count' => count($items),
                'items' => $items,
            ],
        ]);
    }

    public function process(Request $request): Response
    {
        $this->requireAdmin($request);

        return Response::json(['data' => $this->app->pendingComunicatoQueue->process()]);
    }

    private function requireAdmin(Request $request): void
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);

        if (!in_array('admin', $roles, true)) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }
    }
}
