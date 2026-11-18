<?php

declare(strict_types=1);

use App\Controllers\Api\DocumentController;
use App\Controllers\Api\DocumentFolderController;
use App\Core\Application;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\DocumentFolderRepository;
use App\Repositories\ActivityLogRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\PracticeRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;
use App\Services\DocumentFolderAccessService;
use App\Services\DocumentFolderService;
use App\Services\DocumentStorageService;
use App\Services\PracticeAccessService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

final class DocumentVisibilityTestStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private readonly DocumentVisibilityTestPdo $database, private readonly string $sql) {}
    public function execute(?array $params = null): bool { $this->rows = $this->database->executeMemory($this->sql, $params ?? []); return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $mode === PDO::FETCH_COLUMN ? array_map(static fn (array $row): mixed => reset($row), $this->rows) : $this->rows; }
    public function fetchColumn(int $column = 0): mixed { $row = $this->fetch(); return $row === false ? false : array_values($row)[$column]; }
}

final class DocumentVisibilityTestPdo extends PDO
{
    public array $folders = [];
    public array $placements = [];
    public array $permissions = [];
    public array $links = [];
    public array $writes = [];
    public array $queries = [];
    public array $documents;
    public array $roles = [1 => ['admin'], 2 => ['delegato'], 3 => ['delegato'], 4 => ['rls'], 5 => ['membro'], 6 => ['delegato', 'rls']];
    public array $deniedPermissions = [];
    private bool $transaction = false;
    public function __construct() { $this->documents = [100 => ['id' => 100, 'visibility' => 'creator', 'uploaded_by' => 2, 'author_name' => 'Autore test', 'category' => 'documenti', 'original_name' => 'Test.pdf', 'pdf_public_path' => 'public/documents/nonexistent-test-file.pdf', 'practice_ids' => '11', 'practice_titles' => 'Pratica segreta']]; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new DocumentVisibilityTestStatement($this, $query); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false { $statement = $this->prepare($query); $statement->execute(); return $statement; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
    public function executeMemory(string $sql, array $parameters): array
    {
        if (str_starts_with(ltrim($sql), 'SELECT')) {
            $this->queries[] = $sql;
            if (str_contains($sql, 'FROM roles r')) return array_map(static fn (string $name): array => ['name' => $name], $this->roles[$parameters[0]] ?? []);
            if (str_contains($sql, 'FROM roles ORDER')) return [['id' => 1, 'name' => 'admin'], ['id' => 2, 'name' => 'delegato'], ['id' => 3, 'name' => 'rls'], ['id' => 4, 'name' => 'membro']];
            if (str_contains($sql, 'permission_role')) return [['count' => in_array($parameters[1] ?? '', $this->deniedPermissions[$parameters[0]] ?? [], true) ? 0 : 1]];
            if (str_contains($sql, 'SELECT NOT EXISTS')) return [['missing' => 0]];
            if (str_contains($sql, 'FROM document_folders f')) return array_values($this->folders);
            if (str_contains($sql, 'FROM document_folders WHERE')) return isset($this->folders[$parameters[0]]) ? [$this->folders[$parameters[0]]] : [];
            if (str_contains($sql, 'FROM document_folder_items')) return $this->placements;
            if (str_contains($sql, 'FROM document_folder_permissions')) return $this->permissions;
            if (str_contains($sql, 'AS document_id, p.visibility')) return $this->links;
            if (str_contains($sql, 'FROM documents')) return $parameters === [] ? array_values($this->documents) : (isset($this->documents[$parameters[0]]) ? [$this->documents[$parameters[0]]] : []);
            if (str_contains($sql, 'FROM practices')) return [['id' => 11, 'title' => 'Pratica segreta', 'visibility' => 'authorized', 'assigned_user_id' => 2]];
            return [];
        }
        $this->writes[] = [$sql, $parameters];
        if (str_starts_with($sql, 'DELETE FROM document_folder_items')) $this->placements = [];
        elseif (str_contains($sql, 'INSERT INTO document_folder_items')) $this->placements = [['document_id' => $parameters[0], 'folder_id' => $parameters[1]]];
        elseif (str_starts_with($sql, 'UPDATE document_folders')) {
            $this->folders[$parameters[2]]['inherit_permissions'] = $parameters[0];
            $this->folders[$parameters[2]]['visibility'] = $parameters[1];
        } elseif (str_starts_with($sql, 'UPDATE documents SET visibility')) $this->documents[$parameters[1]]['visibility'] = $parameters[0];
        elseif (str_starts_with($sql, 'DELETE FROM document_folder_permissions')) $this->permissions = array_values(array_filter($this->permissions, static fn (array $row): bool => (int)$row['folder_id'] !== (int)$parameters[0]));
        elseif (str_contains($sql, 'INSERT INTO document_folder_permissions')) $this->permissions[] = ['folder_id' => $parameters[0], 'role_id' => $parameters[1], 'access_level' => $parameters[2]];
        elseif (!str_starts_with($sql, 'INSERT INTO activity_logs')) throw new RuntimeException('Scrittura simulata non prevista: ' . $sql);
        return [];
    }
}

function documentVisibilityCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function documentVisibilitySet(object $object, string $property, mixed $value): void { (new ReflectionProperty($object, $property))->setValue($object, $value); }

$checks = 0;
$database = new DocumentVisibilityTestPdo();
$roles = new RolePermissionRepository($database);
$folders = new DocumentFolderRepository($database);
$documents = new DocumentRepository($database);
$access = new DocumentFolderAccessService($folders, $roles, dirname(__DIR__));
$service = new DocumentFolderService($folders, $documents, $roles, $access);
$matrix = ['creator' => [1, 2], 'rsu' => [1, 2, 3], 'members' => [1, 2, 3, 4, 5], 'public' => [0, 1, 2, 3, 4, 5]];
foreach ($matrix as $visibility => $allowed) {
    foreach (range(0, 5) as $userId) {
        $document = $database->documents[100] + [];
        $document['visibility'] = $visibility;
        documentVisibilityCheck($access->allowsDocument($document, $userId === 0 ? null : ['id' => $userId]) === in_array($userId, $allowed, true), 'Visibilità errata: ' . $visibility . '/' . $userId);
    }
}
foreach ([10 => [null, true, null], 11 => [10, false, 'rsu'], 12 => [11, true, null], 13 => [11, false, 'document'], 14 => [null, false, 'public'], 15 => [null, false, 'creator'], 16 => [null, false, null], 17 => [null, false, null]] as $id => [$parent, $inherit, $visibility]) {
    $database->folders[$id] = ['id' => $id, 'name' => 'Cartella ' . $id, 'parent_id' => $parent, 'inherit_permissions' => $inherit, 'visibility' => $visibility, 'system_key' => null, 'practice_id' => null];
}
$database->permissions = [['folder_id' => 16, 'role_id' => 4, 'access_level' => 'read']];
$access->refresh();
$database->placements = [['document_id' => 100, 'folder_id' => 12]];
$access->refresh();
documentVisibilityCheck($access->allowsDocument($database->documents[100], ['id' => 3]), 'Eredità del genitore sostituisce la visibilità propria.');
documentVisibilityCheck(!$access->allowsDocument($database->documents[100], ['id' => 4]), 'Delegati non include RLS.');
documentVisibilityCheck($access->describe($database->documents[100], ['id' => 3])['effective_visibility'] === 'rsu', 'Visibilità effettiva ereditata.');
$database->placements[0]['folder_id'] = 13;
$access->refresh();
documentVisibilityCheck(!$access->allowsDocument($database->documents[100], ['id' => 3]), 'Interruzione esplicita dell’eredità.');
$database->placements[0]['folder_id'] = 14;
$access->refresh();
documentVisibilityCheck($access->allowsDocument($database->documents[100], null), 'Cartella ospiti rende pubblico il documento.');
documentVisibilityCheck($database->documents[100]['visibility'] === 'creator', 'Visibilità propria conservata.');
$database->placements[0]['folder_id'] = 16;
$access->refresh();
documentVisibilityCheck($access->allowsDocument($database->documents[100], ['id' => 5]), 'Permesso personalizzato di lettura.');
documentVisibilityCheck(!$access->allowsDocument($database->documents[100], ['id' => 5], true), 'Lettura non concede gestione.');
documentVisibilityCheck($access->allowsDocument($database->documents[100], ['id' => 2]), 'Creatore mantiene accesso.');
$database->placements[0]['folder_id'] = 17;
$access->refresh();
documentVisibilityCheck(!$access->allowsDocument($database->documents[100], ['id' => 3]), 'Vecchia cartella senza ruoli resta riservata.');
$database->folders[19] = ['id' => 19, 'name' => 'Vecchia sottocartella', 'parent_id' => 16, 'inherit_permissions' => false, 'visibility' => null, 'system_key' => null, 'practice_id' => null];
$database->permissions[] = ['folder_id' => 19, 'role_id' => 2, 'access_level' => 'manage'];
$database->placements[0]['folder_id'] = 19;
$access->refresh();
documentVisibilityCheck(!$access->allowsDocument($database->documents[100], ['id' => 3]), 'Vecchi permessi personalizzati non devono aggirare i vincoli superiori dopo il deploy.');
$database->permissions[] = ['folder_id' => 19, 'role_id' => 4, 'access_level' => 'manage'];
$access->refresh();
documentVisibilityCheck($access->allowsDocument($database->documents[100], ['id' => 5]), 'Vecchio permesso cumulativo di lettura preservato.');
documentVisibilityCheck(!$access->allowsDocument($database->documents[100], ['id' => 5], true), 'Gestione non deve superare i vecchi vincoli del genitore.');
$database->folders[19]['visibility'] = 'members';
$access->refresh();
documentVisibilityCheck($access->allowsDocument($database->documents[100], ['id' => 4]), 'Nuova scelta esplicita di visibilità sostituisce il genitore.');

$database->placements = [];
$access->refresh();
$beforeWrites = count($database->writes);
$result = $service->move(100, ['folder_id' => 14], ['id' => 2]);
documentVisibilityCheck(($result['confirmation_required'] ?? false) && count($database->writes) === $beforeWrites, 'Spostamento più aperto deve attendere conferma.');
$result = $service->move(100, ['folder_id' => 14, 'confirm_visibility_change' => 'true'], ['id' => 2]);
documentVisibilityCheck(($result['confirmation_required'] ?? false) && count($database->writes) === $beforeWrites, 'Conferma deve essere booleano true.');
$service->move(100, ['folder_id' => 14, 'confirm_visibility_change' => true], ['id' => 2]);
documentVisibilityCheck($access->allowsDocument($database->documents[100], null), 'Spostamento confermato applica permessi destinazione.');
$service->move(100, ['folder_id' => null], ['id' => 2]);
documentVisibilityCheck(!$access->allowsDocument($database->documents[100], ['id' => 3]), 'Uscita dalla cartella ripristina visibilità propria.');
$service->move(100, ['folder_id' => 15], ['id' => 2]);
$beforeWrites = count($database->writes);
$result = $service->setPermissions(15, ['inherit_permissions' => false, 'visibility' => 'members', 'permissions' => []]);
documentVisibilityCheck(($result['confirmation_required'] ?? false) && count($database->writes) === $beforeWrites, 'Permessi cartella più ampi devono attendere conferma.');
$service->setPermissions(15, ['inherit_permissions' => false, 'visibility' => 'members', 'permissions' => [], 'confirm_visibility_change' => true]);
documentVisibilityCheck($access->allowsDocument($database->documents[100], ['id' => 4]), 'Cartella membri include RLS autenticati.');
documentVisibilityCheck(!$access->allowsDocument($database->documents[100], null), 'Membri non include ospiti.');
$service->setPermissions(15, ['inherit_permissions' => false, 'visibility' => 'creator', 'permissions' => []]);
documentVisibilityCheck(!$access->allowsDocument($database->documents[100], ['id' => 4]), 'Revoca dinamica senza spostare o duplicare file.');

$database->placements = [];
$database->folders[99] = ['id' => 99, 'name' => 'Pratica segreta', 'parent_id' => null, 'inherit_permissions' => false, 'visibility' => 'public', 'system_key' => 'practice:11', 'practice_id' => 11, 'practice_code' => 'P11', 'practice_title' => 'Pratica segreta', 'practice_visibility' => 'authorized', 'practice_assigned_user_id' => 2];
$database->links = [['practice_id' => 11, 'document_id' => 100, 'visibility' => 'authorized', 'assigned_user_id' => 2]];
$access->refresh();
documentVisibilityCheck($access->allowsDocument($database->documents[100], null), 'Documento indipendente da pratica riservata.');
documentVisibilityCheck(!$access->allowsFolder(99, ['id' => 3]), 'La pratica resta riservata.');
documentVisibilityCheck($access->describe($database->documents[100], ['id' => 3])['visibility_folders'] === ['cartella riservata'], 'Nome della pratica non deve trapelare nei dettagli del documento.');

$application = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
$auth = new Auth(new UserRepository($database), new TokenRepository($database), $roles);
documentVisibilitySet($auth, 'resolved', true);
documentVisibilitySet($auth, 'user', ['id' => 3]);
$storage = (new ReflectionClass(DocumentStorageService::class))->newInstanceWithoutConstructor();
documentVisibilitySet($storage, 'basePath', dirname(__DIR__));
foreach (['auth' => $auth, 'roles' => $roles, 'documents' => $documents, 'documentFolders' => $folders, 'documentFolderAccess' => $access, 'documentStorage' => $storage, 'practices' => new PracticeRepository($database), 'practiceAccess' => new PracticeAccessService($roles)] as $property => $value) documentVisibilitySet($application, $property, $value);
$controller = new DocumentController($application);
$response = $controller->index(new Request('GET', '/', [], [], []));
$payload = (new ReflectionProperty($response, 'payload'))->getValue($response);
documentVisibilityCheck(count($payload['data']) === 1, 'Documento condiviso presente nell’elenco.');
documentVisibilityCheck($payload['data'][0]['author_name'] === 'Autore test', 'Elenco API conserva il nome autore per i documenti autorizzati.');
documentVisibilityCheck((bool)array_filter($database->queries, static fn (string $sql): bool => str_contains($sql, 'u.name AS author_name') && str_contains($sql, 'LEFT JOIN users u ON u.id = d.uploaded_by')), 'Autore ricavato dal creatore senza escludere documenti con utente non disponibile.');
documentVisibilityCheck($payload['data'][0]['practice_ids'] === '' && $payload['data'][0]['practice_titles'] === '', 'Elenco non deve esporre riferimenti alla pratica riservata.');
$database->folders[99]['visibility'] = 'creator';
$access->refresh();
documentVisibilityCheck($access->allowsDocument(['id' => 100], ['id' => 2]), 'API commenti con solo ID riconosce il creatore.');
documentVisibilityCheck(!$access->allowsDocument(['id' => 100], ['id' => 3]), 'API commenti con solo ID non aggira la visibilità.');
documentVisibilityCheck(!$access->allowsDocument(['id' => 9999], ['id' => 1]), 'Documento inesistente non autorizzato.');
try {
    $controller->show(new Request('GET', '/', [], [], []), ['id' => 100]);
    throw new RuntimeException('Documento creatore esposto tramite ID diretto.');
} catch (HttpException $exception) { documentVisibilityCheck($exception->status() === 403, 'ID diretto bloccato.'); }
$activityLogs = new ActivityLogRepository($database);
foreach (range(0, 6) as $userId) {
    $actorAuth = new Auth(new UserRepository($database), new TokenRepository($database), $roles);
    documentVisibilitySet($actorAuth, 'resolved', true);
    documentVisibilitySet($actorAuth, 'user', $userId === 0 ? null : ['id' => $userId]);
    $actorApp = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    foreach (['auth' => $actorAuth, 'roles' => $roles, 'documentFolders' => $folders, 'documentFolderAccess' => $access, 'documentFolderService' => $service, 'activityLogs' => $activityLogs] as $property => $value) documentVisibilitySet($actorApp, $property, $value);
    $folderController = new DocumentFolderController($actorApp);
    $beforeWrites = count($database->writes);
    $request = new Request('PATCH', '/', [], [], ['inherit_permissions' => false, 'visibility' => 'rsu', 'permissions' => []]);
    if ($userId !== 0) {
        $response = $folderController->index(new Request('GET', '/', [], [], []));
        $data = (new ReflectionProperty($response, 'payload'))->getValue($response)['data'];
        documentVisibilityCheck($data['can_set_permissions'] === ($userId === 1), 'Controlli permessi visibili solo ad admin, anche con privilegi globali di gestione.');
        if ($userId !== 1) {
            documentVisibilityCheck(!array_filter($data['folders'], static fn (array $folder): bool => $folder['can_set_permissions']), 'Nessuna cartella modificabile da non admin.');
            documentVisibilityCheck($data['permissions'] === [] && $data['roles'] === [], 'Non admin non riceve i dati per modificare i permessi.');
        }
    }
    if ($userId === 1) {
        $response = $folderController->permissions($request, ['id' => 15]);
        documentVisibilityCheck((new ReflectionProperty($response, 'payload'))->getValue($response)['data']['updated'], 'Admin mantiene la modifica dei permessi.');
        documentVisibilityCheck($database->folders[15]['visibility'] === 'rsu', 'Modifica admin applicata alla sola cartella simulata.');
        continue;
    }
    try {
        $folderController->permissions($request, ['id' => 15]);
        throw new RuntimeException('Modifica permessi cartella consentita a non admin.');
    } catch (HttpException $exception) {
        documentVisibilityCheck($exception->status() === ($userId === 0 ? 401 : 403), 'API permessi cartella nega ospiti e non admin, incluso creatore e doppio ruolo.');
    }
    documentVisibilityCheck(count($database->writes) === $beforeWrites, 'Richiesta non autorizzata non deve effettuare scritture.');
}

$database->folders[99]['visibility'] = 'document';
foreach (range(0, 6) as $userId) {
    $database->documents[100]['visibility'] = 'public';
    $access->refresh();
    $actorAuth = new Auth(new UserRepository($database), new TokenRepository($database), $roles);
    documentVisibilitySet($actorAuth, 'resolved', true);
    documentVisibilitySet($actorAuth, 'user', $userId === 0 ? null : ['id' => $userId]);
    $actorApp = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    foreach (['auth' => $actorAuth, 'roles' => $roles, 'documents' => $documents, 'documentFolderAccess' => $access, 'documentStorage' => $storage, 'practices' => new PracticeRepository($database), 'practiceAccess' => new PracticeAccessService($roles), 'activityLogs' => $activityLogs] as $property => $value) documentVisibilitySet($actorApp, $property, $value);
    $documentController = new DocumentController($actorApp);
    $allowed = in_array($userId, [1, 2], true);
    if ($userId !== 0) {
        foreach (['show', 'index'] as $method) {
            $response = $documentController->$method(new Request('GET', '/', [], [], []), ['id' => 100]);
            $data = (new ReflectionProperty($response, 'payload'))->getValue($response)['data'];
            $row = $method === 'index' ? $data[0] : $data;
            documentVisibilityCheck($row['can_set_visibility'] === $allowed, 'Solo admin e creatore ricevono il controllo per modificare la visibilità.');
        }
    }
    $beforeWrites = count($database->writes);
    $request = new Request('PATCH', '/', [], [], ['visibility' => 'members']);
    if ($allowed) {
        $response = $documentController->update($request, ['id' => 100]);
        $data = (new ReflectionProperty($response, 'payload'))->getValue($response)['data'];
        documentVisibilityCheck($data['visibility'] === 'members', 'Admin e creatore possono modificare la visibilità del documento.');
        continue;
    }
    try {
        $documentController->update($request, ['id' => 100]);
        throw new RuntimeException('Visibilità modificata da utente diverso da admin e creatore.');
    } catch (HttpException $exception) {
        documentVisibilityCheck($exception->status() === ($userId === 0 ? 401 : 403), 'API documenti nega la modifica a non proprietari, anche con tutti i permessi globali.');
    }
    documentVisibilityCheck(count($database->writes) === $beforeWrites && $database->documents[100]['visibility'] === 'public', 'Modifica non autorizzata senza scritture o cambio di visibilità.');
    if ($userId !== 0) {
        $response = $documentController->update(new Request('PATCH', '/', [], [], []), ['id' => 100]);
        $data = (new ReflectionProperty($response, 'payload'))->getValue($response)['data'];
        documentVisibilityCheck($data['visibility'] === 'public', 'Aggiornamento senza modifica di visibilità mantiene i flussi esistenti.');
    }
}

$uploadDatabase = new DocumentVisibilityTestPdo();
$uploadRoles = new RolePermissionRepository($uploadDatabase);
$uploadFolders = new DocumentFolderRepository($uploadDatabase);
$uploadAccess = new DocumentFolderAccessService($uploadFolders, $uploadRoles, dirname(__DIR__));
$uploadService = new DocumentFolderService($uploadFolders, new DocumentRepository($uploadDatabase), $uploadRoles, $uploadAccess);
$uploadDatabase->folders[200] = ['id' => 200, 'parent_id' => null, 'system_key' => 'practices', 'practice_id' => null, 'inherit_permissions' => true, 'visibility' => null];
foreach ([201 => [11, 'authorized', 2], 202 => [12, 'operators', null], 203 => [13, 'authorized', 4], 204 => [14, 'authorized', 2]] as $folderId => [$practiceId, $practiceVisibility, $assignee]) {
    $uploadDatabase->folders[$folderId] = ['id' => $folderId, 'parent_id' => 200, 'system_key' => 'practice:' . $practiceId, 'practice_id' => $practiceId,
        'practice_visibility' => $practiceVisibility, 'practice_assigned_user_id' => $assignee, 'inherit_permissions' => $folderId !== 204, 'visibility' => null];
}
$uploadDatabase->permissions = [['folder_id' => 204, 'role_id' => 2, 'access_level' => 'read']];
documentVisibilityCheck($uploadService->uploadPracticeDestination(null, ['id' => 2]) === null, 'Caricamento senza pratica invariato.');
documentVisibilityCheck($uploadService->uploadPracticeDestination('', ['id' => 2]) === null, 'Pratica vuota compatibile con radice e cartelle manuali.');
foreach ([[1, 11], [2, 11], [2, 12], [6, 12], [4, 13], [1, 14]] as [$userId, $practiceId]) {
    documentVisibilityCheck($uploadService->uploadPracticeDestination((string)$practiceId, ['id' => $userId]) === $practiceId, 'Caricamento autorizzato nella pratica e nella cartella.');
}
foreach ([[3, 11, 403], [4, 11, 403], [5, 12, 403], [4, 12, 403], [2, 14, 403], [1, 999, 404], [1, 'abc', 422], [1, [], 422], [1, 0, 422], [1, true, 422]] as [$userId, $practiceId, $status]) {
    try {
        $uploadService->uploadPracticeDestination($practiceId, ['id' => $userId]);
        throw new RuntimeException('Caricamento in pratica non autorizzato accettato.');
    } catch (HttpException $exception) {
        documentVisibilityCheck($exception->status() === $status, 'Pratica, ruolo, permessi cartella e identificativo verificati prima del salvataggio.');
    }
}
$uploadDatabase->deniedPermissions[2] = ['documents.update'];
try {
    $uploadService->uploadPracticeDestination(11, ['id' => 2]);
    throw new RuntimeException('Caricamento senza permesso gestione documenti accettato.');
} catch (HttpException $exception) {
    documentVisibilityCheck($exception->status() === 403, 'Caricamento nella pratica richiede i permessi documentali esistenti.');
}
documentVisibilityCheck($uploadDatabase->writes === [], 'Verifiche di destinazione senza scritture, duplicazioni o modifiche di visibilità.');

echo "OK: {$checks} verifiche documenti/cartelle. Nessuna connessione reale al database o modifica di file operativi.\n";
