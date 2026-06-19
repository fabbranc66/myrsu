<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class HostingDocumentUploadService
{
    public function __construct(private readonly array $config)
    {
    }

    public function enabled(): bool
    {
        return $this->endpoint() !== '' && $this->token() !== '';
    }

    public function uploadPdf(string $pdfPath, string $publicPath, string $category, string $checksum, array $metadata = []): void
    {
        if (!$this->enabled()) {
            return;
        }

        if (!function_exists('curl_init')) {
            throw new HttpException(500, 'Estensione cURL non disponibile.');
        }

        $curl = curl_init($this->endpoint());
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token(),
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => [
                'category' => $category,
                'public_path' => $publicPath,
                'checksum_sha256' => $checksum,
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_SLASHES),
                'file' => new \CURLFile($pdfPath, 'application/pdf', basename($publicPath)),
            ],
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $status < 200 || $status >= 300) {
            throw new HttpException(502, $error !== '' ? $error : 'Upload hosting fallito.');
        }
    }

    private function endpoint(): string
    {
        return trim((string)($this->config['documents_endpoint'] ?? ''));
    }

    private function token(): string
    {
        return trim((string)($this->config['documents_token'] ?? ''));
    }
}
