<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PracticeArchiveRepository
{
    private ?bool $available = null;

    public function __construct(public readonly PDO $pdo)
    {
    }

    public function installed(): bool
    {
        return $this->available ??= (int)$this->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('practice_transfers', 'practice_archive_links')")->fetchColumn() === 2;
    }

    public function links(int $configuration, ?int $practice = null): array
    {
        if (!$this->installed()) return [];
        $statement = $this->pdo->prepare('SELECT * FROM practice_archive_links WHERE configuration_id = ?' . ($practice === null ? '' : ' AND practice_id = ?') . ' ORDER BY id');
        $statement->execute($practice === null ? [$configuration] : [$configuration, $practice]);
        return $statement->fetchAll();
    }

    public function add(int $configuration, int $practice, int $source, int $sourcePractice): void
    {
        $this->pdo->prepare('INSERT IGNORE INTO practice_archive_links (configuration_id, practice_id, source_configuration_id, source_practice_id) VALUES (?, ?, ?, ?)')->execute([$configuration, $practice, $source, $sourcePractice]);
    }

    public function copyConfiguration(int $source, int $target): void
    {
        if (!$this->installed()) return;
        $this->pdo->prepare('INSERT INTO practice_archive_links (configuration_id, practice_id, source_configuration_id, source_practice_id) SELECT ?, practice_id, source_configuration_id, source_practice_id FROM practice_archive_links WHERE configuration_id = ?')->execute([$target, $source]);
        $this->pdo->prepare('INSERT INTO practice_transfers (source_configuration_id, source_practice_id, target_configuration_id, target_practice_id, source_code, target_code, moved_by, backup_name, created_at) SELECT ?, source_practice_id, target_configuration_id, target_practice_id, source_code, target_code, moved_by, backup_name, created_at FROM practice_transfers WHERE source_configuration_id = ?')->execute([$target, $source]);
    }
}
