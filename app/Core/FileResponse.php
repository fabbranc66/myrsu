<?php

declare(strict_types=1);

namespace App\Core;

final class FileResponse
{
    public function __construct(
        private readonly string $path,
        private readonly string $downloadName,
        private readonly string $mimeType,
        private readonly bool $inline = false
    ) {
    }

    public function send(): void
    {
        header('Content-Type: ' . $this->mimeType);
        header('Content-Disposition: ' . ($this->inline ? 'inline' : 'attachment') . '; filename="' . basename($this->downloadName) . '"');
        header('Content-Length: ' . filesize($this->path));

        readfile($this->path);
    }
}
