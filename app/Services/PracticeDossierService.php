<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\HttpException;
use App\Repositories\PracticeArchiveRepository;
use App\Repositories\PracticeTimelineRepository;

final class PracticeDossierService
{
    private readonly PracticeArchiveRepository $archive;
    private array $sources = [];

    public function __construct(private readonly Application $app)
    {
        $this->archive = new PracticeArchiveRepository($app->configurationContext->repository->pdo);
    }

    public function timeline(int $configuration, int $practice, array $user): array
    {
        $links = $this->archive->links($configuration, $practice);
        if ($links === []) return [];
        $owner = $this->source($configuration)->practices->findById($practice);
        if ($owner === null) return [];
        $this->app->practiceAccess->assert($owner, $user);
        $items = [];
        $visited = [];
        foreach ($links as $link) $this->collect((int)$link['source_configuration_id'], (int)$link['source_practice_id'], $user, $items, $visited);
        foreach ($items as &$item) {
            $item['archive_owner_configuration_id'] = $configuration;
            $item['archive_owner_practice_id'] = $practice;
        }
        unset($item);
        usort($items, static fn (array $left, array $right): int => strcmp((string)$right['event_at'], (string)$left['event_at']));
        return array_values($items);
    }

    public function accessible(array $user): array
    {
        if (!$this->archive->installed()) return [];
        if (!array_intersect($this->app->roles->rolesForUser((int)$user['id']), ['admin', 'delegato', 'rls'])) return [];
        $items = [];
        foreach ($this->owners() as [$configuration, $practice]) {
            $owner = $this->source($configuration)->practices->findById($practice);
            if ($owner === null || !$this->app->practiceAccess->allows($owner, $user)) continue;
            foreach ($this->timeline($configuration, $practice, $user) as $item) $items[$item['archive_source_configuration_id'] . ':' . $item['type'] . ':' . $item['id']] = $item;
        }
        return array_values($items);
    }

    public function accessibleDocuments(array $user): array
    {
        if (!$this->archive->installed()) return [];
        $items = [];
        $visited = [];
        foreach ($this->owners() as [$configuration, $practice]) {
            if ($this->source($configuration)->practices->findById($practice) === null) continue;
            foreach ($this->archive->links($configuration, $practice) as $link) {
                $this->collectDocuments((int)$link['source_configuration_id'], (int)$link['source_practice_id'], $user, $items, $visited);
            }
        }
        return $this->documents(array_values($items));
    }

    public function documents(array $items): array
    {
        $documents = [];
        foreach ($items as $item) {
            if (empty($item['document_id'])) continue;
            $source = $this->source((int)$item['archive_source_configuration_id']);
            $row = $source->documents->findById((int)$item['document_id']);
            if ($row === null) continue;
            $documents[$source->configuration['id'] . ':' . $row['id']] = ['id' => (int)$row['id'], 'type' => 'document', 'name' => $row['original_name'], 'code' => '', 'status' => $row['conversion_status'], 'source_configuration_id' => (int)$source->configuration['id'], 'source_configuration_name' => $source->configuration['name']];
        }
        return array_values($documents);
    }

    private function source(int $configuration): ArchiveSourceService
    {
        if (!isset($this->sources[$configuration])) {
            $row = $this->app->configurationContext->repository->find($configuration) ?? throw new HttpException(409, 'Origine del fascicolo non disponibile.');
            $this->sources[$configuration] = new ArchiveSourceService($this->app, $row);
        }
        return $this->sources[$configuration];
    }

    private function owners(): array
    {
        $current = (int)($this->app->configurationContext->current['id'] ?? 0);
        $owners = [];
        foreach ($this->archive->links($current) as $link) $owners[$current . ':' . $link['practice_id']] = [$current, (int)$link['practice_id']];
        $statement = $this->archive->pdo->prepare("SELECT source_configuration_id, entity_id FROM archive_shares WHERE target_configuration_id = ? AND entity_type = 'practice'");
        $statement->execute([$current]);
        foreach ($statement->fetchAll() as $share) $owners[$share['source_configuration_id'] . ':' . $share['entity_id']] = [(int)$share['source_configuration_id'], (int)$share['entity_id']];
        return array_values($owners);
    }

    private function collectDocuments(int $configuration, int $practice, array $user, array &$items, array &$visited): void
    {
        $key = $configuration . ':' . $practice;
        if (isset($visited[$key])) return;
        if (count($visited) >= 100) throw new HttpException(409, 'Catena del fascicolo troppo estesa.');
        $visited[$key] = true;
        $source = $this->source($configuration);
        foreach ((new PracticeTimelineRepository($source->pdo))->documentIds($practice) as $id) {
            $document = $source->documents->findById($id);
            if ($document !== null && $source->canReadDocument($document, $user)) {
                $items[$configuration . ':' . $id] = ['document_id' => $id, 'archive_source_configuration_id' => $configuration];
            }
        }
        foreach ($this->archive->links($configuration, $practice) as $link) $this->collectDocuments((int)$link['source_configuration_id'], (int)$link['source_practice_id'], $user, $items, $visited);
    }

    private function collect(int $configuration, int $practice, array $user, array &$items, array &$visited): void
    {
        $key = $configuration . ':' . $practice;
        if (isset($visited[$key])) return;
        if (count($visited) >= 100) throw new HttpException(409, 'Catena del fascicolo troppo estesa.');
        $visited[$key] = true;
        $source = $this->source($configuration);
        foreach ((new PracticeTimelineRepository($source->pdo))->forPractice($practice) as $item) {
            $permission = ['comment' => 'comments.moderate', 'report' => 'reports.moderate', 'attachment' => 'reports.moderate', 'protocol' => 'protocol.view'][$item['type']] ?? null;
            if ($permission !== null && !$this->app->roles->userHasPermission((int)$user['id'], $permission)) continue;
            if ($item['type'] === 'attachment') {
                $statement = $source->pdo->prepare('SELECT r.document_id FROM report_attachments a JOIN reports r ON r.id = a.report_id WHERE a.id = ?');
                $statement->execute([$item['id']]);
                $parentDocument = $statement->fetchColumn();
                if ($parentDocument) {
                    $document = $source->documents->findById((int)$parentDocument);
                    if ($document === null || !$source->canReadDocument($document, $user)) continue;
                }
            }
            if (!empty($item['document_id'])) {
                $document = $source->documents->findById((int)$item['document_id']);
                if ($document === null || !$source->canReadDocument($document, $user)) continue;
            }
            $item['archive_source_configuration_id'] = $configuration;
            $item['archive_source_configuration_name'] = $source->configuration['name'];
            $item['archive_origin_editable'] = in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true);
            $item['archive_attachments'] = [];
            if (in_array($item['type'], ['report', 'comment'], true)) {
                $table = $item['type'] === 'report' ? 'reports' : 'document_comments';
                $statement = $source->pdo->prepare('SELECT reply FROM ' . $table . ' WHERE id = ?');
                $statement->execute([$item['id']]);
                $reply = $statement->fetchColumn();
                if ($reply) $item['summary'] .= "\n\nRisposta: " . $reply;
            }
            if ($item['type'] === 'report') {
                $statement = $source->pdo->prepare('SELECT id, original_name FROM report_attachments WHERE report_id = ? ORDER BY id');
                $statement->execute([$item['id']]);
                $item['archive_attachments'] = $statement->fetchAll();
            } elseif ($item['type'] === 'attachment') {
                $item['archive_attachments'] = [['id' => $item['id'], 'original_name' => $item['title']]];
            }
            $items[$configuration . ':' . $item['type'] . ':' . $item['id']] = $item;
        }
        foreach ($this->archive->links($configuration, $practice) as $link) $this->collect((int)$link['source_configuration_id'], (int)$link['source_practice_id'], $user, $items, $visited);
    }
}
