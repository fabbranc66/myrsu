<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class NormativaFileService
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function read(string $sourcePath): array
    {
        $path = $this->resolve($sourcePath);
        return [
            'source_path' => $sourcePath,
            'content' => file_get_contents($path) ?: '',
        ];
    }

    public function write(string $sourcePath, string $content): array
    {
        $path = $this->resolve($sourcePath);
        file_put_contents($path, $content);
        return $this->read($sourcePath);
    }

    private function resolve(string $sourcePath): string
    {
        $cleanPath = str_replace('\\', '/', trim($sourcePath));
        $cleanPath = preg_replace('#^\.\./#', '', $cleanPath) ?? '';
        if (
            !str_starts_with($cleanPath, 'docs/ccnl_work/clean/')
            && !str_starts_with($cleanPath, 'docs/representation_work/clean/')
            && !str_starts_with($cleanPath, 'docs/safety_work/clean/')
        ) {
            throw new HttpException(403, 'File normativa non modificabile.');
        }
        if (!str_ends_with($cleanPath, '.md')) {
            throw new HttpException(403, 'Formato normativa non valido.');
        }
        $path = realpath($this->basePath . '/' . $cleanPath);
        $allowedRoots = [
            realpath($this->basePath . '/docs/ccnl_work/clean'),
            realpath($this->basePath . '/docs/representation_work/clean'),
            realpath($this->basePath . '/docs/safety_work/clean'),
        ];
        if ($path === false || !is_file($path)) {
            throw new HttpException(404, 'File normativa non trovato.');
        }
        foreach ($allowedRoots as $root) {
            if ($root && str_starts_with($path, $root)) return $path;
        }
        throw new HttpException(403, 'Percorso normativa non consentito.');
    }
}
