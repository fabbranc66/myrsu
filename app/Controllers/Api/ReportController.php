<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class ReportController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->app->auth->requirePermission($request, 'reports.moderate');
        $status = (string)$request->query('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            throw new HttpException(422, 'Stato non valido.');
        }

        $reports = array_map(function (array $report): array {
            $report['attachments'] = $this->app->reportAttachments->forReport((int)$report['id']);
            return $report;
        }, $this->app->reports->all($status));

        return Response::json(['data' => $reports]);
    }

    public function stats(Request $request): Response
    {
        $this->app->auth->requirePermission($request, 'reports.moderate');

        return Response::json(['data' => [
            'pending' => $this->app->reports->countByStatus('pending'),
        ]]);
    }

    public function store(Request $request): Response
    {
        $data = $request->all();
        Validator::required($data, ['subject', 'message']);

        $user = $this->app->auth->user($request);
        $trackingCode = $this->app->reportService->trackingCode();
        $report = $this->app->reports->create([
            'tracking_code' => $trackingCode,
            'subject' => trim((string)$data['subject']),
            'message' => trim((string)$data['message']),
            'contact' => trim((string)($data['contact'] ?? '')),
            'user_id' => $user['id'] ?? null,
            'origin' => $user === null ? 'anonymous' : 'member',
        ]);
        $attachments = [];
        foreach ($this->uploadedAttachments() as $file) {
            $attachments[] = $this->app->reportAttachments->create(
                (int)$report['id'],
                $this->app->reportAttachmentStorage->store($file)
            );
        }

        $this->app->activityLogs->write($user['id'] ?? null, 'reports.create', [
            'section' => 'reports',
            'report_id' => $report['id'],
            'attachments' => count($attachments),
        ]);

        return Response::json(['data' => ['report' => $report, 'attachments' => $attachments]], 201);
    }

    public function attachment(Request $request, array $params): FileResponse
    {
        $this->app->auth->requirePermission($request, 'reports.moderate');
        $attachment = $this->app->reportAttachments->findById((int)$params['id']);
        if ($attachment === null) {
            throw new HttpException(404, 'Allegato non trovato.');
        }

        $path = $this->app->reportAttachmentStorage->path((string)$attachment['stored_name']);
        if (!is_file($path)) {
            throw new HttpException(404, 'File non trovato.');
        }

        return new FileResponse($path, (string)$attachment['original_name'], (string)$attachment['mime_type'], true);
    }

    public function moderate(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'reports.moderate');
        $data = $request->all();
        Validator::required($data, ['status']);

        $status = (string)$data['status'];
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new HttpException(422, 'Stato non valido.');
        }

        $report = $this->findReport((int)$params['id']);
        $updated = $this->app->reports->moderate((int)$report['id'], $status, trim((string)($data['reply'] ?? '')));
        $protocol = null;
        $document = null;
        if ($status === 'approved') {
            $document = $this->approvedDocument($report, (int)$user['id']);
            $updated = $this->app->reports->attachDocument((int)$report['id'], (int)$document['id']);
            $protocol = $this->app->protocols->findActiveByDocumentId((int)$document['id'])
                ?? $this->app->protocols->create('IN', 'SEG', (string)$report['subject'], (int)$user['id']);
            $protocol = $this->app->protocols->update((int)$protocol['id'], (string)$report['subject'], (int)$document['id']);
        }

        $this->app->activityLogs->write((int)$user['id'], 'reports.moderate', [
            'section' => 'reports',
            'report_id' => $report['id'],
            'status' => $status,
            'protocol_number' => $protocol['protocol_number'] ?? null,
        ]);

        return Response::json(['data' => ['report' => $updated, 'document' => $document, 'protocol' => $protocol]]);
    }

    private function findReport(int $id): array
    {
        $report = $this->app->reports->findById($id);
        if ($report === null) {
            throw new HttpException(404, 'Segnalazione non trovata.');
        }

        return $report;
    }

    private function uploadedAttachments(): array
    {
        $files = $_FILES['attachments'] ?? null;
        if (!is_array($files) || !isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        foreach ($files['name'] as $index => $name) {
            if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }

    private function signDocument(array $document): array
    {
        $document = $this->app->documents->updateSignature((int)$document['id'], $this->app->documentSignature->sign($document));
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $this->app->documentVerificationPage->append($pdfPath, $document, (string)$document['signature']);

        return $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
    }

    private function approvedDocument(array $report, int $userId): array
    {
        if (!empty($report['document_id'])) {
            $document = $this->app->documents->findById((int)$report['document_id']);
            if ($document !== null) {
                return $document;
            }
        }

        $html = $this->app->reportService->html(
            (string)$report['subject'],
            (string)$report['message'],
            (string)($report['contact'] ?? ''),
            (string)$report['tracking_code']
        );
        $stored = $this->app->documentStorage->storeHtml($html, 'segnalazione-' . $report['tracking_code'] . '.html', 'segnalazioni');
        $document = $this->app->documents->create($stored + [
            'visibility' => 'rsu',
            'uploaded_by' => $userId,
        ]);

        return $this->signDocument($document);
    }
}
