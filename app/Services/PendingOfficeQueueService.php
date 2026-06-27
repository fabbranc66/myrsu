<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class PendingOfficeQueueService
{
    public function __construct(
        private readonly array $hostingConfig,
        private readonly PdfConversionService $conversion,
        private readonly UploadedDocumentPdfService $uploadedPdf,
        private readonly DocumentSignatureService $signature
    ) {
    }

    public function pending(): array
    {
        return $this->requestJson('GET', $this->baseEndpoint() . '/documents/pending-office');
    }

    public function process(): array
    {
        $results = array_map(fn (array $document): array => $this->processOne($document), $this->pending());
        return [
            'processed' => count(array_filter($results, fn (array $row): bool => $row['status'] === 'processed')),
            'errors' => count(array_filter($results, fn (array $row): bool => $row['status'] === 'error')),
            'items' => $results,
        ];
    }

    private function processOne(array $document): array
    {
        $tempDir = sys_get_temp_dir() . '/myrsu_office_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0775, true);
        try {
            $originalPath = $tempDir . '/original';
            $pdfPath = $tempDir . '/' . $this->pdfFileName((string)$document['original_name']);
            $this->download(
                $this->baseEndpoint() . '/documents/' . (int)$document['id'] . '/original',
                $originalPath
            );
            $this->conversion->convert(
                $originalPath,
                (string)$document['original_name'],
                $pdfPath,
                (string)$document['original_mime_type']
            );
            $signature = $this->signature->sign($document);
            $verifyUrl = $this->verificationUrl((int)$document['id'], $signature);
            $this->uploadedPdf->write($originalPath, $pdfPath, $document, null, $verifyUrl, $signature);
            $this->postFile(
                $this->baseEndpoint() . '/documents/' . (int)$document['id'] . '/complete',
                $pdfPath,
                ['checksum_sha256' => hash_file('sha256', $pdfPath), 'signature' => $signature]
            );
            return ['id' => (int)$document['id'], 'original_name' => (string)$document['original_name'], 'status' => 'processed'];
        } catch (\Throwable $exception) {
            return [
                'id' => (int)($document['id'] ?? 0),
                'original_name' => (string)($document['original_name'] ?? ''),
                'status' => 'error',
                'message' => $exception->getMessage(),
            ];
        } finally {
            $this->deleteDirectory($tempDir);
        }
    }

    private function requestJson(string $method, string $url): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token(), 'Accept: application/json'],
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new HttpException(502, $error !== '' ? $error : 'Richiesta hosting fallita.');
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) throw new HttpException(502, 'Risposta hosting non valida.');
        return $payload['data'] ?? [];
    }

    private function pdfFileName(string $originalName): string
    {
        $baseName = pathinfo(basename($originalName), PATHINFO_FILENAME);
        $safeName = trim((string)preg_replace('/[^A-Za-z0-9._-]+/', '-', $baseName), '-_.');
        return ($safeName !== '' ? $safeName : 'document') . '.pdf';
    }

    private function download(string $url, string $targetPath): void
    {
        $handle = fopen($targetPath, 'wb');
        if ($handle === false) throw new HttpException(500, 'File temporaneo non disponibile.');
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token()],
        ]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);
        if (!$ok || $status < 200 || $status >= 300) {
            throw new HttpException(502, $error !== '' ? $error : 'Download originale fallito.');
        }
    }

    private function postFile(string $url, string $pdfPath, array $fields): void
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->token(), 'Accept: application/json'],
            CURLOPT_POSTFIELDS => $fields + ['file' => new \CURLFile($pdfPath, 'application/pdf', basename($pdfPath))],
        ]);
        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new HttpException(502, $error !== '' ? $error : 'Upload PDF convertito fallito.');
        }
    }

    private function baseEndpoint(): string
    {
        $endpoint = rtrim((string)($this->hostingConfig['documents_endpoint'] ?? ''), '/');
        return str_ends_with($endpoint, '/documents') ? substr($endpoint, 0, -10) : $endpoint;
    }

    private function token(): string
    {
        $token = trim((string)($this->hostingConfig['documents_token'] ?? ''));
        if ($token === '') throw new HttpException(500, 'Coda Office non configurata.');
        return $token;
    }

    private function verificationUrl(int $id, string $signature): string
    {
        $base = preg_replace('#/api/v1/hosting$#', '', $this->baseEndpoint()) ?: '';
        return rtrim($base, '/') . '/ui/document-verify.html?id=' . $id . '&sig=' . urlencode($signature);
    }

    private function deleteDirectory(string $path): void
    {
        foreach (glob($path . '/*') ?: [] as $file) is_dir($file) ? $this->deleteDirectory($file) : unlink($file);
        if (is_dir($path)) rmdir($path);
    }
}
