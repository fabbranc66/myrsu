<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PdfBrandingService
{
    public const CAPTION = 'Del. FIM-CISL';
    public const FOOTER = 'MioDelegato - Del. FIM-CISL - Canegrate';
    public const CENTERS = [515.0, 290.0];

    private static ?array $assets = null;

    public static function logoPath(): string
    {
        return dirname(__DIR__, 2) . '/ui/assets/miodelegato-logo.png';
    }

    public static function assets(): array
    {
        if (self::$assets !== null) return self::$assets;
        $logo = imagecreatefrompng(self::logoPath());
        if ($logo === false) throw new RuntimeException('Logo documentale non disponibile.');
        $limit = (int)round(imagesx($logo) * 0.265);
        $left = $limit;
        $top = imagesy($logo);
        $right = $bottom = 0;
        for ($row = 0; $row < imagesy($logo); $row++) {
            for ($column = 0; $column < $limit; $column++) {
                $alpha = (imagecolorat($logo, $column, $row) >> 24) & 127;
                if ($alpha > 37) continue;
                $left = min($left, $column);
                $top = min($top, $row);
                $right = max($right, $column + 1);
                $bottom = max($bottom, $row + 1);
            }
        }
        if ($right <= $left || $bottom <= $top) throw new RuntimeException('Simbolo documentale non individuato.');
        $left = max(0, $left - 3);
        $top = max(0, $top - 3);
        $right = min($limit, $right + 3);
        $bottom = min(imagesy($logo), $bottom + 3);
        $symbol = imagecrop($logo, ['x' => $left, 'y' => $top, 'width' => $right - $left, 'height' => $bottom - $top]);
        if ($symbol === false) throw new RuntimeException('Preparazione simbolo documentale fallita.');
        self::$assets = ['BrandLogo' => self::encode($logo), 'BrandSymbol' => self::encode($symbol), 'BrandSymbolBase' => self::encode($symbol, true)];
        imagedestroy($logo);
        imagedestroy($symbol);
        return self::$assets;
    }

    public static function watermark(): string
    {
        $symbol = self::assets()['BrandSymbol'];
        $height = 170 * $symbol['height'] / $symbol['width'];
        $bottom = -$height / 2 + 12;
        $content = '';
        foreach (self::CENTERS as $center) {
            $content .= sprintf(
                "q 0.882948 0.469472 -0.469472 0.882948 297.64 %.2F cm /BrandUnderlayAlpha gs q 170 0 0 %.3F -85 %.3F cm /BrandSymbolBase Do Q /BrandSymbolAlpha gs q 170 0 0 %.3F -85 %.3F cm /BrandSymbol Do Q /BrandUnderlayAlpha gs 1 g BT /F2 16 Tf -53.344 %.3F Td (%s) Tj ET /BrandCaptionAlpha gs 0.392157 0.454902 0.545098 rg BT /F2 16 Tf -53.344 %.3F Td (%s) Tj ET Q\n",
                $center, $height, $bottom, $height, $bottom, $bottom - 23, self::CAPTION, $bottom - 23, self::CAPTION
            );
        }
        return $content;
    }

    public static function header(): string
    {
        return "q 96 0 0 32 42 754 cm /BrandLogo Do Q\n"
            . "BT /F1 9 Tf 44 741 Td (" . self::CAPTION . ") Tj ET\n";
    }

    private static function encode(\GdImage $image, bool $white = false): array
    {
        $pngImage = $white ? imagecreatetruecolor(imagesx($image), imagesy($image)) : $image;
        if ($pngImage === false) throw new RuntimeException('Preparazione contrasto filigrana fallita.');
        imagealphablending($pngImage, false);
        imagesavealpha($pngImage, true);
        $rgb = $alpha = '';
        for ($row = 0; $row < imagesy($image); $row++) {
            for ($column = 0; $column < imagesx($image); $column++) {
                $pixel = imagecolorat($image, $column, $row);
                $rgb .= $white ? "\xFF\xFF\xFF" : chr(($pixel >> 16) & 255) . chr(($pixel >> 8) & 255) . chr($pixel & 255);
                $alpha .= chr((int)round((127 - (($pixel >> 24) & 127)) * 255 / 127));
                if ($white) imagesetpixel($pngImage, $column, $row, ($pixel & 0x7F000000) | 0xFFFFFF);
            }
        }
        ob_start();
        imagepng($pngImage);
        $png = (string)ob_get_clean();
        if ($white) imagedestroy($pngImage);
        return ['width' => imagesx($image), 'height' => imagesy($image), 'data' => gzcompress($rgb), 'alpha' => gzcompress($alpha), 'png' => $png];
    }
}
