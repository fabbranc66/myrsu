<?php

declare(strict_types=1);

namespace App\Services;

final class PdfLayoutService
{
    public const PAGE_WIDTH = 595.28;
    public const PAGE_HEIGHT = 841.89;
    public const MARGIN_LEFT = 42.0;
    public const MARGIN_RIGHT = 42.0;
    public const MARGIN_TOP = 42.0;
    public const MARGIN_BOTTOM = 42.0;
    public const HEADER_TOP = 42.0;
    public const HEADER_HEIGHT = 86.0;
    public const BODY_TOP = 660.0;
    public const FOOTER_Y = 36.0;
    public const FONT_REGULAR = 'F1';
    public const FONT_BOLD = 'F2';

    public function page(string $body, array $header = [], string $footer = 'RSU Sitem Canegrate'): array
    {
        return [
            'content' => $this->header($header) . $body . $this->footer($footer) . $this->watermark(),
            'images' => [],
            'links' => [],
        ];
    }

    public function header(array $data): string
    {
        $number = (string)($data['number'] ?? '-');
        $protocol = (string)($data['protocol'] ?? '-');
        $date = (string)($data['date'] ?? date('Y-m-d H:i'));
        $revision = trim((string)($data['revision'] ?? ''));
        $verifyText = (string)($data['verify_text'] ?? 'Verifica autenticita copia digitale');

        $content = "q 0 g\n";
        $content .= $this->text(42, 774, 30, self::FONT_BOLD, 'RSU');
        $content .= $this->text(44, 755, 12, self::FONT_REGULAR, 'Canegrate');
        $content .= $this->text(150, 780, 9, self::FONT_BOLD, 'Documento / pagina');
        $content .= $this->text(260, 780, 9, self::FONT_REGULAR, $number);
        $content .= $this->text(150, 762, 9, self::FONT_BOLD, 'Protocollo');
        $content .= $this->text(260, 762, 9, self::FONT_REGULAR, $protocol);
        $content .= $this->text(150, 744, 9, self::FONT_BOLD, 'Data e ora');
        $content .= $this->text(260, 744, 9, self::FONT_REGULAR, $date);
        $content .= $this->text(150, 726, 9, self::FONT_BOLD, 'Verifica');
        $content .= $this->text(260, 726, 9, self::FONT_REGULAR, $verifyText);
        if ($revision !== '') {
            $content .= $this->text(150, 708, 9, self::FONT_BOLD, 'Ultima revisione');
            $content .= $this->text(260, 708, 9, self::FONT_REGULAR, $revision);
        }
        $lineY = $revision !== '' ? 686 : 696;
        $content .= "0 G 0.8 w 42 {$lineY} m 553 {$lineY} l S\n";

        return $content . "Q\n";
    }

    public function footer(string $text): string
    {
        return $this->text(42, self::FOOTER_Y, 8, self::FONT_REGULAR, $text);
    }

    public function verificationBlock(float $y, ?string $signature, ?string $verifyUrl): array
    {
        if ($signature === null || $signature === '') {
            return ['content' => '', 'links' => [], 'y' => $y];
        }

        $y -= 14;
        $content = "0 G 0.45 w 42 {$y} m 553 {$y} l S\n";
        $y -= 20;
        $content .= $this->text(42, $y, 12, self::FONT_BOLD, 'Verifica autenticita copia digitale');
        $y -= 18;
        $content .= $this->text(42, $y, 9, self::FONT_REGULAR, 'Firma documento: ' . $signature);
        $y -= 15;
        $content .= $this->text(42, $y, 9, self::FONT_REGULAR, 'Checksum verificato sul file PDF conservato dal sistema.');
        $y -= 15;
        $links = [];

        if ($verifyUrl !== null && $verifyUrl !== '') {
            $content .= $this->text(42, $y, 9, self::FONT_REGULAR, 'Link verifica: ' . $verifyUrl);
            $links[] = ['rect' => [42, $y - 3, 553, $y + 11], 'url' => $verifyUrl];
            $y -= 15;
        }

        $content .= $this->text(42, $y, 8, self::FONT_REGULAR, 'La verifica conferma firma e integrita della copia digitale conservata.');
        return ['content' => $content, 'links' => $links, 'y' => $y - 18];
    }

    public function watermark(): string
    {
        return "q /GS1 gs 0.48 g 0.8829 0.4695 -0.4695 0.8829 298 625 cm "
            . "BT /F2 105 Tf -98 18 Td (RSU) Tj ET BT /F2 28 Tf -120 -36 Td (Sitem Canegrate) Tj ET Q\n"
            . "q /GS1 gs 0.48 g 0.8829 0.4695 -0.4695 0.8829 298 310 cm "
            . "BT /F2 105 Tf -98 18 Td (RSU) Tj ET BT /F2 28 Tf -120 -36 Td (Sitem Canegrate) Tj ET Q\n";
    }

    public function text(float $x, float $y, int $size, string $font, string $text): string
    {
        return sprintf("BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, $x, $y, $this->pdfString($text));
    }

    public function wrappedText(float $x, float $y, int $size, int $maxChars, string $text): array
    {
        $content = '';
        foreach (preg_split("/\R/", trim($text)) ?: [] as $paragraph) {
            foreach (explode("\n", wordwrap($paragraph, $maxChars, "\n", true)) as $line) {
                $content .= $this->text($x, $y, $size, self::FONT_REGULAR, $line);
                $y -= $size + 5;
            }
            $y -= 4;
        }

        return ['content' => $content, 'y' => $y];
    }

    public function pdfString(string $value): string
    {
        $value = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value) ?: $value;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
