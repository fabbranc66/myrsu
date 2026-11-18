<?php

declare(strict_types=1);

use App\Controllers\Api\AuthController;
use App\Controllers\Api\ProfileController;
use App\Controllers\Api\UserController;
use App\Core\Application;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\ActivityLogRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;
use App\Services\AuthService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

final class SessionSuspensionTestPdo extends PDO
{
    public bool $failRevocation = false;
    public bool $suspendBeforeIssue = false;
    public bool $failDeviceColumn = false;
    public bool $changePasswordBeforeIssue = false;

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->sqliteCreateFunction('NOW', static fn (): string => '2026-10-25 12:00:00');
        $this->exec('PRAGMA foreign_keys = ON');
        $this->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, password_hash TEXT, first_name TEXT, last_name TEXT, phone TEXT, mobile TEXT, city TEXT, country TEXT, union_code TEXT, union_logo_stored_name TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT UNIQUE, label TEXT)');
        $this->exec('CREATE TABLE role_user (user_id INTEGER, role_id INTEGER, PRIMARY KEY(user_id, role_id))');
        $this->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT, label TEXT)');
        $this->exec('CREATE TABLE permission_role (permission_id INTEGER, role_id INTEGER)');
        $this->exec('CREATE TABLE api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER REFERENCES users(id) ON DELETE CASCADE, token_hash TEXT UNIQUE, device_name TEXT, revoked_at TEXT, last_used_at TEXT, created_at TEXT)');
        $this->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT, metadata_json TEXT, created_at TEXT)');
        $this->exec("INSERT INTO roles VALUES (1, 'admin', 'Admin'), (2, 'delegato', 'Delegato'), (3, 'membro', 'Membro')");
        $this->exec("INSERT INTO permissions VALUES (1, 'users.update', 'Modifica utenti')");
        $this->exec('INSERT INTO permission_role VALUES (1, 1)');
        $this->exec('INSERT INTO role_user VALUES (1, 1), (2, 2), (3, 3)');
        $passwordHash = password_hash('password-di-prova', PASSWORD_DEFAULT);
        $statement = $this->prepare("INSERT INTO users (id, name, email, password_hash, status) VALUES (?, ?, ?, ?, 'active')");
        foreach ([1 => 'admin', 2 => 'delegate', 3 => 'other'] as $userId => $name) {
            $statement->execute([$userId, $name, $name . '@example.invalid', $passwordHash]);
        }
        $tokens = new TokenRepository($this);
        foreach ([['admin-token', 1], ['browser-token', 2], ['mobile-token', 2], ['other-token', 3]] as [$token, $userId]) {
            $tokens->create($userId, $token, 'test');
        }
        $this->exec("INSERT INTO api_tokens (user_id, token_hash, revoked_at) VALUES (2, 'already-revoked', '2020-01-01 00:00:00')");
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failRevocation && str_contains($query, 'SET revoked_at = NOW() WHERE user_id = ?')) throw new PDOException('Synthetic revocation failure');
        if ($this->suspendBeforeIssue && str_starts_with($query, 'INSERT INTO api_tokens')) {
            $this->suspendBeforeIssue = false;
            $this->exec("UPDATE users SET status = 'suspended' WHERE id = 2");
        }
        if ($this->changePasswordBeforeIssue && str_starts_with($query, 'INSERT INTO api_tokens')) {
            $this->changePasswordBeforeIssue = false;
            $this->prepare('UPDATE users SET password_hash = ? WHERE id = 2')->execute([password_hash('password-concorrente', PASSWORD_DEFAULT)]);
        }
        if ($this->failDeviceColumn && str_contains($query, 'device_name, created_at')) {
            $this->failDeviceColumn = false;
            throw new PDOException('Synthetic legacy device column failure');
        }
        $query = str_replace(['DATE_SUB(NOW(), INTERVAL 24 HOUR)', 'DATE_SUB(NOW(), INTERVAL 2 HOUR)'], ["datetime(NOW(), '-24 hours')", "datetime(NOW(), '-2 hours')"], $query);
        return parent::prepare(str_replace(['FOR UPDATE', 'INSERT IGNORE INTO', 'BINARY password_hash'], ['', 'INSERT OR IGNORE INTO', 'password_hash'], $query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = str_replace('FOR UPDATE', '', $query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

$checks = 0;
function sessionCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function sessionApp(PDO $pdo): Application
{
    $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    $users = new UserRepository($pdo);
    $tokens = new TokenRepository($pdo);
    $roles = new RolePermissionRepository($pdo);
    $logs = new ActivityLogRepository($pdo);
    foreach (['users' => $users, 'tokens' => $tokens, 'roles' => $roles, 'activityLogs' => $logs, 'auth' => new Auth($users, $tokens, $roles), 'authService' => new AuthService($users, $tokens, $logs)] as $property => $value) {
        (new ReflectionProperty($app, $property))->setValue($app, $value);
    }
    return $app;
}

function sessionRequest(string $token, array $data = [], bool $queryToken = false): Request
{
    return new Request('GET', '/api/v1/me', $queryToken ? [] : ['authorization' => 'Bearer ' . $token], $queryToken ? ['token' => $token] : [], $data);
}

function sessionDenied(callable $operation, int $status): void
{
    try {
        $operation();
        throw new RuntimeException('Accesso non bloccato: atteso HTTP ' . $status);
    } catch (HttpException $exception) {
        sessionCheck($exception->status() === $status, 'Status di rifiuto inatteso.');
    }
}

function sessionStatus(PDO $pdo, string $status, int $userId = 2, string $actorToken = 'admin-token'): void
{
    (new UserController(sessionApp($pdo)))->update(sessionRequest($actorToken, ['status' => $status]), ['id' => $userId]);
}

function sessionTokenRows(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM api_tokens ORDER BY id')->fetchAll();
}

$pdo = new SessionSuspensionTestPdo();
foreach (['browser-token', 'mobile-token'] as $token) {
    sessionCheck(sessionApp($pdo)->auth->requireUser(sessionRequest($token))['id'] === 2, 'Sessione attiva disponibile.');
}
sessionCheck(sessionApp($pdo)->auth->requireUser(sessionRequest('browser-token', [], true))['id'] === 2, 'Token in query conservato per i flussi documentali.');
$otherTokens = $pdo->query('SELECT id, user_id, token_hash, revoked_at FROM api_tokens WHERE user_id <> 2 ORDER BY id')->fetchAll();
sessionStatus($pdo, 'suspended');
sessionCheck((new UserRepository($pdo))->findById(2)['status'] === 'suspended', 'Sospensione dal controller applicata.');
sessionCheck((int)$pdo->query('SELECT COUNT(*) FROM api_tokens WHERE user_id = 2 AND revoked_at IS NULL')->fetchColumn() === 0, 'Tutte le sessioni del sospeso revocate.');
sessionCheck($pdo->query("SELECT revoked_at FROM api_tokens WHERE token_hash = 'already-revoked'")->fetchColumn() === '2020-01-01 00:00:00', 'Revoche preesistenti non riscritte.');
$otherAfter = $pdo->query('SELECT id, user_id, token_hash, revoked_at FROM api_tokens WHERE user_id <> 2 ORDER BY id')->fetchAll();
sessionCheck($otherAfter === $otherTokens, 'Sessioni degli altri utenti conservate.');
foreach (['browser-token', 'mobile-token'] as $token) {
    sessionDenied(static fn () => (new AuthController(sessionApp($pdo)))->me(sessionRequest($token)), 401);
    sessionDenied(static fn () => (new ProfileController(sessionApp($pdo)))->show(sessionRequest($token, [], true)), 401);
}
sessionDenied(static fn () => sessionApp($pdo)->authService->login('delegate@example.invalid', 'password-di-prova'), 403);
$before = sessionTokenRows($pdo);
sessionDenied(static fn () => (new TokenRepository($pdo))->create(2, 'suspended-token'), 403);
sessionCheck(sessionTokenRows($pdo) === $before, 'Nessun token emesso a un sospeso.');
sessionStatus($pdo, 'active');
foreach (['browser-token', 'mobile-token'] as $token) {
    sessionDenied(static fn () => sessionApp($pdo)->auth->requireUser(sessionRequest($token)), 401);
}
$login = sessionApp($pdo)->authService->login('delegate@example.invalid', 'password-di-prova', 'nuova-sessione');
$newToken = $login['access_token'];
sessionCheck(sessionApp($pdo)->auth->requireUser(sessionRequest($newToken))['id'] === 2, 'Dopo riattivazione funziona solo il nuovo login.');
sessionCheck((new RolePermissionRepository($pdo))->rolesForUser(2) === ['delegato'], 'Ruoli invariati.');
sessionStatus($pdo, 'active');
sessionCheck(sessionApp($pdo)->auth->requireUser(sessionRequest($newToken))['id'] === 2, 'Salvare un utente gia attivo non disconnette.');

$pdo->exec("UPDATE users SET status = 'suspended' WHERE id = 2");
$before = sessionTokenRows($pdo);
sessionDenied(static fn () => sessionApp($pdo)->auth->requireUser(sessionRequest($newToken)), 401);
sessionCheck(sessionTokenRows($pdo) === $before, 'Il rifiuto di un token legacy non aggiorna last_used_at.');
sessionStatus($pdo, 'active');
sessionDenied(static fn () => sessionApp($pdo)->auth->requireUser(sessionRequest($newToken)), 401);
sessionCheck((int)$pdo->query('SELECT COUNT(*) FROM api_tokens WHERE user_id = 2 AND revoked_at IS NULL')->fetchColumn() === 0, 'Riattivare un sospeso preesistente invalida anche i token legacy.');

$tokens = new TokenRepository($pdo);
$tokens->create(2, 'rollback-token');
$before = sessionTokenRows($pdo);
$pdo->failRevocation = true;
try {
    sessionStatus($pdo, 'suspended');
    throw new RuntimeException('Errore di revoca atteso.');
} catch (PDOException $exception) {
    sessionCheck((new UserRepository($pdo))->findById(2)['status'] === 'active', 'Revoca fallita: sospensione annullata.');
    sessionCheck(sessionTokenRows($pdo) === $before && !$pdo->inTransaction(), 'Rollback completo delle sessioni.');
}
$pdo->failRevocation = false;
sessionDenied(static fn () => sessionStatus($pdo, 'suspended', 1), 409);
sessionCheck($tokens->findValidByHash(hash('sha256', 'admin-token')) !== null, 'Ultimo admin protetto senza revocare la sua sessione.');
sessionDenied(static fn () => sessionStatus($pdo, 'suspended', 3, 'rollback-token'), 403);
sessionCheck((new UserRepository($pdo))->findById(3)['status'] === 'active', 'Non admin non possono sospendere altri utenti.');

$pdo->failDeviceColumn = true;
$tokens->create(2, 'legacy-column-token', 'test');
sessionCheck($tokens->findValidByHash(hash('sha256', 'legacy-column-token')) !== null, 'Compatibilita senza colonna device conservata.');
$tokenCount = (int)$pdo->query('SELECT COUNT(*) FROM api_tokens')->fetchColumn();
$pdo->suspendBeforeIssue = true;
sessionDenied(static fn () => sessionApp($pdo)->authService->login('delegate@example.invalid', 'password-di-prova'), 403);
sessionCheck((int)$pdo->query('SELECT COUNT(*) FROM api_tokens')->fetchColumn() === $tokenCount, 'Il login concorrente non emette una nuova sessione dopo la sospensione.');
sessionCheck($tokens->findValidByHash(hash('sha256', 'rollback-token')) !== null, 'La simulazione del login concorrente non revoca token estranei al suo inserimento.');
sessionCheck((new UserRepository($pdo))->findById(2)['status'] === 'suspended', 'Login controlla lo stato corrente, non soltanto quello letto prima della password.');

(new RolePermissionRepository($pdo))->assignRole(3, 'admin');
$otherLogin = sessionApp($pdo)->authService->login('other@example.invalid', 'password-di-prova');
$otherAdminToken = $otherLogin['access_token'];
sessionStatus($pdo, 'suspended', 1, $otherAdminToken);
sessionDenied(static fn () => sessionApp($pdo)->auth->requireAdmin(sessionRequest('admin-token')), 401);
sessionDenied(static fn () => sessionStatus($pdo, 'suspended', 3, $otherAdminToken), 409);
sessionCheck(sessionApp($pdo)->auth->requireAdmin(sessionRequest($otherAdminToken))['id'] === 3, 'Secondo admin mantiene accesso e protezione.');

$pdo = new SessionSuspensionTestPdo();
$users = new UserRepository($pdo);
$originalHash = $users->findByEmail('delegate@example.invalid')['password_hash'];
$before = sessionTokenRows($pdo);
foreach (['', '   ', 'breve'] as $password) {
    sessionDenied(static fn () => (new ProfileController(sessionApp($pdo)))->password(sessionRequest('browser-token', ['password' => $password])), 422);
}
sessionCheck($users->findByEmail('delegate@example.invalid')['password_hash'] === $originalHash, 'Password rifiutata: hash invariato.');
$tokens = new TokenRepository($pdo);
sessionCheck($tokens->findValidByHash(hash('sha256', 'browser-token')) !== null, 'Password rifiutata: sessione ancora valida.');
$users->update(2, ['name' => 'Solo anagrafica', 'password' => '   ', 'status' => 'active']);
sessionCheck($tokens->findValidByHash(hash('sha256', 'mobile-token')) !== null, 'Password vuota nel salvataggio admin non revoca sessioni.');
(new ProfileController(sessionApp($pdo)))->password(sessionRequest('browser-token', ['password' => 'nuova-password-profilo']));
sessionCheck(password_verify('nuova-password-profilo', $users->findByEmail('delegate@example.invalid')['password_hash']), 'Cambio password da Profilo riuscito.');
foreach (['browser-token', 'mobile-token'] as $token) sessionDenied(static fn () => sessionApp($pdo)->auth->requireUser(sessionRequest($token)), 401);
sessionCheck($tokens->findValidByHash(hash('sha256', 'admin-token')) !== null && $tokens->findValidByHash(hash('sha256', 'other-token')) !== null, 'Cambio personale non disconnette gli altri utenti.');
sessionDenied(static fn () => sessionApp($pdo)->authService->login('delegate@example.invalid', 'password-di-prova'), 401);
$login = sessionApp($pdo)->authService->login('delegate@example.invalid', 'nuova-password-profilo');
sessionCheck(sessionApp($pdo)->auth->requireUser(sessionRequest($login['access_token']))['id'] === 2, 'Nuovo login con password cambiata riuscito.');
(new UserController(sessionApp($pdo)))->update(sessionRequest('admin-token', ['password' => 'password-reset-admin', 'status' => 'active']), ['id' => 2]);
sessionDenied(static fn () => sessionApp($pdo)->auth->requireUser(sessionRequest($login['access_token'])), 401);
sessionCheck(sessionApp($pdo)->auth->requireAdmin(sessionRequest('admin-token'))['id'] === 1, 'Reset di un altro utente non disconnette admin.');
sessionCheck(password_verify('password-reset-admin', $users->findByEmail('delegate@example.invalid')['password_hash']), 'Reset amministrativo riuscito.');
sessionDenied(static fn () => sessionApp($pdo)->authService->login('delegate@example.invalid', 'nuova-password-profilo'), 401);
$login = sessionApp($pdo)->authService->login('delegate@example.invalid', 'password-reset-admin');
sessionCheck(sessionApp($pdo)->auth->requireUser(sessionRequest($login['access_token']))['id'] === 2, 'Nuovo login dopo reset admin riuscito.');
(new UserController(sessionApp($pdo)))->update(sessionRequest('admin-token', ['password' => 'password-admin-personale']), ['id' => 1]);
sessionDenied(static fn () => sessionApp($pdo)->auth->requireAdmin(sessionRequest('admin-token')), 401);
$adminLogin = sessionApp($pdo)->authService->login('admin@example.invalid', 'password-admin-personale');
sessionCheck(sessionApp($pdo)->auth->requireAdmin(sessionRequest($adminLogin['access_token']))['id'] === 1, 'Ultimo admin puo cambiare password e rientrare.');
sessionCheck($users->findById(1)['status'] === 'active' && (new RolePermissionRepository($pdo))->rolesForUser(1) === ['admin'], 'Cambio password non altera stato o ruolo ultimo admin.');

$pdo = new SessionSuspensionTestPdo();
$originalHash = (new UserRepository($pdo))->findByEmail('delegate@example.invalid')['password_hash'];
$before = sessionTokenRows($pdo);
$pdo->failRevocation = true;
try {
    (new ProfileController(sessionApp($pdo)))->password(sessionRequest('browser-token', ['password' => 'password-da-annullare']));
    throw new RuntimeException('Errore di revoca atteso al cambio password.');
} catch (PDOException $exception) {
    sessionCheck((new UserRepository($pdo))->findByEmail('delegate@example.invalid')['password_hash'] === $originalHash, 'Errore di revoca: vecchia password ripristinata.');
    sessionCheck((int)$pdo->query('SELECT COUNT(*) FROM api_tokens WHERE user_id = 2 AND revoked_at IS NULL')->fetchColumn() === 2 && !$pdo->inTransaction(), 'Errore di revoca: sessioni e transazione ripristinate.');
}
$pdo->failRevocation = false;
$tokens = new TokenRepository($pdo);
$pdo->failDeviceColumn = true;
$tokens->create(2, 'password-fallback-token', 'test', $originalHash);
sessionCheck($tokens->findValidByHash(hash('sha256', 'password-fallback-token')) !== null, 'Fallback colonna device conserva il controllo credenziali.');
$countBeforeLogin = (int)$pdo->query('SELECT COUNT(*) FROM api_tokens')->fetchColumn();
$pdo->changePasswordBeforeIssue = true;
sessionDenied(static fn () => sessionApp($pdo)->authService->login('delegate@example.invalid', 'password-di-prova'), 403);
sessionCheck((int)$pdo->query('SELECT COUNT(*) FROM api_tokens')->fetchColumn() === $countBeforeLogin, 'Login con vecchia password concorrente non emette token dopo il cambio.');
sessionDenied(static fn () => (new TokenRepository($pdo))->create(2, 'stale-password-token', 'test', $originalHash), 403);
sessionCheck((int)$pdo->query('SELECT COUNT(*) FROM api_tokens')->fetchColumn() === $countBeforeLogin, 'Hash superato non puo creare sessioni.');

echo "OK: {$checks} controlli sessioni, endpoint utenti/profilo/me, sospensione, password/reset, rollback, login concorrente e ultimo admin. Solo SQLite in memoria.\n";
