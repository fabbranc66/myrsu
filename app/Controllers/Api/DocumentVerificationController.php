<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;

final class DocumentVerificationController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function show(Request $request, array $params): Response
    {
        $signature = (string)$request->query('sig', '');
        $document = $this->app->documents->findById((int)$params['id']);

        if ($document === null) {
            throw new HttpException(404, 'Documento non trovato.');
        }

        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $realChecksum = is_file($pdfPath) ? hash_file('sha256', $pdfPath) : null;
        $valid = $this->app->documentSignature->valid($document, $signature, $realChecksum);

        return Response::json([
            'data' => [
                'valid' => $valid,
                'document_id' => (int)$document['id'],
                'original_name' => $document['original_name'],
                'signature' => $signature,
                'expected_signature' => $document['signature'],
                'checksum_ok' => $realChecksum === (string)$document['pdf_checksum_sha256'],
                'mode' => 'server_file',
                'signed_at' => $document['signed_at'],
            ],
        ]);
    }

    public function file(Request $request, array $params): Response
    {
        $signature = (string)($_POST['sig'] ?? '');
        $document = $this->app->documents->findById((int)$params['id']);

        if ($document === null) {
            throw new HttpException(404, 'Documento non trovato.');
        }

        $file = $_FILES['file'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, 'File non valido.');
        }

        $uploadedChecksum = hash_file('sha256', (string)$file['tmp_name']);
        $valid = $this->app->documentSignature->valid($document, $signature, $uploadedChecksum);

        return Response::json([
            'data' => [
                'valid' => $valid,
                'document_id' => (int)$document['id'],
                'signature' => $signature,
                'expected_signature' => $document['signature'],
                'checksum_ok' => $uploadedChecksum === (string)$document['pdf_checksum_sha256'],
                'mode' => 'uploaded_file',
            ],
        ]);
    }
}
