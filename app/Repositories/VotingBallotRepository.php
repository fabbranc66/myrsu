<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class VotingBallotRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $votingId, int $optionId, ?int $tokenId, ?int $userId, ?string $ipHash, ?string $localIdentifierHash): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO voting_ballots (voting_id, option_id, token_id, voter_user_id, ip_hash, local_identifier_hash, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$votingId, $optionId, $tokenId, $userId, $ipHash, $localIdentifierHash]);
    }

    public function existsForLocalIdentifier(int $votingId, string $localIdentifierHash): bool
    {
        $stmt = $this->pdo->prepare('SELECT id FROM voting_ballots WHERE voting_id = ? AND local_identifier_hash = ? LIMIT 1');
        $stmt->execute([$votingId, $localIdentifierHash]);
        return (bool)$stmt->fetch();
    }

    public function results(int $votingId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT o.id, o.label, COUNT(b.id) AS votes
             FROM voting_options o
             LEFT JOIN voting_ballots b ON b.option_id = o.id
             WHERE o.voting_id = ?
             GROUP BY o.id, o.label, o.sort_order
             ORDER BY o.sort_order ASC, o.id ASC'
        );
        $stmt->execute([$votingId]);
        return $stmt->fetchAll();
    }
}
