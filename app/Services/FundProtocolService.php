<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\HttpException;

final class FundProtocolService
{
    public function __construct(private readonly Application $app)
    {
    }

    public function contract(array $contract, int $userId): array
    {
        $documentId = (int)($contract['document_id'] ?? 0);
        if ($documentId === 0) {
            throw new HttpException(422, 'Documento contratto mancante.');
        }

        $existing = $this->app->protocols->findActiveByDocumentId($documentId);
        if ($existing !== null) {
            return $existing;
        }

        $subject = 'Contratto distributori automatici - ' . (string)$contract['supplier_name'];
        $entry = $this->app->protocols->create('IN', 'FND', $subject, $userId);
        $entry = $this->app->protocols->update((int)$entry['id'], $subject, $documentId);

        $this->applyOfficialDocumentName($entry);

        return $entry;
    }

    public function movement(array $movement, int $userId): ?array
    {
        $documentId = (int)($movement['document_id'] ?? 0);
        if ($documentId === 0) {
            return null;
        }

        $existing = $this->app->protocols->findActiveByDocumentId($documentId);
        if ($existing !== null) {
            return $existing;
        }

        $direction = (string)$movement['movement_type'] === 'income' ? 'IN' : 'OUT';
        $subject = 'Fondi distributori - ' . (string)$movement['reason'];
        $entry = $this->app->protocols->create($direction, 'FND', $subject, $userId);
        $entry = $this->app->protocols->update((int)$entry['id'], $subject, $documentId);

        $this->applyOfficialDocumentName($entry);

        return $entry;
    }

    public function statement(int $documentId, string $statementDate, int $userId): array
    {
        if ($documentId === 0) {
            throw new HttpException(422, 'Documento estratto conto mancante.');
        }

        $existing = $this->app->protocols->findActiveByDocumentId($documentId);
        if ($existing !== null) {
            return $existing;
        }

        $subject = 'Estratto conto fondi distributori al ' . $statementDate;
        $entry = $this->app->protocols->create('IN', 'FND', $subject, $userId);
        $entry = $this->app->protocols->update((int)$entry['id'], $subject, $documentId);

        $this->applyOfficialDocumentName($entry);

        return $entry;
    }

    private function applyOfficialDocumentName(array $entry): void
    {
        $documentId = (int)($entry['document_id'] ?? 0);
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
}
