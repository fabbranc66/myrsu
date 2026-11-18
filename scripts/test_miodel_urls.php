<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
$originalServer = $_SERVER;
$originalUrl = getenv('APP_URL');
$checks = 0;
$classes = ['App\\Controllers\\Api\\ComunicatoController', 'App\\Controllers\\Api\\DocumentController', 'App\\Controllers\\Api\\ProtocolController', 'App\\Controllers\\Api\\WorkersAssemblyController', 'App\\Controllers\\Api\\UnionPermitRequestController', 'App\\Controllers\\Api\\ReportController', 'App\\Controllers\\Api\\UnionMeetingController', 'App\\Services\\FundProtocolService'];
try {
    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);
        $object = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod($reflection->hasMethod('baseUrl') ? 'baseUrl' : 'appBaseUrl');
        foreach ([
            [[], null, 'http://localhost/miodel'],
            [[], 'https://example.invalid/miodel/', 'https://example.invalid/miodel'],
            [['HTTP_HOST' => 'localhost', 'SCRIPT_NAME' => '/miodel/index.php'], null, 'http://localhost/miodel'],
            [['HTTP_HOST' => 'example.invalid', 'HTTPS' => 'on', 'SCRIPT_NAME' => '/miodel/index.php'], null, 'https://example.invalid/miodel'],
            [['HTTP_HOST' => 'example.invalid', 'HTTPS' => 'on', 'SCRIPT_NAME' => '/myrsu/index.php'], null, 'https://example.invalid/myrsu'],
        ] as [$server, $url, $expected]) {
            $_SERVER = $server;
            putenv($url === null ? 'APP_URL' : 'APP_URL=' . $url);
            if ($class === 'App\\Controllers\\Api\\UnionPermitRequestController') $expected = $url === null ? 'http://localhost/miodel' : rtrim($url, '/');
            if ($method->invoke($object) !== $expected) throw new RuntimeException('Indirizzo non coerente: ' . $class);
            $checks++;
        }
    }
    foreach (['miodel', 'myrsu'] as $prefix) {
        $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/' . $prefix . '/api/v1/documents?probe=1', 'SCRIPT_NAME' => '/' . $prefix . '/index.php', 'HTTP_AUTHORIZATION' => 'Bearer test-relocation'];
        $request = App\Core\Request::capture();
        if ($request->path() !== '/api/v1/documents' || $request->method() !== 'POST' || $request->bearerToken() !== 'test-relocation') throw new RuntimeException('Routing o autorizzazione non conservati.');
        $checks++;
    }
    $legacy = (string)file_get_contents($root . '/scripts/deployment/legacy-myrsu/.htaccess');
    if (!str_contains($legacy, 'RewriteRule ^(.*)$ /miodel/$1 [R=308,L]') || str_contains($legacy, 'R=301') || str_contains($legacy, 'R=302') || !str_contains($legacy, '^storage/private') || !str_contains($legacy, '(?!well-known')) throw new RuntimeException('Compatibilita o protezioni non coerenti.');
    $checks++;
} finally {
    $_SERVER = $originalServer;
    putenv($originalUrl === false ? 'APP_URL' : 'APP_URL=' . $originalUrl);
}
echo "OK: {$checks} controlli URL MioDel, compatibilita myrsu, routing e token. Nessun database o documento modificato.\n";
