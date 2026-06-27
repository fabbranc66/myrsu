<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class ComunicatoController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function store(Request $request): Response
    {
        $user = $this->app->auth->requirePermission($request, 'documents.upload');
        Validator::required($request->all(), ['title', 'body', 'visibility']);

        $data = $request->all();
        if (!in_array((string)$data['visibility'], ['public', 'members', 'rsu'], true)) {
            throw new HttpException(422, 'Visibility non valida.');
        }

        $original = $this->app->comunicatoDirectPdf->draftOriginal(
            (string)$data['title'],
            (string)$data['body']
        );
        $stored = $this->app->documentStorage->storeDraftText(
            $original,
            $this->fileName((string)$data['title']),
            'comunicati'
        );
        $document = $this->app->documents->create($stored + [
            'visibility' => (string)$data['visibility'],
            'uploaded_by' => (int)$user['id'],
        ]);
        $this->app->activityLogs->write((int)$user['id'], 'documents.comunicato_draft_create', [
            'section' => 'documents',
            'document_id' => $document['id'],
            'status' => $document['conversion_status'],
        ]);

        return Response::json(['data' => ['document' => $document]], 201);
    }

    public function generate(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'documents.upload');
        $document = $this->app->documents->findById((int)$params['id']);
        if ($document === null || (string)$document['category'] !== 'comunicati') {
            throw new HttpException(404, 'Comunicato non trovato.');
        }
        if ((string)$document['conversion_status'] !== 'pending') {
            throw new HttpException(422, 'Documento ufficiale gia generato.');
        }

        $sourcePath = $this->app->documentStorage->originalPath((string)$document['original_stored_name']);
        $content = $this->app->comunicatoPdf->parse((string)file_get_contents($sourcePath));
        $protocol = $this->app->protocols->create('OUT', 'COM', (string)$content['title'], (int)$user['id']);
        $officialPublicPath = $this->app->protocolDocumentName->publicPath('comunicati', (string)$protocol['protocol_number']);
        $this->app->protocolDocumentName->move(
            $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']),
            $this->app->documentStorage->pdfPath($officialPublicPath)
        );
        $document = $this->app->documents->updatePublicPath((int)$document['id'], $officialPublicPath);
        $signature = $this->app->documentSignature->sign($document);
        $verifyUrl = $this->appBaseUrl() . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode($signature);
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);

        try {
            $this->app->comunicatoDirectPdf->write(
                $pdfPath,
                (string)$content['title'],
                (string)$content['body'],
                (string)$protocol['protocol_number'],
                (string)$protocol['created_at'],
                null,
                $verifyUrl,
                $signature
            );
        } catch (\Throwable $exception) {
            $this->app->protocols->cancel((int)$protocol['id'], (int)$user['id']);
            if (is_file($pdfPath)) unlink($pdfPath);
            throw $exception;
        }

        $protocol = $this->app->protocols->update((int)$protocol['id'], (string)$content['title'], (int)$document['id']);
        $document = $this->app->documents->updateSignature((int)$document['id'], $signature);
        $document = $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
        $this->app->documentStorage->uploadPdfToHosting($document);
        $this->app->activityLogs->write((int)$user['id'], 'documents.comunicato_generate', [
            'section' => 'documents',
            'document_id' => $document['id'],
            'protocol_number' => $protocol['protocol_number'],
        ]);

        return Response::json(['data' => ['document' => $document, 'protocol' => $protocol]]);
    }

    private function fileName(string $title): string
    {
        return 'comunicato-' . date('Ymd-His') . '.txt';
    }

    private function appBaseUrl(): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
            return $scheme . '://' . $host . ($dir === '' ? '' : $dir);
        }

        return rtrim((string)env_value('APP_URL', 'http://localhost/myrsu'), '/');
    }

}
