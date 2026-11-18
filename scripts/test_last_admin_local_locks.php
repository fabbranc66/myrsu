<?php

declare(strict_types=1);

use App\Repositories\RolePermissionRepository;
use App\Repositories\UserRepository;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
if (!in_array($config['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true) || ($config['database'] ?? '') !== 'myrsu') {
    throw new RuntimeException('Test consentito solo su myrsu locale.');
}
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});

function adminLockSnapshot(PDO $pdo): array
{
    return [
        $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll(),
        $pdo->query('SELECT * FROM role_user ORDER BY user_id, role_id')->fetchAll(),
    ];
}

$connections = [];
foreach ([1, 2] as $connection) {
    $connections[] = new PDO('mysql:host=' . $config['host'] . ';dbname=myrsu;charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
[$first, $second] = $connections;
foreach (['users', 'roles', 'role_user'] as $table) {
    $engine = $first->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $table . "'")->fetchColumn();
    if (strcasecmp((string)$engine, 'InnoDB') !== 0) throw new RuntimeException('Il test richiede le tabelle InnoDB previste dallo schema.');
}
$missingId = 2147483647;
if ((new UserRepository($first))->findById($missingId) !== null) throw new RuntimeException('ID di prova occupato: nessuna operazione eseguita.');
$before = adminLockSnapshot($first);
$second->exec('SET SESSION innodb_lock_wait_timeout = 1');
$operations = [
    'sospensione' => static fn () => (new UserRepository($second))->update($missingId, ['status' => 'suspended']),
    'eliminazione' => static fn () => (new UserRepository($second))->delete($missingId),
    'rimozione ruoli' => static fn () => (new RolePermissionRepository($second))->replaceUserRoles($missingId, []),
    'mantenimento admin' => static fn () => (new RolePermissionRepository($second))->replaceUserRoles($missingId, ['admin']),
];
$checks = 0;
try {
    $first->beginTransaction();
    if ($first->query("SELECT id FROM roles WHERE name = 'admin' FOR UPDATE")->fetchColumn() === false) throw new RuntimeException('Ruolo admin mancante.');
    foreach ($operations as $label => $operation) {
        try {
            $operation();
            throw new RuntimeException('Lock comune non rispettato: ' . $label);
        } catch (PDOException $exception) {
            if ((int)($exception->errorInfo[1] ?? 0) !== 1205 || $second->inTransaction()) throw $exception;
            $checks++;
        }
    }
} finally {
    foreach ($connections as $pdo) {
        if ($pdo->inTransaction()) $pdo->rollBack();
    }
}
if (adminLockSnapshot($first) !== $before) throw new RuntimeException('Account o ruoli cambiati: fermarsi e controllare.');
echo "OK: {$checks} percorsi serializzati su due connessioni MySQL locali. Solo lock e ID inesistente; account importati invariati.\n";
