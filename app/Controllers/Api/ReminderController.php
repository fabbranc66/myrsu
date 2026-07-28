<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class ReminderController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireOperator($request);

        return Response::json(['data' => $this->app->reminders->all([
            'status' => (string)($request->query('status') ?? ''),
            'entity_type' => (string)($request->query('entity_type') ?? ''),
            'assigned_to' => (string)($request->query('assigned_to') ?? ''),
        ])]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $reminder = $this->app->reminders->create($this->validated($request->all()) + [
            'created_by' => (int)$user['id'],
        ]);
        $this->log((int)$user['id'], 'reminders.create', $reminder);

        return Response::json(['data' => $reminder], 201);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $this->findReminder((int)$params['id']);
        $reminder = $this->app->reminders->update((int)$params['id'], $this->validated($request->all(), true));
        $this->log((int)$user['id'], 'reminders.update', $reminder);

        return Response::json(['data' => $reminder]);
    }

    public function done(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $this->findReminder((int)$params['id']);
        $reminder = $this->app->reminders->done((int)$params['id']);
        $this->log((int)$user['id'], 'reminders.done', $reminder);

        return Response::json(['data' => $reminder]);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $reminder = $this->findReminder((int)$params['id']);
        $this->app->reminders->delete((int)$reminder['id']);
        $this->log((int)$user['id'], 'reminders.delete', $reminder);

        return Response::json(['data' => ['deleted' => true]]);
    }

    private function validated(array $data, bool $update = false): array
    {
        Validator::required($data, ['entity_type', 'entity_id', 'title', 'due_at']);
        if (!in_array((string)$data['entity_type'], ['call', 'email'], true)) {
            throw new HttpException(422, 'Tipo collegamento non valido.');
        }
        $status = (string)($data['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'done', 'cancelled'], true)) {
            throw new HttpException(422, 'Stato reminder non valido.');
        }
        if (strtotime((string)$data['due_at']) === false) {
            throw new HttpException(422, 'Scadenza non valida.');
        }

        return [
            'entity_type' => (string)$data['entity_type'],
            'entity_id' => trim((string)$data['entity_id']),
            'title' => trim((string)$data['title']),
            'notes' => trim((string)($data['notes'] ?? '')) ?: null,
            'due_at' => str_replace('T', ' ', substr((string)$data['due_at'], 0, 16)) . ':00',
            'status' => $update ? $status : 'pending',
            'assigned_to' => isset($data['assigned_to']) && $data['assigned_to'] !== '' ? (int)$data['assigned_to'] : null,
        ];
    }

    private function findReminder(int $id): array
    {
        $reminder = $this->app->reminders->findById($id);
        if ($reminder === null) {
            throw new HttpException(404, 'Reminder non trovato.');
        }

        return $reminder;
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

    private function log(int $userId, string $action, array $reminder): void
    {
        $this->app->activityLogs->write($userId, $action, [
            'section' => 'reminders',
            'reminder_id' => $reminder['id'],
            'entity_type' => $reminder['entity_type'],
            'entity_id' => $reminder['entity_id'],
        ]);
    }
}
