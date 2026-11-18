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
        $this->app->documentFolderAccess->assertDocument($document, $user);
        $restricted = !$this->app->documentFolderAccess->allowsDocument($document, null);
        if ($restricted && $request->input('confirm_restricted_projection') !== true) {
            return Response::json(['error' => [
                'code' => 'projection_confirmation_required',
                'message' => 'Il documento è riservato. Proiettarlo lo rende visibile a chi possiede il link della proiezione mentre questa è attiva. La visibilità nell’archivio non cambia. Continuare?',
            ]], 409);
        }
        $session = $this->app->meetingProjections->publish((int)$active['id'], (int)$document['id']);
        $this->app->activityLogs->write((int)$user['id'], 'meetings.projection_document', [
            'section' => 'meetings', 'meeting_id' => $active['meeting_id'], 'document_id' => $document['id'],
            'restricted_projection_confirmed' => $restricted,
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
        $pointer = $this->pointerData($request->all());
        $documentId = $request->input('document_id');
        if ($documentId !== null) {
            if ((!is_int($documentId) && !is_string($documentId)) || filter_var($documentId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new HttpException(422, 'Documento della proiezione non valido.');
            }
            $documentId = (int)$documentId;
            if ($documentId !== (int)($active['current_document_id'] ?? 0)) {
                throw new HttpException(409, 'Il documento proiettato è cambiato.');
            }
        }
        if ($pointer !== null && $documentId === null) throw new HttpException(422, 'Indicare il documento del puntatore.');
        $ratio = max(0, min(1, (float)$request->input('scroll_ratio', $active['scroll_ratio'])));
        $session = $this->app->meetingProjections->position((int)$active['id'], $ratio, $pointer, $documentId);
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
            'pointer_page' => (int)($session['pointer_page'] ?? 1),
            'pointer_x' => (float)($session['pointer_x'] ?? 0.5),
            'pointer_y' => (float)($session['pointer_y'] ?? 0.1),
            'highlight_x' => isset($session['highlight_x']) ? (float)$session['highlight_x'] : null,
            'highlight_y' => isset($session['highlight_y']) ? (float)$session['highlight_y'] : null,
            'highlight_page' => isset($session['highlight_page']) ? (int)$session['highlight_page'] : null,
            'highlight_end_x' => isset($session['highlight_end_x']) ? (float)$session['highlight_end_x'] : null,
            'highlight_end_y' => isset($session['highlight_end_y']) ? (float)$session['highlight_end_y'] : null,
            'revision' => (int)$session['revision'],
        ];
    }

    private function pointerData(array $data): ?array
    {
        $fields = ['pointer_page', 'pointer_x', 'pointer_y'];
        $highlightFields = ['highlight_x', 'highlight_y', 'highlight_page', 'highlight_end_x', 'highlight_end_y'];
        if (array_intersect(array_merge($fields, $highlightFields), array_keys($data)) === []) return null;
        if (array_diff($fields, array_keys($data)) !== []) throw new HttpException(422, 'Coordinate del puntatore incomplete.');
        $page = $data['pointer_page'];
        if ((!is_int($page) && !is_string($page)) || filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
            throw new HttpException(422, 'Pagina del puntatore non valida.');
        }
        $pointer = ['pointer_page' => (int)$page];
        foreach (['pointer_x', 'pointer_y'] as $field) {
            $value = $data[$field];
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < 0 || $value > 1) {
                throw new HttpException(422, 'Coordinate del puntatore non valide.');
            }
            $pointer[$field] = round((float)$value, 6);
        }
        return $pointer + $this->highlightData($data);
    }

    private function highlightData(array $data): array
    {
        $anchorFields = ['highlight_x', 'highlight_y'];
        $fixedFields = ['highlight_page', 'highlight_end_x', 'highlight_end_y'];
        $highlight = array_fill_keys(array_merge($anchorFields, $fixedFields), null);
        $provided = array_intersect(array_keys($highlight), array_keys($data));
        if ($provided === []) return $highlight;
        if (array_diff($anchorFields, array_keys($data)) !== []) throw new HttpException(422, 'Coordinate evidenziazione incomplete.');
        $fixed = array_intersect($fixedFields, array_keys($data));
        if ($fixed !== [] && array_diff($fixedFields, array_keys($data)) !== []) throw new HttpException(422, 'Coordinate evidenziazione fissa incomplete.');
        if ($data['highlight_x'] === null && $data['highlight_y'] === null && array_filter(array_intersect_key($data, $highlight), static fn ($value) => $value !== null) === []) return $highlight;
        $coordinateFields = $anchorFields;
        if ($fixed !== [] && ($data['highlight_page'] !== null || $data['highlight_end_x'] !== null || $data['highlight_end_y'] !== null)) {
            $page = $data['highlight_page'];
            if ((!is_int($page) && !is_string($page)) || filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) === false) {
                throw new HttpException(422, 'Pagina evidenziazione non valida.');
            }
            $highlight['highlight_page'] = (int)$page;
            array_push($coordinateFields, 'highlight_end_x', 'highlight_end_y');
        }
        foreach ($coordinateFields as $field) {
            $value = $data[$field];
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < 0 || $value > 1) {
                throw new HttpException(422, 'Coordinate evidenziazione non valide.');
            }
            $highlight[$field] = round((float)$value, 6);
        }
        return $highlight;
    }

    private function externalUrl(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/miodel/index.php'))), '/');
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
