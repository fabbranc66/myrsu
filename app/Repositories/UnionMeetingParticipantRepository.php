<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UnionMeetingParticipantRepository
{
    private const TYPES = ['user', 'institutional_contact'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forMeeting(int $meetingId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM union_meeting_participants WHERE meeting_id = ? ORDER BY label ASC'
        );
        $stmt->execute([$meetingId]);

        return $stmt->fetchAll();
    }

    public function replace(int $meetingId, array $participants): void
    {
        $this->pdo->prepare('DELETE FROM union_meeting_participants WHERE meeting_id = ?')->execute([$meetingId]);
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO union_meeting_participants
             (meeting_id, participant_type, participant_id, label, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        foreach ($participants as $participant) {
            if (!in_array((string)($participant['type'] ?? ''), self::TYPES, true)) {
                continue;
            }
            $stmt->execute([
                $meetingId,
                (string)$participant['type'],
                (int)$participant['id'],
                trim((string)$participant['label']),
            ]);
        }
    }
}
