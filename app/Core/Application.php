<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\ActivityLogRepository;
use App\Repositories\GdprConsentRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use Throwable;

final class Application
{
    public readonly Router $router;
    public readonly Database $database;
    public readonly Auth $auth;
    public readonly UserRepository $users;
    public readonly TokenRepository $tokens;
    public readonly RolePermissionRepository $roles;
    public readonly GdprConsentRepository $gdprConsents;
    public readonly ActivityLogRepository $activityLogs;
    public readonly AuthService $authService;

    public function __construct(private readonly string $basePath)
    {
        $config = require $this->basePath . '/config/database.php';

        $this->router = new Router();
        $this->database = new Database($config);

        $pdo = $this->database->pdo();

        $this->users = new UserRepository($pdo);
        $this->tokens = new TokenRepository($pdo);
        $this->roles = new RolePermissionRepository($pdo);
        $this->gdprConsents = new GdprConsentRepository($pdo);
        $this->activityLogs = new ActivityLogRepository($pdo);
        $this->auth = new Auth($this->users, $this->tokens, $this->roles);
        $this->authService = new AuthService($this->users, $this->tokens, $this->activityLogs);
    }

    public function run(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        try {
            $app = $this;
            require $this->basePath . '/routes/api.php';

            $request = Request::capture();
            $response = $this->router->dispatch($request);
            $response->send();
        } catch (HttpException $exception) {
            Response::json([
                'error' => [
                    'message' => $exception->getMessage(),
                ],
            ], $exception->status())->send();
        } catch (Throwable $exception) {
            $debug = (bool)getenv('APP_DEBUG');
            Response::json([
                'error' => [
                    'message' => $debug ? $exception->getMessage() : 'Errore interno.',
                ],
            ], 500)->send();
        }
    }
}
