<?php

declare(strict_types=1);

use App\Services\DocumentSignatureService;

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
require $root . '/app/Services/DocumentSignatureService.php';
$checks = 0;

function checkDocumentSignature(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

foreach ([[], ['secret' => null], ['secret' => ''], ['secret' => '   '], ['secret' => 'change-me'], ['secret' => str_repeat('T', 31)], ['secret' => hash('sha256', 'MYRSU_DOCUMENT_SIGNATURE_V1')]] as $config) {
    try {
        new DocumentSignatureService($config);
        throw new RuntimeException('Configurazione debole accettata.');
    } catch (RuntimeException $exception) {
        checkDocumentSignature(str_contains($exception->getMessage(), 'MYRSU_SIGNING_SECRET'), 'Chiave assente/debole segnalata senza mostrare il valore.');
    }
}

$document = ['id' => 123, 'original_name' => 'Verifica qualità.pdf', 'category' => 'documenti', 'visibility' => 'rsu', 'created_at' => '2026-10-25 12:00:00', 'pdf_checksum_sha256' => hash('sha256', 'PDF sintetico')];
$key = str_repeat('T', 64);
$service = new DocumentSignatureService(['secret' => $key]);
$payload = 'document|123|Verifica qualità.pdf|documenti|rsu|2026-10-25 12:00:00';
$expected = strtoupper(substr(hash_hmac('sha256', $payload, $key), 0, 24));
checkDocumentSignature($service->payload($document) === $payload, 'Payload originale invariato.');
checkDocumentSignature($service->sign($document) === $expected, 'Algoritmo e formato della firma invariati con chiave esplicita.');
checkDocumentSignature(preg_match('/^[A-F0-9]{24}$/D', $expected) === 1, 'Formato della firma conservato.');
checkDocumentSignature($service->valid($document, ' ' . strtolower($expected) . ' ', $document['pdf_checksum_sha256']), 'Normalizzazione firma conservata.');
checkDocumentSignature(!$service->valid($document, $expected, null), 'File mancante non valido.');
checkDocumentSignature(!$service->valid($document, $expected, hash('sha256', 'PDF diverso')), 'File modificato non valido.');
checkDocumentSignature(!$service->valid($document, str_repeat('0', 24), $document['pdf_checksum_sha256']), 'Firma diversa rifiutata.');
foreach (['id' => 124, 'original_name' => 'Altro.pdf', 'category' => 'comunicati', 'visibility' => 'public', 'created_at' => '2026-10-26 12:00:00'] as $field => $value) {
    $changed = array_replace($document, [$field => $value]);
    checkDocumentSignature(!$service->valid($changed, $expected, $document['pdf_checksum_sha256']), 'Metadato modificato invalida la firma.');
}
checkDocumentSignature((new DocumentSignatureService(['secret' => '  ' . $key . '  ']))->sign($document) === $expected, 'Trim della chiave conservato.');
checkDocumentSignature((new DocumentSignatureService(['secret' => str_repeat('R', 64)]))->sign($document) !== $expected, 'Chiavi diverse producono firme diverse.');
checkDocumentSignature(strlen((new DocumentSignatureService(['secret' => str_repeat('R', 32)]))->sign($document)) === 24, 'Lunghezza minima esplicita accettata.');

$originalSecret = getenv('MYRSU_SIGNING_SECRET');
try {
    putenv('MYRSU_SIGNING_SECRET');
    try {
        require $root . '/config/signing.php';
        throw new RuntimeException('Variabile assente non rilevata.');
    } catch (RuntimeException $exception) {
        checkDocumentSignature(str_contains($exception->getMessage(), 'MYRSU_SIGNING_SECRET'), 'Variabile obbligatoria senza default nel config.');
    }
    putenv('MYRSU_SIGNING_SECRET=' . $key);
    $config = require $root . '/config/signing.php';
    checkDocumentSignature((new DocumentSignatureService($config))->sign($document) === $expected, 'Configurazione ambiente usata senza modificare il valore.');
    putenv('MYRSU_SIGNING_SECRET=');
    try {
        new DocumentSignatureService(require $root . '/config/signing.php');
        throw new RuntimeException('Variabile vuota non rilevata.');
    } catch (RuntimeException $exception) {
        checkDocumentSignature(str_contains($exception->getMessage(), 'MYRSU_SIGNING_SECRET'), 'Variabile vuota non attiva alcun ripiego.');
    }
} finally {
    putenv($originalSecret === false ? 'MYRSU_SIGNING_SECRET' : 'MYRSU_SIGNING_SECRET=' . $originalSecret);
}

echo "OK: {$checks} controlli firma, chiave obbligatoria, ripiego rifiutato, algoritmo e checksum. Solo dati sintetici; nessun file o database modificato.\n";
