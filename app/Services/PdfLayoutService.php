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
    public const VERIFY_LINK_RECT = [260, 706, 490, 720];

    public function page(string $body, array $header = [], string $footer = 'RSU Sitem Canegrate'): array
    {
        return [
            'content' => $this->watermark() . $this->header($header) . $body . $this->footer($footer),
            'images' => [],
            'links' => [],
        ];
    }

    public function header(array $data): string
    {
        if (($data['simple_brand'] ?? false) === true) {
            $brand = array_key_exists('brand', $data) ? trim((string)$data['brand']) : 'RSU';
            $brandSubtitle = array_key_exists('brand_subtitle', $data) ? trim((string)$data['brand_subtitle']) : 'Canegrate';
            $date = (string)($data['date'] ?? date('Y-m-d'));
            $content = "q 0 g\n";
            $content .= $this->text(42, 774, 30, self::FONT_BOLD, substr($brand, 0, 18));
            $content .= $this->text(44, 755, 12, self::FONT_REGULAR, substr($brandSubtitle, 0, 28));
            $content .= $this->text(430, 774, 10, self::FONT_REGULAR, 'Data: ' . substr($date, 0, 10));
            $content .= "0 G 0.8 w 42 688 m 553 688 l S\n";
            return $content . "Q\n";
        }

        $number = (string)($data['number'] ?? '-');
        $protocol = (string)($data['protocol'] ?? '-');
        $date = (string)($data['date'] ?? date('Y-m-d H:i'));
        $creator = trim((string)($data['creator'] ?? '')) ?: '-';
        $revision = trim((string)($data['revision'] ?? '')) ?: '-';
        $verifyText = (string)($data['verify_text'] ?? 'Verifica autenticita copia digitale');
        $brand = array_key_exists('brand', $data) ? trim((string)$data['brand']) : 'RSU';
        $brandSubtitle = array_key_exists('brand_subtitle', $data) ? trim((string)$data['brand_subtitle']) : 'Canegrate';

        $content = "q 0 g\n";
        $content .= $this->text(42, 774, 30, self::FONT_BOLD, substr($brand, 0, 14));
        $content .= $this->text(44, 755, 12, self::FONT_REGULAR, substr($brandSubtitle, 0, 24));
        $content .= $this->text(150, 780, 9, self::FONT_BOLD, 'Doc/n pag');
        $content .= $this->text(260, 780, 9, self::FONT_REGULAR, $number);
        $content .= $this->text(150, 762, 9, self::FONT_BOLD, 'Prot');
        $content .= $this->text(260, 762, 9, self::FONT_REGULAR, $protocol);
        $content .= $this->text(150, 744, 9, self::FONT_BOLD, 'Data');
        $content .= $this->text(260, 744, 9, self::FONT_REGULAR, $date . ' - ' . $creator);
        $content .= $this->text(150, 727, 9, self::FONT_BOLD, 'Rev');
        $content .= $this->text(260, 727, 9, self::FONT_REGULAR, $revision);
        $content .= $this->text(150, 710, 9, self::FONT_BOLD, 'Verifica');
        $content .= $this->text(260, 710, 9, self::FONT_REGULAR, $verifyText);
        $lineY = 688;
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
        return "q /GS1 gs 0.72 g 0.8829 0.4695 -0.4695 0.8829 298 625 cm "
            . "BT /F2 105 Tf -98 18 Td (RSU) Tj ET BT /F2 28 Tf -120 -36 Td (Sitem Canegrate) Tj ET Q\n"
            . "q /GS1 gs 0.72 g 0.8829 0.4695 -0.4695 0.8829 298 310 cm "
            . "BT /F2 105 Tf -98 18 Td (RSU) Tj ET BT /F2 28 Tf -120 -36 Td (Sitem Canegrate) Tj ET Q\n";
    }

    public function text(float $x, float $y, int $size, string $font, string $text): string
    {
        return sprintf("BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n", $font, $size, $x, $y, $this->pdfString($text));
    }

    public function justifiedText(float $x, float $y, int $size, string $font, string $text, float $width): string
    {
        $wordCount = substr_count(trim($text), ' ');
        if ($wordCount < 1) {
            return $this->text($x, $y, $size, $font, $text);
        }

        $textWidth = strlen($text) * $size * 0.48;
        $wordSpacing = max(0, ($width - $textWidth) / $wordCount);

        return sprintf(
            "BT /%s %d Tf %.3F Tw %.2F %.2F Td (%s) Tj 0 Tw ET\n",
            $font,
            $size,
            $wordSpacing,
            $x,
            $y,
            $this->pdfString($text)
        );
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
