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
        $this->app->antiBot->validate($data);

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

    public function sharedAttachment(Request $request, array $params): FileResponse
    {
        $attachment = $this->app->reportAttachments->findById((int)$params['id']);
        if ($attachment === null) {
            throw new HttpException(404, 'Allegato non trovato.');
        }

        $reportId = (int)$request->query('report_id', 0);
        $signature = (string)$request->query('signature', '');
        if ($reportId !== (int)$attachment['report_id'] || !$this->validSharedAttachmentSignature($attachment, $signature)) {
            throw new HttpException(403, 'Link allegato non valido.');
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
            $document = $this->approvedDocument($report, (int)$user['id'], $this->appBaseUrl());
            $updated = $this->app->reports->attachDocument((int)$report['id'], (int)$document['id']);
            $protocol = $this->app->protocols->findActiveByDocumentId((int)$document['id'])
                ?? $this->app->protocols->create('IN', 'SEG', (string)$report['subject'], (int)$user['id']);
            $protocol = $this->app->protocols->update((int)$protocol['id'], (string)$report['subject'], (int)$document['id']);
            $document = $this->applyOfficialDocumentName($document, $protocol);
            $document = $this->rewriteApprovedDocument($document, $report, $protocol, $this->appBaseUrl());
            $updated['protocol_number'] = $protocol['protocol_number'];
        }

        $this->app->activityLogs->write((int)$user['id'], 'reports.moderate', [
            'section' => 'reports',
            'report_id' => $report['id'],
            'status' => $status,
            'protocol_number' => $protocol['protocol_number'] ?? null,
        ]);

        return Response::json(['data' => ['report' => $updated, 'document' => $document, 'protocol' => $protocol]]);
    }

    public function show(Request $request, array $params): Response
    {
        $this->app->auth->requirePermission($request, 'reports.moderate');
        $report = $this->findReport((int)$params['id']);
        $report['attachments'] = $this->app->reportAttachments->forReport((int)$report['id']);

        return Response::json(['data' => $report]);
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

    private function approvedDocument(array $report, int $userId, string $baseUrl): array
    {
        if (!empty($report['document_id'])) {
            $document = $this->app->documents->findById((int)$report['document_id']);
            if ($document !== null) {
                return $document;
            }
        }

        $attachments = array_map(function (array $attachment) use ($baseUrl): array {
            $path = $this->app->reportAttachmentStorage->path((string)$attachment['stored_name']);
            $attachment['path'] = $path;
            if (str_starts_with((string)$attachment['mime_type'], 'image/') && is_file($path)) {
                $attachment['kind'] = 'image';
                return $attachment;
            }

            $attachment['kind'] = 'video';
            $attachment['shared_url'] = $this->sharedAttachmentUrl($attachment, $baseUrl);
            return $attachment;
        }, $this->app->reportAttachments->forReport((int)$report['id']));
        $html = $this->app->reportService->html(
            (string)$report['subject'],
            (string)$report['message'],
            (string)($report['contact'] ?? ''),
            (string)$report['tracking_code'],
            $attachments
        );
        $stored = $this->app->documentStorage->storeGeneratedPdf(
            $html,
            'segnalazione-' . $report['tracking_code'] . '.html',
            'segnalazioni',
            fn (string $pdfPath) => $this->app->reportPdf->write($pdfPath, $report, $attachments, null, null)
        );
        $document = $this->app->documents->create($stored + [
            'visibility' => 'rsu',
            'uploaded_by' => $userId,
        ]);

        $signature = $this->app->documentSignature->sign($document);
        $verifyUrl = $baseUrl . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode($signature);
        $document = $this->app->documents->updateSignature((int)$document['id'], $signature);
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $this->app->reportPdf->write($pdfPath, $report, $attachments, $signature, $verifyUrl, (string)$document['id'], null);

        return $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
    }

    private function rewriteApprovedDocument(array $document, array $report, array $protocol, string $baseUrl): array
    {
        $attachments = array_map(function (array $attachment) use ($baseUrl): array {
            $path = $this->app->reportAttachmentStorage->path((string)$attachment['stored_name']);
            $attachment['path'] = $path;
            if (str_starts_with((string)$attachment['mime_type'], 'image/') && is_file($path)) {
                $attachment['kind'] = 'image';
                return $attachment;
            }

            $attachment['kind'] = 'video';
            $attachment['shared_url'] = $this->sharedAttachmentUrl($attachment, $baseUrl);
            return $attachment;
        }, $this->app->reportAttachments->forReport((int)$report['id']));

        $signature = (string)$document['signature'];
        $verifyUrl = $baseUrl . '/ui/document-verify.html?id=' . (int)$document['id'] . '&sig=' . urlencode($signature);
        $pdfPath = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
        $this->app->reportPdf->write(
            $pdfPath,
            array_replace($report, ['created_at' => $protocol['created_at']]),
            $attachments,
            $signature,
            $verifyUrl,
            null,
            (string)$protocol['protocol_number']
        );

        return $this->app->documents->updatePdfMetadata((int)$document['id'], filesize($pdfPath), hash_file('sha256', $pdfPath));
    }

    private function applyOfficialDocumentName(array $document, array $protocol): array
    {
        $publicPath = $this->app->protocolDocumentName->publicPath(
            (string)$document['category'],
            (string)$protocol['protocol_number']
        );
        $this->app->protocolDocumentName->move(
            $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']),
            $this->app->documentStorage->pdfPath($publicPath)
        );

        return $this->app->documents->updatePublicPath((int)$document['id'], $publicPath);
    }

    private function sharedAttachmentUrl(array $attachment, string $baseUrl): string
    {
        $id = (int)$attachment['id'];
        $reportId = (int)$attachment['report_id'];
        $signature = $this->sharedAttachmentSignature($attachment);

        return $baseUrl . '/api/v1/reports/attachments/' . $id . '/shared?report_id=' . $reportId . '&signature=' . $signature;
    }

    private function sharedAttachmentSignature(array $attachment): string
    {
        return hash_hmac(
            'sha256',
            implode('|', [
                'report-attachment',
                (string)$attachment['id'],
                (string)$attachment['report_id'],
                (string)$attachment['stored_name'],
                (string)$attachment['checksum_sha256'],
            ]),
            $this->sharedAttachmentSecret()
        );
    }

    private function validSharedAttachmentSignature(array $attachment, string $signature): bool
    {
        return hash_equals($this->sharedAttachmentSignature($attachment), $signature);
    }

    private function sharedAttachmentSecret(): string
    {
        $secret = trim((string)env_value('MYRSU_SIGNING_SECRET', ''));
        return $secret !== '' ? $secret : hash('sha256', 'MYRSU_REPORT_ATTACHMENT_V1');
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
