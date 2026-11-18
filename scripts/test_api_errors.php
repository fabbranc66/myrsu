<?php

declare(strict_types=1);

use App\Controllers\Api\FundController;
use App\Core\ApiErrorHandler;
use App\Core\Application;
use App\Core\HttpException;
use App\Core\Response;
use App\Repositories\ProtocolRepository;
use App\Services\FundProtocolService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
if (is_file($root . '/vendor/autoload.php')) require $root . '/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

final class ApiErrorTestPdo extends PDO
{
    public function __construct(private readonly Throwable $failure) {}
    public function prepare(string $query, array $options = []): PDOStatement|false { throw $this->failure; }
}

$checks = 0;
function checkApiError(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function errorPayload(Response $response): array
{
    return (new ReflectionProperty($response, 'payload'))->getValue($response);
}

function argumentFailure(string $privateArgument): Throwable
{
    return new RuntimeException("SQLSTATE SYNTHETIC C:\\private\\database.php\n[FORGED]");
}

$logPath = tempnam(sys_get_temp_dir(), 'myrsu_errors_');
if ($logPath === false) throw new RuntimeException('Log temporaneo non disponibile.');
$bootScript = null;
$originalLog = ini_get('error_log');
$originalDebug = getenv('APP_DEBUG');
$originalTraceArguments = ini_get('zend.exception_ignore_args');
$originalServer = $_SERVER;
ini_set('error_log', $logPath);
ini_set('zend.exception_ignore_args', '0');
try {
    foreach ([400, 401, 403, 404, 409, 422] as $status) {
        $response = ApiErrorHandler::response(new HttpException($status, 'Messaggio operativo di prova.'));
        checkApiError(errorPayload($response) === ['error' => ['message' => 'Messaggio operativo di prova.']], 'Errori client invariati.');
        checkApiError((new ReflectionProperty($response, 'status'))->getValue($response) === $status, 'Status client conservato.');
    }
    foreach (['', 'false', '0', 'true', '1'] as $debug) {
        putenv('APP_DEBUG=' . $debug);
        foreach ([argumentFailure('SYNTHETIC_PRIVATE_ARGUMENT'), new PDOException('SQLSTATE SYNTHETIC users.email'), new HttpException(503, 'SMTP SYNTHETIC internal-host:123')] as $failure) {
            $response = ApiErrorHandler::response($failure);
            $error = errorPayload($response)['error'];
            checkApiError(array_keys($error) === ['message', 'error_id'] && $error['message'] === 'Errore interno.', 'Nessun dettaglio tecnico al client, anche con debug attivo.');
            checkApiError(preg_match('/^[a-f0-9]{12}$/D', $error['error_id']) === 1, 'Identificativo errore valido.');
            checkApiError((new ReflectionProperty($response, 'status'))->getValue($response) === ($failure instanceof HttpException ? 503 : 500), 'Status server conservato.');
            checkApiError(str_contains((string)file_get_contents($logPath), '[MyRSU ' . $error['error_id'] . ']'), 'Identificativo correlato nei log.');
        }
    }

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/myrsu/api/v1/health';
    $_SERVER['SCRIPT_NAME'] = '/myrsu/index.php';
    foreach ([new PDOException('SYNTHETIC RUN database details'), new HttpException(502, 'SYNTHETIC RUN hosting details'), new HttpException(422, 'Dato di prova non valido.')] as $failure) {
        $app = new Application($root);
        $app->router->get('/api/v1/health', static fn () => throw $failure);
        ob_start();
        $app->run();
        $body = (string)ob_get_clean();
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        $expected = $failure instanceof HttpException ? $failure->status() : 500;
        checkApiError(http_response_code() === $expected, 'Dispatcher applicativo: status corretto.');
        checkApiError(!isset($payload['error']['detail']) && !isset($payload['error']['source']), 'Dispatcher senza source/detail.');
        checkApiError($payload['error']['message'] === ($expected < 500 ? $failure->getMessage() : 'Errore interno.'), 'Dispatcher usa il gestore comune.');
    }

    foreach ([new PDOException('SYNTHETIC FUND SQLSTATE private path'), new HttpException(422, 'Documento di prova non valido.')] as $failure) {
        $app = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($app, 'protocols'))->setValue($app, new ProtocolRepository(new ApiErrorTestPdo($failure)));
        (new ReflectionProperty($app, 'fundProtocol'))->setValue($app, new FundProtocolService($app));
        $controller = new FundController($app);
        [$protocol, $message] = (new ReflectionMethod($controller, 'protocolContractSafe'))->invoke($controller, ['document_id' => 99], 1);
        checkApiError($protocol === null, 'Protocollazione parziale resta non bloccante.');
        checkApiError($failure instanceof HttpException ? $message === $failure->getMessage() : str_starts_with($message, 'Errore interno. Codice errore: '), 'Messaggio parziale sicuro, stesso tipo stringa.');
    }

    $childCode = <<<'PHP'
namespace App\Services {
    final class AntiBotService {
        public function __construct() { throw new \RuntimeException('SYNTHETIC BOOT private config path'); }
    }
}
namespace {
    putenv('APP_DEBUG=true');
    $_SERVER['REQUEST_URI'] = '/myrsu/api/v1/health';
    $_SERVER['SCRIPT_NAME'] = '/myrsu/index.php';
    require $argv[1];
}
PHP;
    $bootScript = tempnam(sys_get_temp_dir(), 'myrsu_boot_');
    if ($bootScript === false || file_put_contents($bootScript, "<?php\n" . $childCode) === false) throw new RuntimeException('Fixture di boot temporanea non disponibile.');
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_log=' . $logPath, '-f', $bootScript, $root . '/index.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Processo isolato di test non disponibile.');
    fclose($pipes[0]);
    $bootBody = stream_get_contents($pipes[1]);
    $bootStderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $bootExit = proc_close($process);
    if ($bootBody === '') throw new RuntimeException('Boot fixture senza JSON: exit ' . $bootExit . '; stderr ' . $bootStderr . '; log ' . substr((string)file_get_contents($logPath), -1400));
    $bootPayload = json_decode($bootBody, true, 512, JSON_THROW_ON_ERROR);
    checkApiError($bootPayload['error']['message'] === 'Errore interno.' && isset($bootPayload['error']['error_id']), 'Eccezione prima di run gestita in index.');
    checkApiError(!str_contains($bootBody, 'SYNTHETIC') && !str_contains($bootBody, 'detail') && $bootStderr === '', 'Boot senza dettagli o warning nel client.');
    $log = (string)file_get_contents($logPath);
    checkApiError(str_contains($log, 'SYNTHETIC BOOT private config path'), 'Dettaglio boot conservato nel log.');
    checkApiError(str_contains($log, 'SQLSTATE SYNTHETIC') && str_contains($log, '"trace":'), 'Tipo, dettaglio e stack tecnico conservati.');
    checkApiError(!str_contains($log, 'SYNTHETIC_PRIVATE_ARGUMENT'), 'Argomenti delle funzioni esclusi dai log.');
    checkApiError(!str_contains($log, "\n[FORGED]"), 'Messaggi su una riga JSON, senza iniezione di righe log.');
} finally {
    $_SERVER = $originalServer;
    ini_set('error_log', (string)$originalLog);
    ini_set('zend.exception_ignore_args', (string)$originalTraceArguments);
    putenv($originalDebug === false ? 'APP_DEBUG' : 'APP_DEBUG=' . $originalDebug);
    if (is_file($logPath)) unlink($logPath);
    if (is_string($bootScript) && is_file($bootScript)) unlink($bootScript);
}

echo "OK: {$checks} controlli errori API, debug, status, log correlati, dispatcher, boot e messaggi parziali. Nessuna connessione DB o modifica ai documenti.\n";
