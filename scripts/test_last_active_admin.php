<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Repositories\RolePermissionRepository;
use App\Repositories\UserRepository;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

final class LastAdminTestPdo extends PDO
{
    public array $lockingQueries = [];
    public bool $failAssignment = false;

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-10-25 12:00:00');
        $this->exec('PRAGMA foreign_keys = ON');
        $this->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY, name TEXT, email TEXT UNIQUE, password_hash TEXT,
            first_name TEXT, last_name TEXT, phone TEXT, mobile TEXT, city TEXT, country TEXT,
            union_code TEXT, union_logo_stored_name TEXT, status TEXT, created_at TEXT, updated_at TEXT
        )");
        $this->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT UNIQUE, label TEXT)');
        $this->exec('CREATE TABLE role_user (user_id INTEGER REFERENCES users(id) ON DELETE CASCADE, role_id INTEGER REFERENCES roles(id), PRIMARY KEY(user_id, role_id))');
        $this->exec('CREATE TABLE api_tokens (id INTEGER PRIMARY KEY, user_id INTEGER, revoked_at TEXT)');
        $this->exec("INSERT INTO roles VALUES (1, 'admin', 'Admin'), (2, 'delegato', 'Delegato'), (3, 'rls', 'RLS'), (4, 'membro', 'Membro')");
        $this->exec("INSERT INTO users (id, name, email, status) VALUES
            (1, 'Admin test', 'admin@example.invalid', 'active'),
            (2, 'Delegato test', 'delegate@example.invalid', 'active'),
            (3, 'Admin sospeso', 'suspended@example.invalid', 'suspended'),
            (4, 'Membro test', 'member@example.invalid', 'active'),
            (5, 'Doppio ruolo', 'dual@example.invalid', 'active')");
        $this->exec('INSERT INTO role_user VALUES (1, 1), (2, 2), (3, 1), (4, 4), (5, 2), (5, 3)');
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare($this->adapt($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = $this->adapt($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    private function adapt(string $query): string
    {
        if ($this->failAssignment && str_contains($query, 'INSERT IGNORE INTO role_user')) throw new PDOException('Synthetic role assignment failure');
        if (str_contains($query, 'FOR UPDATE')) $this->lockingQueries[] = $query;
        return str_replace(['FOR UPDATE', 'INSERT IGNORE INTO'], ['', 'INSERT OR IGNORE INTO'], $query);
    }
}

$checks = 0;
function checkLastAdmin(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function adminSnapshot(PDO $pdo): array
{
    return [
        $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(),
        $pdo->query('SELECT * FROM role_user ORDER BY user_id, role_id')->fetchAll(),
    ];
}

function removeAdmin(LastAdminTestPdo $pdo, int $userId, string $action): void
{
    match ($action) {
        'suspend' => (new UserRepository($pdo))->update($userId, ['status' => 'suspended', 'name' => 'Non deve cambiare se bloccato']),
        'delete' => (new UserRepository($pdo))->delete($userId),
        'demote' => (new RolePermissionRepository($pdo))->replaceUserRoles($userId, ['delegato']),
        'empty_roles' => (new RolePermissionRepository($pdo))->replaceUserRoles($userId, []),
    };
}

function expectLastAdminBlock(LastAdminTestPdo $pdo, int $userId, string $action): void
{
    $before = adminSnapshot($pdo);
    try {
        removeAdmin($pdo, $userId, $action);
        throw new RuntimeException('Ultimo amministratore non protetto: ' . $action);
    } catch (HttpException $exception) {
        checkLastAdmin($exception->status() === 409, 'Il blocco deve essere HTTP 409.');
        checkLastAdmin(str_contains($exception->getMessage(), 'amministratore attivo'), 'Messaggio operativo sul secondo amministratore.');
    }
    checkLastAdmin(adminSnapshot($pdo) === $before, 'Il rifiuto non deve modificare account o ruoli.');
    checkLastAdmin(!$pdo->inTransaction(), 'Transazione chiusa dopo il rifiuto.');
}

foreach (['suspend', 'delete', 'demote', 'empty_roles'] as $action) {
    $pdo = new LastAdminTestPdo();
    expectLastAdminBlock($pdo, 1, $action);
    checkLastAdmin(str_contains($pdo->lockingQueries[0] ?? '', 'FROM roles'), 'Prima si blocca la riga stabile del ruolo admin.');
    checkLastAdmin(str_contains($pdo->lockingQueries[1] ?? '', 'FROM users u'), 'Il conteggio usa una lettura corrente con lock.');
}

foreach (['suspend', 'delete', 'demote', 'empty_roles'] as $firstAction) {
    foreach (['suspend', 'delete', 'demote', 'empty_roles'] as $secondAction) {
        $pdo = new LastAdminTestPdo();
        $pdo->exec('INSERT INTO role_user VALUES (2, 1)');
        removeAdmin($pdo, 1, $firstAction);
        checkLastAdmin(!$pdo->inTransaction(), 'Modifica consentita con secondo admin: commit eseguito.');
        expectLastAdminBlock($pdo, 2, $secondAction);
    }
}

foreach ([2, 3, 4, 5] as $userId) {
    foreach (['suspend', 'delete', 'demote', 'empty_roles'] as $action) {
        $pdo = new LastAdminTestPdo();
        removeAdmin($pdo, $userId, $action);
        checkLastAdmin((new UserRepository($pdo))->findById(1)['status'] === 'active', 'Non admin e admin sospesi non bloccano le operazioni ordinarie.');
    }
}

$pdo = new LastAdminTestPdo();
$users = new UserRepository($pdo);
$roles = new RolePermissionRepository($pdo);
checkLastAdmin($users->update(1, ['name' => 'Profilo aggiornato'])['name'] === 'Profilo aggiornato', 'Profilo ultimo admin modificabile.');
$roles->replaceUserRoles(1, ['admin', 'delegato']);
checkLastAdmin($roles->rolesForUser(1) === ['admin', 'delegato'], 'Il mantenimento del ruolo admin resta consentito.');
$roles->replaceUserRoles(1, ['admin']);
$roles->replaceUserRoles(5, ['delegato', 'rls']);
checkLastAdmin($roles->rolesForUser(5) === ['delegato', 'rls'], 'Doppio ruolo conservato.');
$users->update(3, ['status' => 'active']);
$users->update(1, ['status' => 'suspended']);
checkLastAdmin($users->findById(3)['status'] === 'active', 'Un admin riattivato conta come alternativa.');

$pdo = new LastAdminTestPdo();
$before = adminSnapshot($pdo);
try {
    (new RolePermissionRepository($pdo))->replaceUserRoles(999, ['admin']);
    throw new RuntimeException('Errore SQL atteso.');
} catch (PDOException $exception) {
    checkLastAdmin(adminSnapshot($pdo) === $before && !$pdo->inTransaction(), 'Errore SQL: rollback integrale e nessuna perdita di ruoli.');
}

$pdo->failAssignment = true;
try {
    (new RolePermissionRepository($pdo))->replaceUserRoles(2, ['rls']);
    throw new RuntimeException('Errore di assegnazione atteso dopo la rimozione dei ruoli precedenti.');
} catch (PDOException $exception) {
    checkLastAdmin(adminSnapshot($pdo) === $before && !$pdo->inTransaction(), 'Il rollback ripristina i ruoli cancellati prima di un errore.');
}
$pdo->failAssignment = false;

$pdo->beginTransaction();
$pdo->exec("UPDATE users SET name = 'Modifica del chiamante' WHERE id = 4");
try {
    (new UserRepository($pdo))->update(1, ['status' => 'suspended']);
    throw new RuntimeException('Blocco atteso nella transazione del chiamante.');
} catch (HttpException $exception) {
    checkLastAdmin($exception->status() === 409 && $pdo->inTransaction(), 'Il rifiuto non chiude la transazione esterna.');
    checkLastAdmin((new UserRepository($pdo))->findById(4)['name'] === 'Modifica del chiamante', 'Il rollback locale conserva il lavoro precedente del chiamante.');
}
(new RolePermissionRepository($pdo))->replaceUserRoles(5, ['delegato']);
checkLastAdmin($pdo->inTransaction(), 'La modifica non deve committare la transazione esterna.');
$pdo->rollBack();
checkLastAdmin(adminSnapshot($pdo) === $before, 'Rollback esterno ripristina anche i cambi di ruolo.');

echo "OK: {$checks} controlli ultimo admin, sospensione, cancellazione, ruoli, rollback e profilo. Solo SQLite in memoria; lock MySQL verificati separatamente.\n";
