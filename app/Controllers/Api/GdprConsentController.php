<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class GdprConsentController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);

        return Response::json([
            'data' => $this->app->gdprConsents->latestForUser((int)$user['id']),
        ]);
    }

    public function userIndex(Request $request, array $params): Response
    {
        $this->app->auth->requirePermission($request, 'gdpr.view_all');

        return Response::json([
            'data' => $this->app->gdprConsents->allForUser((int)$params['id']),
        ]);
    }

    public function store(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);
        $data = $request->all();

        Validator::required($data, ['consent_type', 'document_version', 'accepted']);

        $consentId = $this->app->gdprConsents->create(
            (int)$user['id'],
            (string)$data['consent_type'],
            (string)$data['document_version'],
            (bool)$data['accepted'],
            $_SERVER['REMOTE_ADDR'] ?? null
        );
        $this->app->activityLogs->write((int)$user['id'], 'gdpr.consent.recorded', ['consent_id' => $consentId]);

        return Response::json(['data' => ['id' => $consentId]], 201);
    }
}
