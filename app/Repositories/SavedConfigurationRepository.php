<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SavedConfigurationRepository
{
    public function __construct(public readonly PDO $pdo)
    {
    }

    public function installed(): bool
    {
        $statement = $this->pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('saved_configurations', 'configuration_sessions')");
        return (int)$statement->fetchColumn() === 2;
    }

    public function all(): array
    {
        return $this->pdo->query("SELECT * FROM saved_configurations WHERE state = 'ready' ORDER BY operational_key DESC, name, id")->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare("SELECT * FROM saved_configurations WHERE id = ? AND state = 'ready'");
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public function operational(): array
    {
        return $this->pdo->query('SELECT * FROM saved_configurations WHERE operational_key = 1')->fetch() ?: throw new \RuntimeException('Configurazione operativa non definita.');
    }

    public function selected(string $token): ?array
    {
        $statement = $this->pdo->prepare("SELECT c.* FROM saved_configurations c JOIN configuration_sessions s ON s.configuration_id = c.id JOIN api_tokens t ON t.id = s.token_id WHERE t.token_hash = ? AND t.revoked_at IS NULL AND c.state = 'ready'");
        $statement->execute([hash('sha256', $token)]);
        return $statement->fetch() ?: null;
    }

    public function select(string $token, ?int $id): void
    {
        $statement = $this->pdo->prepare('DELETE s FROM configuration_sessions s JOIN api_tokens t ON t.id = s.token_id WHERE t.token_hash = ?');
        $statement->execute([hash('sha256', $token)]);
        if ($id === null) return;
        $statement = $this->pdo->prepare('INSERT INTO configuration_sessions (token_id, configuration_id) SELECT id, ? FROM api_tokens WHERE token_hash = ? AND revoked_at IS NULL');
        $statement->execute([$id, hash('sha256', $token)]);
    }

    public function save(int $id, string $name, int $userId): array
    {
        $statement = $this->pdo->prepare('UPDATE saved_configurations SET name = ?, updated_by = ?, updated_at = NOW() WHERE id = ?');
        $statement->execute([$name, $userId, $id]);
        return $this->find($id) ?? throw new \RuntimeException('Configurazione non trovata.');
    }

    public function makeOperational(int $id): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('UPDATE saved_configurations SET operational_key = NULL WHERE operational_key = 1');
            $statement = $this->pdo->prepare('UPDATE saved_configurations SET operational_key = 1, updated_at = NOW() WHERE id = ?');
            $statement->execute([$id]);
            $this->pdo->exec('DELETE FROM configuration_sessions WHERE configuration_id = ' . $id);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }
}
