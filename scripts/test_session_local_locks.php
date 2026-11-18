<?php

declare(strict_types=1);

use App\Repositories\TokenRepository;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
require $root . '/app/Core/HttpException.php';
require $root . '/app/Repositories/TokenRepository.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
if (!in_array($config['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true) || ($config['database'] ?? '') !== 'myrsu') {
    throw new RuntimeException('Test consentito solo su myrsu locale.');
}
$connections = [];
foreach ([1, 2] as $connection) {
    $connections[] = new PDO('mysql:host=' . $config['host'] . ';dbname=myrsu;charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
[$first, $second] = $connections;
$userId = $first->query("SELECT id FROM users WHERE status = 'active' ORDER BY id LIMIT 1")->fetchColumn();
if ($userId === false) throw new RuntimeException('Serve un utente attivo per verificare il lock senza modificarlo.');
$accounts = $first->query('SELECT * FROM users ORDER BY id')->fetchAll();
$existingTokens = $first->query('SELECT * FROM api_tokens ORDER BY id')->fetchAll();
$token = bin2hex(random_bytes(32));
$hash = hash('sha256', $token);
$second->exec('SET SESSION innodb_lock_wait_timeout = 1');
$tokens = new TokenRepository($second);
$passwordStatement = $first->prepare('SELECT password_hash FROM users WHERE id = ?');
$passwordStatement->execute([(int)$userId]);
$passwordHash = (string)$passwordStatement->fetchColumn();
try {
    $first->beginTransaction();
    $lock = $first->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
    $lock->execute([(int)$userId]);
    try {
        $tokens->create((int)$userId, $token, 'local-session-lock-test', $passwordHash);
        throw new RuntimeException('Emissione del token non serializzata con il lock account.');
    } catch (PDOException $exception) {
        if ((int)($exception->errorInfo[1] ?? 0) !== 1205) throw $exception;
    }
    $first->rollBack();
    if ($tokens->findValidByHash($hash) !== null) throw new RuntimeException('Il timeout non deve emettere token.');
    $tokens->create((int)$userId, $token, 'local-session-lock-test', $passwordHash);
    if ($tokens->findValidByHash($hash) === null) throw new RuntimeException('Emissione ordinaria non riuscita dopo il rilascio del lock.');
    $tokenId = (int)$tokens->findValidByHash($hash)['id'];
    if (!$tokens->touch($tokenId) || !$tokens->touch($tokenId)) throw new RuntimeException('Due richieste nello stesso secondo devono restare valide.');
    foreach ([
        "created_at = NOW(), last_used_at = DATE_SUB(NOW(), INTERVAL 2 HOUR)",
        "created_at = DATE_SUB(NOW(), INTERVAL 24 HOUR), last_used_at = NOW()",
        "created_at = DATE_SUB(NOW(), INTERVAL 2 HOUR), last_used_at = NULL",
    ] as $timestamps) {
        $second->prepare('UPDATE api_tokens SET ' . $timestamps . ' WHERE token_hash = ?')->execute([$hash]);
        if ($tokens->findValidByHash($hash) !== null || $tokens->touch($tokenId)) throw new RuntimeException('Limite temporale non rispettato o token scaduto rinnovato.');
    }
    $second->prepare('DELETE FROM api_tokens WHERE token_hash = ?')->execute([$hash]);
    $staleHash = preg_replace_callback('/[a-zA-Z]/', static fn (array $match): string => ctype_lower($match[0]) ? strtoupper($match[0]) : strtolower($match[0]), $passwordHash, 1);
    if ($staleHash === $passwordHash) throw new RuntimeException('Hash non utilizzabile per il controllo binario.');
    try {
        $tokens->create((int)$userId, $token, 'local-session-lock-test', $staleHash);
        throw new RuntimeException('Un hash differente per maiuscole/minuscole non deve consentire il login.');
    } catch (App\Core\HttpException $exception) {
        if ($exception->status() !== 403) throw $exception;
    }
} finally {
    foreach ($connections as $pdo) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
    $second->prepare('DELETE FROM api_tokens WHERE token_hash = ?')->execute([$hash]);
}
function sessionLockDifferences(array $before, array $after): array
{
    $previous = array_column($before, null, 'id');
    $current = array_column($after, null, 'id');
    $differences = [];
    foreach (array_unique(array_merge(array_keys($previous), array_keys($current))) as $rowId) {
        if (!isset($previous[$rowId], $current[$rowId])) {
            $differences[$rowId] = isset($previous[$rowId]) ? ['removed'] : ['added'];
            continue;
        }
        foreach ($previous[$rowId] as $field => $value) {
            if ($value !== $current[$rowId][$field]) $differences[$rowId][] = $field;
        }
    }
    return $differences;
}
$differences = [
    'users' => sessionLockDifferences($accounts, $first->query('SELECT * FROM users ORDER BY id')->fetchAll()),
    'api_tokens' => sessionLockDifferences($existingTokens, $first->query('SELECT * FROM api_tokens ORDER BY id')->fetchAll()),
];
if ($differences['users'] !== [] || $differences['api_tokens'] !== []) {
    throw new RuntimeException('Account o sessioni preesistenti cambiati (soli ID e campi): ' . json_encode($differences, JSON_THROW_ON_ERROR));
}
echo "OK: MySQL locale: lock account, confronto binario credenziali, touch ripetuto e scadenze esatte 2h/24h. Solo un token di prova rimosso, account e sessioni preesistenti invariati.\n";
