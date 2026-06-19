<?php

declare(strict_types=1);

namespace App\Services;

final class DocumentHeaderService
{
    public function html(array $document, array $protocol): string
    {
        $direction = (string)($protocol['direction'] ?? '');
        $dateLabel = $direction === 'OUT' ? 'Data uscita' : 'Data ingresso';

        return '<div class="document-header">'
            . '<div class="document-header-logo">RSU</div>'
            . '<div class="document-header-data">'
            . $this->row('Documento', (string)($document['original_name'] ?? ''))
            . $this->row('Protocollo', (string)($protocol['protocol_number'] ?? ''))
            . $this->row('Oggetto', (string)($protocol['subject'] ?? ''))
            . $this->row('Direzione', $direction)
            . $this->row($dateLabel, (string)($protocol['created_at'] ?? ''))
            . $this->row('Categoria', (string)($document['category'] ?? ''))
            . $this->row('Hash PDF', (string)($document['pdf_checksum_sha256'] ?? ''))
            . '</div>'
            . '</div>';
    }

    public function css(): string
    {
        return '.document-header{border:1px solid #111;display:flex;font-family:Arial,sans-serif;margin-bottom:18px;}'
            . '.document-header-logo{align-items:center;border-right:1px solid #111;display:flex;font-size:28px;font-weight:700;justify-content:center;min-width:110px;padding:18px;}'
            . '.document-header-data{display:grid;flex:1;grid-template-columns:140px 1fr;}'
            . '.document-header-label{border-bottom:1px solid #111;border-right:1px solid #111;font-size:11px;font-weight:700;padding:6px;text-transform:uppercase;}'
            . '.document-header-value{border-bottom:1px solid #111;font-size:12px;padding:6px;word-break:break-word;}'
            . '.document-header-label:last-of-type,.document-header-value:last-child{border-bottom:0;}';
    }

    private function row(string $label, string $value): string
    {
        return '<div class="document-header-label">' . $this->escape($label) . '</div>'
            . '<div class="document-header-value">' . $this->escape($value) . '</div>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
