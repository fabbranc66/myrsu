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
        $this->log((int)$user['id'], 'emails.create', $email);

        return Response::json(['data' => ['email' => $email]], 201);
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
            'subject' => mb_substr(trim((string)$data['subject']), 0, 255),
            'body' => trim((string)$data['body']),
            'message_at' => trim((string)$data['message_at']),
            'practice_id' => isset($data['practice_id']) && $data['practice_id'] !== '' ? (int)$data['practice_id'] : null,
            'contact_id' => isset($data['contact_id']) && $data['contact_id'] !== '' ? (int)$data['contact_id'] : null,
        ];
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
