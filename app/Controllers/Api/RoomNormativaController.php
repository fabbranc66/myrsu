<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class RoomNormativaController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function search(Request $request): Response
    {
        $this->app->roomAccess->resolve($request);
        $query = trim((string)$request->query('q', ''));
        $scope = trim((string)$request->query('scope', 'all')) ?: 'all';
        if ($query === '') throw new HttpException(422, 'Testo ricerca obbligatorio.');
        if (!in_array($scope, ['all', 'ccnl', 'representation', 'safety'], true)) {
            throw new HttpException(422, 'Ambito normativa non valido.');
        }
        if ($scope === 'all' && $this->app->normativa->meaningfulTermCount($query) < 4) {
            throw new HttpException(422, 'Con una ricerca breve scegli un ambito specifico.');
        }

        return Response::json(['data' => [
            'items' => $this->app->normativa->search($query, $scope, 20, 0),
        ]]);
    }

    public function unit(Request $request, array $params): Response
    {
        $this->app->roomAccess->resolve($request);
        $unit = $this->app->normativa->unit((int)$params['id']);
        if ($unit === []) throw new HttpException(404, 'Riferimento normativa non trovato.');
        return Response::json(['data' => $unit]);
    }

    public function share(Request $request, array $params): Response
    {
        $access = $this->app->roomAccess->resolve($request);
        if (!in_array((string)$access['permission_level'], ['interact', 'manage'], true)) {
            throw new HttpException(403, 'Condivisione non autorizzata.');
        }
        if (!in_array((string)$access['room_status'], ['open', 'in_progress'], true)) {
            throw new HttpException(409, 'Il Tavolo è in sola lettura.');
        }

        $unit = $this->app->normativa->unit((int)$params['id']);
        if ($unit === []) throw new HttpException(404, 'Riferimento normativa non trovato.');
        $title = trim((string)(($unit['rubrica'] ?? '') ?: ($unit['titolo'] ?? '') ?: ($unit['document_title'] ?? 'Normativa')));
        $fullText = $this->bodyText((string)($unit['testo'] ?? ''), $title);
        $selection = trim((string)$request->input('selection', ''));
        $sharedText = $selection !== '' ? $selection : $fullText;
        if (mb_strlen($sharedText) > 30000) {
            throw new HttpException(422, 'Testo troppo lungo: seleziona il passaggio da condividere.');
        }
        $content = "Riferimento normativo: {$title}\n\n{$sharedText}";
        $message = (string)$access['access_type'] === 'internal'
            ? $this->app->roomTimeline->createMessage((int)$access['room_id'], (int)$access['user_id'], [
                'parent_id' => null, 'message_type' => 'message', 'content' => $content,
            ])
            : $this->app->roomTimeline->createExternalMessage((int)$access['room_id'], (int)$access['participant_id'], [
                'parent_id' => null, 'message_type' => 'message', 'content' => $content,
            ]);
        $this->app->activityLogs->write(
            (string)$access['access_type'] === 'internal' ? (int)$access['user_id'] : null,
            'rooms.normativa_shared', [
                'section' => 'rooms', 'room_id' => $access['room_id'], 'unit_id' => (int)$params['id'],
            ]
        );
        return Response::json(['data' => $message], 201);
    }

    private function bodyText(string $text, string $title): string
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];
        foreach ($lines as $index => $line) {
            if (trim($line) === '') continue;
            $heading = trim((string)preg_replace('/^#{1,6}\s*/u', '', trim($line)));
            if (mb_strtolower($heading) === mb_strtolower($title)) unset($lines[$index]);
            break;
        }
        return trim(implode("\n", $lines));
    }
}
