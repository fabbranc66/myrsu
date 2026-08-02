<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\RoomExternalAccessRepository;

final class RoomAccessService
{
    public function __construct(private readonly RoomExternalAccessRepository $access)
    {
    }

    public function resolve(Request $request, bool $registered = true, bool $touch = true): array
    {
        $token = trim((string)$request->header('x-room-token', ''));
        if (strlen($token) < 32) throw new HttpException(401, 'Invito Tavolo non valido.');

        $access = $this->access->findByTokenHash(hash('sha256', $token));
        if ($access === null) throw new HttpException(401, 'Invito Tavolo scaduto o revocato.');
        if (in_array((string)$access['room_status'], ['draft', 'cancelled'], true)) {
            throw new HttpException(403, 'Tavolo non disponibile.');
        }
        if ($registered && $access['registered_at'] === null) {
            throw new HttpException(403, 'Completa prima i dati di accesso.');
        }
        if ($touch) $this->access->touch((int)$access['id'], (string)$access['access_type']);

        return $access;
    }
}
