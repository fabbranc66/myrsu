<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PracticeTimelineRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forPractice(int $practiceId): array
    {
        $items = array_merge(
            $this->linked($practiceId, "SELECT 'document' type, CAST(d.id AS CHAR) id, d.original_name title, d.category summary, d.created_at event_at, d.conversion_status status, d.id document_id FROM practice_links pl JOIN documents d ON d.id = pl.entity_id WHERE pl.practice_id = ? AND pl.entity_type = 'document'"),
            $this->linked($practiceId, "SELECT 'report' type, CAST(r.id AS CHAR) id, r.subject title, r.message summary, r.created_at event_at, r.status, r.document_id FROM practice_links pl JOIN reports r ON r.id = pl.entity_id WHERE pl.practice_id = ? AND pl.entity_type = 'report'"),
            $this->linked($practiceId, "SELECT 'comment' type, CAST(c.id AS CHAR) id, d.original_name title, c.message summary, c.created_at event_at, c.status, c.document_id FROM practice_links pl JOIN document_comments c ON c.id = pl.entity_id JOIN documents d ON d.id = c.document_id WHERE pl.practice_id = ? AND pl.entity_type = 'comment'"),
            $this->linked($practiceId, "SELECT 'protocol' type, CAST(pe.id AS CHAR) id, pe.protocol_number title, pe.subject summary, pe.created_at event_at, pe.direction status, pe.document_id FROM practice_links pl JOIN protocol_entries pe ON pe.id = pl.entity_id WHERE pl.practice_id = ? AND pl.entity_type = 'protocol'"),
            $this->linked($practiceId, "SELECT 'attachment' type, CAST(a.id AS CHAR) id, a.original_name title, a.mime_type summary, a.created_at event_at, NULL status, NULL document_id FROM practice_links pl JOIN report_attachments a ON a.id = pl.entity_id WHERE pl.practice_id = ? AND pl.entity_type = 'attachment'"),
            $this->linked($practiceId, "SELECT 'meeting' type, CAST(m.id AS CHAR) id, m.title, m.location summary, m.meeting_date event_at, m.status, m.public_document_id document_id FROM practice_links pl JOIN union_meetings m ON m.id = pl.entity_id WHERE pl.practice_id = ? AND pl.entity_type = 'meeting'"),
            $this->assemblies($practiceId),
            $this->notes($practiceId),
            $this->calls($practiceId)
        );

        usort($items, static fn (array $left, array $right): int => strcmp((string)$right['event_at'], (string)$left['event_at']));
        return $items;
    }

    private function linked(int $practiceId, string $sql): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$practiceId]);
        return $stmt->fetchAll();
    }

    private function calls(int $practiceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT 'call' type, id, interlocutor_name title, content summary,
                    TIMESTAMP(call_date, call_time) event_at, outcome status, NULL document_id
             FROM calls_log WHERE practice_id = ?"
        );
        $stmt->execute([$practiceId]);
        return $stmt->fetchAll();
    }

    private function assemblies(int $practiceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT 'assembly' type, CAST(wa.id AS CHAR) id, wa.title, wa.agenda summary,
                    COALESCE(MIN(TIMESTAMP(was.assembly_date, was.time_start)), wa.created_at) event_at,
                    wa.status, NULL document_id
             FROM workers_assemblies wa
             LEFT JOIN workers_assembly_sessions was ON was.assembly_id = wa.id
             WHERE wa.practice_id = ?
             GROUP BY wa.id"
        );
        $stmt->execute([$practiceId]);
        return $stmt->fetchAll();
    }

    private function notes(int $practiceId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT 'note' type, CAST(pn.id AS CHAR) id, u.name title, pn.body summary,
                    pn.created_at event_at, NULL status, NULL document_id
             FROM practice_notes pn JOIN users u ON u.id = pn.created_by WHERE pn.practice_id = ?"
        );
        $stmt->execute([$practiceId]);
        return $stmt->fetchAll();
    }
}
