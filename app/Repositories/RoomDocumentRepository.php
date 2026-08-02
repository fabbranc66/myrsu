<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class RoomDocumentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function available(): array
    {
        return $this->pdo->query(
            "SELECT id, original_name, category, visibility, conversion_status, created_at
             FROM documents WHERE conversion_status = 'ready' ORDER BY id DESC"
        )->fetchAll();
    }

    public function forRoom(int $roomId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rd.id, rd.document_id, rd.shared_at, rd.shared_by, d.original_name, d.category,
                    d.signature, d.created_at AS document_created_at, u.name AS shared_by_name
             FROM room_documents rd
             JOIN documents d ON d.id = rd.document_id
             JOIN users u ON u.id = rd.shared_by
             WHERE rd.room_id = ? AND rd.revoked_at IS NULL ORDER BY rd.shared_at DESC'
        );
        $stmt->execute([$roomId]);
        return $stmt->fetchAll();
    }

    public function share(int $roomId, int $documentId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO room_documents (room_id, document_id, shared_by, shared_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE shared_by = VALUES(shared_by), shared_at = NOW(), revoked_by = NULL, revoked_at = NULL'
        );
        $stmt->execute([$roomId, $documentId, $userId]);
        return $this->findActive($roomId, $documentId);
    }

    public function findActive(int $roomId, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM room_documents WHERE room_id = ? AND document_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$roomId, $documentId]);
        return $stmt->fetch() ?: null;
    }

    public function revoke(int $roomId, int $documentId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE room_documents SET revoked_by = ?, revoked_at = NOW() WHERE room_id = ? AND document_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$userId, $roomId, $documentId]);
    }
}
