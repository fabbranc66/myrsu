<?php

declare(strict_types=1);

namespace App\Services;

final class OfficeFileService
{
    private const EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'xlsm', 'ods', 'odt'];

    public function isOffice(array $file): bool
    {
        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        return in_array($extension, self::EXTENSIONS, true);
    }
}
