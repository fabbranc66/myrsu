<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class EmailSmtpService
{
    public function send(array $email, array $attachments = []): void
    {
        $host = trim((string)env_value('EMAIL_SMTP_HOST', 'smtps.aruba.it'));
        $port = (int)env_value('EMAIL_SMTP_PORT', '465');
        $user = trim((string)env_value('EMAIL_SMTP_USER', env_value('EMAIL_IMAP_USER', '')));
        $password = trim((string)env_value('EMAIL_SMTP_PASSWORD', env_value('EMAIL_IMAP_PASSWORD', '')));
        $secure = trim((string)env_value('EMAIL_SMTP_SECURE', 'ssl'));
        $from = trim((string)env_value('EMAIL_SMTP_FROM', $user));

        if ($host === '' || $user === '' || $password === '' || $from === '') {
            throw new HttpException(500, 'Configurazione SMTP mancante.');
        }

        $socket = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : '') . $host . ':' . $port, $errno, $errstr, 20);
        if (!$socket) {
            throw new HttpException(500, 'Connessione SMTP fallita: ' . $errstr);
        }

        $this->expect($socket, [220]);
        $this->command($socket, 'EHLO myrsu.local', [250]);
        if ($secure === 'tls') {
            $this->command($socket, 'STARTTLS', [220]);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->command($socket, 'EHLO myrsu.local', [250]);
        }
        $this->command($socket, 'AUTH LOGIN', [334]);
        $this->command($socket, base64_encode($user), [334]);
        $this->command($socket, base64_encode($password), [235]);
        $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
        foreach ($this->addresses((string)$email['to_emails'] . ',' . (string)($email['cc_emails'] ?? '') . ',' . (string)($email['bcc_emails'] ?? '')) as $address) {
            $this->command($socket, 'RCPT TO:<' . $address . '>', [250, 251]);
        }
        $this->command($socket, 'DATA', [354]);
        fwrite($socket, $this->message($email, $from, $attachments) . "\r\n.\r\n");
        $this->expect($socket, [250]);
        $this->command($socket, 'QUIT', [221]);
        fclose($socket);
    }

    private function message(array $email, string $from, array $attachments): string
    {
        $boundary = 'myrsu-' . bin2hex(random_bytes(12));
        $headers = [
            'From: ' . $from,
            'To: ' . (string)$email['to_emails'],
            (string)($email['cc_emails'] ?? '') !== '' ? 'Cc: ' . (string)$email['cc_emails'] : '',
            'Subject: ' . mb_encode_mimeheader((string)$email['subject'], 'UTF-8'),
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        ];
        $body = implode("\r\n", array_filter($headers)) . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= (string)$email['body'] . "\r\n";
        foreach ($attachments as $attachment) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Type: ' . $attachment['mime_type'] . '; name="' . $this->safeName($attachment['name']) . "\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $this->safeName($attachment['name']) . "\"\r\n\r\n";
            $body .= chunk_split(base64_encode((string)file_get_contents($attachment['path']))) . "\r\n";
        }

        return $body . '--' . $boundary . '--';
    }

    private function addresses(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $value) ?: []), fn (string $item): bool => filter_var($item, FILTER_VALIDATE_EMAIL) !== false));
    }

    private function safeName(string $value): string
    {
        return str_replace(['"', "\r", "\n"], '', basename($value));
    }

    private function command($socket, string $command, array $codes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $codes);
    }

    private function expect($socket, array $codes): void
    {
        $response = '';
        do {
            $line = fgets($socket, 515);
            if ($line === false) break;
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        if (!in_array((int)substr($response, 0, 3), $codes, true)) {
            throw new HttpException(500, 'Errore SMTP: ' . trim($response));
        }
    }
}
