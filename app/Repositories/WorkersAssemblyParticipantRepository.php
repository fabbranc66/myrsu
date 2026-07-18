<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WorkersAssemblyParticipantRepository
{
    private const TYPES = ['user', 'institutional_contact'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forAssembly(int $assemblyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM workers_assembly_participants WHERE assembly_id = ? ORDER BY label ASC'
        );
        $stmt->execute([$assemblyId]);

        return $stmt->fetchAll();
    }

    public function replace(int $assemblyId, array $participants): void
    {
        $this->pdo->prepare('DELETE FROM workers_assembly_participants WHERE assembly_id = ?')->execute([$assemblyId]);
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO workers_assembly_participants
             (assembly_id, participant_type, participant_id, label, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        foreach ($participants as $participant) {
            if (!in_array((string)($participant['type'] ?? ''), self::TYPES, true)) {
                continue;
            }
            $stmt->execute([
                $assemblyId,
                (string)$participant['type'],
                (int)$participant['id'],
                trim((string)$participant['label']),
            ]);
        }
    }
}
