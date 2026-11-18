<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

define('BASE_PATH', __DIR__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

set_exception_handler(static function (Throwable $exception): void {
    App\Core\ApiErrorHandler::response($exception)->send();
});

require BASE_PATH . '/app/Core/helpers.php';
load_env(BASE_PATH . '/.env');
if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$appPath = $scriptDir === '' ? '/' : $scriptDir;
$baseName = '/' . trim(basename(BASE_PATH), '/');

if (
    in_array($requestPath, [$appPath, $appPath . '/', $appPath . '/index.php', $baseName, $baseName . '/', $baseName . '/index.php'], true)
) {
    header('Location: ' . $appPath . '/ui/app/index.html');
    exit;
}

$app = new App\Core\Application(BASE_PATH);
$app->run();
