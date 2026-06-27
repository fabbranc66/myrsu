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

        $comunicati = array_map(fn (array $item): array => $item + ['queue_type' => 'comunicato'], $this->app->pendingComunicatoQueue->pending());
        $office = array_map(fn (array $item): array => $item + ['queue_type' => 'office'], $this->app->pendingOfficeQueue->pending());
        $items = array_merge($comunicati, $office);

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
        $this->requireLocalRuntime();

        $comunicati = $this->app->pendingComunicatoQueue->process();
        $office = $this->app->pendingOfficeQueue->process();
        return Response::json(['data' => [
            'processed' => $comunicati['processed'] + $office['processed'],
            'errors' => $comunicati['errors'] + $office['errors'],
            'items' => array_merge($comunicati['items'], $office['items']),
        ]]);
    }

    private function requireAdmin(Request $request): void
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);

        if (!in_array('admin', $roles, true)) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }
    }

    private function requireLocalRuntime(): void
    {
        $host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            throw new HttpException(403, 'Elaborazione disponibile solo in locale.');
        }
    }
}
