<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Services\PracticeDossierService;

final class PracticeController
{
    public function __construct(private readonly Application $app)
    {
    }

    public function index(Request $request): Response
    {
        $user = $this->requireLinkRole($request);
        $items = $request->query('scope') === 'all'
            ? $this->app->practices->all()
            : $this->app->practices->allOpen();
        return Response::json(['data' => $this->app->practiceAccess->filter($items, $user)]);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireLinkRole($request);
        $practice = $this->app->practiceService->create($request->all(), (int)$user['id']);
        $this->log((int)$user['id'], 'practices.create', $practice);
        return Response::json(['data' => ['practice' => $practice]], 201);
    }

    public function assignees(Request $request): Response
    {
        $this->requireLinkRole($request);
        return Response::json(['data' => $this->app->users->operators()]);
    }

    public function show(Request $request, array $params): Response
    {
        $user = $this->requireLinkRole($request);
        $practice = $this->findPractice((int)$params['id'], $user);
        $timeline = array_merge($this->app->practiceTimeline->forPractice((int)$practice['id']), (new PracticeDossierService($this->app))->timeline((int)($this->app->configurationContext->current['id'] ?? 0), (int)$practice['id'], $user));
        $timeline = array_values(array_filter($timeline, function (array $item) use ($user): bool {
            if (isset($item['archive_source_configuration_id']) || empty($item['document_id'])) return true;
            $document = $this->app->documents->findById((int)$item['document_id']);
            return $document !== null && $this->app->documentFolderAccess->allowsDocument($document, $user);
        }));
        usort($timeline, static fn (array $left, array $right): int => strcmp((string)$right['event_at'], (string)$left['event_at']));
        return Response::json(['data' => [
            'practice' => $practice,
            'timeline' => $timeline,
        ]]);
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->requireLinkRole($request);
        $current = $this->findPractice((int)$params['id'], $user);
        $practice = $this->app->practiceService->update($current, $request->all());
        $this->log((int)$user['id'], 'practices.update', $practice);
        return Response::json(['data' => ['practice' => $practice]]);
    }

    public function addNote(Request $request, array $params): Response
    {
        $user = $this->requireLinkRole($request);
        $practice = $this->findPractice((int)$params['id'], $user);
        $data = $request->all();
        Validator::required($data, ['body']);
        $body = trim((string)$data['body']);
        if ($body === '') throw new HttpException(422, 'Testo nota obbligatorio.');
        $note = $this->app->practiceNotes->create((int)$practice['id'], $body, (int)$user['id']);
        $this->app->activityLogs->write((int)$user['id'], 'practices.note_create', [
            'section' => 'practices',
            'practice_id' => $practice['id'],
            'note_id' => $note['id'],
        ]);
        return Response::json(['data' => ['note' => $note]], 201);
    }

    public function addCcnlLink(Request $request, array $params): Response
    {
        $user = $this->requireLinkRole($request);
        $practice = $this->findPractice((int)$params['id'], $user);
        $data = $request->all();
        Validator::required($data, ['block_code', 'block_title', 'section_title', 'source_path', 'excerpt']);

        $link = $this->app->practiceCcnlLinks->create((int)$practice['id'], [
            'block_code' => substr(trim((string)$data['block_code']), 0, 10),
            'block_title' => substr(trim((string)$data['block_title']), 0, 255),
            'section_title' => substr(trim((string)$data['section_title']), 0, 255),
            'source_path' => substr(trim((string)$data['source_path']), 0, 255),
            'excerpt' => trim((string)$data['excerpt']),
        ], (int)$user['id']);

        $this->app->activityLogs->write((int)$user['id'], 'practices.ccnl_link', [
            'section' => 'practices',
            'practice_id' => $practice['id'],
            'ccnl_link_id' => $link['id'],
            'ccnl_section' => $link['section_title'],
        ]);

        return Response::json(['data' => ['link' => $link]], 201);
    }

    public function showCcnlLink(Request $request, array $params): Response
    {
        $user = $this->requireLinkRole($request);
        $practice = $this->findPractice((int)$params['id'], $user);
        $link = $this->app->practiceCcnlLinks->find((int)$params['linkId']);
        if (!$link || (int)$link['practice_id'] !== (int)$practice['id']) {
            throw new HttpException(404, 'Riferimento CCNL non trovato.');
        }

        return Response::json(['data' => ['link' => $link]]);
    }

    public function deleteCcnlLink(Request $request, array $params): Response
    {
        $user = $this->requireLinkRole($request);
        $practice = $this->findPractice((int)$params['id'], $user);
        $deleted = $this->app->practiceCcnlLinks->delete((int)$practice['id'], (int)$params['linkId']);

        $this->app->activityLogs->write((int)$user['id'], 'practices.ccnl_unlink', [
            'section' => 'practices',
            'practice_id' => $practice['id'],
            'ccnl_link_id' => (int)$params['linkId'],
        ]);

        return Response::json(['data' => ['deleted' => $deleted]]);
    }

    public function link(Request $request): Response
    {
        $user = $this->requireLinkRole($request);
        $data = $request->all();
        Validator::required($data, ['practice_id', 'entity_type', 'entity_id']);
        $this->findPractice((int)$data['practice_id'], $user);

        $this->app->practiceLinks->link(
            (int)$data['practice_id'],
            (string)$data['entity_type'],
            (int)$data['entity_id'],
            (int)$user['id']
        );
        $this->app->activityLogs->write((int)$user['id'], 'practices.link', [
            'section' => 'practices',
            'practice_id' => (int)$data['practice_id'],
            'entity_type' => (string)$data['entity_type'],
            'entity_id' => (int)$data['entity_id'],
        ]);

        return Response::json(['data' => ['linked' => true]]);
    }

    public function unlink(Request $request): Response
    {
        $user = $this->requireLinkRole($request);
        $data = $request->all();
        Validator::required($data, ['practice_id', 'entity_type', 'entity_id']);
        $this->findPractice((int)$data['practice_id'], $user);

        $unlinked = $this->app->practiceLinks->unlink(
            (int)$data['practice_id'],
            (string)$data['entity_type'],
            (int)$data['entity_id']
        );

        $this->app->activityLogs->write((int)$user['id'], 'practices.unlink', [
            'section' => 'practices',
            'practice_id' => (int)$data['practice_id'],
            'entity_type' => (string)$data['entity_type'],
            'entity_id' => (int)$data['entity_id'],
        ]);

        return Response::json(['data' => ['unlinked' => $unlinked]]);
    }

    private function requireLinkRole(Request $request): array
    {
        $user = $this->app->auth->requireUser($request);
        $roles = $this->app->roles->rolesForUser((int)$user['id']);
        if (!array_intersect($roles, ['admin', 'delegato', 'rls'])) {
            throw new HttpException(403, 'Permesso insufficiente.');
        }

        return $user;
    }

    private function findPractice(int $id, array $user): array
    {
        $practice = $this->app->practices->findById($id);
        if ($practice === null) {
            throw new HttpException(404, 'Pratica non trovata.');
        }
        $this->app->practiceAccess->assert($practice, $user);
        return $practice;
    }

    private function log(int $userId, string $action, array $practice): void
    {
        $this->app->activityLogs->write($userId, $action, [
            'section' => 'practices',
            'practice_id' => $practice['id'],
            'title' => $practice['title'],
            'status' => $practice['status'],
        ]);
    }

}
