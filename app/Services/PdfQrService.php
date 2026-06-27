<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class PdfQrService
{
    public function image(string $url, string $name, float $x, float $y, float $size): array
    {
        $matrix = (new QRCode(new QROptions()))->addByteSegment($url)->getQRMatrix();
        $scale = 6;
        $pixelSize = $matrix->getSize() * $scale;
        $image = imagecreatetruecolor($pixelSize, $pixelSize);
        if ($image === false) {
            throw new HttpException(500, 'QR non generato.');
        }
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($row = 0; $row < $matrix->getSize(); $row++) {
            for ($column = 0; $column < $matrix->getSize(); $column++) {
                if ($matrix->check($column, $row)) {
                    imagefilledrectangle(
                        $image,
                        $column * $scale,
                        $row * $scale,
                        (($column + 1) * $scale) - 1,
                        (($row + 1) * $scale) - 1,
                        $black
                    );
                }
            }
        }

        ob_start();
        if (!imagejpeg($image, null, 92)) {
            ob_end_clean();
            throw new HttpException(500, 'QR non generato.');
        }
        $data = (string)ob_get_clean();

        return [
            'name' => $name,
            'data' => $data,
            'width' => $pixelSize,
            'height' => $pixelSize,
            'rect' => [$x, $y, $size, $size],
        ];
    }
}
