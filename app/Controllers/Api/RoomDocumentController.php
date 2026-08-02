<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class RoomDocumentController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function available(Request $request, array $params): Response
    {
        $user = $this->requireDocumentManager($request, (int)$params['id']);
        return Response::json(['data' => $this->app->roomDocuments->available()]);
    }

    public function store(Request $request, array $params): Response
    {
        $user = $this->requireDocumentManager($request, (int)$params['id']);
        $data = $request->all();
        Validator::required($data, ['document_id']);
        $document = $this->app->documents->findById((int)$data['document_id']);
        if ($document === null || (string)$document['conversion_status'] !== 'ready') {
            throw new HttpException(404, 'Documento non disponibile.');
        }
        $shared = $this->app->roomDocuments->share((int)$params['id'], (int)$document['id'], (int)$user['id']);
        $this->app->roomTimeline->event((int)$params['id'], (int)$user['id'], 'document.shared', 'document', (int)$document['id']);
        $this->log((int)$user['id'], 'rooms.document_share', (int)$params['id'], (int)$document['id']);
        return Response::json(['data' => $shared], 201);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->requireDocumentManager($request, (int)$params['id']);
        $documentId = (int)$params['documentId'];
        if ($this->app->roomDocuments->findActive((int)$params['id'], $documentId) === null) {
            throw new HttpException(404, 'Condivisione non trovata.');
        }
        $this->app->roomDocuments->revoke((int)$params['id'], $documentId, (int)$user['id']);
        $this->app->roomTimeline->event((int)$params['id'], (int)$user['id'], 'document.revoked', 'document', $documentId);
        $this->log((int)$user['id'], 'rooms.document_revoke', (int)$params['id'], $documentId);
        return Response::json(['data' => ['revoked' => true]]);
    }

    public function preview(Request $request, array $params): FileResponse
    {
        $user = $this->app->auth->requireUser($request);
        $roomId = (int)$params['id'];
        $documentId = (int)$params['documentId'];
        $this->app->roomPermissions->requireView($roomId, (int)$user['id']);
        if ($this->app->roomDocuments->findActive($roomId, $documentId) === null) {
            throw new HttpException(404, 'Documento non condiviso.');
        }
        $document = $this->app->documents->findById($documentId);
        if ($document === null || (string)$document['conversion_status'] !== 'ready') {
            throw new HttpException(404, 'Documento non disponibile.');
        }
        $path = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        if (!is_file($path)) throw new HttpException(404, 'File non trovato.');
        $this->log((int)$user['id'], 'rooms.document_preview', $roomId, $documentId);
        return new FileResponse($path, basename((string)$document['pdf_public_path']), 'application/pdf', true);
    }

    private function requireDocumentManager(Request $request, int $roomId): array
    {
        $user = $this->app->auth->requireUser($request);
        if ($this->app->rooms->findById($roomId) === null) throw new HttpException(404, 'Tavolo non trovato.');
        $this->app->roomPermissions->requireManage($roomId, (int)$user['id']);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!$this->app->roles->userHasPermission((int)$user['id'], 'documents.view') && !array_intersect($roles, ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Accesso ai documenti MyRSU non autorizzato.');
        }
        return $user;
    }

    private function log(int $userId, string $action, int $roomId, int $documentId): void
    {
        $this->app->activityLogs->write($userId, $action, [
            'section' => 'rooms', 'room_id' => $roomId, 'document_id' => $documentId,
        ]);
    }
}
