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

    public function brandingWatermark(string $symbolPath, float $symbolHeight, float $centerY, string $basePath): void
    {
        $centerX = PdfLayoutService::PAGE_WIDTH / 2;
        $top = PdfLayoutService::PAGE_HEIGHT - $centerY;
        $this->_out(sprintf('q 0.882948 0.469472 -0.469472 0.882948 %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $centerX, $centerY, -$centerX, -$centerY));
        $this->setAlpha(0.32);
        $this->Image($basePath, $centerX - 85, $top - $symbolHeight / 2 - 12, 170, $symbolHeight, 'PNG');
        $this->setAlpha(0.11);
        $this->Image($symbolPath, $centerX - 85, $top - $symbolHeight / 2 - 12, 170, $symbolHeight, 'PNG');
        $this->SetFont('Helvetica', 'B', 16);
        $this->setAlpha(0.32);
        $this->SetTextColor(255, 255, 255);
        $this->Text($centerX - $this->GetStringWidth(PdfBrandingService::CAPTION) / 2, $top + $symbolHeight / 2 + 11, PdfBrandingService::CAPTION);
        $this->setAlpha(0.19);
        $this->SetTextColor(100, 116, 139);
        $this->Text($centerX - $this->GetStringWidth(PdfBrandingService::CAPTION) / 2, $top + $symbolHeight / 2 + 11, PdfBrandingService::CAPTION);
        $this->_out('Q');
        $this->setAlpha(1);
        $this->SetTextColor(0);
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
