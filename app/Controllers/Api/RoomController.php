<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class RoomController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);
        return Response::json(['data' => $this->app->rooms->allForUser(
            (int)$user['id'],
            $this->app->roomPermissions->isSystemAdmin((int)$user['id'])
        )]);
    }

    public function categories(Request $request): Response
    {
        $this->app->auth->requireUser($request);
        return Response::json(['data' => $this->app->rooms->categories()]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $data = $request->all();
        Validator::required($data, ['title', 'category_id']);
        $status = (string)($data['status'] ?? 'draft');
        $this->validateStatus($status);
        $room = $this->app->roomService->create($data + ['status' => $status], (int)$user['id']);
        $this->log((int)$user['id'], 'rooms.create', $room);
        return Response::json(['data' => $room], 201);
    }

    public function show(Request $request, array $params): Response
    {
        $user = $this->app->auth->requireUser($request);
        $room = $this->find((int)$params['id']);
        $membership = $this->app->roomPermissions->requireView((int)$room['id'], (int)$user['id']);
        if (!empty($membership['system_admin'])) {
            $this->log((int)$user['id'], 'rooms.admin_access', $room);
        }
        return Response::json(['data' => [
            'room' => $room,
            'membership' => $membership,
            'participants' => $this->app->roomParticipants->forRoom((int)$room['id']),
            'documents' => $this->app->roomDocuments->forRoom((int)$room['id']),
        ]]);
    }

    public function timeline(Request $request, array $params): Response
    {
        $user = $this->app->auth->requireUser($request);
        $room = $this->find((int)$params['id']);
        $this->app->roomPermissions->requireView((int)$room['id'], (int)$user['id']);
        $limit = max(10, min(100, (int)$request->query('limit', 40)));
        $before = $request->query('before_id');
        return Response::json(['data' => $this->app->roomTimeline->page(
            (int)$room['id'], $limit, $before === null ? null : (int)$before
        )]);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->app->auth->requireUser($request);
        $room = $this->find((int)$params['id']);
        $this->app->roomPermissions->requireManage((int)$room['id'], (int)$user['id']);
        $data = $request->all();
        Validator::required($data, ['title', 'category_id', 'responsible_id']);
        if ($this->app->rooms->categoryById((int)$data['category_id']) === null) {
            throw new HttpException(422, 'Categoria Tavolo non valida.');
        }
        $updated = $this->app->rooms->update((int)$room['id'], [
            'title' => trim((string)$data['title']),
            'description' => trim((string)($data['description'] ?? '')) ?: null,
            'category_id' => (int)$data['category_id'],
            'responsible_id' => (int)$data['responsible_id'],
        ]);
        $this->app->roomTimeline->event((int)$room['id'], (int)$user['id'], 'room.updated', 'room', (int)$room['id']);
        $this->log((int)$user['id'], 'rooms.update', $updated);
        return Response::json(['data' => $updated]);
    }

    public function status(Request $request, array $params): Response
    {
        $user = $this->app->auth->requireUser($request);
        $room = $this->find((int)$params['id']);
        $this->app->roomPermissions->requireManage((int)$room['id'], (int)$user['id']);
        $status = (string)$request->input('status', '');
        $this->validateStatus($status);
        $updated = $this->app->rooms->changeStatus((int)$room['id'], $status);
        $this->app->roomTimeline->event((int)$room['id'], (int)$user['id'], 'room.status_changed', 'room', (int)$room['id'], [
            'from' => $room['status'], 'to' => $status,
        ]);
        $this->log((int)$user['id'], 'rooms.status', $updated, ['from' => $room['status']]);
        return Response::json(['data' => $updated]);
    }

    private function find(int $id): array
    {
        $room = $this->app->rooms->findById($id);
        if ($room === null) throw new HttpException(404, 'Tavolo non trovato.');
        return $room;
    }

    private function requireOperator(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!array_intersect($this->app->roles->rolesForUser((int)$user['id']), ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }
        return $user;
    }

    private function validateStatus(string $status): void
    {
        if (!in_array($status, ['draft', 'open', 'in_progress', 'suspended', 'closed', 'archived', 'cancelled'], true)) {
            throw new HttpException(422, 'Stato Tavolo non valido.');
        }
    }

    private function log(int $userId, string $action, array $room, array $extra = []): void
    {
        $this->app->activityLogs->write($userId, $action, $extra + [
            'section' => 'rooms', 'room_id' => $room['id'], 'title' => $room['title'],
        ]);
    }
}
