<?php

declare(strict_types=1);

namespace App\Services;

final class ComunicatoPdfService
{
    public function parse(string $html): array
    {
        if (str_starts_with(trim($html), 'Titolo:')) {
            return $this->parseTextOriginal($html);
        }

        preg_match('/MYRSU_COMUNICATO_TITLE:(.*?)-->/s', $html, $titleMatch);
        preg_match('/MYRSU_COMUNICATO_BODY:(.*?)-->/s', $html, $bodyMatch);
        preg_match('/<h1 class="title">(.*?)<\/h1>/s', $html, $titleHtmlMatch);
        preg_match('/<div class="body">(.*?)<\/div>/s', $html, $bodyHtmlMatch);

        return [
            'title' => $this->parsedTitle($titleMatch[1] ?? null, $titleHtmlMatch[1] ?? null),
            'body' => $this->parsedBody($bodyMatch[1] ?? null, $bodyHtmlMatch[1] ?? null),
        ];
    }

    private function parseTextOriginal(string $text): array
    {
        $text = trim($text);
        if (preg_match('/^Titolo:\s*(.*?)\RProtocollo:.*?\RData e ora:.*?\R\R(.*)$/s', $text, $matches)) {
            return [
                'title' => trim((string)$matches[1]),
                'body' => $this->cleanRepeatedHeaders((string)$matches[2]),
            ];
        }

        [$header, $body] = array_pad(preg_split('/\R\R/', $text, 2) ?: [], 2, '');
        $title = preg_replace('/^Titolo:\s*/', '', $header) ?? $header;

        return [
            'title' => trim($title),
            'body' => $this->cleanRepeatedHeaders($body),
        ];
    }

    private function cleanRepeatedHeaders(string $body): string
    {
        do {
            $cleaned = preg_replace('/^Titolo:.*?\RProtocollo:.*?\RData e ora:.*?\R\R/s', '', $body) ?? $body;
            $changed = $cleaned !== $body;
            $body = trim($cleaned);
        } while ($changed);

        return trim($body);
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

}
