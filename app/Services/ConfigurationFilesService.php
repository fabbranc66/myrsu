<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use FilesystemIterator;

final class ConfigurationFilesService
{
    public const DIRECTORIES = ['public/documents', 'storage/private/originals', 'storage/private/report-attachments', 'storage/private/room-attachments', 'storage/private/email-attachments'];

    public function __construct(private readonly string $basePath)
    {
    }

    public function root(array $configuration): string
    {
        return $configuration['table_prefix'] === '' ? $this->basePath : $this->basePath . '/storage/private/configurations/' . (int)$configuration['id'];
    }

    public function copy(array $source, array $target): void
    {
        foreach (self::DIRECTORIES as $directory) {
            $this->copyDirectory($this->root($source) . '/' . $directory, $this->root($target) . '/' . $directory);
        }
    }

    public function remove(array $configuration): void
    {
        if ($configuration['table_prefix'] === '' || (int)$configuration['id'] <= 0) throw new HttpException(422, 'Archivio originale protetto.');
        $path = $this->root($configuration);
        $root = str_replace('\\', '/', realpath($this->basePath . '/storage/private/configurations') ?: '');
        $resolved = str_replace('\\', '/', realpath($path) ?: '');
        if ($root === '' || $resolved === '' || !str_starts_with($resolved, $root . '/') || is_link($path)) return;
        $this->removeDirectory($path);
    }

    private function copyDirectory(string $source, string $target): void
    {
        if (!is_dir($source)) return;
        if (is_link($source)) throw new HttpException(422, 'Collegamenti simbolici non ammessi negli archivi.');
        if (!is_dir($target) && !mkdir($target, 0775, true) && !is_dir($target)) throw new HttpException(500, 'Creazione archivio file fallita.');
        foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isLink()) throw new HttpException(422, 'Collegamenti simbolici non ammessi negli archivi.');
            $destination = $target . '/' . $item->getFilename();
            if ($item->isDir()) $this->copyDirectory($item->getPathname(), $destination);
            elseif (!copy($item->getPathname(), $destination)) throw new HttpException(500, 'Copia dei file della configurazione fallita.');
        }
    }

    private function removeDirectory(string $path): void
    {
        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
            if ($item->isLink()) unlink($item->getPathname());
            elseif ($item->isDir()) $this->removeDirectory($item->getPathname());
            else unlink($item->getPathname());
        }
        rmdir($path);
    }
}
