<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Repositories\UserRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__) . '/app/Core/HttpException.php';
require dirname(__DIR__) . '/app/Repositories/AdminAccountProtectionRepository.php';
require dirname(__DIR__) . '/app/Repositories/UserRepository.php';

final class UserDeletionTestStatement extends PDOStatement
{
    public ?array $parameters = null;

    public function __construct(private readonly ?PDOException $failure)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->parameters = $params;
        if ($this->failure !== null) throw $this->failure;
        return true;
    }

    public function fetchColumn(int $column = 0): mixed { return 1; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
}

final class UserDeletionTestPdo extends PDO
{
    private bool $transaction = false;
    public string $queryText = '';
    public readonly UserDeletionTestStatement $statement;

    public function __construct(?PDOException $failure = null)
    {
        $this->statement = new UserDeletionTestStatement($failure);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with(ltrim($query), 'SELECT')) return new UserDeletionTestStatement(null);
        $this->queryText = $query;
        return $this->statement;
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false { return new UserDeletionTestStatement(null); }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
}

function checkUserDeletion(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$pdo = new UserDeletionTestPdo();
(new UserRepository($pdo))->delete(123);
checkUserDeletion($pdo->queryText === 'DELETE FROM users WHERE id = ?', 'Cancellazione parametrizzata invariata.');
checkUserDeletion($pdo->statement->parameters === [123], 'Identificativo utente corretto.');

$blocked = new PDOException('Synthetic foreign key conflict');
$blocked->errorInfo = ['23000', 1451, 'Test reference'];
try {
    (new UserRepository(new UserDeletionTestPdo($blocked)))->delete(123);
    throw new RuntimeException('Conflitto non segnalato.');
} catch (HttpException $exception) {
    checkUserDeletion($exception->status() === 409, 'I vincoli devono restituire HTTP 409.');
    checkUserDeletion(str_contains($exception->getMessage(), 'archivi salvati'), 'Il messaggio comprende gli archivi.');
    checkUserDeletion(str_contains($exception->getMessage(), 'sospeso'), 'Il messaggio indica l’alternativa non distruttiva.');
}

foreach ([1062, 1452, 2006, null] as $driverCode) {
    $failure = new PDOException('Synthetic database failure');
    if ($driverCode !== null) $failure->errorInfo = ['HY000', $driverCode, 'Test failure'];
    try {
        (new UserRepository(new UserDeletionTestPdo($failure)))->delete(123);
        throw new RuntimeException('Errore non propagato.');
    } catch (PDOException $exception) {
        checkUserDeletion($exception === $failure, 'Gli altri errori SQL non devono essere mascherati.');
    }
}

echo "OK: eliminazione utenti, vincoli e altri errori SQL. Nessuna connessione al database.\n";
