<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ProtocolRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(): array
    {
        return $this->pdo
            ->query(
                'SELECT pe.*, creator.name AS created_by_name, canceler.name AS canceled_by_name
                 , d.id AS preview_document_id
                 FROM protocol_entries pe
                 LEFT JOIN users creator ON creator.id = pe.created_by
                 LEFT JOIN users canceler ON canceler.id = pe.canceled_by
                 LEFT JOIN documents d ON d.id = pe.document_id
                 ORDER BY pe.id DESC'
            )
            ->fetchAll();
    }

    public function findById(int $id): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pe.*, creator.name AS created_by_name, canceler.name AS canceled_by_name
             , d.id AS preview_document_id
             FROM protocol_entries pe
             LEFT JOIN users creator ON creator.id = pe.created_by
             LEFT JOIN users canceler ON canceler.id = pe.canceled_by
             LEFT JOIN documents d ON d.id = pe.document_id
             WHERE pe.id = ?'
        );
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    public function create(string $direction, string $typeCode, string $subject, int $userId): array
    {
        $year = (int)date('Y');
        $this->pdo->beginTransaction();

        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(sequence), 0) + 1
             FROM protocol_entries
             WHERE direction = ? AND type_code = ? AND year = ?
             FOR UPDATE'
        );
        $stmt->execute([$direction, $typeCode, $year]);
        $sequence = (int)$stmt->fetchColumn();
        $number = sprintf('RSU-%s-%s-%d-%04d', $direction, $typeCode, $year, $sequence);

        $insert = $this->pdo->prepare(
            'INSERT INTO protocol_entries
             (protocol_number, direction, type_code, year, sequence, subject, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $insert->execute([$number, $direction, $typeCode, $year, $sequence, $subject, $userId]);

        $id = (int)$this->pdo->lastInsertId();
        $this->pdo->commit();

        return $this->findById($id);
    }

    public function update(int $id, string $subject, ?int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE protocol_entries SET subject = ?, document_id = ? WHERE id = ? AND canceled_at IS NULL'
        );
        $stmt->execute([$subject, $documentId, $id]);

        return $this->findById($id);
    }

    public function cancel(int $id, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE protocol_entries
             SET canceled_at = NOW(), canceled_by = ?
             WHERE id = ? AND canceled_at IS NULL'
        );
        $stmt->execute([$userId, $id]);

        return $this->findById($id);
    }

    public function findActiveByDocumentId(int $documentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pe.*, creator.name AS created_by_name, canceler.name AS canceled_by_name
             , d.id AS preview_document_id
             FROM protocol_entries pe
             LEFT JOIN users creator ON creator.id = pe.created_by
             LEFT JOIN users canceler ON canceler.id = pe.canceled_by
             LEFT JOIN documents d ON d.id = pe.document_id
             WHERE pe.document_id = ? AND pe.canceled_at IS NULL
             ORDER BY pe.id DESC
             LIMIT 1'
        );
        $stmt->execute([$documentId]);

        return $stmt->fetch() ?: null;
    }
}
