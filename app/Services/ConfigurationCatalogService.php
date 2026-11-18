<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\SavedConfigurationRepository;
use App\Repositories\ArchiveShareRepository;
use App\Repositories\PracticeArchiveRepository;
use PDO;
use Throwable;

final class ConfigurationCatalogService
{
    private const SHARED = ['users', 'roles', 'permissions', 'role_user', 'permission_role', 'api_tokens', 'gdpr_consents', 'institutional_contacts', 'saved_configurations', 'configuration_sessions', 'archive_shares', 'practice_transfers', 'practice_archive_links', 'rsu_election_drafts'];

    public function __construct(private readonly SavedConfigurationRepository $repository, private readonly ConfigurationFilesService $files)
    {
    }

    public static function tables(PDO $pdo): array
    {
        $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter($tables, static fn (string $table): bool => !in_array($table, self::SHARED, true) && !str_starts_with($table, 'normative_') && !preg_match('/^cfg_[0-9]+_/', $table)));
    }

    public function name(mixed $name): string
    {
        if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name)) > 120 || preg_match('/[\x00-\x1F]/', $name)) throw new HttpException(422, 'Nome configurazione obbligatorio, massimo 120 caratteri.');
        return trim($name);
    }

    public function create(array $source, string $name, bool $copy, int $userId): array
    {
        $pdo = $this->repository->pdo;
        $tables = self::tables($pdo);
        if (count($tables) === 0) throw new HttpException(409, 'Nessuna tabella operativa disponibile.');
        if ($source['table_prefix'] !== '' && array_diff($tables, json_decode($source['tables_json'] ?? '[]', true) ?: [])) throw new HttpException(409, 'Aggiornare lo schema della configurazione prima di copiarla.');
        $lock = $pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':configurations'), 10)")->fetchColumn();
        if ((int)$lock !== 1) throw new HttpException(409, 'Un salvataggio è già in corso. Riprova.');
        $target = null;
        $created = [];
        try {
            $statement = $pdo->prepare("INSERT INTO saved_configurations (name, table_prefix, tables_json, state, created_by, updated_by) VALUES (?, ?, ?, 'building', ?, ?)");
            $statement->execute([$name, 'new_' . bin2hex(random_bytes(8)), json_encode($tables), $userId, $userId]);
            $id = (int)$pdo->lastInsertId();
            $target = ['id' => $id, 'table_prefix' => 'cfg_' . $id . '_'];
            $pdo->prepare('UPDATE saved_configurations SET table_prefix = ? WHERE id = ?')->execute([$target['table_prefix'], $id]);
            foreach ($tables as $table) {
                $pdo->exec('CREATE TABLE ' . $this->identifier($target['table_prefix'] . $table) . ' LIKE ' . $this->identifier($source['table_prefix'] . $table));
                $created[] = $target['table_prefix'] . $table;
            }
            $this->foreignKeys($tables, $target['table_prefix']);
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->beginTransaction();
            foreach ($tables as $table) {
                if (!$copy && $table !== 'room_categories') continue;
                $columns = $pdo->query('SHOW COLUMNS FROM ' . $this->identifier($source['table_prefix'] . $table))->fetchAll();
                $columns = array_column(array_filter($columns, static fn (array $column): bool => !preg_match('/(VIRTUAL|STORED) GENERATED/', $column['Extra'])), 'Field');
                $columnSql = implode(', ', array_map($this->identifier(...), $columns));
                $pdo->exec('INSERT INTO ' . $this->identifier($target['table_prefix'] . $table) . ' (' . $columnSql . ') SELECT ' . $columnSql . ' FROM ' . $this->identifier($source['table_prefix'] . $table));
            }
            if ($copy) {
                $this->files->copy($source, $target);
                $this->verifyDocuments($target);
                (new ArchiveShareRepository($pdo))->copyTarget((int)$source['id'], $id);
                (new PracticeArchiveRepository($pdo))->copyConfiguration((int)$source['id'], $id);
            }
            $pdo->prepare("UPDATE saved_configurations SET state = 'ready' WHERE id = ?")->execute([$id]);
            $pdo->commit();
            return $this->repository->find($id) ?? throw new HttpException(500, 'Salvataggio configurazione fallito.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach (array_reverse($created) as $table) $pdo->exec('DROP TABLE IF EXISTS ' . $this->identifier($table));
            if ($target !== null) {
                $pdo->prepare('DELETE FROM saved_configurations WHERE id = ?')->execute([$target['id']]);
                $this->files->remove($target);
            }
            throw $exception;
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':configurations'))");
        }
    }

    private function foreignKeys(array $tables, string $prefix): void
    {
        $pdo = $this->repository->pdo;
        foreach ($tables as $table) {
            $definition = $pdo->query('SHOW CREATE TABLE ' . $this->identifier($table))->fetch(PDO::FETCH_NUM)[1];
            preg_match_all('/CONSTRAINT `([^`]+)` FOREIGN KEY (\([^)]+\)) REFERENCES `([^`]+)` (\([^)]+\))((?: ON (?:DELETE|UPDATE) (?:RESTRICT|CASCADE|SET NULL|NO ACTION))*)/', $definition, $constraints, PREG_SET_ORDER);
            foreach ($constraints as $constraint) {
                $reference = (in_array($constraint[3], $tables, true) ? $prefix : '') . $constraint[3];
                $name = $prefix . substr(hash('sha256', $table . ':' . $constraint[1]), 0, 24);
                $pdo->exec('ALTER TABLE ' . $this->identifier($prefix . $table) . ' ADD CONSTRAINT ' . $this->identifier($name) . ' FOREIGN KEY ' . $constraint[2] . ' REFERENCES ' . $this->identifier($reference) . ' ' . $constraint[4] . $constraint[5]);
            }
        }
    }

    private function verifyDocuments(array $configuration): void
    {
        $documents = $this->repository->pdo->query('SELECT * FROM ' . $this->identifier($configuration['table_prefix'] . 'documents'))->fetchAll();
        foreach ($documents as $document) {
            $checks = [['public_path' => $document['pdf_public_path'], 'checksum' => $document['conversion_status'] === 'ready' ? $document['pdf_checksum_sha256'] : null], ['public_path' => 'storage/private/originals/' . basename($document['original_stored_name']), 'checksum' => $document['original_checksum_sha256'] ?? null]];
            foreach ($checks as $check) {
                if (!$check['checksum'] || $check['checksum'] === str_repeat('0', 64)) continue;
                $path = $this->files->root($configuration) . '/' . ltrim($check['public_path'], '/');
                if (!is_file($path) || !hash_equals($check['checksum'], hash_file('sha256', $path))) throw new HttpException(409, 'Documento #' . $document['id'] . ': file mancante o modificato durante la copia. Nessuna configurazione creata.');
            }
        }
    }

    private function identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $name)) throw new HttpException(422, 'Identificatore archivio non valido.');
        return '`' . $name . '`';
    }
}
