<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class RoomExternalAccessController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function show(Request $request): Response
    {
        $access = $this->app->roomAccess->resolve($request);
        $wasRegistered = $access['registered_at'] !== null;
        if ((string)$access['access_type'] === 'external') {
            $access = $this->app->roomExternalInvitations->confirm($access);
        }
        if (!$wasRegistered && $access['registered_at'] !== null) {
            $this->app->roomTimeline->event((int)$access['room_id'], null, 'external_participant.registered', 'external_participant', (int)$access['id'], [
                'local_identifier' => $access['local_identifier'],
                'matched_user_id' => $access['matched_user_id'],
            ]);
            $this->app->activityLogs->write(null, 'rooms.external_register', [
                'section' => 'rooms', 'room_id' => $access['room_id'], 'external_participant_id' => $access['id'],
            ]);
        }
        $registered = $access['registered_at'] !== null;
        return Response::json(['data' => [
            'requires_registration' => !$registered,
            'room' => $this->roomData($access),
            'participant' => $registered ? $this->participantData($access) : null,
            'documents' => $registered ? $this->publicDocuments((int)$access['room_id']) : [],
        ]]);
    }

    public function register(Request $request): Response
    {
        $access = $this->app->roomAccess->resolve($request, false, false);
        if ($access['registered_at'] !== null || $access['verification_sent_at'] !== null) {
            throw new HttpException(409, 'Invito già utilizzato. Apri il link ricevuto via e-mail.');
        }
        $data = $request->all();
        Validator::required($data, ['name', 'email']);
        $email = strtolower(trim((string)$data['email']));
        Validator::email($email);
        $participant = $this->app->roomExternalInvitations->begin(
            $access,
            trim((string)$data['name']),
            $email,
            trim((string)($data['phone'] ?? '')) ?: null
        );
        $this->app->roomTimeline->event((int)$access['room_id'], null, 'external_participant.verification_sent', 'external_participant', (int)$access['id'], [
            'email' => $email,
        ]);
        $this->app->activityLogs->write(null, 'rooms.external_verification_sent', [
            'section' => 'rooms', 'room_id' => $access['room_id'], 'external_participant_id' => $access['id'],
        ]);
        return Response::json(['data' => [
            'verification_sent' => true,
            'email' => $participant['email'],
        ]], 202);
    }

    public function timeline(Request $request): Response
    {
        $access = $this->app->roomAccess->resolve($request);
        $rows = $this->app->roomTimeline->page((int)$access['room_id'], 60, null);
        $messageIds = array_map(
            static fn (array $row): int => (int)$row['timeline_id'],
            array_filter($rows, static fn (array $row): bool => (string)$row['timeline_type'] === 'message')
        );
        $attachments = $this->app->roomAttachments->forMessageIds($messageIds);
        foreach ($rows as &$row) {
            $attachment = $attachments[(int)$row['timeline_id']] ?? null;
            $legacyAudio = $attachment !== null
                && str_starts_with((string)$attachment['original_name'], 'audio-')
                && str_ends_with(strtolower((string)$attachment['original_name']), '.webm');
            $row['attachment'] = $attachment === null ? null : [
                'id' => (int)$attachment['id'],
                'original_name' => $attachment['original_name'],
                'mime_type' => $attachment['mime_type'],
                'attachment_type' => $legacyAudio ? 'audio' : $attachment['attachment_type'],
                'size_bytes' => (int)$attachment['size_bytes'],
            ];
            if ((string)$row['timeline_type'] !== 'message') {
                $row['user_id'] = null;
                $row['external_author_id'] = null;
            }
            if ((string)$row['timeline_type'] === 'event') {
                $row['content'] = '{}';
            }
        }
        return Response::json(['data' => $rows]);
    }

    public function message(Request $request): Response
    {
        $access = $this->app->roomAccess->resolve($request);
        if (!in_array((string)$access['permission_level'], ['interact', 'manage'], true)) {
            throw new HttpException(403, 'Interazione non autorizzata.');
        }
        if (!in_array((string)$access['room_status'], ['open', 'in_progress'], true)) {
            throw new HttpException(409, 'Il Tavolo è in sola lettura.');
        }
        $data = $request->all();
        Validator::required($data, ['content']);
        $content = trim((string)$data['content']);
        if (mb_strlen($content) < 2 || mb_strlen($content) > 10000) {
            throw new HttpException(422, 'Il messaggio deve contenere da 2 a 10000 caratteri.');
        }
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        if ($parentId !== null) {
            $parent = $this->app->roomTimeline->message($parentId);
            if ($parent === null || (int)$parent['room_id'] !== (int)$access['room_id']) {
                throw new HttpException(422, 'Messaggio di risposta non valido.');
            }
        }
        $message = (string)$access['access_type'] === 'internal'
            ? $this->app->roomTimeline->createMessage((int)$access['room_id'], (int)$access['user_id'], [
                'parent_id' => $parentId, 'message_type' => 'message', 'content' => $content,
            ])
            : $this->app->roomTimeline->createExternalMessage((int)$access['room_id'], (int)$access['participant_id'], [
                'parent_id' => $parentId, 'message_type' => 'message', 'content' => $content,
            ]);
        $this->app->activityLogs->write(
            (string)$access['access_type'] === 'internal' ? (int)$access['user_id'] : null,
            'rooms.room_message', [
            'section' => 'rooms', 'room_id' => $access['room_id'], 'message_id' => $message['id'],
        ]);
        return Response::json(['data' => $message], 201);
    }

    public function preview(Request $request, array $params): FileResponse
    {
        $access = $this->app->roomAccess->resolve($request);
        $documentId = (int)$params['documentId'];
        if ($this->app->roomDocuments->findActive((int)$access['room_id'], $documentId) === null) {
            throw new HttpException(404, 'Documento non condiviso.');
        }
        $document = $this->app->documents->findById($documentId);
        if ($document === null || (string)$document['conversion_status'] !== 'ready') {
            throw new HttpException(404, 'Documento non disponibile.');
        }
        $path = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        if (!is_file($path)) throw new HttpException(404, 'File non trovato.');
        return new FileResponse($path, basename((string)$document['pdf_public_path']), 'application/pdf', true);
    }

    private function roomData(array $access): array
    {
        return ['code' => $access['room_code'], 'title' => $access['room_title'], 'description' => $access['room_description'], 'status' => $access['room_status'], 'category' => $access['category_name']];
    }

    private function publicDocuments(int $roomId): array
    {
        return array_map(static fn (array $document): array => [
            'document_id' => $document['document_id'],
            'original_name' => $document['original_name'],
            'shared_at' => $document['shared_at'],
        ], $this->app->roomDocuments->forRoom($roomId));
    }

    private function participantData(array $access): array
    {
        return [
            'id' => $access['access_type'] === 'internal' ? $access['user_id'] : $access['participant_id'],
            'name' => $access['name'],
            'email' => $access['email'],
            'phone' => $access['phone'],
            'local_identifier' => $access['local_identifier'],
            'identity_type' => $access['access_type'] === 'internal' || $access['matched_user_id'] !== null ? 'myrsu' : 'external',
            'permission_level' => $access['permission_level'],
            'room_role' => $access['room_role'],
        ];
    }
}
