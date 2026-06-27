<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class RenderedPdfUploadService
{
    public function path(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, 'PDF rasterizzato non valido.');
        }

        $path = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($path === '' || $size <= 0 || $size > 52428800) {
            throw new HttpException(422, 'PDF rasterizzato non valido.');
        }
        if ((mime_content_type($path) ?: '') !== 'application/pdf') {
            throw new HttpException(422, 'Formato PDF rasterizzato non valido.');
        }
        if ((string)file_get_contents($path, false, null, 0, 5) !== '%PDF-') {
            throw new HttpException(422, 'Contenuto PDF rasterizzato non valido.');
        }

        return $path;
    }
}
