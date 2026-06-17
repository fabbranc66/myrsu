<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class ProtocolController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->app->auth->requirePermission($request, 'protocol.view');

        return Response::json(['data' => $this->app->protocols->all()]);
    }

    public function show(Request $request, array $params): Response
    {
        $this->app->auth->requirePermission($request, 'protocol.view');

        return Response::json(['data' => $this->app->protocols->findById((int)$params['id'])]);
    }

    public function store(Request $request): Response
    {
        $user = $this->app->auth->requirePermission($request, 'protocol.create');
        $data = $request->all();

        Validator::required($data, ['direction', 'type_code', 'subject']);

        $direction = strtoupper((string)$data['direction']);
        $typeCode = strtoupper((string)$data['type_code']);

        if (!in_array($direction, ['IN', 'OUT'], true)) {
            throw new HttpException(422, 'Direction non valida.');
        }

        $entry = $this->app->protocols->create($direction, $typeCode, (string)$data['subject'], (int)$user['id']);
        $this->app->activityLogs->write((int)$user['id'], 'protocol.create', [
            'protocol_number' => $entry['protocol_number'],
        ]);

        return Response::json(['data' => $entry], 201);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'protocol.update');
        $data = $request->all();

        Validator::required($data, ['subject']);

        $entry = $this->app->protocols->update(
            (int)$params['id'],
            (string)$data['subject'],
            isset($data['document_id']) ? (int)$data['document_id'] : null
        );

        $this->app->activityLogs->write((int)$user['id'], 'protocol.update', [
            'protocol_number' => $entry['protocol_number'],
        ]);

        return Response::json(['data' => $entry]);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'protocol.cancel');
        $entry = $this->app->protocols->cancel((int)$params['id'], (int)$user['id']);

        $this->app->activityLogs->write((int)$user['id'], 'protocol.cancel', [
            'protocol_number' => $entry['protocol_number'],
        ]);

        return Response::json(['data' => $entry]);
    }
}
