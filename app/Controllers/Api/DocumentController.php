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
        $this->requireDocumentViewer($request);

        return Response::json(['data' => $this->app->documents->all()]);
    }

    public function publicIndex(Request $request): Response
    {
        $sections = [];
        foreach ($this->app->documents->publicReady() as $document) {
            if ((string)$document['category'] === 'comunicati') {
                $html = @file_get_contents($this->app->documentStorage->originalPath((string)$document['original_stored_name'])) ?: '';
                $document['comunicato'] = $this->app->comunicatoPdf->parse($html);
            }
            unset($document['original_stored_name']);
            $sections[(string)$document['category']][] = $document;
        }

        return Response::json(['data' => ['sections' => $sections]]);
    }

    public function privateIndex(Request $request): Response
    {
        $user = $this->app->auth->requireUser($request);
        if (!in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

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
        if (!in_array((string)$document['category'], ['documenti', 'comunicati'], true)) {
            $visibility = 'rsu';
        }

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
        $revisionAt = date('Y-m-d H:i:s') . ' - ' . trim((string)$user['name']);
        $officialPublicPath = $this->app->protocolDocumentName->publicPath('comunicati', (string)$protocol['protocol_number']);
        $this->app->protocolDocumentName->move(
            $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']),
            $this->app->documentStorage->pdfPath($officialPublicPath)
        );
        $document = $this->app->documents->updatePublicPath((int)$document['id'], $officialPublicPath);

        $original = $this->app->comunicatoDirectPdf->textOriginal(
            (string)$data['title'],
            (string)$data['body'],
            (string)$protocol['protocol_number'],
            (string)$protocol['created_at']
        );
        $meta = $this->app->documentStorage->rewriteGeneratedPdf(
            $document,
            $original,
            'comunicato-' . date('Ymd-His', strtotime((string)$protocol['created_at'])) . '.txt',
            function (string $pdfPath) use ($data, $protocol, $document, $revisionAt): void {
                $this->app->comunicatoDirectPdf->write(
                    $pdfPath,
                    (string)$data['title'],
                    (string)$data['body'],
                    (string)$protocol['protocol_number'],
                    (string)$protocol['created_at'],
                    null,
                    $this->baseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'],
                    null,
                    $revisionAt
                );
            }
        );
        $updated = $this->app->documents->updateComunicato((int)$document['id'], $meta + ['visibility' => $visibility]);
        $updated = $this->app->documents->updateSignature(
            (int)$updated['id'],
            $this->app->documentSignature->sign($updated)
        );
        $pdfPath = $this->app->documentStorage->pdfPath((string)$updated['pdf_public_path']);
        $verifyUrl = $this->baseUrl() . '/ui/document-verify.html?id=' . (int)$updated['id'] . '&sig=' . urlencode((string)$updated['signature']);
        $this->app->comunicatoDirectPdf->write(
            $pdfPath,
            (string)$data['title'],
            (string)$data['body'],
            (string)$protocol['protocol_number'],
            (string)$protocol['created_at'],
            null,
            $verifyUrl,
            (string)$updated['signature'],
            $revisionAt
        );
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

    private function baseUrl(): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
            return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
        }

        return rtrim((string)env_value('APP_URL', 'http://localhost/myrsu'), '/');
    }

    public function store(Request $request): Response
    {
        $user = $this->app->auth->requirePermission($request, 'documents.upload');
        $visibility = (string)($_POST['visibility'] ?? 'rsu');
        $category = (string)($_POST['category'] ?? 'documenti');
        if (!in_array($category, ['documenti', 'comunicati'], true)) {
            $visibility = 'rsu';
        }

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
        $verifyUrl = $this->baseUrl() . '/ui/document-verify.html?id=' . (int)$document['id']
            . '&sig=' . urlencode((string)$document['signature']);
        $this->app->uploadedDocumentPdf->write(
            $this->app->documentStorage->originalPath((string)$document['original_stored_name']),
            $pdfPath,
            $document,
            null,
            $verifyUrl,
            (string)$document['signature']
        );
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

        return new FileResponse($path, basename((string)$document['pdf_public_path']), 'application/pdf');
    }

    public function preview(Request $request, array $params): FileResponse
    {
        $document = $this->findDocument((int)$params['id']);
        $user = $this->authorizePublicOrDownload($request, $document);
        if ((string)$document['conversion_status'] === 'pending') {
            throw new HttpException(409, 'Documento in elaborazione.');
        }
        $path = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);

        if (!is_file($path)) {
            throw new HttpException(404, 'File non trovato.');
        }

        if ($user !== null) {
            $this->app->activityLogs->write((int)$user['id'], 'documents.preview', [
                'section' => 'documents',
                'document_id' => $document['id'],
            ]);
        }

        return new FileResponse($path, basename((string)$document['pdf_public_path']), 'application/pdf', true);
    }

    public function thumbnail(Request $request, array $params): FileResponse
    {
        $document = $this->findDocument((int)$params['id']);
        $this->authorizePublicOrDownload($request, $document);
        if ((string)$document['conversion_status'] === 'pending') {
            throw new HttpException(409, 'Documento in elaborazione.');
        }

        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $thumbnailPath = $this->app->documentThumbnail->firstPage($document, $pdfPath);

        return new FileResponse($thumbnailPath, 'document-' . (int)$document['id'] . '.png', 'image/png', true);
    }

    public function privatePreview(Request $request, array $params): FileResponse
    {
        return $this->privateOriginal($request, (int)$params['id'], true);
    }

    public function privateDownload(Request $request, array $params): FileResponse
    {
        return $this->privateOriginal($request, (int)$params['id'], false);
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

    private function privateOriginal(Request $request, int $id, bool $inline): FileResponse
    {
        $user = $this->app->auth->requireUser($request);
        if (!in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true)) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        $document = $this->findDocument($id);
        $storedName = (string)($document['original_stored_name'] ?? '');
        if ($storedName === '') {
            throw new HttpException(404, 'Originale non trovato.');
        }

        $path = $this->app->documentStorage->originalPath($storedName);
        if (!is_file($path)) {
            throw new HttpException(404, 'File originale non trovato.');
        }

        return new FileResponse(
            $path,
            (string)$document['original_name'],
            (string)($document['original_mime_type'] ?: 'application/octet-stream'),
            $inline
        );
    }

    private function authorizeDocumentAccess(Request $request, array $document, string $permission): void
    {
        $user = $this->app->auth->requireUser($request);

        if ($this->app->roles->userHasPermission((int)$user['id'], $permission)) {
            return;
        }

        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (in_array($permission, ['documents.view', 'documents.download'], true) && array_intersect($roles, ['admin', 'delegato', 'rls'])) {
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

    private function authorizePublicOrDownload(Request $request, array $document): ?array
    {
        $user = $this->app->auth->user($request);
        $isPublicBoardDocument = (string)$document['visibility'] === 'public'
            && in_array((string)$document['category'], ['documenti', 'comunicati'], true);
        if (!$isPublicBoardDocument) {
            $this->authorizeDocumentAccess($request, $document, 'documents.download');
        }

        return $user;
    }

    private function requireDocumentViewer(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!$this->app->roles->userHasPermission((int)$user['id'], 'documents.view') && !array_intersect($roles, ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        return $user;
    }
}
