<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\HttpException;
use App\Repositories\DocumentFolderRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\PracticeRepository;
use PDO;

final class ArchiveSourceService
{
    public readonly PDO $pdo;
    public readonly DocumentRepository $documents;
    public readonly PracticeRepository $practices;
    public readonly DocumentFolderAccessService $access;
    public readonly string $root;

    public function __construct(private readonly Application $app, public readonly array $configuration)
    {
        $this->pdo = $app->configurationConnection($configuration);
        $this->documents = new DocumentRepository($this->pdo);
        $this->practices = new PracticeRepository($this->pdo, (int)$configuration['id']);
        $this->root = (new ConfigurationFilesService($app->basePath()))->root($configuration);
        $this->access = new DocumentFolderAccessService(new DocumentFolderRepository($this->pdo), $app->roles, $this->root);
    }

    public function canReadDocument(array $document, array $user): bool
    {
        return $this->access->allowsDocument($document, $user);
    }

    public function documentIdsForPractice(int $id): array
    {
        if ($this->practices->findById($id) === null) return [];
        $statement = $this->pdo->prepare("SELECT entity_id AS document_id FROM practice_links WHERE practice_id = ? AND entity_type = 'document' UNION SELECT pe.document_id FROM practice_links pl JOIN protocol_entries pe ON pe.id = pl.entity_id WHERE pl.practice_id = ? AND pl.entity_type = 'protocol' AND pe.document_id IS NOT NULL");
        $statement->execute([$id, $id]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function protocolsForDocument(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT id, protocol_number, direction, type_code, subject, document_id, created_at, canceled_at FROM protocol_entries WHERE document_id = ? ORDER BY id DESC');
        $statement->execute([$id]);
        return $statement->fetchAll();
    }

    public function protocolsForPractice(int $id): array
    {
        $statement = $this->pdo->prepare("SELECT pe.id, pe.protocol_number, pe.direction, pe.type_code, pe.subject, pe.document_id, pe.created_at, pe.canceled_at FROM protocol_entries pe JOIN practice_links pl ON pl.entity_id = pe.id AND pl.entity_type = 'protocol' WHERE pl.practice_id = ? ORDER BY pe.id DESC");
        $statement->execute([$id]);
        return $statement->fetchAll();
    }

    public function notesForPractice(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT pn.body, pn.created_at, u.name AS author FROM practice_notes pn JOIN users u ON u.id = pn.created_by WHERE pn.practice_id = ? ORDER BY pn.created_at, pn.id');
        $statement->execute([$id]);
        return $statement->fetchAll();
    }

    public function pdfPath(array $document): string
    {
        $relative = str_replace('\\', '/', (string)$document['pdf_public_path']);
        if (!str_starts_with($relative, 'public/documents/') || preg_match('~(^|/)\.\.(/|$)|\x00~', $relative)) throw new HttpException(404, 'Percorso documento non valido.');
        $path = realpath($this->root . '/' . $relative);
        $base = realpath($this->root . '/public/documents');
        if ($path === false || $base === false || !is_file($path) || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $base) . '/')) throw new HttpException(404, 'File originale non disponibile.');
        return $path;
    }
}
