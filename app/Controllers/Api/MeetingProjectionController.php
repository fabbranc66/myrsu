<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class MeetingProjectionController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function status(Request $request): Response
    {
        $this->requireOperator($request);
        $active = $this->app->meetingProjections->active();
        return Response::json(['data' => $active === null ? null : $this->operatorData($active)]);
    }

    public function open(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $meeting = $this->app->unionMeetings->findById((int)$params['id']);
        if ($meeting === null) throw new HttpException(404, 'Incontro non trovato.');
        $active = $this->app->meetingProjections->active();
        if ($active !== null) {
            if ((int)$active['meeting_id'] !== (int)$meeting['id']) {
                throw new HttpException(409, 'Proiezione già attiva per: ' . $active['meeting_title']);
            }
            return Response::json(['data' => $this->operatorData($active)]);
        }
        $session = $this->app->meetingProjections->create(
            (int)$meeting['id'],
            (int)$user['id'],
            bin2hex(random_bytes(32)),
            bin2hex(random_bytes(32))
        );
        $this->log((int)$user['id'], 'meetings.projection_open', $session);
        return Response::json(['data' => $this->operatorData($session)], 201);
    }

    public function close(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $active = $this->requireActive();
        if ((int)$active['meeting_id'] !== (int)$params['id']) {
            throw new HttpException(409, 'La proiezione appartiene a un altro incontro.');
        }
        $this->app->meetingProjections->close((int)$active['id']);
        $this->log((int)$user['id'], 'meetings.projection_close', $active);
        return Response::json(['data' => ['closed' => true]]);
    }

    public function publish(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $active = $this->requireActive();
        $document = $this->app->documents->findById((int)$params['id']);
        if ($document === null || (string)$document['conversion_status'] !== 'ready') {
            throw new HttpException(404, 'Documento non disponibile.');
        }
        $session = $this->app->meetingProjections->publish((int)$active['id'], (int)$document['id']);
        $this->app->activityLogs->write((int)$user['id'], 'meetings.projection_document', [
            'section' => 'meetings', 'meeting_id' => $active['meeting_id'], 'document_id' => $document['id'],
        ]);
        return Response::json(['data' => $this->operatorData($session)]);
    }

    public function position(Request $request): Response
    {
        $controlToken = trim((string)$request->input('control_token', ''));
        if ($controlToken !== '') {
            $active = $this->app->meetingProjections->activeByControlToken($controlToken);
            if ($active === null) throw new HttpException(403, 'Controllo proiezione non autorizzato.');
        } else {
            $this->requireOperator($request);
            $active = $this->requireActive();
        }
        $ratio = max(0, min(1, (float)$request->input('scroll_ratio', 0)));
        $session = $this->app->meetingProjections->position((int)$active['id'], $ratio);
        return Response::json(['data' => $this->publicData($session)]);
    }

    public function publicState(Request $request): Response
    {
        $session = $this->requireToken($request);
        $controlToken = trim((string)$request->query('control', ''));
        $operator = $controlToken !== '' && hash_equals((string)$session['control_token'], $controlToken);
        return Response::json(['data' => $operator ? $this->operatorData($session) : $this->publicData($session)]);
    }

    public function publicDocument(Request $request): FileResponse
    {
        $session = $this->requireToken($request);
        if ($session['current_document_id'] === null) throw new HttpException(404, 'Nessun documento in proiezione.');
        $document = $this->app->documents->findById((int)$session['current_document_id']);
        if ($document === null) throw new HttpException(404, 'Documento non trovato.');
        $path = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        if (!is_file($path)) throw new HttpException(404, 'File non trovato.');
        return new FileResponse($path, basename($path), 'application/pdf', true);
    }

    private function requireOperator(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!array_intersect($this->app->roles->rolesForUser((int)$user['id']), ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }
        return $user;
    }

    private function requireActive(): array
    {
        $session = $this->app->meetingProjections->active();
        if ($session === null) throw new HttpException(409, 'Nessuna proiezione attiva.');
        return $session;
    }

    private function requireToken(Request $request): array
    {
        $token = trim((string)$request->query('token', ''));
        $session = $token === '' ? null : $this->app->meetingProjections->activeByToken($token);
        if ($session === null) throw new HttpException(410, 'Proiezione terminata o non disponibile.');
        return $session;
    }

    private function operatorData(array $session): array
    {
        $url = $this->externalUrl((string)$session['public_token']);
        $qr = $this->app->pdfQr->image($url, 'projection-qr', 0, 0, 1);
        return $this->publicData($session) + [
            'public_url' => $url,
            'operator_url' => $url . '&operator=1&control=' . urlencode((string)$session['control_token']),
            'qr_data_url' => 'data:image/jpeg;base64,' . base64_encode($qr['data']),
        ];
    }

    private function publicData(array $session): array
    {
        return [
            'meeting_id' => (int)$session['meeting_id'], 'meeting_title' => $session['meeting_title'],
            'document_id' => $session['current_document_id'] === null ? null : (int)$session['current_document_id'],
            'document_name' => $session['document_name'], 'scroll_ratio' => (float)$session['scroll_ratio'],
            'revision' => (int)$session['revision'],
        ];
    }

    private function externalUrl(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/myrsu/index.php'))), '/');
        $parent = rtrim(str_replace('\\', '/', dirname($scriptDir)), '/');
        return $scheme . '://' . $host . ($parent === '' ? '' : $parent) . '/video/?token=' . urlencode($token);
    }

    private function log(int $userId, string $action, array $session): void
    {
        $this->app->activityLogs->write($userId, $action, [
            'section' => 'meetings', 'meeting_id' => $session['meeting_id'], 'projection_id' => $session['id'],
        ]);
    }
}
