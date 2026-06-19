<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

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
        $this->app->auth->requirePermission($request, 'documents.view');

        return Response::json(['data' => $this->findDocument((int)$params['id'])]);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'documents.update');
        $data = $request->all();
        $visibility = (string)($data['visibility'] ?? '');

        if (!in_array($visibility, ['public', 'members', 'rsu'], true)) {
            throw new HttpException(422, 'Visibility non valida.');
        }

        $document = $this->findDocument((int)$params['id']);
        $updated = $this->app->documents->update((int)$document['id'], $visibility);

        $this->app->activityLogs->write((int)$user['id'], 'documents.update', [
            'section' => 'documents',
            'document_id' => $document['id'],
            'changes' => ['visibility' => ['from' => $document['visibility'], 'to' => $visibility]],
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
        $user = $this->app->auth->requirePermission($request, 'documents.download');
        $document = $this->findDocument((int)$params['id']);
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
        $user = $this->app->auth->requirePermission($request, 'documents.download');
        $document = $this->findDocument((int)$params['id']);
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
}
