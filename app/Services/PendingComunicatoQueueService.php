<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class PendingComunicatoQueueService
{
    public function __construct(
        private readonly array $hostingConfig,
        private readonly DocumentStorageService $documentStorage,
        private readonly DocumentSignatureService $documentSignature,
        private readonly ComunicatoDirectPdfService $comunicatoPdf
    ) {
    }

    public function pending(): array
    {
        $this->assertConfigured();
        return $this->requestJson('GET', $this->baseEndpoint() . '/comunicati/pending?token=' . urlencode($this->token()));
    }

    public function process(): array
    {
        $pending = $this->pending();
        $results = [];

        foreach ($pending as $document) {
            $results[] = $this->processOne($document);
        }

        return [
            'processed' => count(array_filter($results, static fn (array $row): bool => $row['status'] === 'processed')),
            'errors' => count(array_filter($results, static fn (array $row): bool => $row['status'] === 'error')),
            'items' => $results,
        ];
    }

    private function processOne(array $document): array
    {
        try {
            $payload = $document['comunicato'] ?? [];
            if (!is_array($payload) || trim((string)($payload['title'] ?? '')) === '' || trim((string)($payload['body'] ?? '')) === '') {
                $document = $this->requestJson(
                    'GET',
                    $this->baseEndpoint() . '/comunicati/' . (int)$document['id'] . '?token=' . urlencode($this->token())
                );
                $payload = $document['comunicato'] ?? [];
            }
            $title = trim((string)($payload['title'] ?? ''));
            $body = trim((string)($payload['body'] ?? ''));

            if ($title === '' || $body === '') {
                throw new HttpException(422, 'Contenuto comunicato mancante.');
            }

            $pdfPath = $this->documentStorage->pdfPath((string)$document['pdf_public_path']);
            $dir = dirname($pdfPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            $signature = $this->documentSignature->sign($document);
            $verifyUrl = $this->verificationUrl((int)$document['id'], $signature);
            $this->comunicatoPdf->write(
                $pdfPath,
                $title,
                $body,
                (string)$document['protocol_number'],
                (string)$document['protocol_created_at'],
                (string)$document['id'],
                $verifyUrl,
                $signature
            );

            $this->postFile(
                $this->baseEndpoint() . '/comunicati/' . (int)$document['id'] . '/complete',
                $pdfPath,
                [
                    'checksum_sha256' => hash_file('sha256', $pdfPath),
                    'signature' => $signature,
                ]
            );

            return [
                'id' => (int)$document['id'],
                'protocol_number' => (string)$document['protocol_number'],
                'status' => 'processed',
            ];
        } catch (\Throwable $exception) {
            return [
                'id' => (int)($document['id'] ?? 0),
                'protocol_number' => (string)($document['protocol_number'] ?? ''),
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function requestJson(string $method, string $url): array
    {
        if (!function_exists('curl_init')) {
            throw new HttpException(500, 'Estensione cURL non disponibile.');
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $status < 200 || $status >= 300) {
            throw new HttpException(502, $error !== '' ? $error : 'Richiesta hosting fallita.');
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            throw new HttpException(502, 'Risposta hosting non valida.');
        }

        return $payload['data'] ?? [];
    }

    private function postFile(string $url, string $pdfPath, array $fields): void
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token(),
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $fields + [
                'file' => new \CURLFile($pdfPath, 'application/pdf', basename($pdfPath)),
            ],
        ]);

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($body === false || $status < 200 || $status >= 300) {
            throw new HttpException(502, $error !== '' ? $error : 'Upload PDF pending fallito.');
        }
    }

    private function assertConfigured(): void
    {
        if ($this->baseEndpoint() === '' || $this->token() === '') {
            throw new HttpException(500, 'Coda comunicati non configurata.');
        }
    }

    private function baseEndpoint(): string
    {
        $endpoint = rtrim(trim((string)($this->hostingConfig['documents_endpoint'] ?? '')), '/');
        return str_ends_with($endpoint, '/documents') ? substr($endpoint, 0, -strlen('/documents')) : $endpoint;
    }

    private function token(): string
    {
        return trim((string)($this->hostingConfig['documents_token'] ?? ''));
    }

    private function verificationUrl(int $documentId, string $signature): string
    {
        $baseUrl = preg_replace('#/api/v1/hosting$#', '', $this->baseEndpoint()) ?: '';
        return rtrim($baseUrl, '/') . '/ui/document-verify.html?id=' . $documentId . '&sig=' . urlencode($signature);
    }
}
