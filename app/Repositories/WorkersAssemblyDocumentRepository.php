<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WorkersAssemblyDocumentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forAssembly(int $assemblyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ad.*, d.original_name, d.category, d.visibility, d.conversion_status, d.pdf_size_bytes, d.created_at AS document_created_at
             FROM workers_assembly_documents ad
             JOIN documents d ON d.id = ad.document_id
             WHERE ad.assembly_id = ?
             ORDER BY ad.created_at DESC, ad.id DESC'
        );
        $stmt->execute([$assemblyId]);

        return $stmt->fetchAll();
    }

    public function create(int $assemblyId, int $documentId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO workers_assembly_documents (assembly_id, document_id, created_by, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$assemblyId, $documentId, $userId]);

        return $this->findByAssemblyDocument($assemblyId, $documentId);
    }

    public function delete(int $assemblyId, int $documentId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM workers_assembly_documents WHERE assembly_id = ? AND document_id = ?');
        $stmt->execute([$assemblyId, $documentId]);
    }

    private function findByAssemblyDocument(int $assemblyId, int $documentId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM workers_assembly_documents WHERE assembly_id = ? AND document_id = ?');
        $stmt->execute([$assemblyId, $documentId]);

        return $stmt->fetch();
    }
}
