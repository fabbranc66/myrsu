<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class WorkersAssemblySessionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forAssembly(int $assemblyId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM workers_assembly_sessions
             WHERE assembly_id = ?
             ORDER BY assembly_date ASC, time_start ASC, id ASC'
        );
        $stmt->execute([$assemblyId]);

        return $stmt->fetchAll();
    }

    public function findForAssembly(int $assemblyId, int $sessionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM workers_assembly_sessions WHERE assembly_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$assemblyId, $sessionId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function replace(int $assemblyId, array $sessions): void
    {
        $this->pdo->prepare('DELETE FROM workers_assembly_sessions WHERE assembly_id = ?')->execute([$assemblyId]);
        $stmt = $this->pdo->prepare(
            'INSERT INTO workers_assembly_sessions
             (assembly_id, shift_label, assembly_date, time_start, time_end, mode, place, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        foreach ($sessions as $session) {
            $stmt->execute([
                $assemblyId,
                $session['shift_label'],
                $session['assembly_date'],
                $session['time_start'],
                $session['time_end'],
                $session['mode'],
                $session['place'],
                $session['status'],
            ]);
        }
    }
}
