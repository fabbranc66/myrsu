<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class RoomParticipantController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function candidates(Request $request, array $params): Response
    {
        $user = $this->authorize($request, (int)$params['id']);
        return Response::json(['data' => array_values(array_filter(
            $this->app->users->all(),
            static fn (array $candidate): bool => (string)$candidate['status'] === 'active'
        ))]);
    }

    public function store(Request $request, array $params): Response
    {
        $user = $this->authorize($request, (int)$params['id']);
        $data = $request->all();
        Validator::required($data, ['user_id', 'permission_level']);
        $permission = (string)$data['permission_level'];
        if (!in_array($permission, ['read', 'interact', 'manage'], true)) {
            throw new HttpException(422, 'Livello di accesso non valido.');
        }
        $target = $this->app->users->findById((int)$data['user_id']);
        if ($target === null || (string)$target['status'] !== 'active') {
            throw new HttpException(404, 'Utente non disponibile.');
        }
        $room = $this->app->rooms->findById((int)$params['id']);
        if ($room !== null && (int)$room['responsible_id'] === (int)$target['id']) {
            $permission = 'manage';
        }
        $participant = $this->app->roomParticipants->save(
            (int)$params['id'], (int)$target['id'], $permission,
            trim((string)($data['room_role'] ?? '')), (int)$user['id']
        );
        $this->app->roomTimeline->event((int)$params['id'], (int)$user['id'], 'participant.saved', 'user', (int)$target['id'], [
            'permission_level' => $permission,
        ]);
        $this->log((int)$user['id'], 'rooms.participant_save', (int)$params['id'], (int)$target['id']);
        return Response::json(['data' => $participant], 201);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->authorize($request, (int)$params['id']);
        $room = $this->app->rooms->findById((int)$params['id']);
        if ($room === null) throw new HttpException(404, 'Tavolo non trovato.');
        if ((int)$room['responsible_id'] === (int)$params['userId']) {
            throw new HttpException(409, 'Il responsabile del Tavolo non può essere rimosso.');
        }
        $this->app->roomParticipants->revoke((int)$room['id'], (int)$params['userId']);
        $this->app->roomTimeline->event((int)$room['id'], (int)$user['id'], 'participant.revoked', 'user', (int)$params['userId']);
        $this->log((int)$user['id'], 'rooms.participant_revoke', (int)$room['id'], (int)$params['userId']);
        return Response::json(['data' => ['revoked' => true]]);
    }

    public function storeExternal(Request $request, array $params): Response
    {
        $user = $this->authorize($request, (int)$params['id']);
        $data = $request->all();
        Validator::required($data, ['permission_level']);
        $permission = (string)$data['permission_level'];
        if (!in_array($permission, ['read', 'interact'], true)) {
            throw new HttpException(422, 'Livello di accesso non valido.');
        }
        $plainToken = bin2hex(random_bytes(32));
        $participant = $this->app->roomParticipants->createExternal((int)$params['id'], [
            'name' => null,
            'email' => null,
            'organization' => trim((string)($data['organization'] ?? '')) ?: null,
            'room_role' => trim((string)($data['room_role'] ?? '')) ?: null,
            'permission_level' => $permission,
            'access_token_hash' => hash('sha256', $plainToken),
            'token_expires_at' => !empty($data['token_expires_at'])
                ? str_replace('T', ' ', (string)$data['token_expires_at']) : null,
        ], (int)$user['id']);
        $this->app->roomTimeline->event((int)$params['id'], (int)$user['id'], 'external_participant.created', 'external_participant', (int)$participant['id'], [
            'permission_level' => $permission,
        ]);
        $this->log((int)$user['id'], 'rooms.external_participant_create', (int)$params['id'], (int)$participant['id']);
        unset($participant['access_token_hash']);
        $participant['access_url'] = $this->externalUrl($plainToken);
        return Response::json(['data' => $participant], 201);
    }

    public function destroyExternal(Request $request, array $params): Response
    {
        $user = $this->authorize($request, (int)$params['id']);
        $participant = $this->app->roomParticipants->externalById((int)$params['externalId']);
        if ($participant === null || (int)$participant['room_id'] !== (int)$params['id']) {
            throw new HttpException(404, 'Partecipante esterno non trovato.');
        }
        $this->app->roomParticipants->revokeExternal((int)$params['id'], (int)$participant['id']);
        $this->app->roomTimeline->event((int)$params['id'], (int)$user['id'], 'external_participant.revoked', 'external_participant', (int)$participant['id']);
        $this->log((int)$user['id'], 'rooms.external_participant_revoke', (int)$params['id'], (int)$participant['id']);
        return Response::json(['data' => ['revoked' => true]]);
    }

    private function authorize(Request $request, int $roomId): array
    {
        $user = $this->app->auth->requireUser($request);
        if ($this->app->rooms->findById($roomId) === null) throw new HttpException(404, 'Tavolo non trovato.');
        $this->app->roomPermissions->requireManage($roomId, (int)$user['id']);
        return $user;
    }

    private function log(int $userId, string $action, int $roomId, int $targetId): void
    {
        $this->app->activityLogs->write($userId, $action, [
            'section' => 'rooms', 'room_id' => $roomId, 'target_user_id' => $targetId,
        ]);
    }

    private function externalUrl(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . '/tavolo/?invite=' . urlencode($token);
    }
}
