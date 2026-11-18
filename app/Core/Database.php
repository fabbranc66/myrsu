<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(array $config, array $tables = [], string $prefix = '')
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['database'],
            $config['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = $prefix === ''
            ? new PDO($dsn, $config['username'], $config['password'], $options)
            : new ConfigurationPdo($dsn, $config['username'], $config['password'], $options, $tables, $prefix);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
