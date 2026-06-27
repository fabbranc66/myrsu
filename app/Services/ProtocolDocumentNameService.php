<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class ProtocolDocumentNameService
{
    public function code(string $protocolNumber): string
    {
        if (!preg_match('/^RSU-(?:IN|OUT)-([A-Z0-9]+)-(\d{4})-(\d+)$/', strtoupper($protocolNumber), $matches)) {
            throw new HttpException(422, 'Numero di protocollo non valido.');
        }

        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    public function publicPath(string $category, string $protocolNumber): string
    {
        return 'public/documents/' . $category . '/' . $this->code($protocolNumber) . '.pdf';
    }

    public function move(string $currentPath, string $targetPath): void
    {
        if ($currentPath === $targetPath) {
            return;
        }

        $directory = dirname($targetPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        if (is_file($targetPath)) {
            unlink($targetPath);
        }
        if (is_file($currentPath) && !rename($currentPath, $targetPath)) {
            throw new HttpException(500, 'Rinomina documento fallita.');
        }
    }
}
