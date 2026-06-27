<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class PdfImageFitService
{
    public function image(string $path, string $name, float $maxWidth, float $maxHeight, float $centerX, float $centerY): array
    {
        $jpeg = $this->jpeg($path);
        $scale = min($maxWidth / $jpeg['width'], $maxHeight / $jpeg['height']);
        $width = $jpeg['width'] * $scale;
        $height = $jpeg['height'] * $scale;

        return [
            'name' => $name,
            'data' => $jpeg['data'],
            'width' => $jpeg['width'],
            'height' => $jpeg['height'],
            'rect' => [$centerX - ($width / 2), $centerY - ($height / 2), $width, $height],
        ];
    }

    private function jpeg(string $path): array
    {
        if (!is_file($path)) {
            throw new HttpException(404, 'Immagine non trovata.');
        }

        $source = imagecreatefromstring((string)file_get_contents($path));
        if ($source === false) {
            throw new HttpException(422, 'Immagine non valida.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        ob_start();
        imagejpeg($canvas, null, 94);
        $data = (string)ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        return ['data' => $data, 'width' => $width, 'height' => $height];
    }
}
