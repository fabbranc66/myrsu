<?php

declare(strict_types=1);

use App\Controllers\Api\ActivityController;
use App\Controllers\Api\AuthController;
use App\Controllers\Api\DocumentController;
use App\Controllers\Api\DocumentVerificationController;
use App\Controllers\Api\GdprConsentController;
use App\Controllers\Api\HostingDocumentController;
use App\Controllers\Api\ProfileController;
use App\Controllers\Api\ProtocolController;
use App\Controllers\Api\RoleController;
use App\Controllers\Api\UserController;
use App\Core\Response;

$auth = new AuthController($app);
$activity = new ActivityController($app);
$documents = new DocumentController($app);
$documentVerification = new DocumentVerificationController($app);
$hostingDocuments = new HostingDocumentController($app);
$profile = new ProfileController($app);
$protocol = new ProtocolController($app);
$users = new UserController($app);
$roles = new RoleController($app);
$gdpr = new GdprConsentController($app);

$app->router->get('/api/v1/health', static fn () => Response::json([
    'data' => [
        'status' => 'ok',
        'app' => 'myrsu-api',
    ],
]));

$app->router->post('/api/v1/auth/login', [$auth, 'login']);
$app->router->post('/api/v1/auth/logout', [$auth, 'logout']);
$app->router->get('/api/v1/me', [$auth, 'me']);

$app->router->get('/api/v1/profile', [$profile, 'show']);
$app->router->patch('/api/v1/profile', [$profile, 'update']);
$app->router->patch('/api/v1/profile/password', [$profile, 'password']);

$app->router->get('/api/v1/users', [$users, 'index']);
$app->router->post('/api/v1/users', [$users, 'store']);
$app->router->get('/api/v1/users/{id}', [$users, 'show']);
$app->router->patch('/api/v1/users/{id}', [$users, 'update']);
$app->router->delete('/api/v1/users/{id}', [$users, 'destroy']);

$app->router->get('/api/v1/roles', [$roles, 'roles']);
$app->router->get('/api/v1/permissions', [$roles, 'permissions']);
$app->router->post('/api/v1/users/{id}/roles', [$roles, 'replaceUserRoles']);

$app->router->get('/api/v1/gdpr/consents', [$gdpr, 'index']);
$app->router->post('/api/v1/gdpr/consents', [$gdpr, 'store']);
$app->router->get('/api/v1/users/{id}/gdpr/consents', [$gdpr, 'userIndex']);
$app->router->get('/api/v1/users/{id}/activity', [$activity, 'userIndex']);

$app->router->get('/api/v1/documents', [$documents, 'index']);
$app->router->post('/api/v1/documents', [$documents, 'store']);
$app->router->get('/api/v1/documents/{id}', [$documents, 'show']);
$app->router->patch('/api/v1/documents/{id}', [$documents, 'update']);
$app->router->get('/api/v1/documents/{id}/preview', [$documents, 'preview']);
$app->router->get('/api/v1/documents/{id}/download', [$documents, 'download']);
$app->router->delete('/api/v1/documents/{id}', [$documents, 'destroy']);
$app->router->get('/api/v1/documents/{id}/verify', [$documentVerification, 'show']);
$app->router->post('/api/v1/hosting/documents', [$hostingDocuments, 'store']);

$app->router->get('/api/v1/protocol', [$protocol, 'index']);
$app->router->post('/api/v1/protocol', [$protocol, 'store']);
$app->router->get('/api/v1/protocol/{id}', [$protocol, 'show']);
$app->router->patch('/api/v1/protocol/{id}', [$protocol, 'update']);
$app->router->delete('/api/v1/protocol/{id}', [$protocol, 'destroy']);
