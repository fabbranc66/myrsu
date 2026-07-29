<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class EmailController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->requireOperator($request);
        return Response::json(['data' => $this->app->emails->all([
            'direction' => (string)($request->query('direction') ?? ''),
            'read_status' => (string)($request->query('read_status') ?? ''),
            'handling_status' => (string)($request->query('handling_status') ?? ''),
            'practice_id' => (string)($request->query('practice_id') ?? ''),
        ])]);
    }

    public function show(Request $request, array $params): Response
    {
        $this->requireOperator($request);
        $email = $this->findEmail((int)$params['id']);

        return Response::json(['data' => [
            'email' => $email,
            'attachments' => $this->app->emails->attachments((int)$email['id']),
            'notes' => $this->app->emails->notes((int)$email['id']),
        ]]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $email = $this->app->emails->create($this->validated($request->all()) + ['created_by' => (int)$user['id']]);
        $this->app->emailContacts->storeMissing($email, (int)$user['id']);
        $this->log((int)$user['id'], 'emails.create', $email);

        return Response::json(['data' => ['email' => $email]], 201);
    }

    public function send(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $data = $this->validatedSend($request->all(), $user);
        $email = $this->app->emails->create($data + ['created_by' => (int)$user['id']]);
        $this->app->emailContacts->storeMissing($email, (int)$user['id']);
        $attachments = array_merge(
            $this->storeUploadedAttachments((int)$email['id']),
            $this->storeDocumentAttachments((int)$email['id'], $request->all())
        );
        $this->app->emailSmtp->send($email, array_map(fn (array $item): array => [
            'path' => $this->attachmentPath($item),
            'name' => $item['original_name'],
            'mime_type' => $item['mime_type'] ?: 'application/octet-stream',
        ], $attachments));
        $this->log((int)$user['id'], 'emails.send', $email);

        return Response::json(['data' => ['email' => $email, 'attachments' => $attachments]], 201);
    }

    public function sync(Request $request): Response
    {
        $user = $this->requireOperator($request);
        $result = $this->app->emailImapSync->sync((int)$user['id']);
        $this->app->activityLogs->write((int)$user['id'], 'emails.sync', [
            'section' => 'emails',
            'imported' => $result['imported'],
            'checked' => $result['checked'],
        ]);

        return Response::json(['data' => $result]);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $this->findEmail((int)$params['id']);
        $email = $this->app->emails->update((int)$params['id'], $this->validated($request->all()));
        $this->app->emailContacts->storeMissing($email ?? [], (int)$user['id']);
        $this->log((int)$user['id'], 'emails.update', $email);

        return Response::json(['data' => ['email' => $email]]);
    }

    public function manage(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $this->findEmail((int)$params['id']);
        $email = $this->app->emails->manage((int)$params['id'], (int)$user['id']);
        $this->log((int)$user['id'], 'emails.manage', $email);

        return Response::json(['data' => ['email' => $email]]);
    }

    public function linkPractice(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $data = $request->all();
        Validator::required($data, ['practice_id']);
        $email = $this->findEmail((int)$params['id']);
        $practice = $this->app->practices->findById((int)$data['practice_id']);
        if ($practice === null) throw new HttpException(404, 'Pratica non trovata.');
        $updated = $this->app->emails->linkPractice((int)$email['id'], (int)$practice['id']);
        $this->log((int)$user['id'], 'emails.link_practice', $updated);

        return Response::json(['data' => ['email' => $updated]]);
    }

    public function unlinkPractice(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $email = $this->findEmail((int)$params['id']);
        $updated = $this->app->emails->linkPractice((int)$email['id'], null);
        $this->log((int)$user['id'], 'emails.unlink_practice', $updated);

        return Response::json(['data' => ['email' => $updated]]);
    }

    public function addNote(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $email = $this->findEmail((int)$params['id']);
        $data = $request->all();
        Validator::required($data, ['body']);
        $body = trim((string)$data['body']);
        if ($body === '') throw new HttpException(422, 'Nota obbligatoria.');
        $note = $this->app->emails->addNote((int)$email['id'], $body, (int)$user['id']);
        $this->log((int)$user['id'], 'emails.note_create', $email);

        return Response::json(['data' => ['note' => $note]], 201);
    }

    public function destroy(Request $request, array $params): Response
    {
        $user = $this->requireOperator($request);
        $email = $this->findEmail((int)$params['id']);
        $this->deleteAttachmentFiles((int)$email['id']);
        $this->app->emails->delete((int)$email['id']);
        $this->log((int)$user['id'], 'emails.delete', $email);

        return Response::json(['data' => ['deleted' => true]]);
    }

    public function attachment(Request $request, array $params): Response
    {
        return $this->streamAttachment($request, $params, false);
    }

    public function downloadAttachment(Request $request, array $params): Response
    {
        return $this->streamAttachment($request, $params, true);
    }

    private function streamAttachment(Request $request, array $params, bool $download): Response
    {
        $this->requireOperator($request);
        $attachment = $this->app->emails->findAttachment((int)$params['id']);
        if ($attachment === null) throw new HttpException(404, 'Allegato non trovato.');

        $basePath = realpath(dirname(__DIR__, 3));
        $path = realpath($basePath . '/' . $attachment['storage_path'] . '/' . $attachment['stored_name']);
        $root = realpath($basePath . '/storage/private/email-attachments');
        if ($path === false || $root === false || !str_starts_with($path, $root) || !is_file($path)) {
            throw new HttpException(404, 'File allegato non trovato.');
        }

        header('Content-Type: ' . ((string)$attachment['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . str_replace('"', '', (string)$attachment['original_name']) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . (string)filesize($path));
        readfile($path);
        exit;
    }

    private function validated(array $data): array
    {
        Validator::required($data, ['direction', 'subject', 'body', 'message_at']);
        if (!in_array((string)$data['direction'], ['incoming', 'outgoing', 'draft'], true)) {
            throw new HttpException(422, 'Tipo e-mail non valido.');
        }
        foreach (['read_status' => ['unread', 'read'], 'handling_status' => ['new', 'in_progress', 'managed', 'archived']] as $field => $allowed) {
            if (isset($data[$field]) && !in_array((string)$data[$field], $allowed, true)) {
                throw new HttpException(422, 'Stato e-mail non valido.');
            }
        }
        if (trim((string)$data['subject']) === '') throw new HttpException(422, 'Oggetto obbligatorio.');

        return [
            'direction' => (string)$data['direction'],
            'external_id' => null,
            'import_source' => 'manual',
            'read_status' => (string)($data['read_status'] ?? 'unread'),
            'handling_status' => (string)($data['handling_status'] ?? 'new'),
            'from_name' => trim((string)($data['from_name'] ?? '')) ?: null,
            'from_email' => trim((string)($data['from_email'] ?? '')) ?: null,
            'to_emails' => trim((string)($data['to_emails'] ?? '')) ?: null,
            'cc_emails' => trim((string)($data['cc_emails'] ?? '')) ?: null,
            'bcc_emails' => trim((string)($data['bcc_emails'] ?? '')) ?: null,
            'subject' => mb_substr(trim((string)$data['subject']), 0, 255),
            'body' => trim((string)$data['body']),
            'message_at' => trim((string)$data['message_at']),
            'practice_id' => isset($data['practice_id']) && $data['practice_id'] !== '' ? (int)$data['practice_id'] : null,
            'contact_id' => isset($data['contact_id']) && $data['contact_id'] !== '' ? (int)$data['contact_id'] : null,
        ];
    }

    private function validatedSend(array $data, array $user): array
    {
        Validator::required($data, ['to_emails', 'subject', 'body']);
        $to = trim(is_array($data['to_emails'] ?? null) ? implode(',', $data['to_emails']) : (string)$data['to_emails']);
        if ($this->validEmails($to) === []) throw new HttpException(422, 'Destinatario non valido.');
        $cc = trim(is_array($data['cc_emails'] ?? null) ? implode(',', $data['cc_emails']) : (string)($data['cc_emails'] ?? ''));
        if ($cc !== '' && $this->validEmails($cc) === []) throw new HttpException(422, 'CC non valido.');
        $bcc = trim(is_array($data['bcc_emails'] ?? null) ? implode(',', $data['bcc_emails']) : (string)($data['bcc_emails'] ?? ''));
        if ($bcc !== '' && $this->validEmails($bcc) === []) throw new HttpException(422, 'CCN non valido.');

        return [
            'direction' => 'outgoing',
            'external_id' => null,
            'import_source' => 'smtp',
            'read_status' => 'read',
            'handling_status' => 'managed',
            'from_name' => $user['name'] ?? null,
            'from_email' => trim((string)env_value('EMAIL_SMTP_FROM', env_value('EMAIL_SMTP_USER', env_value('EMAIL_IMAP_USER', '')))) ?: null,
            'to_emails' => implode(', ', $this->validEmails($to)),
            'cc_emails' => $cc !== '' ? implode(', ', $this->validEmails($cc)) : null,
            'bcc_emails' => $bcc !== '' ? implode(', ', $this->validEmails($bcc)) : null,
            'subject' => mb_substr(trim((string)$data['subject']), 0, 255),
            'body' => trim((string)$data['body']),
            'message_at' => date('Y-m-d H:i:s'),
            'practice_id' => isset($data['practice_id']) && $data['practice_id'] !== '' ? (int)$data['practice_id'] : null,
            'contact_id' => null,
        ];
    }

    private function validEmails(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $value) ?: []), fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }

    private function storeUploadedAttachments(int $emailId): array
    {
        $files = $this->uploadedFiles('attachments');
        $saved = [];
        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $saved[] = $this->saveAttachmentFile($emailId, (string)$file['name'], (string)$file['tmp_name'], true);
        }
        return $saved;
    }

    private function storeDocumentAttachments(int $emailId, array $data): array
    {
        $ids = $data['document_ids'] ?? [];
        if (!is_array($ids)) $ids = preg_split('/[,;]/', (string)$ids) ?: [];
        $saved = [];
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            if ($id <= 0) continue;
            $document = $this->app->documents->findById($id);
            if (
                $document === null
                || (string)$document['category'] !== 'documenti'
                || (string)$document['conversion_status'] !== 'ready'
            ) continue;
            $path = $this->app->documentStorage->pdfPath((string)$document['pdf_public_path']);
            if (!is_file($path)) continue;
            $saved[] = $this->saveAttachmentFile($emailId, basename((string)$document['pdf_public_path']), $path, false);
        }
        return $saved;
    }

    private function saveAttachmentFile(int $emailId, string $originalName, string $sourcePath, bool $uploaded): array
    {
        $basePath = realpath(dirname(__DIR__, 3)) ?: dirname(__DIR__, 3);
        $storagePath = 'storage/private/email-attachments/' . $emailId;
        $dir = $basePath . '/' . $storagePath;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $storedName = bin2hex(random_bytes(16)) . '-' . preg_replace('/[^a-zA-Z0-9._-]+/', '-', basename($originalName));
        $target = $dir . '/' . $storedName;
        $ok = $uploaded ? move_uploaded_file($sourcePath, $target) : copy($sourcePath, $target);
        if (!$ok) throw new HttpException(500, 'Salvataggio allegato fallito.');
        return $this->app->emails->addAttachment($emailId, [
            'original_name' => basename($originalName),
            'stored_name' => $storedName,
            'storage_path' => $storagePath,
            'mime_type' => mime_content_type($target) ?: 'application/octet-stream',
            'size_bytes' => filesize($target),
        ]);
    }

    private function uploadedFiles(string $key): array
    {
        if (!isset($_FILES[$key])) return [];
        $files = $_FILES[$key];
        if (!is_array($files['name'])) return [$files];
        $normalized = [];
        foreach ($files['name'] as $index => $name) {
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

    private function attachmentPath(array $attachment): string
    {
        $basePath = realpath(dirname(__DIR__, 3)) ?: dirname(__DIR__, 3);
        return $basePath . '/' . $attachment['storage_path'] . '/' . $attachment['stored_name'];
    }

    private function deleteAttachmentFiles(int $emailId): void
    {
        $basePath = realpath(dirname(__DIR__, 3));
        $dir = realpath($basePath . '/storage/private/email-attachments/' . $emailId);
        $root = realpath($basePath . '/storage/private/email-attachments');
        if ($dir === false || $root === false || !str_starts_with($dir, $root) || !is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) unlink($file);
        }
        @rmdir($dir);
    }

    private function requireOperator(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        if (!array_intersect($this->app->roles->rolesForUser((int)$user['id']), ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }
        return $user;
    }

    private function findEmail(int $id): array
    {
        $email = $this->app->emails->find($id);
        if ($email === null) throw new HttpException(404, 'E-mail non trovata.');
        return $email;
    }

    private function log(int $userId, string $action, ?array $email): void
    {
        $this->app->activityLogs->write($userId, $action, [
            'section' => 'emails',
            'email_id' => (int)($email['id'] ?? 0),
            'practice_id' => $email['practice_id'] ?? null,
            'subject' => $email['subject'] ?? '',
        ]);
    }
}
