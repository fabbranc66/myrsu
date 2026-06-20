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

        $protocol = $this->app->protocols->create('OUT', 'COM', (string)$data['title'], (int)$user['id']);
        $html = $this->app->comunicatoPdf->html(
            (string)$data['title'],
            (string)$data['body'],
            $protocol['protocol_number'],
            (string)$protocol['created_at']
        );
        $stored = $this->app->documentStorage->storeHtml($html, $this->fileName((string)$data['title']), 'comunicati');
        $document = $this->app->documents->create($stored + [
            'visibility' => (string)$data['visibility'],
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
        $protocol = $this->app->protocols->update((int)$protocol['id'], (string)$data['title'], (int)$document['id']);
        $this->app->documentStorage->uploadPdfToHosting($document);
        $this->app->activityLogs->write((int)$user['id'], 'documents.comunicato_create', [
            'section' => 'documents',
            'document_id' => $document['id'],
            'protocol_number' => $protocol['protocol_number'],
        ]);

        return Response::json(['data' => ['document' => $document, 'protocol' => $protocol]], 201);
    }

    private function fileName(string $title): string
    {
        return 'comunicato-' . date('Ymd-His') . '.html';
    }
}
