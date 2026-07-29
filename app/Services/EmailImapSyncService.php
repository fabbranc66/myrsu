<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\EmailRepository;

final class EmailImapSyncService
{
    public function __construct(
        private readonly string $basePath,
        private readonly EmailRepository $emails,
        private readonly EmailContactService $emailContacts
    ) {
    }

    public function sync(int $userId): array
    {
        if (!function_exists('imap_open')) {
            throw new HttpException(500, 'Estensione IMAP non abilitata.');
        }

        $host = trim((string)env_value('EMAIL_IMAP_HOST', ''));
        $port = trim((string)env_value('EMAIL_IMAP_PORT', '993'));
        $user = trim((string)env_value('EMAIL_IMAP_USER', ''));
        $password = trim((string)env_value('EMAIL_IMAP_PASSWORD', ''));
        $flags = trim((string)env_value('EMAIL_IMAP_FLAGS', '/imap/ssl'));
        if ($host === '' || $user === '' || $password === '') {
            throw new HttpException(422, 'Configurazione IMAP mancante.');
        }

        $imap = @imap_open('{' . $host . ':' . $port . $flags . '}INBOX', $user, $password);
        if ($imap === false) {
            throw new HttpException(500, 'Connessione IMAP non riuscita: ' . (imap_last_error() ?: 'errore sconosciuto'));
        }

        $imported = 0;
        $messages = imap_search($imap, 'ALL') ?: [];
        sort($messages);
        foreach ($messages as $messageNo) {
            $externalId = $this->externalId($imap, (int)$messageNo);
            if ($this->emails->externalExists($externalId)) {
                continue;
            }

            $header = imap_headerinfo($imap, (int)$messageNo);
            $parts = $this->parts($imap, (int)$messageNo, imap_fetchstructure($imap, (int)$messageNo));
            $from = $header->from[0] ?? null;
            $email = $this->emails->create([
                'external_id' => $externalId,
                'import_source' => 'imap',
                'direction' => 'incoming',
                'read_status' => 'unread',
                'handling_status' => 'new',
                'from_name' => $from ? $this->decodeMime((string)($from->personal ?? '')) : null,
                'from_email' => $from ? trim((string)($from->mailbox ?? '') . '@' . (string)($from->host ?? ''), '@') : null,
                'to_emails' => $this->addresses($header->to ?? []),
                'cc_emails' => $this->addresses($header->cc ?? []),
                'subject' => $this->decodeMime((string)($header->subject ?? '(senza oggetto)')) ?: '(senza oggetto)',
                'body' => $parts['body'],
                'message_at' => isset($header->date) ? date('Y-m-d H:i:s', strtotime((string)$header->date)) : date('Y-m-d H:i:s'),
                'practice_id' => null,
                'contact_id' => null,
                'created_by' => $userId,
            ]);
            $this->saveAttachments((int)$email['id'], $parts['attachments']);
            ++$imported;
        }
        imap_close($imap);

        return ['imported' => $imported, 'checked' => count($messages)];
    }

    private function externalId($imap, int $messageNo): string
    {
        $overview = imap_fetch_overview($imap, (string)$messageNo, 0);
        $messageId = trim((string)($overview[0]->message_id ?? ''));
        return mb_substr($messageId !== '' ? $messageId : 'imap-uid-' . imap_uid($imap, $messageNo), 0, 255);
    }

    private function parts($imap, int $messageNo, object $structure): array
    {
        $plain = '';
        $html = '';
        $attachments = [];
        foreach ($this->flattenParts($structure) as [$partNo, $part]) {
            $body = $partNo === '1' && empty($structure->parts) ? imap_body($imap, $messageNo) : imap_fetchbody($imap, $messageNo, $partNo);
            $decoded = $this->decodeBody((string)$body, (int)($part->encoding ?? 0));
            $filename = $this->filename($part);
            $subtype = strtolower((string)($part->subtype ?? ''));
            if ($filename !== '') {
                $attachments[] = ['name' => $filename, 'content' => $decoded, 'mime' => $this->mime($part)];
            } elseif ((int)($part->type ?? 0) === 0 && $subtype === 'plain') {
                $plain .= $decoded . "\n";
            } elseif ((int)($part->type ?? 0) === 0 && $subtype === 'html') {
                $html .= $decoded . "\n";
            }
        }

        return [
            'body' => trim($plain) ?: trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'attachments' => $attachments,
        ];
    }

    private function flattenParts(object $structure, string $prefix = ''): array
    {
        if (empty($structure->parts)) {
            return [['1', $structure]];
        }
        $rows = [];
        foreach ($structure->parts as $index => $part) {
            $partNo = $prefix === '' ? (string)($index + 1) : $prefix . '.' . ($index + 1);
            $rows = array_merge($rows, !empty($part->parts) ? $this->flattenParts($part, $partNo) : [[$partNo, $part]]);
        }
        return $rows;
    }

    private function filename(object $part): string
    {
        foreach (['dparameters', 'parameters'] as $group) {
            foreach (($part->{$group} ?? []) as $param) {
                $name = strtolower((string)$param->attribute);
                if (in_array($name, ['filename', 'name'], true)) {
                    return $this->decodeMime((string)$param->value);
                }
            }
        }
        return '';
    }

    private function saveAttachments(int $emailId, array $attachments): void
    {
        if ($attachments === []) return;
        $dir = $this->basePath . '/storage/private/email-attachments/' . $emailId;
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        foreach ($attachments as $attachment) {
            $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string)$attachment['name']) ?: 'attachment.bin';
            $stored = bin2hex(random_bytes(12)) . '-' . $safeName;
            file_put_contents($dir . '/' . $stored, (string)$attachment['content']);
            $this->emails->addAttachment($emailId, [
                'original_name' => (string)$attachment['name'],
                'stored_name' => $stored,
                'storage_path' => 'storage/private/email-attachments/' . $emailId,
                'mime_type' => (string)$attachment['mime'],
                'size_bytes' => filesize($dir . '/' . $stored),
            ]);
        }
    }

    private function addresses(array $items): ?string
    {
        $values = array_map(static fn ($item): string => trim((string)($item->mailbox ?? '') . '@' . (string)($item->host ?? ''), '@'), $items);
        $values = array_values(array_filter($values));
        return $values === [] ? null : implode(', ', $values);
    }

    private function decodeMime(string $value): string
    {
        if ($value === '') return '';
        $parts = imap_mime_header_decode($value);
        $out = '';
        foreach ($parts as $part) {
            $charset = strtoupper((string)($part->charset ?? 'UTF-8'));
            $text = (string)($part->text ?? '');
            $out .= $charset !== '' && $charset !== 'DEFAULT' && $charset !== 'UTF-8'
                ? (mb_convert_encoding($text, 'UTF-8', $charset) ?: $text)
                : $text;
        }
        return trim($out);
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            3 => (string)base64_decode($body),
            4 => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function mime(object $part): string
    {
        $type = (int)($part->type ?? 3);
        $subtype = strtolower((string)($part->subtype ?? 'octet-stream'));
        return match ($type) {
            0 => 'text/' . $subtype,
            1 => 'multipart/' . $subtype,
            2 => 'message/' . $subtype,
            3 => 'application/' . $subtype,
            4 => 'audio/' . $subtype,
            5 => 'image/' . $subtype,
            6 => 'video/' . $subtype,
            default => 'application/octet-stream',
        };
    }
}
