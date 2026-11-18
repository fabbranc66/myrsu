<?php

declare(strict_types=1);

use App\Controllers\Api\RoleController;
use App\Core\Application;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\ActivityLogRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

final class SessionPolicyTestPdo extends PDO
{
    public string $now = '2026-10-25 12:00:00';
    public bool $failRevocation = false;
    public bool $expireOnTouch = false;

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->sqliteCreateFunction('NOW', fn (): string => $this->now);
        $this->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, first_name TEXT, last_name TEXT, phone TEXT, mobile TEXT, city TEXT, country TEXT, union_code TEXT, union_logo_stored_name TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT UNIQUE)');
        $this->exec('CREATE TABLE role_user (user_id INTEGER, role_id INTEGER, PRIMARY KEY(user_id, role_id))');
        $this->exec('CREATE TABLE permissions (id INTEGER PRIMARY KEY, name TEXT)');
        $this->exec('CREATE TABLE permission_role (permission_id INTEGER, role_id INTEGER)');
        $this->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY, user_id INTEGER, action TEXT, metadata_json TEXT, created_at TEXT)');
        $this->exec('CREATE TABLE api_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, token_hash TEXT UNIQUE, device_name TEXT, last_used_at TEXT, revoked_at TEXT, created_at TEXT)');
        $this->exec("INSERT INTO users (id, name, status) VALUES (1, 'Admin test', 'active'), (2, 'Delegato test', 'active'), (3, 'Altro test', 'active')");
        $this->exec("INSERT INTO roles VALUES (1, 'admin'), (2, 'delegato'), (3, 'rls'), (4, 'membro')");
        $this->exec('INSERT INTO role_user VALUES (1, 1), (2, 2), (3, 4)');
        $this->exec("INSERT INTO permissions VALUES (1, 'roles.manage')");
        $this->exec('INSERT INTO permission_role VALUES (1, 1)');
        foreach ([['admin', 1], ['browser', 2], ['mobile', 2], ['other', 3]] as [$token, $userId]) {
            (new TokenRepository($this))->create($userId, $token, 'test');
        }
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failRevocation && str_contains($query, 'SET revoked_at = NOW() WHERE user_id = ?')) throw new PDOException('Synthetic role revocation failure');
        if ($this->expireOnTouch && str_starts_with($query, 'UPDATE api_tokens SET last_used_at')) {
            $this->expireOnTouch = false;
            $this->now = '2026-10-25 12:00:01';
        }
        return parent::prepare($this->adapt($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = $this->adapt($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    private function adapt(string $query): string
    {
        return str_replace(
            ['DATE_SUB(NOW(), INTERVAL 24 HOUR)', 'DATE_SUB(NOW(), INTERVAL 2 HOUR)', 'FOR UPDATE', 'INSERT IGNORE INTO'],
            ["datetime(NOW(), '-24 hours')", "datetime(NOW(), '-2 hours')", '', 'INSERT OR IGNORE INTO'],
            $query
        );
    }
}

$checks = 0;
function policyCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function policyRequest(string $token, array $body = [], bool $queryToken = false): Request
{
    return new Request('POST', '/api/v1/users/2/roles', $queryToken ? [] : ['authorization' => 'Bearer ' . $token], $queryToken ? ['token' => $token] : [], $body);
}

function policyAuth(PDO $pdo): Auth
{
    return new Auth(new UserRepository($pdo), new TokenRepository($pdo), new RolePermissionRepository($pdo));
}

function policyDenied(callable $operation, int $status): void
{
    try {
        $operation();
        throw new RuntimeException('Rifiuto HTTP atteso: ' . $status);
    } catch (HttpException $exception) {
        policyCheck($exception->status() === $status, 'Status di rifiuto corretto.');
    }
}

function policyRoleController(PDO $pdo): RoleController
{
    $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    foreach (['auth' => policyAuth($pdo), 'users' => new UserRepository($pdo), 'roles' => new RolePermissionRepository($pdo), 'activityLogs' => new ActivityLogRepository($pdo)] as $property => $value) {
        (new ReflectionProperty($app, $property))->setValue($app, $value);
    }
    return new RoleController($app);
}

function policySnapshot(PDO $pdo): array
{
    return [$pdo->query('SELECT * FROM role_user ORDER BY user_id, role_id')->fetchAll(), $pdo->query('SELECT * FROM api_tokens ORDER BY id')->fetchAll()];
}

foreach ([
    ['2026-10-25 12:00:00', null, true],
    ['2026-10-25 10:00:01', null, true],
    ['2026-10-25 10:00:00', null, false],
    ['2026-10-25 09:59:59', null, false],
    ['2026-10-25 09:00:00', '2026-10-25 10:00:00', false],
    ['2026-10-24 12:00:01', '2026-10-25 12:00:00', true],
    ['2026-10-24 12:00:00', '2026-10-25 12:00:00', false],
    ['2026-10-24 11:59:59', '2026-10-25 12:00:00', false],
] as [$created, $lastUsed, $valid]) {
    $pdo = new SessionPolicyTestPdo();
    $tokens = new TokenRepository($pdo);
    $hash = hash('sha256', 'browser');
    $pdo->prepare('UPDATE api_tokens SET created_at = ?, last_used_at = ? WHERE token_hash = ?')->execute([$created, $lastUsed, $hash]);
    policyCheck(($tokens->findValidByHash($hash) !== null) === $valid, 'Limite temporale corretto: ' . $created);
    $tokenId = (int)$pdo->query("SELECT id FROM api_tokens WHERE user_id = 2 ORDER BY id LIMIT 1")->fetchColumn();
    policyCheck($tokens->touch($tokenId) === $valid, 'Touch non riattiva token scaduti.');
    $row = $pdo->query('SELECT created_at, last_used_at FROM api_tokens WHERE id = ' . $tokenId)->fetch();
    policyCheck($row['created_at'] === $created, 'L attivita non prolunga la durata massima.');
    if (!$valid) policyCheck($row['last_used_at'] === $lastUsed, 'Nessuna attivita aggiornata dopo la scadenza.');
}

$pdo = new SessionPolicyTestPdo();
$tokens = new TokenRepository($pdo);
$pdo->now = '2026-10-25 13:59:59';
policyCheck(policyAuth($pdo)->requireUser(policyRequest('browser'))['id'] === 2, 'Attivita entro due ore prolunga il limite di inattivita.');
$pdo->now = '2026-10-25 15:59:58';
policyCheck(policyAuth($pdo)->requireUser(policyRequest('browser', [], true))['id'] === 2, 'Token in query segue la stessa policy.');
$pdo->now = '2026-10-26 12:00:00';
policyDenied(static fn () => policyAuth($pdo)->requireUser(policyRequest('browser')), 401);

$pdo = new SessionPolicyTestPdo();
$pdo->prepare('UPDATE api_tokens SET last_used_at = ? WHERE token_hash = ?')->execute(['2026-10-25 10:00:01', hash('sha256', 'browser')]);
$pdo->expireOnTouch = true;
policyDenied(static fn () => policyAuth($pdo)->requireUser(policyRequest('browser')), 401);
policyCheck($pdo->query('SELECT last_used_at FROM api_tokens WHERE user_id = 2 ORDER BY id LIMIT 1')->fetchColumn() === '2026-10-25 10:00:01', 'Scadenza tra lettura e touch non rinnova il token.');

$pdo = new SessionPolicyTestPdo();
$response = policyRoleController($pdo)->replaceUserRoles(policyRequest('admin', ['roles' => ['delegato', 'rls']]), ['id' => 2]);
policyCheck((new ReflectionProperty($response, 'payload'))->getValue($response)['data']['sessions_revoked'] === true, 'Endpoint segnala la revoca al cambio effettivo dei ruoli.');
foreach (['browser', 'mobile'] as $token) policyDenied(static fn () => policyAuth($pdo)->requireUser(policyRequest($token)), 401);
policyCheck(policyAuth($pdo)->requireUser(policyRequest('admin'))['id'] === 1 && policyAuth($pdo)->requireUser(policyRequest('other'))['id'] === 3, 'Le altre sessioni restano valide.');
$tokens = new TokenRepository($pdo);
$tokens->create(2, 'new-role-session');
$roles = new RolePermissionRepository($pdo);
policyCheck($roles->replaceUserRoles(2, ['rls', 'delegato', 'delegato']) === false, 'Ordine e duplicati non sono un cambio ruolo.');
$roles->assignRole(2, 'delegato');
policyCheck(policyAuth($pdo)->requireUser(policyRequest('new-role-session'))['id'] === 2, 'Stessi ruoli non disconnettono.');
policyDenied(static fn () => policyAuth($pdo)->requireUser(policyRequest('browser')), 401);
$roles->assignRole(2, 'membro');
policyDenied(static fn () => policyAuth($pdo)->requireUser(policyRequest('new-role-session')), 401);
$tokens->create(2, 'rollback-role-session');
$before = policySnapshot($pdo);
$pdo->failRevocation = true;
try {
    $roles->replaceUserRoles(2, ['rls']);
    throw new RuntimeException('Revoca fallita attesa.');
} catch (PDOException $exception) {
    policyCheck(policySnapshot($pdo) === $before && !$pdo->inTransaction(), 'Revoca fallita annulla anche il cambio ruolo.');
}
$pdo->failRevocation = false;
policyCheck($roles->replaceUserRoles(2, []) === true, 'Rimozione di tutti i ruoli conta come modifica.');
policyDenied(static fn () => policyAuth($pdo)->requireUser(policyRequest('rollback-role-session')), 401);

$pdo = new SessionPolicyTestPdo();
$roles = new RolePermissionRepository($pdo);
$roles->assignRole(3, 'admin');
(new TokenRepository($pdo))->create(3, 'second-admin');
policyRoleController($pdo)->replaceUserRoles(policyRequest('admin', ['roles' => ['delegato']]), ['id' => 1]);
policyDenied(static fn () => policyAuth($pdo)->requireUser(policyRequest('admin')), 401);
policyCheck(policyAuth($pdo)->requireAdmin(policyRequest('second-admin'))['id'] === 3, 'Secondo admin conserva l accesso.');
$before = policySnapshot($pdo);
policyDenied(static fn () => $roles->replaceUserRoles(3, ['membro']), 409);
policyCheck(policySnapshot($pdo) === $before, 'Blocco ultimo admin non revoca sessioni valide.');

echo "OK: {$checks} controlli scadenze 2h/24h, soglie esatte, token in query, anti-rinnovo, endpoint ruoli, revoca, rollback e ultimo admin. Solo SQLite in memoria.\n";
