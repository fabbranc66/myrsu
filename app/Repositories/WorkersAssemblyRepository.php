<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WorkersAssemblyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo
            ->query('SELECT * FROM workers_assemblies ORDER BY created_at DESC, id DESC')
            ->fetchAll();
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO workers_assemblies
             (practice_id, title, agenda, description, final_statement, status, visibility, voting_enabled, voting_subject, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['practice_id'],
            $data['title'],
            $data['agenda'],
            $data['description'],
            $data['final_statement'],
            $data['status'],
            $data['visibility'],
            $data['voting_enabled'],
            $data['voting_subject'],
            $data['created_by'],
        ]);

        return $this->findById((int)$this->pdo->lastInsertId()) ?? [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM workers_assemblies WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE workers_assemblies
             SET practice_id = ?, title = ?, agenda = ?, description = ?, final_statement = ?, status = ?, visibility = ?,
                 voting_enabled = ?, voting_subject = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['practice_id'],
            $data['title'],
            $data['agenda'],
            $data['description'],
            $data['final_statement'],
            $data['status'],
            $data['visibility'],
            $data['voting_enabled'],
            $data['voting_subject'],
            $id,
        ]);

        return $this->findById($id);
    }

    public function attachPublicDocument(int $id, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE workers_assemblies SET public_document_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$documentId, $id]);

        return $this->findById($id);
    }

    public function attachMinutesDocument(int $id, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE workers_assemblies SET minutes_document_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$documentId, $id]);

        return $this->findById($id);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM workers_assemblies WHERE id = ?');
        $stmt->execute([$id]);
    }
}
