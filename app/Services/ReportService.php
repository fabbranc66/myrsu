<?php

declare(strict_types=1);

namespace App\Services;

final class ReportService
{
    public function trackingCode(): string
    {
        return 'SEG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    public function html(
        string $subject,
        string $message,
        string $contact,
        string $trackingCode,
        array $attachments = [],
        ?string $signature = null,
        ?string $verifyUrl = null,
        ?string $qrDataUri = null
    ): string {
        $attachmentsHtml = $this->attachmentsHtml($attachments);
        $signatureHtml = $this->signatureHtml($signature, $verifyUrl, $qrDataUri);

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . '@page{margin:20mm 16mm 22mm 16mm}body{font-family:Arial,sans-serif;color:#111827;padding:0}h1{font-size:24px}'
            . '.meta{border:1px solid #d1d5db;padding:12px;margin-bottom:20px}.body{font-size:15px;line-height:1.6}'
            . '.attachments{margin-top:24px;padding:12px;border:1px solid #d1d5db}.attachments h2{font-size:18px;margin:0 0 12px}'
            . '.attachment-item{margin:0 0 18px;page-break-inside:avoid}.attachment-name{font-weight:bold;margin-bottom:6px}'
            . '.attachment-image{display:block;border:1px solid #d1d5db;margin:0 auto;object-fit:contain}'
            . '.attachment-image.landscape{width:100%;height:auto}.attachment-image.portrait{height:620px;max-width:100%;width:auto}'
            . '.attachment-video{padding:10px;border:1px dashed #9ca3af;background:#f9fafb}'
            . '.signature{margin-top:24px;padding:12px;border:1px solid #d1d5db;background:#f9fafb}.signature a{color:#084298;text-decoration:underline}'
            . '.signature-qr{display:block;width:130px;height:130px;margin-top:10px}'
            . '</style></head><body>'
            . '<h1>Segnalazione</h1><div class="meta">'
            . '<strong>Codice:</strong> ' . $this->escape($trackingCode) . '<br>'
            . '<strong>Oggetto:</strong> ' . $this->escape($subject) . '<br>'
            . '<strong>Contatto:</strong> ' . $this->escape($contact !== '' ? $contact : '-')
            . '</div><div class="body">' . nl2br($this->escape($message)) . '</div>'
            . $attachmentsHtml
            . $signatureHtml
            . '</body></html>';
    }

    private function attachmentsHtml(array $attachments): string
    {
        if ($attachments === []) {
            return '<div class="attachments"><h2>Allegati</h2><p>Nessun allegato.</p></div>';
        }

        $items = array_map(function (array $attachment): string {
            $name = $this->escape((string)($attachment['original_name'] ?? '-'));
            $type = $this->escape((string)($attachment['mime_type'] ?? '-'));
            if (($attachment['kind'] ?? '') === 'image' && !empty($attachment['data_uri'])) {
                $src = $this->escape((string)$attachment['data_uri']);
                $orientation = ($attachment['orientation'] ?? '') === 'portrait' ? 'portrait' : 'landscape';
                return '<div class="attachment-item"><div class="attachment-name">' . $name . ' <small>(' . $type . ')</small></div><img class="attachment-image ' . $orientation . '" src="' . $src . '" alt="' . $name . '"></div>';
            }

            $link = '';
            if (!empty($attachment['shared_url'])) {
                $url = $this->escape((string)$attachment['shared_url']);
                $link = '<div><a href="' . $url . '">Apri video allegato</a></div>';
            }

            return '<div class="attachment-item attachment-video"><div class="attachment-name">' . $name . ' <small>(' . $type . ')</small></div><div>Video allegato riservato presente in archivio privato.</div>' . $link . '</div>';
        }, $attachments);

        return '<div class="attachments"><h2>Allegati</h2>' . implode('', $items) . '</div>';
    }

    private function signatureHtml(?string $signature, ?string $verifyUrl, ?string $qrDataUri): string
    {
        if ($signature === null || $signature === '') {
            return '';
        }

        $html = '<div class="signature"><strong>Firma documento:</strong> ' . $this->escape($signature);
        if ($verifyUrl !== null && $verifyUrl !== '') {
            $url = $this->escape($verifyUrl);
            $html .= '<br><a href="' . $url . '">Verifica autenticita documento</a>';
        }
        if ($qrDataUri !== null && $qrDataUri !== '') {
            $html .= '<img class="signature-qr" src="' . $this->escape($qrDataUri) . '" alt="QR verifica">';
        }

        return $html . '</div>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
