<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UnionMeetingDocumentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forMeeting(int $meetingId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT md.*, d.original_name, d.category, d.visibility, d.conversion_status, d.pdf_size_bytes, d.created_at AS document_created_at
             FROM union_meeting_documents md
             JOIN documents d ON d.id = md.document_id
             WHERE md.meeting_id = ?
             ORDER BY md.created_at DESC, md.id DESC'
        );
        $stmt->execute([$meetingId]);

        return $stmt->fetchAll();
    }

    public function create(int $meetingId, int $documentId, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO union_meeting_documents (meeting_id, document_id, created_by, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$meetingId, $documentId, $userId]);

        return $this->findByMeetingDocument($meetingId, $documentId);
    }

    public function delete(int $meetingId, int $documentId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM union_meeting_documents WHERE meeting_id = ? AND document_id = ?');
        $stmt->execute([$meetingId, $documentId]);
    }

    private function findById(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM union_meeting_documents WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    private function findByMeetingDocument(int $meetingId, int $documentId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM union_meeting_documents WHERE meeting_id = ? AND document_id = ?');
        $stmt->execute([$meetingId, $documentId]);

        return $stmt->fetch();
    }
}
