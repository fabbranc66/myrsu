<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class ProtocolController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->app->auth->requirePermission($request, 'protocol.view');

        return Response::json(['data' => $this->app->protocols->all()]);
    }

    public function show(Request $request, array $params): Response
    {
        $this->app->auth->requirePermission($request, 'protocol.view');

        return Response::json(['data' => $this->app->protocols->findById((int)$params['id'])]);
    }

    public function store(Request $request): Response
    {
        $user = $this->app->auth->requirePermission($request, 'protocol.create');
        $data = $request->all();

        Validator::required($data, ['direction', 'type_code', 'subject']);

        $direction = strtoupper((string)$data['direction']);
        $typeCode = strtoupper((string)$data['type_code']);

        if (!in_array($direction, ['IN', 'OUT'], true)) {
            throw new HttpException(422, 'Direction non valida.');
        }

        $entry = $this->app->protocols->create($direction, $typeCode, (string)$data['subject'], (int)$user['id']);
        if (isset($data['document_id'])) {
            $entry = $this->app->protocols->update((int)$entry['id'], (string)$data['subject'], (int)$data['document_id']);
            $this->applyOfficialDocumentName($entry);
        }

        $this->app->activityLogs->write((int)$user['id'], 'protocol.create', [
            'section' => 'protocol',
            'protocol_number' => $entry['protocol_number'],
        ]);

        return Response::json(['data' => $entry], 201);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'protocol.update');
        $data = $request->all();

        Validator::required($data, ['subject']);

        $entry = $this->app->protocols->update(
            (int)$params['id'],
            (string)$data['subject'],
            isset($data['document_id']) ? (int)$data['document_id'] : null
        );
        $this->applyOfficialDocumentName($entry);

        $this->app->activityLogs->write((int)$user['id'], 'protocol.update', [
            'section' => 'protocol',
            'protocol_number' => $entry['protocol_number'],
        ]);

        return Response::json(['data' => $entry]);
    }

    private function applyOfficialDocumentName(array $entry): void
    {
        $documentId = (int)($entry['document_id'] ?? 0);
        if ($documentId === 0) {
            return;
        }

        $document = $this->app->documents->findById($documentId);
        if ($document === null || (string)$document['category'] !== 'documenti' || (string)$document['conversion_status'] !== 'ready') {
            return;
        }

        $publicPath = $this->app->protocolDocumentName->publicPath('documenti', (string)$entry['protocol_number']);
        $this->app->protocolDocumentName->move(
            $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']),
            $this->app->documentStorage->pdfPath($publicPath)
        );
        $document = $this->app->documents->updatePublicPath($documentId, $publicPath);
        $signature = (string)($document['signature'] ?? '');
        if ($signature === '') {
            $signature = $this->app->documentSignature->sign($document);
            $document = $this->app->documents->updateSignature($documentId, $signature);
        }
        $verifyUrl = $this->baseUrl() . '/ui/document-verify.html?id=' . $documentId . '&sig=' . urlencode($signature);
        $pdfPath = $this->app->documentStorage->pdfPath($publicPath);
        $creator = $this->app->users->findById((int)$document['uploaded_by']);
        $document['creator_name'] = (string)($creator['name'] ?? '');
        $this->app->uploadedDocumentPdf->write(
            $this->app->documentStorage->originalPath((string)$document['original_stored_name']),
            $pdfPath,
            $document,
            $entry,
            $verifyUrl,
            $signature
        );
        $document = $this->app->documents->updatePdfMetadata($documentId, filesize($pdfPath), hash_file('sha256', $pdfPath));
        $this->app->documentStorage->uploadPdfToHosting($document);
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

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'protocol.cancel');
        $entry = $this->app->protocols->findById((int)$params['id']);
        if (!$entry) {
            throw new HttpException(404, 'Protocollo non trovato.');
        }

        $documentId = (int)($entry['document_id'] ?? 0);
        $isComunicato = strtoupper((string)($entry['type_code'] ?? '')) === 'COM';
        $entry = $this->app->protocols->cancel((int)$params['id'], (int)$user['id']);

        if ($isComunicato && $documentId > 0) {
            $document = $this->app->documents->findById($documentId);
            if ($document !== null) {
                $this->app->documentStorage->delete($document);
                $this->app->documents->delete($documentId);
            }
            $this->app->unionMeetings->clearPublicDocumentByDocumentId($documentId);
        }

        $this->app->activityLogs->write((int)$user['id'], 'protocol.cancel', [
            'section' => 'protocol',
            'protocol_number' => $entry['protocol_number'],
        ]);

        return Response::json(['data' => $entry]);
    }
}
