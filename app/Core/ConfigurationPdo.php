<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

final class ConfigurationPdo extends PDO
{
    public function __construct(string $dsn, string $username, string $password, array $options, private readonly array $tables, private readonly string $prefix)
    {
        parent::__construct($dsn, $username, $password, $options);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(ConfigurationSql::map($query, $this->tables, $this->prefix), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $query = ConfigurationSql::map($query, $this->tables, $this->prefix);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(ConfigurationSql::map($statement, $this->tables, $this->prefix));
    }
}
