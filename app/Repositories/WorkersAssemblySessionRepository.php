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
        $existingSessions = $this->forAssembly($assemblyId);
        $existingIds = array_map('intval', array_column($existingSessions, 'id'));
        $existingByShift = [];
        foreach ($existingSessions as $existingSession) {
            $existingByShift[$this->shiftKey((string)$existingSession['shift_label'])] = (int)$existingSession['id'];
        }
        $keptIds = [];
        $insert = $this->pdo->prepare(
            'INSERT INTO workers_assembly_sessions
             (assembly_id, shift_label, assembly_date, time_start, time_end, mode, place, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $update = $this->pdo->prepare(
            'UPDATE workers_assembly_sessions
             SET shift_label = ?, assembly_date = ?, time_start = ?, time_end = ?, mode = ?, place = ?, status = ?, updated_at = NOW()
             WHERE id = ? AND assembly_id = ?'
        );
        foreach ($sessions as $session) {
            $sessionId = (int)($session['id'] ?? 0);
            $shiftKey = $this->shiftKey((string)$session['shift_label']);
            if ($sessionId === 0 && isset($existingByShift[$shiftKey])) {
                $sessionId = $existingByShift[$shiftKey];
            }
            if ($sessionId > 0 && in_array($sessionId, $existingIds, true)) {
                $update->execute([
                    $session['shift_label'], $session['assembly_date'], $session['time_start'],
                    $session['time_end'], $session['mode'], $session['place'], $session['status'],
                    $sessionId, $assemblyId,
                ]);
                $keptIds[] = $sessionId;
                continue;
            }

            $insert->execute([
                $assemblyId, $session['shift_label'], $session['assembly_date'], $session['time_start'],
                $session['time_end'], $session['mode'], $session['place'], $session['status'],
            ]);
            $keptIds[] = (int)$this->pdo->lastInsertId();
        }

        $removeIds = array_values(array_diff($existingIds, $keptIds));
        if ($removeIds !== []) {
            $placeholders = implode(',', array_fill(0, count($removeIds), '?'));
            $this->pdo->prepare("DELETE FROM workers_assembly_sessions WHERE assembly_id = ? AND id IN ($placeholders)")
                ->execute(array_merge([$assemblyId], $removeIds));
        }
    }

    private function shiftKey(string $shiftLabel): string
    {
        return mb_strtolower(trim($shiftLabel));
    }
}
