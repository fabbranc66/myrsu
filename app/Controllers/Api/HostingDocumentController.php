<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class HostingDocumentController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function store(Request $request): Response
    {
        $this->app->hostingDocumentReceive->assertToken($request->bearerToken());

        $result = $this->app->hostingDocumentReceive->receive(
            $_FILES['file'] ?? [],
            (string)($_POST['category'] ?? ''),
            (string)($_POST['checksum_sha256'] ?? ''),
            json_decode((string)($_POST['metadata_json'] ?? '[]'), true) ?: []
        );

        return Response::json(['data' => $result], 201);
    }

    public function pendingComunicati(Request $request): Response
    {
        $this->app->hostingDocumentReceive->assertToken($request->bearerToken());

        return Response::json([
            'data' => array_map(fn (array $document): array => $this->withComunicato($document), $this->app->documents->pendingComunicati()),
        ]);
    }

    public function showPendingComunicato(Request $request, array $params): Response
    {
        $this->app->hostingDocumentReceive->assertToken($request->bearerToken());

        foreach ($this->app->documents->pendingComunicati() as $document) {
            if ((int)$document['id'] === (int)$params['id']) {
                return Response::json(['data' => $this->withComunicato($document)]);
            }
        }

        return Response::json(['error' => ['message' => 'Documento non trovato.']], 404);
    }

    public function completeComunicato(Request $request, array $params): Response
    {
        $this->app->hostingDocumentReceive->assertToken($request->bearerToken());

        $result = $this->app->hostingDocumentReceive->receivePendingComunicato(
            $_FILES['file'] ?? [],
            (int)$params['id'],
            (string)($_POST['checksum_sha256'] ?? ''),
            (string)($_POST['signature'] ?? '')
        );

        return Response::json(['data' => $result]);
    }

    public function pendingOffice(Request $request): Response
    {
        $this->app->hostingDocumentReceive->assertToken($request->bearerToken());
        return Response::json(['data' => $this->app->documents->pendingOffice()]);
    }

    public function pendingOfficeOriginal(Request $request, array $params): FileResponse
    {
        $this->app->hostingDocumentReceive->assertToken($request->bearerToken());
        $document = $this->app->documents->findById((int)$params['id']);
        if ($document === null || (string)$document['category'] !== 'documenti' || (string)$document['conversion_status'] !== 'pending') {
            throw new HttpException(404, 'Documento pending non trovato.');
        }
        $path = $this->app->documentStorage->originalPath((string)$document['original_stored_name']);
        if (!is_file($path)) throw new HttpException(404, 'Originale non trovato.');
        return new FileResponse($path, (string)$document['original_name'], (string)$document['original_mime_type']);
    }

    public function completeOffice(Request $request, array $params): Response
    {
        $this->app->hostingDocumentReceive->assertToken($request->bearerToken());
        return Response::json(['data' => $this->app->hostingDocumentReceive->receivePendingDocument(
            $_FILES['file'] ?? [],
            (int)$params['id'],
            (string)($_POST['checksum_sha256'] ?? ''),
            (string)($_POST['signature'] ?? '')
        )]);
    }

    private function withComunicato(array $document): array
    {
        $html = @file_get_contents($this->app->documentStorage->originalPath((string)$document['original_stored_name'])) ?: '';
        $document['comunicato'] = $this->app->comunicatoPdf->parse($html);

        return $document;
    }
}
