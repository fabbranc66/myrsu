<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class DocumentController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->app->auth->requirePermission($request, 'documents.view');

        return Response::json(['data' => $this->app->documents->all()]);
    }

    public function show(Request $request, array $params): Response
    {
        $document = $this->findDocument((int)$params['id']);
        $this->authorizeDocumentAccess($request, $document, 'documents.view');
        if ((string)$document['category'] === 'comunicati') {
            $html = @file_get_contents($this->app->documentStorage->originalPath((string)$document['original_stored_name'])) ?: '';
            $document['comunicato'] = $this->app->comunicatoPdf->parse($html);
        }

        return Response::json(['data' => $document]);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->app->auth->requireUser($request);
        $data = $request->all();
        $document = $this->findDocument((int)$params['id']);
        $this->authorizeDocumentAccess($request, $document, 'documents.update');
        $visibility = (string)($data['visibility'] ?? $document['visibility']);

        if (!in_array($visibility, ['public', 'members', 'rsu'], true)) {
            throw new HttpException(422, 'Visibility non valida.');
        }

        if ((string)$document['category'] === 'comunicati' && isset($data['title'], $data['body'])) {
            return $this->regenerateComunicato($user, $document, $visibility, $data);
        }

        $updated = $this->app->documents->update((int)$document['id'], $visibility);

        $this->app->activityLogs->write((int)$user['id'], 'documents.update', [
            'section' => 'documents',
            'document_id' => $document['id'],
            'changes' => ['visibility' => ['from' => $document['visibility'], 'to' => $visibility]],
        ]);

        return Response::json(['data' => $updated]);
    }

    private function regenerateComunicato(array $user, array $document, string $visibility, array $data): Response
    {
        Validator::required($data, ['title', 'body']);
        $protocol = $this->app->protocols->findActiveByDocumentId((int)$document['id']);
        if ($protocol === null) {
            throw new HttpException(422, 'Protocollo comunicato non trovato.');
        }

        $html = $this->app->comunicatoPdf->html(
            (string)$data['title'],
            (string)$data['body'],
            (string)$protocol['protocol_number'],
            (string)$protocol['created_at']
        );
        $meta = $this->app->documentStorage->rewriteHtmlDocument(
            $document,
            $html,
            'comunicato-' . date('Ymd-His', strtotime((string)$protocol['created_at'])) . '.html'
        );
        $updated = $this->app->documents->updateComunicato((int)$document['id'], $meta + ['visibility' => $visibility]);
        $updated = $this->app->documents->updateSignature(
            (int)$updated['id'],
            $this->app->documentSignature->sign($updated)
        );
        $pdfPath = $this->app->documentStorage->pdfPath((string)$updated['pdf_public_path']);
        $this->app->documentVerificationPage->append($pdfPath, $updated, (string)$updated['signature']);
        $updated = $this->app->documents->updatePdfMetadata((int)$updated['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
        $this->app->protocols->update((int)$protocol['id'], (string)$data['title'], (int)$updated['id']);
        $this->app->documentStorage->uploadPdfToHosting($updated);
        $this->app->activityLogs->write((int)$user['id'], 'documents.regenerate', [
            'section' => 'documents',
            'document_id' => $updated['id'],
            'changes' => ['title' => ['from' => $protocol['subject'], 'to' => (string)$data['title']], 'body' => 'changed'],
        ]);

        return Response::json(['data' => $updated]);
    }

    public function store(Request $request): Response
    {
        $user = $this->app->auth->requirePermission($request, 'documents.upload');
        $visibility = (string)($_POST['visibility'] ?? 'rsu');
        $category = (string)($_POST['category'] ?? 'documenti');

        if (!in_array($visibility, ['public', 'members', 'rsu'], true)) {
            throw new HttpException(422, 'Visibility non valida.');
        }

        $stored = $this->app->documentStorage->store($_FILES['file'] ?? [], $category);
        $document = $this->app->documents->create($stored + [
            'visibility' => $visibility,
            'uploaded_by' => (int)$user['id'],
        ]);
        $document = $this->app->documents->updateSignature(
            (int)$document['id'],
            $this->app->documentSignature->sign($document)
        );
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $this->app->documentVerificationPage->append($pdfPath, $document, (string)$document['signature']);
        $document = $this->app->documents->updatePdfMetadata(
            (int)$document['id'],
            filesize($pdfPath),
            hash_file('sha256', $pdfPath)
        );
        $this->app->documentStorage->uploadPdfToHosting($document);

        $this->app->activityLogs->write((int)$user['id'], 'documents.upload', [
            'section' => 'documents',
            'document_id' => $document['id'],
            'signature' => $document['signature'],
        ]);

        return Response::json(['data' => $document], 201);
    }

    public function download(Request $request, array $params): FileResponse
    {
        $document = $this->findDocument((int)$params['id']);
        $user = $this->app->auth->requireUser($request);
        $this->authorizeDocumentAccess($request, $document, 'documents.download');
        if ((string)$document['conversion_status'] === 'pending') {
            throw new HttpException(409, 'Documento in elaborazione.');
        }
        $path = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);

        if (!is_file($path)) {
            throw new HttpException(404, 'File non trovato.');
        }

        $this->app->activityLogs->write((int)$user['id'], 'documents.download', [
            'section' => 'documents',
            'document_id' => $document['id'],
        ]);

        return new FileResponse($path, pathinfo((string)$document['original_name'], PATHINFO_FILENAME) . '.pdf', 'application/pdf');
    }

    public function preview(Request $request, array $params): FileResponse
    {
        $document = $this->findDocument((int)$params['id']);
        $user = $this->app->auth->requireUser($request);
        $this->authorizeDocumentAccess($request, $document, 'documents.download');
        if ((string)$document['conversion_status'] === 'pending') {
            throw new HttpException(409, 'Documento in elaborazione.');
        }
        $path = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);

        if (!is_file($path)) {
            throw new HttpException(404, 'File non trovato.');
        }

        $this->app->activityLogs->write((int)$user['id'], 'documents.preview', [
            'section' => 'documents',
            'document_id' => $document['id'],
        ]);

        return new FileResponse($path, pathinfo((string)$document['original_name'], PATHINFO_FILENAME) . '.pdf', 'application/pdf', true);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'documents.delete');
        $document = $this->findDocument((int)$params['id']);

        $this->app->documentStorage->delete($document);
        $this->app->documents->delete((int)$document['id']);
        $this->app->activityLogs->write((int)$user['id'], 'documents.delete', [
            'section' => 'documents',
            'document_id' => $document['id'],
        ]);

        return Response::json(['data' => ['deleted' => true]]);
    }

    private function findDocument(int $id): array
    {
        $document = $this->app->documents->findById($id);
        if ($document === null) {
            throw new HttpException(404, 'Documento non trovato.');
        }

        return $document;
    }

    private function authorizeDocumentAccess(Request $request, array $document, string $permission): void
    {
        $user = $this->app->auth->requireUser($request);

        if ($this->app->roles->userHasPermission((int)$user['id'], $permission)) {
            return;
        }

        if (
            (string)$document['category'] === 'comunicati'
            && (int)$document['uploaded_by'] === (int)$user['id']
        ) {
            return;
        }

        throw new HttpException(403, 'Permesso insufficiente.');
    }
}
