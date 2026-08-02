<?php

declare(strict_types=1);

namespace App\Services;

final class NormativaTextCleaner
{
    public static function clean(string $text): string
    {
        $text = strtr($text, [
            'Ã¨' => 'è', 'Ã©' => 'é', 'Ã ' => 'à', 'Ã¹' => 'ù', 'Ã²' => 'ò', 'Ã¬' => 'ì',
            'Ãˆ' => 'È', 'àˆ' => 'È', 'â€™' => "'", 'â€œ' => '"', 'â€' => '"',
            'â€“' => '-', 'â€”' => '-', 'â€¢' => '-', 'â€¦' => '…',
            'Â«' => '«', 'Â»' => '»', 'Â°' => '°', "Â " => ' ',
            'pià¹' => 'più', 'puà²' => 'può', 'nonchà©' => 'nonché',
            'perchà©' => 'perché', 'cià²' => 'ciò',
            'interministerial e' => 'interministeriale', 'decreto -legge' => 'decreto-legge',
            'modalitàdi' => 'modalità di', 'ten uto' => 'tenuto', 'presen te' => 'presente',
            'cantier i' => 'cantieri', 'organizz azioni' => 'organizzazioni',
            'traspor ti' => 'trasporti', 'rappresentat ive' => 'rappresentative',
            'del la legge' => 'della legge', 'mo dalità' => 'modalità', 'pu ò' => 'può',
            'puà²' => 'può', 'pià¹' => 'più', 'nà©' => 'né', 'altresà¬' => 'altresì',
            'Â½' => '½', '1Â°' => '1°',
        ]);
        $text = preg_replace('/\bR\.\s*s\.\s*u\.?/iu', 'RSU', $text) ?? $text;
        $text = preg_replace('/\b[Nn]\s*[°º]\s*/u', 'N. ', $text) ?? $text;
        $text = preg_replace('/\bn\.\s*\R\s*(\d+)/u', 'n. $1', $text) ?? $text;
        $text = preg_replace('/…{2,}/u', '________________', $text) ?? $text;
        $text = preg_replace('/^\s*Industria metalmeccanica e installatrice\s*[^\p{L}\p{N}]*\s*Allegati\s*\d+\s*$/imu', '', $text) ?? $text;
        $text = str_replace('prenderàcontatti', 'prenderà contatti', $text);
        $text = preg_replace(
            '/MODULO PER LA RACCOLTA DELLE FIRME CERTIFICATE PER LA\R\s*PRESENTAZIONE DELLE LISTE PER LA ELEZIONE DELLA RSU\./u',
            'MODULO PER LA RACCOLTA DELLE FIRME CERTIFICATE PER LA PRESENTAZIONE DELLE LISTE PER LA ELEZIONE DELLA RSU.',
            $text
        ) ?? $text;
        $text = preg_replace('/elezioni della RSU\R\s*di cui/iu', 'elezioni della RSU di cui', $text) ?? $text;
        $text = preg_replace(
            '/(?:^|\R)4?Livello Durata ordinaria Durata ridotta\R'
            . 'D1, D2 e C1 1 mese e ½ 1 mese\R'
            . 'C2, C3 e B1 3 mesi 2 mesi\R'
            . 'B2, B3 e A1 6 mesi 3 mesi/u',
            "\n| Livello | Durata ordinaria | Durata ridotta |\n"
            . "|---|---:|---:|\n"
            . "| D1, D2 e C1 | 1 mese e ½ | 1 mese |\n"
            . "| C2, C3 e B1 | 3 mesi | 2 mesi |\n"
            . "| B2, B3 e A1 | 6 mesi | 3 mesi |",
            $text
        ) ?? $text;
        $text = preg_replace('/(?<!\n)(ESEMPLIFICAZIONE PROFILI DI AREA FUNZIONALE)/u', "\n$1", $text) ?? $text;
        $text = preg_replace_callback('/([\p{L}]+)-\R([\p{Ll}]+)/u', static function (array $matches): string {
            return in_array(mb_strtolower($matches[1], 'UTF-8'), ['decreto'], true)
                ? $matches[1] . '-' . $matches[2]
                : $matches[1] . $matches[2];
        }, $text) ?? $text;
        $text = preg_replace(
            '/N\.\s+Nome e cognome\s+Documento di identità\s+firma/iu',
            "| N. | Nome e cognome | Documento di identità | Firma |\n|---:|---|---|---|",
            $text
        ) ?? $text;
        $text = preg_replace(
            '/(Firma RSU in carica Foglio N\.\s*_+\R)(\| N\. \| Nome e cognome \| Documento di identità \| Firma \|\R\|---:\|---\|---\|---\|)/u',
            "$2\n$1",
            $text
        ) ?? $text;
        return str_replace("\u{00A0}", ' ', $text);
    }
}
