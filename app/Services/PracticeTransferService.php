<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Application;
use App\Core\HttpException;
use App\Repositories\PracticeArchiveRepository;
use PDO;
use Throwable;

final class PracticeTransferService
{
    private readonly PDO $pdo;
    private readonly PracticeArchiveRepository $archive;

    public function __construct(private readonly Application $app)
    {
        $this->pdo = $app->configurationContext->repository->pdo;
        $this->archive = new PracticeArchiveRepository($this->pdo);
    }

    public function preview(int $sourceId, int $practiceId): array
    {
        if (!$this->archive->installed()) throw new HttpException(409, 'Applicare la migration 20261025_practice_transfers.sql.');
        $current = $this->app->configurationContext->current;
        if ($current === null || (int)$current['id'] === $sourceId) throw new HttpException(422, 'Apri prima una configurazione destinataria diversa dall’origine.');
        $sharing = new ArchiveSharingService($this->app);
        $source = $sharing->source($sourceId);
        $this->app->configurationConnection($current);
        $practice = $source->practices->findById($practiceId) ?? throw new HttpException(404, 'Pratica non trovata o già trasferita.');
        return ['source' => ['id' => $sourceId, 'name' => $source->configuration['name']], 'target' => $this->app->configurationContext->summary(), 'practice' => $practice,
            'notice' => 'La pratica sarà gestita nella destinazione. Tutto il fascicolo precedente rimane collegato agli originali: documenti, commenti, segnalazioni, allegati, note e protocolli non vengono duplicati o cancellati. All’origine resta soltanto una traccia tecnica non operativa.'];
    }

    public function move(int $sourceId, int $practiceId, string $confirm, int $user): array
    {
        $preview = $this->preview($sourceId, $practiceId);
        if ($confirm !== $preview['practice']['code']) throw new HttpException(422, 'Conferma lo spostamento scrivendo il codice della pratica.');
        $source = $this->app->configurationContext->repository->find($sourceId);
        $target = $this->app->configurationContext->current;
        $sourceTable = $this->table($source, 'practices');
        $targetTable = $this->table($target, 'practices');
        if ((int)$this->pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':configurations'), 10)")->fetchColumn() !== 1) throw new HttpException(409, 'Un’altra operazione sulle configurazioni è in corso.');
        try {
            $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare('SELECT * FROM ' . $sourceTable . ' WHERE id = ? FOR UPDATE');
            $statement->execute([$practiceId]);
            $row = $statement->fetch();
            if (!$row) throw new HttpException(404, 'Pratica originale non disponibile.');
            $moved = $this->pdo->prepare('SELECT id FROM practice_transfers WHERE source_configuration_id = ? AND source_practice_id = ?');
            $moved->execute([$sourceId, $practiceId]);
            if ($moved->fetchColumn()) throw new HttpException(409, 'La pratica è già stata trasferita. Ricarica l’archivio.');
            if ($row['code'] !== $confirm) throw new HttpException(409, 'La pratica è cambiata. Ricarica l’anteprima.');
            $backup = (new ConfigurationBackupService($this->pdo, $this->app->basePath()))->create();
            $sourceCode = $row['code'];
            $row['code'] = $this->availableCode($targetTable, $sourceCode);
            unset($row['id']);
            $columns = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', array_keys($row)));
            $statement = $this->pdo->prepare('INSERT INTO ' . $targetTable . ' (' . $columns . ') VALUES (' . implode(', ', array_fill(0, count($row), '?')) . ')');
            $statement->execute(array_values($row));
            $newId = (int)$this->pdo->lastInsertId();
            $targetId = (int)$target['id'];
            $this->archive->add($targetId, $newId, $sourceId, $practiceId);
            $this->pdo->prepare('INSERT INTO practice_transfers (source_configuration_id, source_practice_id, target_configuration_id, target_practice_id, source_code, target_code, moved_by, backup_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$sourceId, $practiceId, $targetId, $newId, $sourceCode, $row['code'], $user, $backup]);
            $this->pdo->prepare("INSERT IGNORE INTO archive_shares (target_configuration_id, source_configuration_id, entity_type, entity_id, created_by, created_at) SELECT target_configuration_id, ?, 'practice', ?, created_by, created_at FROM archive_shares WHERE source_configuration_id = ? AND entity_type = 'practice' AND entity_id = ? AND target_configuration_id <> ?")->execute([$targetId, $newId, $sourceId, $practiceId, $targetId]);
            $this->pdo->prepare("DELETE FROM archive_shares WHERE source_configuration_id = ? AND entity_type = 'practice' AND entity_id = ?")->execute([$sourceId, $practiceId]);
            $this->pdo->prepare('INSERT INTO ' . $this->table($target, 'activity_logs') . " (user_id, action, metadata_json, created_at) VALUES (?, 'practices.transfer', ?, NOW())")->execute([$user, json_encode(['source_configuration_id' => $sourceId, 'source_practice_id' => $practiceId, 'practice_id' => $newId, 'source_code' => $sourceCode, 'target_code' => $row['code'], 'backup_name' => $backup])]);
            $this->pdo->commit();
            return ['practice_id' => $newId, 'code' => $row['code'], 'source_code' => $sourceCode, 'target_configuration_id' => $targetId, 'backup_name' => $backup];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':configurations'))");
        }
    }

    private function table(array $configuration, string $table): string
    {
        $prefix = $configuration['table_prefix'];
        if (!preg_match('/^(cfg_[0-9]+_)?$/D', $prefix)) throw new HttpException(409, 'Configurazione non valida.');
        return '`' . $prefix . $table . '`';
    }

    private function availableCode(string $table, string $code): string
    {
        $statement = $this->pdo->prepare('SELECT id FROM ' . $table . ' WHERE code = ? FOR UPDATE');
        $statement->execute([$code]);
        if (!$statement->fetchColumn()) return $code;
        $year = preg_match('/^PRA-([0-9]{4})-/', $code, $match) ? $match[1] : gmdate('Y');
        $statement = $this->pdo->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(code, 10) AS UNSIGNED)), 0) + 1 FROM ' . $table . ' WHERE code LIKE ? FOR UPDATE');
        $statement->execute(['PRA-' . $year . '-%']);
        return sprintf('PRA-%s-%04d', $year, (int)$statement->fetchColumn());
    }
}
