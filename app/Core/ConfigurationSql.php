<?php

declare(strict_types=1);

namespace App\Core;

final class ConfigurationSql
{
    public static function map(string $sql, array $tables, string $prefix): string
    {
        if ($prefix === '') return $sql;
        if (!preg_match('/^cfg_[0-9]+_$/D', $prefix)) throw new \InvalidArgumentException('Prefisso configurazione non valido.');
        $mapping = array_fill_keys($tables, true);
        $pattern = '~\'(?:\'\'|\\\\.|[^\'\\\\])*\'|"(?:""|\\\\.|[^"\\\\])*"|`(?:``|[^`])*`|/\*.*?\*/|--[^\r\n]*|\#[^\r\n]*|:[A-Za-z_][A-Za-z0-9_]*|[A-Za-z_][A-Za-z0-9_]*~s';
        return preg_replace_callback($pattern, static function (array $match) use ($mapping, $prefix): string {
            $token = $match[0];
            $name = strtolower(trim($token, '`'));
            return isset($mapping[$name]) ? '`' . $prefix . $name . '`' : $token;
        }, $sql) ?? throw new \RuntimeException('Query configurazione non valida.');
    }
}
