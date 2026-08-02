<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class RoomMessageController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function store(Request $request, array $params): Response
    {
        $user = $this->app->auth->requireUser($request);
        $room = $this->findRoom((int)$params['id']);
        $this->app->roomPermissions->requireInteract((int)$room['id'], (int)$user['id']);
        $this->app->roomService->assertWritable($room);
        $data = $request->all();
        Validator::required($data, ['content']);
        $type = (string)($data['message_type'] ?? 'message');
        if (!in_array($type, ['message', 'update', 'request', 'reply', 'proposal', 'decision', 'notice'], true)) {
            throw new HttpException(422, 'Tipo messaggio non valido.');
        }
        $content = trim((string)$data['content']);
        if (mb_strlen($content) < 2 || mb_strlen($content) > 10000) {
            throw new HttpException(422, 'Il messaggio deve contenere da 2 a 10000 caratteri.');
        }
        $message = $this->app->roomTimeline->createMessage((int)$room['id'], (int)$user['id'], [
            'parent_id' => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            'message_type' => $type,
            'content' => $content,
        ]);
        $this->app->activityLogs->write((int)$user['id'], 'rooms.message_create', [
            'section' => 'rooms', 'room_id' => $room['id'], 'message_id' => $message['id'],
        ]);
        return Response::json(['data' => $message], 201);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->app->auth->requireUser($request);
        $room = $this->findRoom((int)$params['id']);
        $membership = $this->app->roomPermissions->requireInteract((int)$room['id'], (int)$user['id']);
        $message = $this->app->roomTimeline->message((int)$params['messageId']);
        if ($message === null || (int)$message['room_id'] !== (int)$room['id']) {
            throw new HttpException(404, 'Messaggio non trovato.');
        }
        if ((int)$message['author_id'] !== (int)$user['id'] && (string)$membership['permission_level'] !== 'manage') {
            throw new HttpException(403, 'Cancellazione non autorizzata.');
        }
        $this->app->roomTimeline->deleteMessage((int)$message['id']);
        $this->app->roomTimeline->event((int)$room['id'], (int)$user['id'], 'message.deleted', 'message', (int)$message['id']);
        $this->app->activityLogs->write((int)$user['id'], 'rooms.message_delete', [
            'section' => 'rooms', 'room_id' => $room['id'], 'message_id' => $message['id'],
        ]);
        return Response::json(['data' => ['deleted' => true]]);
    }

    private function findRoom(int $id): array
    {
        $room = $this->app->rooms->findById($id);
        if ($room === null) throw new HttpException(404, 'Tavolo non trovato.');
        return $room;
    }
}
