<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
$originalPassword = getenv('DB_PASSWORD');
$checks = 0;

function checkDatabaseConfig(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

try {
    foreach (['valore-sintetico-di-prova', 'test=con#simboli', '0', ''] as $value) {
        putenv('DB_PASSWORD=' . $value);
        $password = 'variabile-del-chiamante';
        $config = require $root . '/config/database.php';
        checkDatabaseConfig($config['password'] === $value, 'Usare esattamente il valore di ambiente, compresa una password vuota esplicita.');
        checkDatabaseConfig(array_keys($config) === ['host', 'database', 'username', 'password', 'charset'], 'Struttura della configurazione invariata.');
        checkDatabaseConfig($password === 'variabile-del-chiamante', 'Il file di configurazione non altera le variabili del chiamante.');
    }
    putenv('DB_PASSWORD');
    try {
        require $root . '/config/database.php';
        throw new RuntimeException('Una password assente non deve attivare valori di ripiego.');
    } catch (RuntimeException $exception) {
        checkDatabaseConfig(str_contains($exception->getMessage(), 'Configurazione database incompleta'), 'Assenza della variabile segnalata prima della connessione.');
        checkDatabaseConfig(str_contains($exception->getMessage(), 'DB_PASSWORD'), 'Messaggio operativo indica solo il nome della variabile mancante.');
    }
} finally {
    putenv($originalPassword === false ? 'DB_PASSWORD' : 'DB_PASSWORD=' . $originalPassword);
}

echo "OK: {$checks} controlli configurazione DB, variabile obbligatoria, valore esplicito e nessun ripiego. Nessuna connessione o modifica al database.\n";
