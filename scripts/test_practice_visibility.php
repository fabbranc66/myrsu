<?php

declare(strict_types=1);

use App\Controllers\Api\PracticeController;
use App\Controllers\Api\CallController;
use App\Controllers\Api\EmailController;
use App\Core\Application;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\DocumentFolderRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\CallRepository;
use App\Repositories\EmailRepository;
use App\Repositories\PracticeRepository;
use App\Repositories\PracticeTimelineRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\SavedConfigurationRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;
use App\Services\ArchiveSharingService;
use App\Services\ArchiveSourceService;
use App\Services\ConfigurationContextService;
use App\Services\DocumentFolderAccessService;
use App\Services\PracticeAccessService;
use App\Services\PracticeService;
use App\Services\PracticeDossierService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

final class VisibilityTestStatement extends PDOStatement
{
    private array $rows = [];

    public function __construct(private readonly VisibilityTestPdo $database, private readonly string $sql)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->rows = $this->database->rows($this->sql, $params ?? []);
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        return $mode === PDO::FETCH_COLUMN ? array_map(static fn (array $row): mixed => reset($row), $this->rows) : $this->rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->fetch();
        return $row === false ? false : array_values($row)[$column];
    }
}

final class VisibilityTestPdo extends PDO
{
    public array $practices;
    public bool $archivesInstalled = false;
    public array $archiveLinks = [];
    public array $documentLinks = [11 => [110], 12 => [120, 121]];
    public array $documents = [
        110 => ['id' => 110, 'visibility' => 'rsu', 'uploaded_by' => 2, 'original_name' => 'Documento delegati.pdf', 'conversion_status' => 'ready'],
        120 => ['id' => 120, 'visibility' => 'rsu', 'uploaded_by' => 2, 'original_name' => 'Documento trasferito.pdf', 'conversion_status' => 'ready'],
        121 => ['id' => 121, 'visibility' => 'creator', 'uploaded_by' => 2, 'original_name' => 'Documento riservato.pdf', 'conversion_status' => 'ready'],
        130 => ['id' => 130, 'visibility' => 'rsu', 'uploaded_by' => 2, 'original_name' => 'Documento non condiviso.pdf', 'conversion_status' => 'ready'],
    ];
    public array $roles = [1 => ['admin'], 2 => ['delegato'], 3 => ['delegato'], 4 => ['rls'], 5 => ['worker'], 6 => ['delegato', 'rls']];

    public function __construct()
    {
        $this->practices = [
            11 => ['id' => 11, 'code' => 'PRA-2026-0011', 'title' => 'Riservata', 'visibility' => 'authorized', 'assigned_user_id' => 2, 'status' => 'new'],
            12 => ['id' => 12, 'code' => 'PRA-2026-0012', 'title' => 'Delegati', 'visibility' => 'operators', 'assigned_user_id' => null, 'status' => 'new'],
            13 => ['id' => 13, 'code' => 'PRA-2026-0013', 'title' => 'RLS assegnatario', 'visibility' => 'authorized', 'assigned_user_id' => 4, 'status' => 'closed'],
            14 => ['id' => 14, 'code' => 'PRA-2026-0014', 'title' => 'Senza assegnatario', 'visibility' => 'authorized', 'assigned_user_id' => null, 'status' => 'new'],
            15 => ['id' => 15, 'code' => 'PRA-2026-0015', 'title' => 'Valore precedente', 'visibility' => 'public_summary', 'assigned_user_id' => 2, 'status' => 'new'],
        ];
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new VisibilityTestStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $statement = $this->prepare($query);
        $statement->execute();
        return $statement;
    }

    public function rows(string $sql, array $parameters): array
    {
        if (!str_starts_with(ltrim($sql), 'SELECT')) throw new RuntimeException('Il test non deve scrivere sul database.');
        if (str_contains($sql, 'FROM roles r')) return array_map(static fn (string $name): array => ['name' => $name], $this->roles[$parameters[0]] ?? []);
        if (str_contains($sql, 'FROM roles ORDER')) return array_map(static fn (string $name, int $index): array => ['id' => $index + 1, 'name' => $name], ['admin', 'delegato', 'rls', 'worker'], range(0, 3));
        if (str_contains($sql, 'information_schema')) return [['count' => $this->archivesInstalled ? 2 : 0]];
        if (str_contains($sql, 'FROM practice_archive_links')) return array_values(array_filter($this->archiveLinks, static fn (array $link): bool => $link['configuration_id'] === $parameters[0] && (!isset($parameters[1]) || $link['practice_id'] === $parameters[1])));
        if (str_contains($sql, 'FROM practices p') && str_contains($sql, 'WHERE p.id = ?')) return isset($this->practices[$parameters[0]]) ? [$this->practices[$parameters[0]]] : [];
        if (str_contains($sql, 'FROM practices')) return array_values(array_filter($this->practices, static fn (array $practice): bool => !str_contains($sql, 'status NOT IN') || $practice['status'] !== 'closed'));
        if (str_contains($sql, 'FROM document_folders f')) return [
            ['id' => 20, 'parent_id' => null, 'inherit_permissions' => 1, 'system_key' => 'practices', 'practice_id' => null],
            ['id' => 21, 'parent_id' => 20, 'inherit_permissions' => 1, 'system_key' => 'practice:11', 'practice_id' => 11, 'practice_visibility' => 'authorized', 'practice_assigned_user_id' => 2],
            ['id' => 22, 'parent_id' => 21, 'inherit_permissions' => 1, 'system_key' => null, 'practice_id' => null],
        ];
        if (str_contains($sql, 'AS document_id, p.visibility')) return [['practice_id' => 11, 'document_id' => 100, 'visibility' => 'authorized', 'assigned_user_id' => 2]];
        if (str_contains($sql, 'AS document_id FROM practice_links')) return array_map(static fn (int $id): array => ['document_id' => $id], $this->documentLinks[$parameters[0]] ?? []);
        if (str_contains($sql, 'FROM documents WHERE id = ?')) return isset($this->documents[$parameters[0]]) ? [$this->documents[$parameters[0]]] : [];
        if (str_contains($sql, 'FROM calls_log') || str_contains($sql, 'FROM emails e')) return [['id' => 701, 'practice_id' => 11]];
        if (str_contains($sql, 'FROM email_attachments')) return [['id' => 702, 'email_id' => 701]];
        return [];
    }
}

function setVisibilityProperty(object $object, string $name, mixed $value): void
{
    (new ReflectionProperty($object, $name))->setValue($object, $value);
}

function visibilityCheck(bool $valid, string $message): void
{
    global $checks;
    if (!$valid) throw new RuntimeException($message);
    $checks++;
}

function visibilityDenied(callable $callback, int $status = 403): void
{
    try {
        $callback();
    } catch (HttpException $exception) {
        visibilityCheck($exception->status() === $status, 'Status di accesso non corretto.');
        return;
    }
    throw new RuntimeException('Accesso riservato non bloccato.');
}

$checks = 0;
$database = new VisibilityTestPdo();
$roles = new RolePermissionRepository($database);
$practices = new PracticeRepository($database);
$access = new PracticeAccessService($roles);
$expectedIds = [1 => [11, 12, 13, 14, 15], 2 => [11, 12], 3 => [12], 4 => [13], 5 => [], 6 => [12]];
foreach ($expectedIds as $userId => $expected) {
    visibilityCheck(array_column($access->filter(array_values($database->practices), ['id' => $userId]), 'id') === $expected, 'Matrice visibilità errata per utente ' . $userId);
}
visibilityCheck(!$access->allows($database->practices[12], null), 'Ospite non autorizzato.');
visibilityDenied(fn () => $access->assertLinked(['practice_id' => 11], ['id' => 3], $practices));
visibilityDenied(fn () => $access->assertLinked(['practice_id' => 999], ['id' => 1], $practices), 404);
$access->assertLinked(['practice_id' => null], ['id' => 3], $practices);
$items = [['id' => 1, 'practice_id' => 11], ['id' => 2, 'practice_id' => 12], ['id' => 3, 'practice_id' => null]];
visibilityCheck(array_column($access->filterLinked($items, ['id' => 3], $practices), 'id') === [2, 3], 'Email e telefonate riservate filtrate.');

$application = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
foreach (['roles' => $roles, 'practices' => $practices, 'practiceAccess' => $access, 'practiceTimeline' => new PracticeTimelineRepository($database), 'calls' => new CallRepository($database), 'emails' => new EmailRepository($database)] as $property => $value) setVisibilityProperty($application, $property, $value);
$context = (new ReflectionClass(ConfigurationContextService::class))->newInstanceWithoutConstructor();
setVisibilityProperty($context, 'repository', new SavedConfigurationRepository($database));
setVisibilityProperty($context, 'current', null);
setVisibilityProperty($application, 'configurationContext', $context);
$controller = new PracticeController($application);
foreach ($expectedIds as $userId => $expected) {
    $auth = new Auth(new UserRepository($database), new TokenRepository($database), $roles);
    setVisibilityProperty($auth, 'resolved', true);
    setVisibilityProperty($auth, 'user', ['id' => $userId]);
    $appForUser = clone $application;
    setVisibilityProperty($appForUser, 'auth', $auth);
    $userController = new PracticeController($appForUser);
    if ($userId === 5) {
        visibilityDenied(fn () => $userController->index(new Request('GET', '/', [], ['scope' => 'all'], [])));
        continue;
    }
    foreach (['all', 'open'] as $scope) {
        $response = $userController->index(new Request('GET', '/', [], $scope === 'all' ? ['scope' => 'all'] : [], []));
        $payload = (new ReflectionProperty($response, 'payload'))->getValue($response);
        $visible = $scope === 'all' ? $expected : array_values(array_diff($expected, [13]));
        visibilityCheck(array_column($payload['data'], 'id') === $visible, 'Elenco API non filtrato: ' . $userId . '/' . $scope);
    }
    if ($userId === 3 || $userId === 4) {
        foreach (['show', 'update', 'addNote', 'addCcnlLink', 'showCcnlLink', 'deleteCcnlLink'] as $method) {
            visibilityDenied(fn () => $userController->$method(new Request('POST', '/', [], [], ['body' => 'Test']), ['id' => 11, 'linkId' => 1]));
        }
        foreach (['link', 'unlink'] as $method) visibilityDenied(fn () => $userController->$method(new Request('POST', '/', [], [], ['practice_id' => 11, 'entity_type' => 'document', 'entity_id' => 100])));
        $callController = new CallController($appForUser);
        foreach (['show', 'update', 'destroy'] as $method) visibilityDenied(fn () => $callController->$method(new Request('POST', '/', [], [], []), ['id' => 701]));
        $response = $callController->index(new Request('GET', '/', [], [], []));
        visibilityCheck((new ReflectionProperty($response, 'payload'))->getValue($response)['data'] === [], 'Elenco telefonate riservate.');
        $emailController = new EmailController($appForUser);
        foreach (['show', 'update', 'manage', 'unlinkPractice', 'addNote', 'destroy'] as $method) visibilityDenied(fn () => $emailController->$method(new Request('POST', '/', [], [], []), ['id' => 701]));
        visibilityDenied(fn () => $emailController->attachment(new Request('GET', '/', [], [], []), ['id' => 702]));
        $response = $emailController->index(new Request('GET', '/', [], [], []));
        visibilityCheck((new ReflectionProperty($response, 'payload'))->getValue($response)['data'] === [], 'Elenco email riservate.');
    } else {
        $userController->show(new Request('GET', '/', [], [], []), ['id' => $userId === 1 ? 14 : 12]);
        visibilityCheck(true, 'Dettaglio autorizzato disponibile.');
    }
}

$source = (new ReflectionClass(ArchiveSourceService::class))->newInstanceWithoutConstructor();
setVisibilityProperty($source, 'configuration', ['id' => 8, 'name' => 'Archivio']);
setVisibilityProperty($source, 'practices', $practices);
setVisibilityProperty($source, 'pdo', $database);
$folderAccess = new DocumentFolderAccessService(new DocumentFolderRepository($database), $roles, dirname(__DIR__));
setVisibilityProperty($source, 'documents', new DocumentRepository($database));
setVisibilityProperty($source, 'access', $folderAccess);
$archive = (new ReflectionClass(ArchiveSharingService::class))->newInstanceWithoutConstructor();
setVisibilityProperty($archive, 'app', $application);
setVisibilityProperty($archive, 'target', 9);
setVisibilityProperty($archive, 'sources', [8 => $source]);
setVisibilityProperty($archive, 'grants', [['source_configuration_id' => 8, 'entity_type' => 'practice', 'entity_id' => 11], ['source_configuration_id' => 8, 'entity_type' => 'practice', 'entity_id' => 12]]);
visibilityCheck(array_column($archive->shared('practice', ['id' => 3]), 'id') === [12], 'Condivisioni non devono scavalcare la riservatezza.');
visibilityCheck(array_column($archive->shared('practice', ['id' => 2]), 'id') === [11, 12], 'Assegnatario vede la pratica condivisa.');
visibilityDenied(fn () => $archive->item(8, 'practice', 11, ['id' => 3]));
$availableDocuments = new ReflectionMethod($archive, 'availableDocumentIds');
visibilityCheck($availableDocuments->invoke($archive, 8, ['id' => 3]) === [110, 120, 121], 'La pratica condivisa riservata non deve nascondere i documenti candidati: vale la loro visibilità.');
visibilityCheck($availableDocuments->invoke($archive, 8, ['id' => 2]) === [110, 120, 121], 'Documenti candidati indipendenti dall’assegnatario della pratica.');
setVisibilityProperty($archive, 'dossierDocuments', [2 => [], 3 => [], 4 => []]);
visibilityCheck(array_column($archive->shared('document', ['id' => 3]), 'id') === [110, 120], 'Delegato vede i documenti autorizzati, non quelli riservati al creatore.');
visibilityCheck($archive->document(8, 110, ['id' => 3])['id'] === 110, 'Accesso diretto al documento della pratica riservata.');
visibilityDenied(fn () => $archive->document(8, 121, ['id' => 3]));
visibilityCheck($archive->shared('document', ['id' => 4]) === [], 'Il solo ruolo RLS non vede i documenti per delegati.');

$folderAccess = new DocumentFolderAccessService(new DocumentFolderRepository($database), $roles, dirname(__DIR__));
foreach ([1 => true, 2 => true, 3 => false, 4 => false] as $userId => $allowed) {
    visibilityCheck($folderAccess->allowsFolder(22, ['id' => $userId]) === $allowed, 'Sottocartella pratica riservata.');
    visibilityCheck($folderAccess->allowsDocument(['id' => 100, 'visibility' => 'creator', 'uploaded_by' => 2], ['id' => $userId]) === $allowed, 'Documento riservato al creatore.');
}
visibilityCheck($folderAccess->allowsDocument(['id' => 100, 'visibility' => 'public', 'uploaded_by' => 2], ['id' => 3]), 'Il documento pubblico non rende visibile la pratica riservata.');

$practiceService = new PracticeService($practices, new UserRepository($database));
$validate = new ReflectionMethod($practiceService, 'validated');
visibilityDenied(fn () => $validate->invoke($practiceService, ['title' => 'Test', 'visibility' => 'public_summary']), 422);
visibilityCheck($validate->invoke($practiceService, ['title' => 'Test', 'visibility' => 'authorized'])['visibility'] === 'authorized', 'Visibilità solo assegnatario valida.');

$database->archivesInstalled = true;
$database->archiveLinks = [['configuration_id' => 0, 'practice_id' => 11, 'source_configuration_id' => 8, 'source_practice_id' => 12]];
unset($database->practices[12]);
$dossier = new PracticeDossierService($application);
setVisibilityProperty($dossier, 'sources', [0 => $source, 8 => $source]);
visibilityCheck($dossier->accessible(['id' => 3]) === [], 'Fascicolo trasferito nascosto ai non assegnatari.');
visibilityDenied(fn () => $dossier->timeline(0, 11, ['id' => 3]));
$delegateDocuments = $dossier->accessibleDocuments(['id' => 3]);
visibilityCheck(array_column($delegateDocuments, 'id') === [120], 'Documento trasferito visibile anche con pratica riservata e sorgente non più attiva.');
visibilityCheck(!array_intersect(['practice_id', 'archive_owner_practice_id', 'practice_title', 'summary'], array_keys($delegateDocuments[0])), 'Scoperta documenti non espone dati del fascicolo.');
visibilityCheck(array_column($dossier->accessibleDocuments(['id' => 2]), 'id') === [120, 121], 'Il creatore conserva accesso ai propri documenti trasferiti.');
visibilityCheck($dossier->accessibleDocuments(['id' => 4]) === [], 'Documenti trasferiti per delegati non visibili al solo RLS.');
setVisibilityProperty($archive, 'target', 0);
setVisibilityProperty($archive, 'dossierDocuments', [3 => $delegateDocuments]);
visibilityCheck($archive->document(8, 120, ['id' => 3])['id'] === 120, 'Anteprima archivio autorizzata dal documento trasferito, non dalla pratica.');
visibilityDenied(fn () => $archive->document(8, 121, ['id' => 3]));
visibilityDenied(fn () => $archive->document(8, 130, ['id' => 3]));
visibilityDenied(fn () => $archive->item(8, 'practice', 11, ['id' => 3]));
echo "OK: {$checks} verifiche visibilità pratiche. Nessuna connessione o modifica al database.\n";
