<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class CallController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireOperator($request);
        $practiceId = $request->query('practice_id');

        return Response::json([
            'data' => array_map(
                [$this, 'transform'],
                $this->app->calls->all($practiceId !== null && $practiceId !== '' ? (int)$practiceId : null)
            ),
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $this->requireOperator($request);

        return Response::json(['data' => $this->transform($this->findCall((string)$params['id']))]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $call = $this->app->calls->create($this->validated($request->all()) + [
            'id' => $this->uuid(),
            'created_by' => (int)$user['id'],
        ]);

        $this->app->activityLogs->write((int)$user['id'], 'calls.create', [
            'section' => 'calls',
            'call_id' => $call['id'],
            'practice_id' => $call['practice_id'],
        ]);

        return Response::json(['data' => $this->transform($call)], 201);
    }

    public function linkPractice(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $data = $request->all();
        Validator::required($data, ['practice_id']);
        $call = $this->findCall((string)$params['id']);
        if ($call['practice_id'] !== null) {
            throw new HttpException(422, 'Telefonata gia collegata a pratica.');
        }
        $practice = $this->app->practices->findById((int)$data['practice_id']);
        if ($practice === null) {
            throw new HttpException(404, 'Pratica non trovata.');
        }

        $updated = $this->app->calls->linkPractice((string)$call['id'], (int)$practice['id']);

        $this->app->activityLogs->write((int)$user['id'], 'calls.link_practice', [
            'section' => 'calls',
            'call_id' => $updated['id'],
            'practice_id' => $practice['id'],
        ]);

        return Response::json(['data' => $this->transform($updated)]);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $call = $this->findCall((string)$params['id']);
        if ($call['practice_id'] !== null) {
            throw new HttpException(422, 'Telefonata collegata a pratica: modifica non consentita.');
        }

        $updated = $this->app->calls->update((string)$call['id'], $this->validated($request->all()) + [
            'practice_id' => null,
        ]);
        $this->app->activityLogs->write((int)$user['id'], 'calls.update', [
            'section' => 'calls',
            'call_id' => $updated['id'],
        ]);

        return Response::json(['data' => $this->transform($updated)]);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $call = $this->findCall((string)$params['id']);
        if ($call['practice_id'] !== null) {
            throw new HttpException(422, 'Telefonata collegata a pratica: cancellazione non consentita.');
        }

        $this->app->calls->delete((string)$call['id']);
        $this->app->activityLogs->write((int)$user['id'], 'calls.delete', [
            'section' => 'calls',
            'call_id' => $call['id'],
        ]);

        return Response::json(['data' => ['deleted' => true]]);
    }

    private function validated(array $data): array
    {
        Validator::required($data, ['interlocutor_name', 'direction', 'call_date', 'call_time', 'content']);

        if (!in_array((string)$data['direction'], ['incoming', 'outgoing'], true)) {
            throw new HttpException(422, 'Direzione non valida.');
        }

        if (strlen(trim((string)$data['content'])) < 10) {
            throw new HttpException(422, 'Contenuto troppo corto.');
        }

        $date = trim((string)$data['call_date']);
        $time = trim((string)$data['call_time']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
            throw new HttpException(422, 'Data non valida.');
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $time) || strtotime('1970-01-01 ' . $time) === false) {
            throw new HttpException(422, 'Ora non valida.');
        }

        return [
            'practice_id' => isset($data['practice_id']) && $data['practice_id'] !== '' ? (int)$data['practice_id'] : null,
            'direction' => trim((string)$data['direction']),
            'interlocutor_name' => trim((string)$data['interlocutor_name']),
            'interlocutor_role' => trim((string)($data['interlocutor_role'] ?? '')) ?: null,
            'interlocutor_org' => trim((string)($data['interlocutor_org'] ?? '')) ?: null,
            'call_date' => $date,
            'call_time' => $time,
            'content' => trim((string)$data['content']),
            'outcome' => trim((string)($data['outcome'] ?? '')) ?: null,
        ];
    }

    private function requireOperator(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!array_intersect($roles, ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        return $user;
    }

    private function findCall(string $id): array
    {
        $call = $this->app->calls->findById($id);
        if ($call === null) {
            throw new HttpException(404, 'Telefonata non trovata.');
        }

        return $call;
    }

    private function transform(array $call): array
    {
        return [
            'id' => (string)$call['id'],
            'type' => 'call',
            'direction' => (string)$call['direction'],
            'interlocutor' => [
                'name' => (string)$call['interlocutor_name'],
                'role' => $call['interlocutor_role'],
                'org' => $call['interlocutor_org'],
            ],
            'datetime' => sprintf('%sT%s:00', $call['call_date'], substr((string)$call['call_time'], 0, 5)),
            'content' => (string)$call['content'],
            'outcome' => $call['outcome'],
            'practice_id' => $call['practice_id'] !== null ? (int)$call['practice_id'] : null,
            'created_by' => (int)$call['created_by'],
            'created_at' => (string)$call['created_at'],
        ];
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
