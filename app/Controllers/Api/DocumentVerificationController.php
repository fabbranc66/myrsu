<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class DocumentVerificationController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function show(Request $request, array $params): Response
    {
        $signature = (string)$request->query('sig', '');
        $document = $this->findDocument((int)$params['id']);

        if ($document === null) {
            throw new HttpException(404, 'Documento non trovato.');
        }

        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $realChecksum = is_file($pdfPath) ? hash_file('sha256', $pdfPath) : null;
        $checksumOk = $realChecksum === (string)$document['pdf_checksum_sha256'];
        $valid = $checksumOk && hash_equals((string)$document['signature'], strtoupper(trim($signature)));

        return Response::json([
            'data' => [
                'valid' => $valid,
                'document_id' => (int)$document['id'],
                'original_name' => $document['original_name'],
                'signature' => $signature,
                'expected_signature' => $document['signature'],
                'checksum_ok' => $checksumOk,
                'mode' => 'server_file',
                'signed_at' => $document['signed_at'] ?? null,
            ],
        ]);
    }

    public function file(Request $request, array $params): Response
    {
        $signature = (string)($_POST['sig'] ?? '');
        $document = $this->findDocument((int)$params['id']);

        if ($document === null) {
            throw new HttpException(404, 'Documento non trovato.');
        }

        $file = $_FILES['file'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, 'File non valido.');
        }

        $uploadedChecksum = hash_file('sha256', (string)$file['tmp_name']);
        $checksumOk = $uploadedChecksum === (string)$document['pdf_checksum_sha256'];
        $valid = $checksumOk && hash_equals((string)$document['signature'], strtoupper(trim($signature)));

        return Response::json([
            'data' => [
                'valid' => $valid,
                'document_id' => (int)$document['id'],
                'signature' => $signature,
                'expected_signature' => $document['signature'],
                'checksum_ok' => $checksumOk,
                'mode' => 'uploaded_file',
            ],
        ]);
    }

    private function findDocument(int $id): ?array
    {
        $metadata = $this->app->documentVerificationMetadata->find($id);
        if ($metadata !== null) {
            return $metadata;
        }

        if (isset($this->app->documents)) {
            return $this->app->documents->findById($id);
        }

        try {
            $this->app->bootDatabase();
            return $this->app->documents->findById($id);
        } catch (Throwable) {
            return null;
        }
    }
}
