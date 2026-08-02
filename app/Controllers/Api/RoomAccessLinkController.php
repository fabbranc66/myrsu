<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class RoomAccessLinkController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function create(Request $request, array $params): Response
    {
        $user = $this->app->auth->requireUser($request);
        $roomId = (int)$params['id'];
        if ($this->app->rooms->findById($roomId) === null) {
            throw new HttpException(404, 'Tavolo non trovato.');
        }
        $membership = $this->app->roomPermissions->requireView($roomId, (int)$user['id']);
        $token = bin2hex(random_bytes(32));
        $this->app->roomExternalAccess->createInternalToken(
            $roomId,
            (int)$user['id'],
            (string)$membership['permission_level'],
            hash('sha256', $token)
        );
        $this->app->activityLogs->write((int)$user['id'], 'rooms.access_link_create', [
            'section' => 'rooms', 'room_id' => $roomId,
        ]);
        return Response::json(['data' => ['url' => $this->url($token)]]);
    }

    private function url(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . '/tavolo/?token=' . urlencode($token);
    }
}
