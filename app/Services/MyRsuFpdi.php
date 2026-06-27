<?php

declare(strict_types=1);

namespace App\Services;

use setasign\Fpdi\Fpdi;

final class MyRsuFpdi extends Fpdi
{
    public function rotatedText(float $x, float $y, string $text, float $angle): void
    {
        $radians = deg2rad($angle);
        $cosine = cos($radians);
        $sine = sin($radians);
        $centerX = $x * $this->k;
        $centerY = ($this->h - $y) * $this->k;
        $this->_out(sprintf(
            'q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',
            $cosine,
            $sine,
            -$sine,
            $cosine,
            $centerX,
            $centerY,
            -$centerX,
            -$centerY
        ));
        $this->Text($x, $y, $text);
        $this->_out('Q');
    }
}
