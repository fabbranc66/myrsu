<?php

declare(strict_types=1);

use App\Controllers\Api\ActivityController;
use App\Controllers\Api\AuthController;
use App\Controllers\Api\ComunicatoController;
use App\Controllers\Api\DocumentController;
use App\Controllers\Api\DocumentVerificationController;
use App\Controllers\Api\GdprConsentController;
use App\Controllers\Api\HostingDocumentController;
use App\Controllers\Api\PendingComunicatoQueueController;
use App\Controllers\Api\ProfileController;
use App\Controllers\Api\ProtocolController;
use App\Controllers\Api\ReportController;
use App\Controllers\Api\RoleController;
use App\Controllers\Api\UserController;
use App\Core\Response;

$auth = new AuthController($app);
$activity = new ActivityController($app);
$comunicati = new ComunicatoController($app);
$documents = new DocumentController($app);
$documentVerification = new DocumentVerificationController($app);
$hostingDocuments = new HostingDocumentController($app);
$pendingQueue = new PendingComunicatoQueueController($app);
$profile = new ProfileController($app);
$protocol = new ProtocolController($app);
$reports = new ReportController($app);
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
$app->router->get('/api/v1/public/documents', [$documents, 'publicIndex']);
$app->router->get('/api/v1/documents/private', [$documents, 'privateIndex']);
$app->router->post('/api/v1/documents', [$documents, 'store']);
$app->router->post('/api/v1/comunicati', [$comunicati, 'store']);
$app->router->get('/api/v1/documents/{id}/thumbnail', [$documents, 'thumbnail']);
$app->router->get('/api/v1/documents/{id}/preview', [$documents, 'preview']);
$app->router->get('/api/v1/documents/{id}/download', [$documents, 'download']);
$app->router->get('/api/v1/documents/{id}/private-preview', [$documents, 'privatePreview']);
$app->router->get('/api/v1/documents/{id}/private-download', [$documents, 'privateDownload']);
$app->router->get('/api/v1/documents/{id}/verify', [$documentVerification, 'show']);
$app->router->post('/api/v1/documents/{id}/verify-file', [$documentVerification, 'file']);
$app->router->get('/api/v1/documents/{id}', [$documents, 'show']);
$app->router->patch('/api/v1/documents/{id}', [$documents, 'update']);
$app->router->delete('/api/v1/documents/{id}', [$documents, 'destroy']);
$app->router->post('/api/v1/hosting/documents', [$hostingDocuments, 'store']);
$app->router->get('/api/v1/hosting/comunicati/pending', [$hostingDocuments, 'pendingComunicati']);
$app->router->get('/api/v1/hosting/comunicati/{id}', [$hostingDocuments, 'showPendingComunicato']);
$app->router->post('/api/v1/hosting/comunicati/{id}/complete', [$hostingDocuments, 'completeComunicato']);
$app->router->get('/api/v1/local/comunicati/pending', [$pendingQueue, 'index']);
$app->router->post('/api/v1/local/comunicati/process', [$pendingQueue, 'process']);

$app->router->get('/api/v1/reports', [$reports, 'index']);
$app->router->get('/api/v1/reports/stats', [$reports, 'stats']);
$app->router->post('/api/v1/reports', [$reports, 'store']);
$app->router->get('/api/v1/reports/attachments/{id}/preview', [$reports, 'attachment']);
$app->router->patch('/api/v1/reports/{id}/moderation', [$reports, 'moderate']);

$app->router->get('/api/v1/protocol', [$protocol, 'index']);
$app->router->post('/api/v1/protocol', [$protocol, 'store']);
$app->router->get('/api/v1/protocol/{id}', [$protocol, 'show']);
$app->router->patch('/api/v1/protocol/{id}', [$protocol, 'update']);
$app->router->delete('/api/v1/protocol/{id}', [$protocol, 'destroy']);
