<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\HttpException;
use App\Repositories\ArchiveShareRepository;

final class ArchiveSharingService
{
    public readonly ArchiveShareRepository $repository;
    private array $grants;
    private array $sources = [];
    private array $documentIds = [];
    private int $target;
    private ?array $dossier = null;
    private array $dossierDocuments = [];

    public function __construct(private readonly Application $app)
    {
        $this->target = (int)($app->configurationContext->current['id'] ?? 0);
        $this->repository = new ArchiveShareRepository($app->configurationContext->repository->pdo);
        $this->grants = $this->repository->forTarget($this->target);
    }

    public function source(int $id): ArchiveSourceService
    {
        if (!isset($this->sources[$id])) {
            $row = $this->app->configurationContext->repository->find($id) ?? throw new HttpException(404, 'Archivio d’origine non trovato.');
            $this->sources[$id] = new ArchiveSourceService($this->app, $row);
        }
        return $this->sources[$id];
    }

    public function catalogue(string $type, string $query, int $sourceId, int $page): array
    {
        $items = [];
        $warnings = [];
        $configurations = $this->app->configurationContext->repository->all();
        foreach ($configurations as $configuration) {
            $id = (int)$configuration['id'];
            if ($sourceId > 0 && $sourceId !== $id) continue;
            try {
                $source = $this->source($id);
                $rows = $type === 'practice' ? $source->practices->all() : $source->documents->all();
                foreach ($rows as $row) {
                    $item = $this->summary($source, $type, $row);
                    if ($query !== '' && mb_stripos($item['name'] . ' ' . $item['code'] . ' ' . $configuration['name'], $query) === false) continue;
                    $item['is_current'] = $id === $this->target;
                    $item['explicitly_shared'] = $this->direct($id, $type, (int)$row['id']);
                    $item['via_practice'] = $type === 'document' && in_array((int)$row['id'], $this->availableDocumentIds($id), true) && !$item['explicitly_shared'];
                    $items[] = $item;
                }
            } catch (HttpException $exception) {
                if ($exception->status() !== 409) throw $exception;
                $warnings[] = $configuration['name'] . ': schema da aggiornare.';
            }
        }
        $limit = 50;
        return ['items' => array_slice($items, ($page - 1) * $limit, $limit), 'total' => count($items), 'page' => $page, 'limit' => $limit, 'target' => $this->app->configurationContext->summary(), 'configurations' => array_map(static fn (array $row): array => ['id' => (int)$row['id'], 'name' => $row['name']], $configurations), 'warnings' => $warnings];
    }

    public function set(int $sourceId, string $type, int $id, bool $enabled, int $user): void
    {
        if ($sourceId === $this->target) throw new HttpException(422, 'Il contenuto appartiene già alla configurazione attiva.');
        $source = $this->source($sourceId);
        if ($enabled && ($type === 'practice' ? $source->practices->findById($id) : $source->documents->findById($id)) === null) throw new HttpException(404, 'Contenuto originale non trovato.');
        $this->repository->set($this->target, $sourceId, $type, $id, $enabled, $user);
    }

    public function shared(string $type, array $user): array
    {
        $items = [];
        foreach (array_unique(array_column($this->grants, 'source_configuration_id')) as $sourceId) {
            $source = $this->source((int)$sourceId);
            if ($type === 'practice') {
                foreach ($this->practiceIds((int)$sourceId, $user) as $id) {
                    $row = $source->practices->findById($id);
                    if ($row !== null) $items[] = $this->summary($source, 'practice', $row);
                }
            } else {
                foreach ($this->availableDocumentIds((int)$sourceId, $user) as $id) {
                    $row = $source->documents->findById($id);
                    if ($row !== null && $source->canReadDocument($row, $user)) $items[] = $this->summary($source, 'document', $row);
                }
            }
        }
        if ($type === 'document') {
            $items = array_merge($items, $this->dossierDocuments($user));
            $unique = [];
            foreach ($items as $item) $unique[$item['source_configuration_id'] . ':' . $item['id']] = $item;
            return array_values($unique);
        }
        return $items;
    }

    public function item(int $sourceId, string $type, int $id, array $user): array
    {
        $source = $this->source($sourceId);
        if ($type === 'document') {
            $document = $this->document($sourceId, $id, $user);
            $comments = $source->pdo->prepare("SELECT subject, message, reply, status, created_at FROM document_comments WHERE document_id = ?" . ($this->app->roles->userHasPermission((int)$user['id'], 'comments.moderate') ? '' : " AND status = 'approved'") . ' ORDER BY id');
            $comments->execute([$id]);
            return ['item' => $this->summary($source, 'document', $document), 'document' => array_intersect_key($document, array_flip(['original_name', 'category', 'visibility', 'conversion_status', 'pdf_size_bytes', 'signature', 'created_at'])), 'comments' => $comments->fetchAll(), 'protocols' => $this->protocols($source, [], [$id], $user)];
        }
        if ($sourceId !== $this->target && !$this->admin($user) && !$this->direct($sourceId, 'practice', $id)) throw new HttpException(403, 'Pratica non condivisa con la configurazione attiva.');
        $practice = $source->practices->findById($id) ?? throw new HttpException(404, 'Pratica originale non trovata.');
        $this->app->practiceAccess->assert($practice, $user);
        $documents = [];
        foreach ($source->documentIdsForPractice($id) as $documentId) {
            $document = $source->documents->findById($documentId);
            if ($document !== null && $source->canReadDocument($document, $user)) $documents[] = $this->summary($source, 'document', $document);
        }
        $dossier = new PracticeDossierService($this->app);
        $timeline = $dossier->timeline($sourceId, $id, $user);
        $documents = array_merge($documents, $dossier->documents($timeline));
        return ['item' => $this->summary($source, 'practice', $practice), 'practice' => $practice, 'notes' => $source->notesForPractice($id), 'documents' => $documents, 'timeline' => $timeline, 'protocols' => $this->protocols($source, [$id], $source->documentIdsForPractice($id), $user)];
    }

    public function document(int $sourceId, int $id, array $user): array
    {
        $dossierAccess = false;
        foreach ($this->dossierDocuments($user) as $document) {
            if ($document['source_configuration_id'] === $sourceId && $document['id'] === $id) $dossierAccess = true;
        }
        if ($sourceId !== $this->target && !$this->admin($user) && !$dossierAccess && !in_array($id, $this->availableDocumentIds($sourceId, $user), true)) throw new HttpException(403, 'Documento non condiviso con la configurazione attiva.');
        $source = $this->source($sourceId);
        $document = $source->documents->findById($id) ?? throw new HttpException(404, 'Documento originale non trovato.');
        if (!$source->canReadDocument($document, $user)) throw new HttpException(403, 'Permessi originali del documento insufficienti.');
        return $document;
    }

    public function sharedProtocols(array $user): array
    {
        $items = [];
        foreach (array_unique(array_column($this->grants, 'source_configuration_id')) as $sourceId) {
            $source = $this->source((int)$sourceId);
            $items = array_merge($items, $this->protocols($source, $this->practiceIds((int)$sourceId, $user), $this->availableDocumentIds((int)$sourceId, $user), $user));
        }
        foreach ($this->dossierDocuments($user) as $document) $items = array_merge($items, $this->protocols($this->source($document['source_configuration_id']), [], [$document['id']], $user));
        foreach ($this->dossier($user) as $item) {
            if ($item['type'] !== 'protocol' || !empty($item['document_id'])) continue;
            $source = $this->source($item['archive_source_configuration_id']);
            $statement = $source->pdo->prepare('SELECT id, protocol_number, subject, canceled_at FROM protocol_entries WHERE id = ?');
            $statement->execute([$item['id']]);
            $row = $statement->fetch();
            if ($row) $items[] = $row + ['source_configuration_id' => $item['archive_source_configuration_id'], 'source_configuration_name' => $item['archive_source_configuration_name'], 'item_source_configuration_id' => $item['archive_owner_configuration_id'], 'item_type' => 'practice', 'item_id' => $item['archive_owner_practice_id']];
        }
        $unique = [];
        foreach ($items as $item) $unique[$item['source_configuration_id'] . ':' . $item['id']] = $item;
        return array_values($unique);
    }

    public function dossier(array $user): array
    {
        return $this->dossier ??= (new PracticeDossierService($this->app))->accessible($user);
    }

    private function dossierDocuments(array $user): array
    {
        return $this->dossierDocuments[(int)$user['id']] ??= (new PracticeDossierService($this->app))->accessibleDocuments($user);
    }

    private function protocols(ArchiveSourceService $source, array $practices, array $documents, array $user): array
    {
        if (!$this->app->roles->userHasPermission((int)$user['id'], 'protocol.view')) return [];
        $items = [];
        foreach (['practice' => $practices, 'document' => $documents] as $type => $ids) {
            foreach ($ids as $id) {
                foreach ($type === 'practice' ? $source->protocolsForPractice((int)$id) : $source->protocolsForDocument((int)$id) as $row) {
                    if ($row['document_id'] !== null) {
                        $document = $source->documents->findById((int)$row['document_id']);
                        if ($document === null || !$source->canReadDocument($document, $user)) continue;
                    }
                    $items[$row['id']] = $row + ['source_configuration_id' => (int)$source->configuration['id'], 'source_configuration_name' => $source->configuration['name'], 'item_type' => $type, 'item_id' => (int)$id];
                }
            }
        }
        return array_values($items);
    }

    private function direct(int $source, string $type, int $id): bool
    {
        foreach ($this->grants as $grant) {
            if ((int)$grant['source_configuration_id'] === $source && $grant['entity_type'] === $type && (int)$grant['entity_id'] === $id) return true;
        }
        return false;
    }

    private function admin(array $user): bool
    {
        return in_array('admin', $this->app->roles->rolesForUser((int)$user['id']), true);
    }

    private function practiceIds(int $source, ?array $user = null): array
    {
        $ids = array_map(static fn (array $row): int => (int)$row['entity_id'], array_values(array_filter($this->grants, static fn (array $row): bool => (int)$row['source_configuration_id'] === $source && $row['entity_type'] === 'practice')));
        if ($user === null) return $ids;
        return array_values(array_filter($ids, function (int $id) use ($source, $user): bool {
            $practice = $this->source($source)->practices->findById($id);
            return $practice !== null && $this->app->practiceAccess->allows($practice, $user);
        }));
    }

    private function availableDocumentIds(int $source, ?array $user = null): array
    {
        $key = $source . ':' . ($user['id'] ?? 'all');
        if (!isset($this->documentIds[$key])) {
            $ids = array_map(static fn (array $row): int => (int)$row['entity_id'], array_values(array_filter($this->grants, static fn (array $row): bool => (int)$row['source_configuration_id'] === $source && $row['entity_type'] === 'document')));
            foreach ($this->practiceIds($source) as $practice) $ids = array_merge($ids, $this->source($source)->documentIdsForPractice($practice));
            $this->documentIds[$key] = array_values(array_unique($ids));
        }
        return $this->documentIds[$key];
    }

    private function summary(ArchiveSourceService $source, string $type, array $row): array
    {
        return ['id' => (int)$row['id'], 'type' => $type, 'source_configuration_id' => (int)$source->configuration['id'], 'source_configuration_name' => $source->configuration['name'], 'name' => $type === 'practice' ? $row['title'] : $row['original_name'], 'code' => $type === 'practice' ? $row['code'] : '', 'status' => $type === 'practice' ? $row['status'] : $row['conversion_status']];
    }
}
