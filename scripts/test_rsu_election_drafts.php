<?php

declare(strict_types=1);

use App\Controllers\Api\RsuElectionController;
use App\Core\Application;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\RolePermissionRepository;
use App\Repositories\RsuElectionDraftRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;
use App\Services\ConfigurationCatalogService;
use App\Services\RsuElectionDraftService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

final class ElectionDraftTestPdo extends PDO
{
    public string $now = '2026-11-05 12:00:00';

    public function __construct()
    {
        parent::__construct('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $this->sqliteCreateFunction('NOW', fn (): string => $this->now);
        $this->exec('PRAGMA foreign_keys = ON');
        $this->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, first_name TEXT, last_name TEXT, phone TEXT, mobile TEXT, city TEXT, country TEXT, union_code TEXT, union_logo_stored_name TEXT, status TEXT, created_at TEXT, updated_at TEXT)');
        $this->exec('CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT)');
        $this->exec('CREATE TABLE role_user (user_id INTEGER, role_id INTEGER)');
        $this->exec('CREATE TABLE api_tokens (id INTEGER PRIMARY KEY, user_id INTEGER, token_hash TEXT, created_at TEXT, last_used_at TEXT, revoked_at TEXT)');
        $this->exec('CREATE TABLE rsu_election_drafts (user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, payload_json TEXT NOT NULL, created_at TEXT, updated_at TEXT)');
        $this->exec("INSERT INTO users (id, name, status) VALUES (1, 'Admin uno', 'active'), (2, 'Admin due', 'active'), (3, 'Delegato', 'active'), (4, 'RLS', 'active'), (5, 'Membro', 'active'), (6, 'Admin sospeso', 'suspended')");
        $this->exec("INSERT INTO roles VALUES (1, 'admin'), (2, 'delegato'), (3, 'rls'), (4, 'membro')");
        $this->exec('INSERT INTO role_user VALUES (1, 1), (2, 1), (3, 2), (4, 3), (5, 4), (6, 1)');
        foreach (range(1, 6) as $userId) {
            $this->prepare('INSERT INTO api_tokens (user_id, token_hash, created_at) VALUES (?, ?, NOW())')->execute([$userId, hash('sha256', 'test-' . $userId)]);
        }
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $query = str_replace(
            ['ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), updated_at = NOW()', 'DATE_SUB(NOW(), INTERVAL 24 HOUR)', 'DATE_SUB(NOW(), INTERVAL 2 HOUR)'],
            ['ON CONFLICT(user_id) DO UPDATE SET payload_json = excluded.payload_json, updated_at = NOW()', "datetime(NOW(), '-24 hours')", "datetime(NOW(), '-2 hours')"],
            $query
        );
        return parent::prepare($query, $options);
    }
}

$checks = 0;
function draftCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function draftController(PDO $pdo): RsuElectionController
{
    $database = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(Database::class, 'pdo'))->setValue($database, $pdo);
    $roles = new RolePermissionRepository($pdo);
    $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    foreach (['database' => $database, 'roles' => $roles, 'auth' => new Auth(new UserRepository($pdo), new TokenRepository($pdo), $roles)] as $property => $value) {
        (new ReflectionProperty(Application::class, $property))->setValue($app, $value);
    }
    return new RsuElectionController($app);
}

function draftRequest(?int $userId, array $body = [], array $query = []): Request
{
    return new Request('POST', '/api/v1/rsu-elections/draft', $userId === null ? [] : ['authorization' => 'Bearer test-' . $userId], $query, $body);
}

function draftResponse(object $response): mixed
{
    ob_start();
    $response->send();
    $payload = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
    return $payload['data'];
}

function draftDenied(callable $operation, int $status): void
{
    try {
        $operation();
        throw new RuntimeException('Rifiuto atteso: ' . $status);
    } catch (HttpException $exception) {
        draftCheck($exception->status() === $status, 'Status di rifiuto corretto.');
    }
}

$pdo = new ElectionDraftTestPdo();
$service = new RsuElectionDraftService(new RsuElectionDraftRepository($pdo));
$form = ['electors' => '154', 'voters' => '130', 'valid_votes' => '130', 'blank_votes' => '0', 'null_votes' => '', 'seats' => '3', 'lists' => []];
foreach (['CGIL', 'CISL'] as $listIndex => $name) {
    $candidates = [];
    foreach (range(1, 5) as $index) $candidates[] = ['name' => $index === 5 ? 'Nome con accento à <test>' : 'Candidato ' . $index, 'preferences' => $index === 1 ? '0' : (string)$index];
    $form['lists'][] = ['name' => $name, 'votes' => $name === 'CGIL' ? '108' : '22', 'presentation_order' => (string)($listIndex + 1), 'candidates' => $candidates];
}
draftCheck($service->load(1) === null, 'Account senza dati restituisce null.');
$saved = draftResponse(draftController($pdo)->saveDraft(draftRequest(1, $form + ['user_id' => 2])));
draftCheck($saved['form'] === $form, 'Tutti i campi, zero, vuoti e cinque candidati conservati.');
draftCheck($service->load(2) === null, 'Il mittente non puo scegliere un altro proprietario.');
$loaded = draftResponse(draftController($pdo)->draft(draftRequest(1, [], ['user_id' => 2])));
draftCheck($loaded === $saved, 'Un nuovo accesso ripristina solo i dati del proprio account.');
$otherForm = $form;
$otherForm['electors'] = '200';
draftResponse(draftController($pdo)->saveDraft(draftRequest(2, $otherForm)));
draftCheck($service->load(1)['form'] === $form, 'Salvataggi di altri admin non sovrascrivono il primo.');
draftCheck($service->load(2)['form'] === $otherForm, 'Secondo account conserva i propri dati.');
$pdo->now = '2026-11-05 12:01:00';
$form['lists'][1]['candidates'][4]['preferences'] = '22';
$service->save(1, $form);
draftCheck((int)$pdo->query('SELECT COUNT(*) FROM rsu_election_drafts')->fetchColumn() === 2, 'Una sola bozza per account.');
draftCheck($service->load(1)['updated_at'] === $pdo->now, 'Aggiornamento della data di salvataggio.');
draftCheck($pdo->query('SELECT created_at FROM rsu_election_drafts WHERE user_id = 1')->fetchColumn() === '2026-11-05 12:00:00', 'Creazione conservata negli aggiornamenti.');

$invalidForms = [];
$invalidForms[] = array_replace($form, ['electors' => []]);
$invalidForms[] = array_replace($form, ['voters' => '-1']);
$invalidForms[] = array_replace($form, ['seats' => '0']);
$invalidForms[] = array_replace($form, ['lists' => 'invalid']);
$invalidForms[] = array_replace($form, ['lists' => [$form['lists'][0], $form['lists'][0]]]);
$invalid = $form;
$invalid['lists'][0]['candidates'][] = ['name' => 'Sesto', 'preferences' => '0'];
$invalidForms[] = $invalid;
$invalid['lists'][0]['candidates'] = [['name' => [], 'preferences' => '0']];
$invalidForms[] = $invalid;
$invalid['lists'][0]['candidates'] = [['name' => str_repeat('à', 161), 'preferences' => '0']];
$invalidForms[] = $invalid;
$invalid['lists'][0]['candidates'] = [['name' => 'Nome', 'preferences' => '-1']];
$invalidForms[] = $invalid;
foreach ($invalidForms as $invalid) {
    draftDenied(fn () => $service->save(1, $invalid), 422);
    draftCheck($service->load(1)['form'] === $form, 'Input non valido non modifica i dati precedenti.');
}
foreach ([null => 401, 3 => 403, 4 => 403, 5 => 403, 6 => 401] as $userId => $status) {
    $userId = $userId === '' ? null : (int)$userId;
    draftDenied(fn () => draftController($pdo)->draft(draftRequest($userId)), $status);
    draftDenied(fn () => draftController($pdo)->saveDraft(draftRequest($userId, $form)), $status);
}
draftCheck(in_array('rsu_election_drafts', (new ReflectionClass(ConfigurationCatalogService::class))->getConstant('SHARED'), true), 'Dati personali non duplicati nelle configurazioni.');
$pdo->exec('DELETE FROM users WHERE id = 2');
draftCheck($service->load(2) === null, 'Eliminazione account elimina soltanto la propria bozza.');
draftCheck($service->load(1)['form'] === $form, 'Altri account non modificati dalla cancellazione.');
echo "OK: {$checks} controlli su salvataggio, ripristino, proprieta, permessi e validazione. Solo database in memoria.\n";
