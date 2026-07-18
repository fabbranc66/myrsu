<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class DocumentCommentController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $this->app->auth->requirePermission($request, 'comments.moderate');
        $status = (string)$request->query('status', 'pending');
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            throw new HttpException(422, 'Stato non valido.');
        }

        return Response::json(['data' => $this->app->documentComments->grouped($status)]);
    }

    public function stats(Request $request): Response
    {
        $this->app->auth->requirePermission($request, 'comments.moderate');

        return Response::json(['data' => ['pending' => $this->app->documentComments->countByStatus('pending')]]);
    }

    public function approvedByDocument(Request $request, array $params): Response
    {
        $this->app->auth->requirePermission($request, 'comments.moderate');
        $documentId = (int)$params['id'];
        if ($this->app->documents->findById($documentId) === null) {
            throw new HttpException(404, 'Documento non trovato.');
        }

        return Response::json(['data' => $this->app->documentComments->publicForDocument($documentId)]);
    }

    public function publicIndex(Request $request, array $params): Response
    {
        $document = $this->publicDocument((int)$params['id']);

        return Response::json(['data' => $this->app->documentComments->publicForDocument((int)$document['id'])]);
    }

    public function store(Request $request, array $params): Response
    {
        $document = $this->publicDocument((int)$params['id']);
        $data = $request->all();
        Validator::required($data, ['message']);
        $this->app->antiBot->validate($data);
        $user = $this->app->auth->user($request);

        $comment = $this->app->documentComments->create([
            'document_id' => (int)$document['id'],
            'subject' => (string)$document['original_name'],
            'message' => trim((string)$data['message']),
            'contact' => trim((string)($data['contact'] ?? '')),
            'user_id' => $user['id'] ?? null,
            'origin' => $user === null ? 'anonymous' : 'member',
        ]);

        $this->app->activityLogs->write($user['id'] ?? null, 'comments.create', [
            'section' => 'comments',
            'comment_id' => $comment['id'],
            'document_id' => $document['id'],
        ]);

        return Response::json(['data' => ['comment' => $comment]], 201);
    }

    public function moderate(Request $request, array $params): Response
    {
        $user = $this->app->auth->requirePermission($request, 'comments.moderate');
        $data = $request->all();
        Validator::required($data, ['status']);
        $status = (string)$data['status'];
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
            throw new HttpException(422, 'Stato non valido.');
        }

        $comment = $this->findComment((int)$params['id']);
        $reply = trim((string)($data['reply'] ?? ''));
        $updated = $this->app->documentComments->moderate((int)$comment['id'], $status, $reply);
        $this->app->activityLogs->write((int)$user['id'], 'comments.moderate', [
            'section' => 'comments',
            'comment_id' => $comment['id'],
            'document_id' => $comment['document_id'],
            'status' => $status,
            'reply' => $reply,
            'reply_changed' => $reply !== (string)($comment['reply'] ?? ''),
        ]);

        return Response::json(['data' => ['comment' => $updated]]);
    }

    private function publicDocument(int $id): array
    {
        $document = $this->app->documents->findById($id);
        if ($document === null || (string)$document['visibility'] !== 'public' || !in_array((string)$document['category'], ['documenti', 'comunicati'], true)) {
            throw new HttpException(404, 'Documento non trovato.');
        }

        return $document;
    }

    private function findComment(int $id): array
    {
        $comment = $this->app->documentComments->findById($id);
        if ($comment === null) {
            throw new HttpException(404, 'Commento non trovato.');
        }

        return $comment;
    }
}
