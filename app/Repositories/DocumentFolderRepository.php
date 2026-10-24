<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use Throwable;

final class DocumentFolderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function snapshot(): array
    {
        return [
            'folders' => $this->pdo->query(
                "SELECT f.*, p.id AS practice_id, p.code AS practice_code, p.title AS practice_title
                 FROM document_folders f
                 LEFT JOIN practices p ON f.system_key = CONCAT('practice:', p.id)
                 WHERE f.system_key IS NULL OR f.system_key = 'practices' OR p.id IS NOT NULL
                 ORDER BY f.name, f.id"
            )->fetchAll(),
            'placements' => $this->pdo->query('SELECT document_id, folder_id FROM document_folder_items')->fetchAll(),
            'permissions' => $this->pdo->query('SELECT * FROM document_folder_permissions')->fetchAll(),
            'practice_links' => $this->pdo->query("SELECT practice_id, entity_id AS document_id FROM practice_links WHERE entity_type = 'document'")->fetchAll(),
        ];
    }

    public function ensurePracticeFolders(): bool
    {
        $missing = $this->pdo->query(
            "SELECT NOT EXISTS (SELECT 1 FROM document_folders WHERE system_key = 'practices')
             OR EXISTS (SELECT 1 FROM practices p WHERE NOT EXISTS
                (SELECT 1 FROM document_folders f WHERE f.system_key = CONCAT('practice:', p.id)))"
        )->fetchColumn();
        if (!(bool)$missing) return false;
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $this->pdo->exec(
                "INSERT INTO document_folders (parent_id, name, system_key, created_at, updated_at)
                 SELECT NULL, 'Documenti pratiche', 'practices', NOW(), NOW()
                 WHERE NOT EXISTS (SELECT 1 FROM document_folders WHERE system_key = 'practices')
                 ON DUPLICATE KEY UPDATE system_key = COALESCE(document_folders.system_key, VALUES(system_key))"
            );
            $this->pdo->exec(
                "INSERT INTO document_folders (parent_id, name, system_key, created_at, updated_at)
                 SELECT root.id, CONCAT('Pratica #', p.id), CONCAT('practice:', p.id), NOW(), NOW()
                 FROM practices p JOIN document_folders root ON root.system_key = 'practices'
                 WHERE NOT EXISTS (SELECT 1 FROM document_folders existing WHERE existing.system_key = CONCAT('practice:', p.id))
                 ON DUPLICATE KEY UPDATE system_key = COALESCE(document_folders.system_key, VALUES(system_key))"
            );
            if ($ownsTransaction) $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction) $this->pdo->rollBack();
            throw $exception;
        }
        return true;
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM document_folders WHERE id = ?');
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public function create(string $name, ?int $parentId, int $userId): array
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO document_folders (name, parent_id, created_by, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())'
        );
        $statement->execute([$name, $parentId, $userId]);
        return $this->find((int)$this->pdo->lastInsertId());
    }

    public function place(int $documentId, ?int $folderId, int $userId): void
    {
        if ($folderId === null) {
            $statement = $this->pdo->prepare('DELETE FROM document_folder_items WHERE document_id = ?');
            $statement->execute([$documentId]);
            return;
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO document_folder_items (document_id, folder_id, placed_by, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE folder_id = VALUES(folder_id), placed_by = VALUES(placed_by), updated_at = NOW()'
        );
        $statement->execute([$documentId, $folderId, $userId]);
    }

    public function setPermissions(int $folderId, bool $inherit, array $permissions): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('UPDATE document_folders SET inherit_permissions = ?, updated_at = NOW() WHERE id = ?')
                ->execute([(int)$inherit, $folderId]);
            $this->pdo->prepare('DELETE FROM document_folder_permissions WHERE folder_id = ?')->execute([$folderId]);
            $statement = $this->pdo->prepare(
                'INSERT INTO document_folder_permissions (folder_id, role_id, access_level) VALUES (?, ?, ?)'
            );
            foreach ($inherit ? [] : $permissions as $permission) {
                $statement->execute([$folderId, $permission['role_id'], $permission['access_level']]);
            }
            if ($ownsTransaction) $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function documentsForFile(string $relativePath): array
    {
        if (str_starts_with($relativePath, 'public/documents/')) {
            $statement = $this->pdo->prepare('SELECT id FROM documents WHERE pdf_public_path = ?');
            $statement->execute([$relativePath]);
        } elseif (str_starts_with($relativePath, 'storage/private/originals/')) {
            $statement = $this->pdo->prepare('SELECT id FROM documents WHERE original_stored_name = ?');
            $statement->execute([basename($relativePath)]);
        } else {
            return [];
        }
        return $statement->fetchAll();
    }
}
