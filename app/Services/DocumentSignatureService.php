<?php

declare(strict_types=1);

namespace App\Services;

final class DocumentSignatureService
{
    public function __construct(private readonly array $config)
    {
    }

    public function sign(array $document): string
    {
        return strtoupper(substr(hash_hmac('sha256', $this->payload($document), $this->secret()), 0, 24));
    }

    public function valid(array $document, string $signature, ?string $realChecksum): bool
    {
        if ($realChecksum === null || $realChecksum !== (string)$document['pdf_checksum_sha256']) {
            return false;
        }

        return hash_equals($this->sign($document), strtoupper(trim($signature)));
    }

    public function payload(array $document): string
    {
        return implode('|', [
            'document',
            (string)$document['id'],
            (string)$document['original_name'],
            (string)$document['category'],
            (string)$document['visibility'],
            (string)$document['created_at'],
        ]);
    }

    private function secret(): string
    {
        $secret = trim((string)($this->config['secret'] ?? ''));
        return $secret !== '' ? $secret : hash('sha256', 'MYRSU_DOCUMENT_SIGNATURE_V1');
    }
}
