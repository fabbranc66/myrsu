<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use PDO;
use Throwable;

final class ConfigurationBackupService
{
    public function __construct(private readonly PDO $pdo, private readonly string $root)
    {
    }

    public function create(): string
    {
        $directory = $this->root . '/storage/private/backups';
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new HttpException(500, 'Backup preventivo non disponibile.');
        $name = 'practice_transfer_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.sql';
        $path = $directory . '/' . $name;
        $file = fopen($path, 'xb');
        if ($file === false) throw new HttpException(500, 'Creazione backup preventivo fallita.');
        $write = static function (string $text) use ($file): void {
            if (fwrite($file, $text) !== strlen($text)) throw new HttpException(500, 'Scrittura backup incompleta: spostamento annullato.');
        };
        try {
            $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            foreach ($this->pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) throw new HttpException(500, 'Tabella backup non valida.');
                $identifier = '`' . $table . '`';
                $definition = $this->pdo->query('SHOW CREATE TABLE ' . $identifier)->fetch(PDO::FETCH_NUM)[1];
                $write('DROP TABLE IF EXISTS ' . $identifier . ";\n" . $definition . ";\n");
                $columns = array_column(array_filter($this->pdo->query('SHOW COLUMNS FROM ' . $identifier)->fetchAll(), static fn (array $column): bool => !preg_match('/(VIRTUAL|STORED) GENERATED/', $column['Extra'])), 'Field');
                $columnSql = implode(', ', array_map(static fn (string $column): string => '`' . $column . '`', $columns));
                foreach ($this->pdo->query('SELECT ' . $columnSql . ' FROM ' . $identifier) as $row) {
                    $values = array_map(fn (mixed $value): string => $value === null ? 'NULL' : $this->pdo->quote((string)$value), array_values($row));
                    $write('INSERT INTO ' . $identifier . ' (' . $columnSql . ') VALUES (' . implode(', ', $values) . ");\n");
                }
            }
            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            if (!fflush($file)) throw new HttpException(500, 'Backup non completato.');
        } catch (Throwable $exception) {
            fclose($file);
            unlink($path);
            throw $exception;
        }
        fclose($file);
        return $name;
    }
}
