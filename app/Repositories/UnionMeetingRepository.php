<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UnionMeetingRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo
            ->query('SELECT * FROM union_meetings ORDER BY meeting_date DESC, id DESC')
            ->fetchAll();
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO union_meetings
             (title, description, participants, agenda, meeting_date, location, status, visibility, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['participants'],
            $data['agenda'],
            $data['meeting_date'],
            $data['location'],
            $data['status'],
            $data['visibility'],
            $data['created_by'],
        ]);

        return $this->findById((int)$this->pdo->lastInsertId());
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM union_meetings WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function attachPublicDocument(int $id, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE union_meetings SET public_document_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$documentId, $id]);

        return $this->findById($id);
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE union_meetings
             SET title = ?, description = ?, participants = ?, agenda = ?, meeting_date = ?,
                 location = ?, status = ?, visibility = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['title'],
            $data['description'],
            $data['participants'],
            $data['agenda'],
            $data['meeting_date'],
            $data['location'],
            $data['status'],
            $data['visibility'],
            $id,
        ]);

        return $this->findById($id);
    }
}
