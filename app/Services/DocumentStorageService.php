<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use Throwable;

final class DocumentStorageService
{
    private string $originalsPath;
    private string $publicDocumentsPath;

    public function __construct(
        private readonly string $basePath,
        private readonly PdfConversionService $pdfConversion,
        private readonly HostingDocumentUploadService $hostingUpload
    )
    {
        $this->originalsPath = $this->basePath . '/storage/private/originals';
        $this->publicDocumentsPath = $this->basePath . '/public/documents';

        foreach ([$this->originalsPath, $this->publicDocumentsPath] as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }
    }

    public function store(array $file, string $category): array
    {
        $this->assertCategory($category);
        $this->assertUpload($file);

        $originalName = basename((string)$file['name']);
        $originalStoredName = bin2hex(random_bytes(20));
        $originalPath = $this->originalsPath . '/' . $originalStoredName;

        if (!move_uploaded_file((string)$file['tmp_name'], $originalPath)) {
            throw new HttpException(500, 'Salvataggio file fallito.');
        }

        try {
            $mimeType = mime_content_type($originalPath) ?: 'application/octet-stream';
            $pdf = $this->createPdf($originalPath, $originalName, $mimeType, $category);
        } catch (Throwable $exception) {
            if (is_file($originalPath)) {
                unlink($originalPath);
            }

            throw $exception;
        }

        return [
            'original_name' => $originalName,
            'original_stored_name' => $originalStoredName,
            'original_mime_type' => $mimeType,
            'original_size_bytes' => filesize($originalPath),
            'original_checksum_sha256' => hash_file('sha256', $originalPath),
            'category' => $category,
            'pdf_public_path' => $pdf['path'],
            'pdf_size_bytes' => $pdf['size'],
            'pdf_checksum_sha256' => $pdf['checksum'],
            'conversion_status' => $pdf['status'],
        ];
    }

    public function storeHtml(string $html, string $originalName, string $category): array
    {
        $this->assertCategory($category);
        $originalStoredName = bin2hex(random_bytes(20)) . '.html';
        $originalPath = $this->originalsPath . '/' . $originalStoredName;

        if (file_put_contents($originalPath, $html) === false) {
            throw new HttpException(500, 'Salvataggio comunicato fallito.');
        }

        try {
            $pdf = $this->createPdf($originalPath, $originalName, 'text/html', $category);
        } catch (Throwable $exception) {
            if (is_file($originalPath)) {
                unlink($originalPath);
            }

            throw $exception;
        }

        return [
            'original_name' => $originalName,
            'original_stored_name' => $originalStoredName,
            'original_mime_type' => 'text/html',
            'original_size_bytes' => filesize($originalPath),
            'original_checksum_sha256' => hash_file('sha256', $originalPath),
            'category' => $category,
            'pdf_public_path' => $pdf['path'],
            'pdf_size_bytes' => $pdf['size'],
            'pdf_checksum_sha256' => $pdf['checksum'],
            'conversion_status' => $pdf['status'],
        ];
    }

    public function storePendingHtml(string $html, string $originalName, string $category): array
    {
        $this->assertCategory($category);
        $originalStoredName = bin2hex(random_bytes(20)) . '.html';
        $originalPath = $this->originalsPath . '/' . $originalStoredName;

        if (file_put_contents($originalPath, $html) === false) {
            throw new HttpException(500, 'Salvataggio comunicato fallito.');
        }

        $reserved = $this->reservePdf($originalName, $category);

        return [
            'original_name' => $originalName,
            'original_stored_name' => $originalStoredName,
            'original_mime_type' => 'text/html',
            'original_size_bytes' => filesize($originalPath),
            'original_checksum_sha256' => hash_file('sha256', $originalPath),
            'category' => $category,
            'pdf_public_path' => $reserved['path'],
            'pdf_size_bytes' => 0,
            'pdf_checksum_sha256' => str_repeat('0', 64),
            'conversion_status' => 'pending',
        ];
    }

    public function storeGeneratedPdf(string $html, string $originalName, string $category, callable $writer): array
    {
        $this->assertCategory($category);
        $originalStoredName = bin2hex(random_bytes(20)) . '.html';
        $originalPath = $this->originalsPath . '/' . $originalStoredName;

        if (file_put_contents($originalPath, $html) === false) {
            throw new HttpException(500, 'Salvataggio documento fallito.');
        }

        try {
            $reserved = $this->reservePdf($originalName, $category);
            $pdfPath = $this->pdfPath($reserved['path']);
            $writer($pdfPath);
        } catch (Throwable $exception) {
            if (is_file($originalPath)) {
                unlink($originalPath);
            }

            throw $exception;
        }

        return [
            'original_name' => $originalName,
            'original_stored_name' => $originalStoredName,
            'original_mime_type' => 'text/html',
            'original_size_bytes' => filesize($originalPath),
            'original_checksum_sha256' => hash_file('sha256', $originalPath),
            'category' => $category,
            'pdf_public_path' => $reserved['path'],
            'pdf_size_bytes' => filesize($pdfPath),
            'pdf_checksum_sha256' => hash_file('sha256', $pdfPath),
            'conversion_status' => 'ready',
        ];
    }

    public function pdfPath(string $publicPath): string
    {
        return $this->basePath . '/' . ltrim($publicPath, '/');
    }

    public function originalPath(string $storedName): string
    {
        return $this->originalsPath . '/' . basename($storedName);
    }

    public function rewriteGeneratedPdf(array $document, string $original, string $originalName, callable $writer): array
    {
        $originalPath = $this->originalPath((string)$document['original_stored_name']);
        if (file_put_contents($originalPath, $original) === false) {
            throw new HttpException(500, 'Aggiornamento documento fallito.');
        }

        $pdfPath = $this->pdfPath((string)$document['pdf_public_path']);
        $writer($pdfPath);

        return [
            'original_name' => $originalName,
            'original_mime_type' => 'text/plain',
            'original_size_bytes' => filesize($originalPath),
            'original_checksum_sha256' => hash_file('sha256', $originalPath),
            'pdf_size_bytes' => filesize($pdfPath),
            'pdf_checksum_sha256' => hash_file('sha256', $pdfPath),
            'conversion_status' => 'ready',
        ];
    }

    public function delete(array $document): void
    {
        foreach ([
            $this->originalsPath . '/' . basename((string)$document['original_stored_name']),
            $this->pdfPath((string)$document['pdf_public_path']),
        ] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function uploadPdfToHosting(array $document): void
    {
        $this->hostingUpload->uploadPdf(
            $this->pdfPath((string)$document['pdf_public_path']),
            (string)$document['pdf_public_path'],
            (string)$document['category'],
            (string)$document['pdf_checksum_sha256'],
            [
                'document_id' => (string)$document['id'],
                'original_name' => (string)$document['original_name'],
                'signature' => (string)$document['signature'],
                'signed_at' => (string)$document['signed_at'],
            ]
        );
    }

    private function assertUpload(array $file): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new HttpException(400, 'Upload non valido.');
        }

        if ((int)$file['size'] <= 0 || (int)$file['size'] > 10485760) {
            throw new HttpException(422, 'File non valido.');
        }
    }

    private function assertCategory(string $category): void
    {
        if (!in_array($category, ['comunicati', 'documenti', 'segnalazioni'], true)) {
            throw new HttpException(422, 'Category non valida.');
        }
    }

    private function createPdf(string $originalPath, string $originalName, string $mimeType, string $category): array
    {
        $reserved = $this->reservePdf($originalName, $category);
        $pdfPath = $this->pdfPath($reserved['path']);

        if (is_file($pdfPath)) {
            unlink($pdfPath);
        }

        try {
            $this->pdfConversion->convert($originalPath, $originalName, $pdfPath, $mimeType);
        } catch (Throwable $exception) {
            if (is_file($pdfPath)) {
                unlink($pdfPath);
            }

            throw $exception;
        }

        $checksum = hash_file('sha256', $pdfPath);

        return [
            'path' => $reserved['path'],
            'size' => filesize($pdfPath),
            'checksum' => $checksum,
            'status' => 'ready',
        ];
    }

    private function reservePdf(string $originalName, string $category): array
    {
        $categoryPath = $this->publicDocumentsPath . '/' . $category;
        if (!is_dir($categoryPath)) {
            mkdir($categoryPath, 0775, true);
        }

        $pdfName = $this->safePdfBaseName($originalName) . '-' . bin2hex(random_bytes(6)) . '.pdf';

        return [
            'path' => 'public/documents/' . $category . '/' . $pdfName,
            'name' => $pdfName,
        ];
    }

    private function safePdfBaseName(string $originalName): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($base)) ?: 'document';
        return substr(trim($safe, '-'), 0, 40) ?: 'document';
    }

}
