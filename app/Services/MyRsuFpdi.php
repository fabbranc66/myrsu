<?php

declare(strict_types=1);

namespace App\Services;

use setasign\Fpdi\Fpdi;

final class MyRsuFpdi extends Fpdi
{
    private array $extGStates = [];

    public function setAlpha(float $alpha, string $blendMode = 'Normal'): void
    {
        $alpha = max(0.0, min(1.0, $alpha));
        $this->WithAlpha = $alpha < 1.0;
        $this->setExtGState($this->addExtGState([
            'ca' => $alpha,
            'CA' => $alpha,
            'BM' => '/' . $blendMode,
        ]));
    }

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

    private function addExtGState(array $parameters): int
    {
        $this->extGStates[] = ['parameters' => $parameters, 'object' => 0];
        return count($this->extGStates);
    }

    private function setExtGState(int $state): void
    {
        $this->_out(sprintf('/GS%d gs', $state));
    }

    protected function _putresources(): void
    {
        $this->putExtGStates();
        parent::_putresources();
    }

    protected function _putresourcedict(): void
    {
        parent::_putresourcedict();
        if ($this->extGStates === []) {
            return;
        }

        $this->_put('/ExtGState <<');
        foreach ($this->extGStates as $index => $state) {
            $this->_put('/GS' . ($index + 1) . ' ' . $state['object'] . ' 0 R');
        }
        $this->_put('>>');
    }

    private function putExtGStates(): void
    {
        foreach ($this->extGStates as $index => $state) {
            $this->_newobj();
            $this->extGStates[$index]['object'] = $this->n;
            $this->_put('<</Type /ExtGState');
            foreach ($state['parameters'] as $key => $value) {
                $this->_put('/' . $key . ' ' . $value);
            }
            $this->_put('>>');
            $this->_put('endobj');
        }
    }
}
