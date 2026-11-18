<?php

declare(strict_types=1);

use App\Controllers\Api\MeetingProjectionController;
use App\Core\Application;
use App\Core\Auth;
use App\Core\FileResponse;
use App\Core\HttpException;
use App\Core\Request;
use App\Repositories\ActivityLogRepository;
use App\Repositories\DocumentFolderRepository;
use App\Repositories\DocumentRepository;
use App\Repositories\MeetingProjectionRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;
use App\Services\DocumentFolderAccessService;
use App\Services\DocumentStorageService;
use App\Services\PdfQrService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) require dirname(__DIR__) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

final class ProjectionTestStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private readonly ProjectionTestPdo $database, private readonly string $sql) {}
    public function execute(?array $params = null): bool { $this->rows = $this->database->executeMemory($this->sql, $params ?? []); return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $mode === PDO::FETCH_COLUMN ? array_map(static fn (array $row): mixed => reset($row), $this->rows) : $this->rows; }
}

final class ProjectionTestPdo extends PDO
{
    public array $document = ['id' => 9, 'conversion_status' => 'ready', 'visibility' => 'creator', 'uploaded_by' => 2, 'pdf_public_path' => 'public/documents/test.pdf'];
    public array $session = ['id' => 1, 'meeting_id' => 1, 'meeting_title' => 'Test', 'current_document_id' => null, 'document_name' => 'Test.pdf', 'active_slot' => 1, 'public_token' => 'test-public', 'control_token' => 'test-control', 'scroll_ratio' => 0, 'pointer_page' => 1, 'pointer_x' => 0.5, 'pointer_y' => 0.1, 'highlight_x' => null, 'highlight_y' => null, 'highlight_page' => null, 'highlight_end_x' => null, 'highlight_end_y' => null, 'revision' => 1];
    public array $writes = [];
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ProjectionTestStatement($this, $query); }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false { $statement = $this->prepare($query); $statement->execute(); return $statement; }
    public function executeMemory(string $sql, array $params): array
    {
        if (str_contains($sql, 'FROM roles r')) return [['name' => match ($params[0]) { 1 => 'admin', 2 => 'delegato', 3 => 'rls', default => 'membro' }]];
        if (str_contains($sql, 'FROM documents')) return $params[0] === $this->document['id'] || $params[0] === $this->document['pdf_public_path'] ? [$this->document] : [];
        if (str_contains($sql, 'FROM meeting_projection_sessions')) {
            $expected = str_contains($sql, 's.control_token = ?') ? $this->session['control_token'] : $this->session['public_token'];
            return $this->session['active_slot'] === 1 && ($params === [] || $params[0] === $expected) ? [$this->session] : [];
        }
        if (str_contains($sql, 'UPDATE meeting_projection_sessions') && str_contains($sql, 'SET current_document_id')) {
            $this->session['current_document_id'] = $params[0];
            $this->session['highlight_x'] = null;
            $this->session['highlight_y'] = null;
            $this->session['highlight_page'] = null;
            $this->session['highlight_end_x'] = null;
            $this->session['highlight_end_y'] = null;
            $this->session['revision']++;
            $this->writes[] = 'publish';
            return [];
        }
        if (str_contains($sql, 'UPDATE meeting_projection_sessions') && str_contains($sql, 'SET scroll_ratio = ?')) {
            if (str_contains($sql, 'AND current_document_id = ?') && end($params) !== $this->session['current_document_id']) return [];
            $this->session['scroll_ratio'] = $params[0];
            if (str_contains($sql, 'pointer_page = ?')) {
                $this->session['pointer_page'] = $params[1];
                $this->session['pointer_x'] = $params[2];
                $this->session['pointer_y'] = $params[3];
                $this->session['highlight_x'] = $params[4];
                $this->session['highlight_y'] = $params[5];
                $this->session['highlight_page'] = $params[6];
                $this->session['highlight_end_x'] = $params[7];
                $this->session['highlight_end_y'] = $params[8];
            }
            $this->session['revision']++;
            $this->writes[] = 'position';
            return [];
        }
        if (str_contains($sql, 'INSERT INTO activity_logs')) { $this->writes[] = json_decode($params[2], true); return []; }
        if (str_starts_with(ltrim($sql), 'SELECT')) return [];
        throw new RuntimeException('Scrittura non prevista nel test.');
    }
}

function projectionSet(object $object, string $name, mixed $value): void { (new ReflectionProperty($object, $name))->setValue($object, $value); }
function projectionCheck(bool $condition, string $message): void { global $checks; if (!$condition) throw new RuntimeException($message); $checks++; }
function projectionDenied(callable $action, int $status): void
{
    try { $action(); throw new RuntimeException('Accesso non autorizzato accettato.'); }
    catch (HttpException $exception) { projectionCheck($exception->status() === $status, 'Stato di rifiuto non corretto.'); }
}
function projectionApplication(ProjectionTestPdo $database, string $root, ?int $actor): Application
{
    $roles = new RolePermissionRepository($database);
    $auth = new Auth(new UserRepository($database), new TokenRepository($database), $roles);
    projectionSet($auth, 'resolved', true);
    projectionSet($auth, 'user', $actor === null ? null : ['id' => $actor]);
    $storage = (new ReflectionClass(DocumentStorageService::class))->newInstanceWithoutConstructor();
    projectionSet($storage, 'basePath', $root);
    $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
    foreach (['auth' => $auth, 'roles' => $roles, 'documents' => new DocumentRepository($database), 'meetingProjections' => new MeetingProjectionRepository($database),
        'documentFolderAccess' => new DocumentFolderAccessService(new DocumentFolderRepository($database), $roles, $root),
        'documentStorage' => $storage, 'activityLogs' => new ActivityLogRepository($database), 'pdfQr' => new PdfQrService()] as $name => $value) projectionSet($app, $name, $value);
    return $app;
}

$checks = 0;
$root = sys_get_temp_dir() . '/miodel_projection_test_' . bin2hex(random_bytes(8));
mkdir($root . '/public/documents', 0700, true);
$pdfPath = $root . '/public/documents/test.pdf';
copy(dirname(__DIR__) . '/storage/private/branding/fixtures/source.pdf', $pdfPath);
try {
    foreach ([false, null, 1, 'true'] as $confirmation) {
        $database = new ProjectionTestPdo();
        $controller = new MeetingProjectionController(projectionApplication($database, $root, 2));
        $response = $controller->publish(new Request('POST', '/', [], [], ['confirm_restricted_projection' => $confirmation]), ['id' => 9]);
        projectionCheck((new ReflectionProperty($response, 'status'))->getValue($response) === 409, 'Documento riservato richiede conferma esplicita.');
        projectionCheck($database->session['current_document_id'] === null && $database->writes === [], 'Prima della conferma nessuna pubblicazione o modifica.');
    }
    foreach (['creator', 'public'] as $visibility) {
        $database = new ProjectionTestPdo();
        $database->document['visibility'] = $visibility;
        $before = $database->document;
        $controller = new MeetingProjectionController(projectionApplication($database, $root, 2));
        $response = $controller->publish(new Request('POST', '/', [], [], ['confirm_restricted_projection' => true]), ['id' => 9]);
        projectionCheck((new ReflectionProperty($response, 'status'))->getValue($response) === 200 && $database->session['current_document_id'] === 9, 'Documento pubblicato nella sessione attiva.');
        projectionCheck($database->document === $before, 'Visibilità, autore e dati del documento invariati.');
        projectionCheck($database->writes[1]['restricted_projection_confirmed'] === ($visibility === 'creator'), 'Conferma del documento riservato tracciata.');
    }
    foreach ([3, 4] as $actor) {
        $database = new ProjectionTestPdo();
        $controller = new MeetingProjectionController(projectionApplication($database, $root, $actor));
        projectionDenied(fn () => $controller->publish(new Request('POST', '/', [], [], ['confirm_restricted_projection' => true]), ['id' => 9]), 403);
        projectionCheck($database->writes === [], 'Conferma non aggira ruolo o accesso al documento.');
    }
    $database = new ProjectionTestPdo();
    $database->session['current_document_id'] = 9;
    $app = projectionApplication($database, $root, null);
    $controller = new MeetingProjectionController($app);
    $request = new Request('GET', '/api/v1/projection-access/document', [], ['token' => 'test-public'], []);
    $response = $controller->publicDocument($request);
    $guard = new ReflectionMethod($app, 'assertFileResponse');
    $guard->invoke($app, $response, $request);
    projectionCheck($response->path() === $pdfPath, 'Il token valido autorizza il PDF effettivamente selezionato.');
    projectionDenied(fn () => $guard->invoke($app, $response, new Request('GET', '/api/v1/documents/9/preview', [], [], [])), 403);
    projectionDenied(fn () => $guard->invoke($app, new FileResponse(__FILE__, 'other.pdf', 'application/pdf'), $request), 410);
    foreach (['', 'wrong-token'] as $token) {
        $invalid = new Request('GET', '/api/v1/projection-access/document', [], ['token' => $token], []);
        projectionDenied(fn () => $controller->publicDocument($invalid), 410);
        projectionDenied(fn () => $guard->invoke($app, $response, $invalid), 410);
    }
    $database->session['active_slot'] = null;
    projectionDenied(fn () => $controller->publicDocument($request), 410);
    projectionDenied(fn () => $guard->invoke($app, $response, $request), 410);
    projectionCheck($database->document['visibility'] === 'creator', 'Documento ancora riservato dopo la proiezione.');
    $database = new ProjectionTestPdo();
    $database->session['current_document_id'] = 9;
    $controller = new MeetingProjectionController(projectionApplication($database, $root, null));
    $point = ['control_token' => 'test-control', 'document_id' => 9, 'scroll_ratio' => 0.4, 'pointer_page' => 2, 'pointer_x' => 0.25, 'pointer_y' => 0.75];
    $response = $controller->position(new Request('PATCH', '/', [], [], $point));
    $data = (new ReflectionProperty($response, 'payload'))->getValue($response)['data'];
    projectionCheck($data['pointer_page'] === 2 && $data['pointer_x'] === 0.25 && $data['pointer_y'] === 0.75, 'Coordinate condivise tramite il token di regia.');
    projectionCheck($data['scroll_ratio'] === 0.4 && !isset($data['control_token']), 'Posizione pubblica senza esposizione del token di controllo.');
    foreach ([['pointer_page' => 0], ['pointer_page' => 65536], ['pointer_page' => true], ['pointer_x' => -0.1], ['pointer_y' => 1.1], ['pointer_x' => '0.5'], ['pointer_y' => INF], ['pointer_x' => null], ['document_id' => null]] as $invalid) {
        $before = $database->writes;
        projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], array_replace($point, $invalid))), 422);
        projectionCheck($database->writes === $before, 'Coordinate non valide respinte senza scritture.');
    }
    $before = $database->writes;
    projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], ['control_token' => 'test-control', 'document_id' => 9, 'pointer_x' => 0.2])), 422);
    projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], array_replace($point, ['document_id' => 8]))), 409);
    projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], array_replace($point, ['control_token' => 'test-public']))), 403);
    projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], array_replace($point, ['control_token' => '']))), 401);
    projectionCheck($database->writes === $before, 'Gli spettatori e i messaggi per documenti precedenti non spostano il puntatore.');
    $controller->position(new Request('PATCH', '/', [], [], ['control_token' => 'test-control', 'scroll_ratio' => 0.6]));
    projectionCheck($database->session['pointer_page'] === 2 && $database->session['pointer_x'] === 0.25 && $database->session['pointer_y'] === 0.75, 'Regia precedente compatibile: lo scroll conserva il puntatore.');
    $repository = new MeetingProjectionRepository($database);
    $before = $database->writes;
    $repository->position(1, 0.2, ['pointer_page' => 1, 'pointer_x' => 0, 'pointer_y' => 1], 8);
    projectionCheck($database->writes === $before, 'Condizione SQL sul documento impedisce aggiornamenti concorrenti obsoleti.');
    foreach ([0, 1] as $edge) {
        $controller->position(new Request('PATCH', '/', [], [], array_replace($point, ['pointer_x' => $edge, 'pointer_y' => $edge])));
        projectionCheck($database->session['pointer_x'] === (float)$edge && $database->session['pointer_y'] === (float)$edge, 'Coordinate ai bordi accettate.');
    }
    $highlight = $point + ['highlight_x' => 0, 'highlight_y' => 1];
    $response = $controller->position(new Request('PATCH', '/', [], [], $highlight));
    $data = (new ReflectionProperty($response, 'payload'))->getValue($response)['data'];
    projectionCheck($data['highlight_x'] === 0.0 && $data['highlight_y'] === 1.0, 'Evidenziazione condivisa anche ai bordi della pagina.');
    $controller->position(new Request('PATCH', '/', [], [], ['control_token' => 'test-control', 'scroll_ratio' => 0.6]));
    projectionCheck($database->session['highlight_x'] === 0.0 && $database->session['highlight_y'] === 1.0, 'Il solo scroll conserva evidenziazione.');
    foreach ([['highlight_x' => null], ['highlight_y' => null], ['highlight_x' => -0.1], ['highlight_y' => 1.1], ['highlight_x' => '0.5'], ['highlight_y' => true], ['highlight_x' => INF], ['highlight_y' => NAN]] as $invalid) {
        $before = $database->writes;
        projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], array_replace($highlight, $invalid))), 422);
        projectionCheck($database->writes === $before, 'Evidenziazione non valida respinta senza scritture.');
    }
    foreach ([$point + ['highlight_x' => 0.2], ['control_token' => 'test-control', 'document_id' => 9, 'highlight_x' => 0.2, 'highlight_y' => 0.3]] as $incomplete) {
        projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], $incomplete)), 422);
    }
    $before = $database->writes;
    projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], array_replace($highlight, ['document_id' => 8]))), 409);
    projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], array_replace($highlight, ['control_token' => 'test-public']))), 403);
    projectionCheck($database->writes === $before, 'Spettatori e documenti precedenti non modificano evidenziazione.');
    foreach ([$point, $point + ['highlight_x' => null, 'highlight_y' => null]] as $clear) {
        $controller->position(new Request('PATCH', '/', [], [], $highlight));
        $controller->position(new Request('PATCH', '/', [], [], $clear));
        projectionCheck($database->session['highlight_x'] === null && $database->session['highlight_y'] === null, 'Movimento normale del puntatore cancella evidenziazione.');
    }
    $frozen = $highlight + ['highlight_page' => 1, 'highlight_end_x' => 0.4, 'highlight_end_y' => 0.6];
    $response = $controller->position(new Request('PATCH', '/', [], [], $frozen));
    $data = (new ReflectionProperty($response, 'payload'))->getValue($response)['data'];
    projectionCheck($data['highlight_page'] === 1 && $data['highlight_end_x'] === 0.4 && $data['highlight_end_y'] === 0.6, 'Area fissa distinta dalla pagina e dal punto del puntatore.');
    $controller->position(new Request('PATCH', '/', [], [], array_replace($frozen, ['pointer_page' => 3, 'pointer_x' => 0.8, 'pointer_y' => 0.9])));
    projectionCheck($database->session['highlight_page'] === 1 && $database->session['highlight_end_x'] === 0.4 && $database->session['highlight_end_y'] === 0.6, 'Spostare il puntatore non altera il rettangolo congelato.');
    foreach ([['highlight_page' => null], ['highlight_page' => 0], ['highlight_page' => 65536], ['highlight_page' => true], ['highlight_end_x' => null], ['highlight_end_x' => -0.1], ['highlight_end_y' => 1.1], ['highlight_end_y' => '0.5'], ['highlight_end_x' => NAN], ['highlight_x' => null, 'highlight_y' => null]] as $invalid) {
        $before = $database->writes;
        projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], array_replace($frozen, $invalid))), 422);
        projectionCheck($database->writes === $before, 'Rettangolo fisso non valido respinto senza scritture.');
    }
    foreach ([$highlight + ['highlight_page' => 1], $point + ['highlight_page' => 1, 'highlight_end_x' => 0.2, 'highlight_end_y' => 0.3]] as $incomplete) {
        projectionDenied(fn () => $controller->position(new Request('PATCH', '/', [], [], $incomplete)), 422);
    }
    $controller->position(new Request('PATCH', '/', [], [], $point + ['highlight_x' => null, 'highlight_y' => null, 'highlight_page' => null, 'highlight_end_x' => null, 'highlight_end_y' => null]));
    projectionCheck($database->session['highlight_page'] === null && $database->session['highlight_end_x'] === null && $database->session['highlight_end_y'] === null, 'Cancellazione completa rettangolo fisso.');
    $controller->position(new Request('PATCH', '/', [], [], $frozen));
    $repository->publish(1, 9);
    projectionCheck($database->session['highlight_x'] === null && $database->session['highlight_y'] === null, 'Una nuova pubblicazione azzera evidenziazione.');
    projectionCheck($database->session['highlight_page'] === null && $database->session['highlight_end_x'] === null && $database->session['highlight_end_y'] === null, 'Una nuova pubblicazione azzera anche gli estremi fissi.');
    projectionCheck($database->document['visibility'] === 'creator', 'Evidenziazione non modifica documento o visibilita.');
    echo "OK: {$checks} controlli di conferma, permessi, PDF, puntatore ed evidenziazione. Database solo simulato.\n";
} finally {
    if (is_file($pdfPath)) unlink($pdfPath);
    rmdir($root . '/public/documents');
    rmdir($root . '/public');
    rmdir($root);
}
