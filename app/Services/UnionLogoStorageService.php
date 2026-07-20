<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class UnionLogoStorageService
{
    private string $path;

    public function __construct(string $basePath)
    {
        $this->path = $basePath . '/storage/private/union-logos';
        if (!is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }
    }

    public function store(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, 'Logo non valido.');
        }
        if ((int)$file['size'] <= 0 || (int)$file['size'] > 2097152) {
            throw new HttpException(422, 'Logo troppo grande.');
        }

        $info = getimagesize((string)$file['tmp_name']);
        $mime = (string)($info['mime'] ?? '');
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg((string)$file['tmp_name']),
            'image/png' => imagecreatefrompng((string)$file['tmp_name']),
            default => false,
        };
        if ($image === false) {
            throw new HttpException(422, 'Formato logo non valido.');
        }

        $storedName = bin2hex(random_bytes(16)) . '.jpg';
        if (!imagejpeg($image, $this->path . '/' . $storedName, 90)) {
            throw new HttpException(500, 'Salvataggio logo fallito.');
        }

        return $storedName;
    }

    public function image(string $storedName, string $name, float $x, float $y, float $width, float $height): ?array
    {
        $path = $this->path . '/' . basename($storedName);
        if (!is_file($path)) {
            return null;
        }
        $info = getimagesize($path);
        if ($info === false) {
            return null;
        }

        $ratio = min($width / (float)$info[0], $height / (float)$info[1]);
        $drawWidth = (float)$info[0] * $ratio;
        $drawHeight = (float)$info[1] * $ratio;

        return [
            'name' => $name,
            'data' => (string)file_get_contents($path),
            'width' => (int)$info[0],
            'height' => (int)$info[1],
            'rect' => [$x, $y + (($height - $drawHeight) / 2), $drawWidth, $drawHeight],
        ];
    }
}
