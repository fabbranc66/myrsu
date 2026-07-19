<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class VotingTokenRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forVoting(int $votingId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voting_tokens WHERE voting_id = ? ORDER BY id DESC');
        $stmt->execute([$votingId]);
        return $stmt->fetchAll();
    }

    public function generate(int $votingId, int $count): array
    {
        $stmt = $this->pdo->prepare('INSERT INTO voting_tokens (voting_id, token, status, created_at) VALUES (?, ?, \'unused\', NOW())');
        for ($index = 0; $index < $count; $index++) {
            $stmt->execute([$votingId, strtoupper(bin2hex(random_bytes(5)))]);
        }
        return $this->forVoting($votingId);
    }

    public function findByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voting_tokens WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        return $stmt->fetch() ?: null;
    }

    public function findForVoting(int $votingId, int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voting_tokens WHERE voting_id = ? AND id = ? LIMIT 1');
        $stmt->execute([$votingId, $id]);
        return $stmt->fetch() ?: null;
    }

    public function markUsed(int $id): void
    {
        $this->pdo->prepare('UPDATE voting_tokens SET status = \'used\', used_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public function cancel(int $id): void
    {
        $this->pdo->prepare('UPDATE voting_tokens SET status = \'cancelled\' WHERE id = ? AND status = \'unused\'')->execute([$id]);
    }
}
