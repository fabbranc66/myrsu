<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ArchiveShareRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function installed(): bool
    {
        return (bool)$this->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'archive_shares'")->fetchColumn();
    }

    public function forTarget(int $target): array
    {
        if (!$this->installed()) return [];
        $statement = $this->pdo->prepare('SELECT * FROM archive_shares WHERE target_configuration_id = ? ORDER BY source_configuration_id, entity_type, entity_id');
        $statement->execute([$target]);
        return $statement->fetchAll();
    }

    public function set(int $target, int $source, string $type, int $entity, bool $enabled, int $user): void
    {
        if ($enabled) {
            $statement = $this->pdo->prepare('INSERT IGNORE INTO archive_shares (target_configuration_id, source_configuration_id, entity_type, entity_id, created_by) VALUES (?, ?, ?, ?, ?)');
            $statement->execute([$target, $source, $type, $entity, $user]);
        } else {
            $statement = $this->pdo->prepare('DELETE FROM archive_shares WHERE target_configuration_id = ? AND source_configuration_id = ? AND entity_type = ? AND entity_id = ?');
            $statement->execute([$target, $source, $type, $entity]);
        }
    }

    public function copyTarget(int $source, int $target): void
    {
        if (!$this->installed()) return;
        $statement = $this->pdo->prepare('INSERT INTO archive_shares (target_configuration_id, source_configuration_id, entity_type, entity_id, created_by) SELECT ?, source_configuration_id, entity_type, entity_id, created_by FROM archive_shares WHERE target_configuration_id = ? AND source_configuration_id <> ?');
        $statement->execute([$target, $source, $target]);
    }
}
