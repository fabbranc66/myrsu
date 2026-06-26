<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CallRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(?int $practiceId = null): array
    {
        if ($practiceId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM calls_log WHERE practice_id = ? ORDER BY call_date DESC, call_time DESC, created_at DESC'
            );
            $stmt->execute([$practiceId]);

            return $stmt->fetchAll();
        }

        return $this->pdo
            ->query('SELECT * FROM calls_log ORDER BY call_date DESC, call_time DESC, created_at DESC')
            ->fetchAll();
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO calls_log
             (id, practice_id, direction, interlocutor_name, interlocutor_role, interlocutor_org, call_date, call_time, content, outcome, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $data['id'],
            $data['practice_id'],
            $data['direction'],
            $data['interlocutor_name'],
            $data['interlocutor_role'],
            $data['interlocutor_org'],
            $data['call_date'],
            $data['call_time'],
            $data['content'],
            $data['outcome'],
            $data['created_by'],
        ]);

        return $this->findById((string)$data['id']) ?? [];
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM calls_log WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function linkPractice(string $id, ?int $practiceId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE calls_log SET practice_id = ? WHERE id = ?');
        $stmt->execute([$practiceId, $id]);

        return $this->findById($id);
    }

    public function update(string $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE calls_log
             SET direction = ?, interlocutor_name = ?, interlocutor_role = ?, interlocutor_org = ?,
                 call_date = ?, call_time = ?, content = ?, outcome = ?
             WHERE id = ?'
        );
        $stmt->execute([
            $data['direction'],
            $data['interlocutor_name'],
            $data['interlocutor_role'],
            $data['interlocutor_org'],
            $data['call_date'],
            $data['call_time'],
            $data['content'],
            $data['outcome'],
            $id,
        ]);

        return $this->findById($id);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM calls_log WHERE id = ?');
        $stmt->execute([$id]);
    }
}
