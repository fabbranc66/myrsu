<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class RoomAttachmentController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function store(Request $request): Response
    {
        $access = $this->app->roomAccess->resolve($request);
        if (!in_array((string)$access['permission_level'], ['interact', 'manage'], true)) {
            throw new HttpException(403, 'Condivisione non autorizzata.');
        }
        if (!in_array((string)$access['room_status'], ['open', 'in_progress'], true)) {
            throw new HttpException(409, 'Il Tavolo è in sola lettura.');
        }
        $file = $_FILES['attachment'] ?? null;
        if (!is_array($file)) throw new HttpException(400, 'Allegato obbligatorio.');
        $stored = $this->app->roomAttachmentStorage->store((int)$access['room_id'], $file);
        $caption = trim((string)$request->input('caption', '')) ?: (string)$stored['original_name'];
        $message = (string)$access['access_type'] === 'internal'
            ? $this->app->roomTimeline->createMessage((int)$access['room_id'], (int)$access['user_id'], [
                'parent_id' => null, 'message_type' => 'message', 'content' => $caption,
            ])
            : $this->app->roomTimeline->createExternalMessage((int)$access['room_id'], (int)$access['participant_id'], [
                'parent_id' => null, 'message_type' => 'message', 'content' => $caption,
            ]);
        try {
            $attachment = $this->app->roomAttachments->create(
                (int)$access['room_id'], (int)$message['id'], $stored,
                (string)$access['access_type'] === 'internal' ? (int)$access['user_id'] : null,
                (string)$access['access_type'] === 'external' ? (int)$access['participant_id'] : null
            );
        } catch (\Throwable $exception) {
            $this->app->roomTimeline->deleteMessage((int)$message['id']);
            $this->app->roomAttachmentStorage->delete((int)$access['room_id'], (string)$stored['stored_name']);
            throw $exception;
        }
        $this->app->activityLogs->write(
            (string)$access['access_type'] === 'internal' ? (int)$access['user_id'] : null,
            'rooms.attachment_shared', [
                'section' => 'rooms', 'room_id' => $access['room_id'], 'attachment_id' => $attachment['id'],
            ]
        );
        return Response::json(['data' => $attachment], 201);
    }

    public function show(Request $request, array $params): FileResponse
    {
        $access = $this->app->roomAccess->resolve($request);
        $attachment = $this->app->roomAttachments->find((int)$params['id']);
        if ($attachment === null || (int)$attachment['room_id'] !== (int)$access['room_id']) {
            throw new HttpException(404, 'Allegato non trovato.');
        }
        $path = $this->app->roomAttachmentStorage->path((int)$access['room_id'], (string)$attachment['stored_name']);
        if (!is_file($path)) throw new HttpException(404, 'File non trovato.');
        $mimeType = (string)$attachment['mime_type'];
        if (str_starts_with((string)$attachment['original_name'], 'audio-') && str_ends_with(strtolower((string)$attachment['original_name']), '.webm')) {
            $mimeType = 'audio/webm';
        }
        return new FileResponse($path, (string)$attachment['original_name'], $mimeType, true);
    }
}
