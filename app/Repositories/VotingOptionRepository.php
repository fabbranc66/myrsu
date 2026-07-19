<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class VotingOptionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forVoting(int $votingId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voting_options WHERE voting_id = ? ORDER BY sort_order ASC, id ASC');
        $stmt->execute([$votingId]);
        return $stmt->fetchAll();
    }

    public function replace(int $votingId, array $labels): void
    {
        $this->pdo->prepare('DELETE FROM voting_options WHERE voting_id = ?')->execute([$votingId]);
        $stmt = $this->pdo->prepare('INSERT INTO voting_options (voting_id, label, sort_order, created_at) VALUES (?, ?, ?, NOW())');
        $sort = 1;
        foreach ($labels as $label) {
            $value = trim((string)$label);
            if ($value === '') continue;
            $stmt->execute([$votingId, $value, $sort++]);
        }
    }
}
