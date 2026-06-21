<?php

declare(strict_types=1);

namespace App\Services;

final class ReportService
{
    public function trackingCode(): string
    {
        return 'SEG-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    public function html(string $subject, string $message, string $contact, string $trackingCode): string
    {
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $safeContact = htmlspecialchars($contact !== '' ? $contact : '-', ENT_QUOTES, 'UTF-8');

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            . 'body{font-family:Arial,sans-serif;color:#111827;padding:32px}h1{font-size:24px}'
            . '.meta{border:1px solid #d1d5db;padding:12px;margin-bottom:20px}.body{font-size:15px;line-height:1.6}'
            . '</style></head><body>'
            . '<h1>Segnalazione</h1><div class="meta">'
            . '<strong>Codice:</strong> ' . $trackingCode . '<br>'
            . '<strong>Oggetto:</strong> ' . $safeSubject . '<br>'
            . '<strong>Contatto:</strong> ' . $safeContact
            . '</div><div class="body">' . $safeMessage . '</div>'
            . '</body></html>';
    }
}
