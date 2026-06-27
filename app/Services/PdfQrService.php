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
        $png = (new QRCode(new QROptions(['outputType' => QRCode::OUTPUT_IMAGE_PNG, 'scale' => 6])))->render($url);
        if (str_starts_with($png, 'data:image/')) {
            $png = base64_decode((string)preg_replace('#^data:image/[^;]+;base64,#', '', $png), true) ?: '';
        }

        $image = imagecreatefromstring($png);
        if ($image === false) {
            throw new HttpException(500, 'QR non generato.');
        }

        ob_start();
        imagejpeg($image, null, 92);
        $data = (string)ob_get_clean();
        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        return [
            'name' => $name,
            'data' => $data,
            'width' => $width,
            'height' => $height,
            'rect' => [$x, $y, $size, $size],
        ];
    }
}
