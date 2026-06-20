<?php

declare(strict_types=1);

namespace App\Services;

final class ComunicatoPdfService
{
    public function __construct(private readonly string $basePath)
    {
    }

    public function html(string $title, string $body, string $protocolNumber, string $createdAt): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page{margin:22mm 18mm 24mm 18mm;}body{font-family:Arial,sans-serif;color:#111;font-size:12pt;line-height:1.55;}'
            . '.header{border:1px solid #111;display:grid;grid-template-columns:90px 1fr;margin-bottom:24px;}'
            . '.logo{align-items:center;border-right:1px solid #111;display:flex;font-size:26px;font-weight:700;justify-content:center;padding:18px 10px;}'
            . '.meta{display:grid;grid-template-columns:150px 1fr;}.label{border-bottom:1px solid #111;border-right:1px solid #111;font-size:10pt;font-weight:700;padding:8px;}'
            . '.value{border-bottom:1px solid #111;padding:8px;}.title{font-size:18pt;font-weight:700;margin:0 0 18px;}.body p{margin:0 0 12px;white-space:pre-wrap;}'
            . '.footer{margin-top:28px;font-weight:700;}.sign{margin-top:34px;}'
            . '</style></head><body>'
            . '<!-- MYRSU_COMUNICATO_TITLE:' . $this->escape($title) . ' -->'
            . '<!-- MYRSU_COMUNICATO_BODY:' . base64_encode($body) . ' -->'
            . '<div class="header"><div class="logo">RSU</div><div class="meta">'
            . $this->row('Protocollo', $protocolNumber)
            . $this->row('Data e ora', $createdAt)
            . $this->row('Oggetto', $title)
            . '</div></div>'
            . '<h1 class="title">' . $this->escape($title) . '</h1>'
            . '<div class="body">' . $this->body($body) . '</div>'
            . '<div class="footer">Cordiali saluti.</div>'
            . '<div class="sign">RSU</div>'
            . '</body></html>';
    }

    public function parse(string $html): array
    {
        preg_match('/MYRSU_COMUNICATO_TITLE:(.*?)-->/s', $html, $titleMatch);
        preg_match('/MYRSU_COMUNICATO_BODY:(.*?)-->/s', $html, $bodyMatch);
        preg_match('/<h1 class="title">(.*?)<\/h1>/s', $html, $titleHtmlMatch);
        preg_match('/<div class="body">(.*?)<\/div>/s', $html, $bodyHtmlMatch);

        return [
            'title' => $this->parsedTitle($titleMatch[1] ?? null, $titleHtmlMatch[1] ?? null),
            'body' => $this->parsedBody($bodyMatch[1] ?? null, $bodyHtmlMatch[1] ?? null),
        ];
    }

    private function parsedTitle(?string $comment, ?string $html): string
    {
        $value = trim((string)$comment);
        if ($value !== '') {
            return html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return trim(strip_tags(html_entity_decode((string)$html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
    }

    private function parsedBody(?string $comment, ?string $html): string
    {
        $value = base64_decode(trim((string)$comment), true);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $body = preg_replace('/<br\\s*\\/?>/i', "\n", (string)$html) ?? '';
        $body = preg_replace('/<\\/p>\\s*<p>/i', "\n\n", $body) ?? $body;
        return trim(strip_tags(html_entity_decode($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));
    }

    private function row(string $label, string $value): string
    {
        return '<div class="label">' . $this->escape($label) . '</div><div class="value">' . $this->escape($value) . '</div>';
    }

    private function body(string $body): string
    {
        $parts = preg_split("/\\R{2,}/", trim($body)) ?: [];
        return implode('', array_map(fn (string $part): string => '<p>' . nl2br($this->escape(trim($part))) . '</p>', $parts));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
