<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
if (!in_array($config['host'], ['127.0.0.1', 'localhost', '::1'], true) || $config['database'] !== 'myrsu') throw new RuntimeException('Solo test locale.');
$inventoryPath = $argv[1] ?? null;
if ($inventoryPath === null) {
    $applied = json_decode((string)file_get_contents($root . '/storage/private/branding/applied.json'), true, 512, JSON_THROW_ON_ERROR);
    $inventoryPath = $applied['inventory'];
}
$inventory = json_decode((string)file_get_contents($inventoryPath), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO('mysql:host=' . $config['host'] . ';dbname=myrsu;charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin = (int)$pdo->query("SELECT u.id FROM users u JOIN role_user ru ON ru.user_id = u.id JOIN roles r ON r.id = ru.role_id WHERE u.status = 'active' AND r.name = 'admin' ORDER BY u.id LIMIT 1")->fetchColumn();
if ($admin < 1) throw new RuntimeException('Admin attivo richiesto.');
$token = bin2hex(random_bytes(32));
$hash = hash('sha256', $token);
$insert = $pdo->prepare("INSERT INTO api_tokens (user_id, token_hash, device_name, created_at) VALUES (?, ?, 'test-local-pdf-branding', NOW())");
$insert->execute([$admin, $hash]);
$checks = 0;

function verifyLocalPdf(string $path, string $token, ?array $fields = null): array
{
    $curl = curl_init('http://localhost/miodel/api/v1/' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROXY => '', CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token]]);
    if ($fields !== null) curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($status !== 200 || !is_string($body)) throw new RuntimeException('Verifica HTTP fallita: ' . $status . ' / ' . preg_replace('/\?.*$/', '', $path));
    return json_decode($body, true, 512, JSON_THROW_ON_ERROR)['data'] ?? [];
}

try {
    foreach ($inventory['documents'] as $entry) {
        $document = $entry['document'];
        if (trim((string)$document['signature']) === '') continue;
        $path = $entry['table'] === 'documents' ? 'documents/' . (int)$document['id'] : 'archive/sources/' . (int)$entry['configuration_id'] . '/documents/' . (int)$document['id'];
        $result = verifyLocalPdf($path . '/verify?sig=' . rawurlencode($document['signature']), $token);
        if (($result['valid'] ?? false) !== true || ($result['checksum_ok'] ?? false) !== true) throw new RuntimeException('Firma/checksum non validi: ' . $entry['configuration_id'] . '/' . $document['id']);
        $checks++;
        if (in_array((int)$document['id'], [15, 16, 42], true)) {
            $result = verifyLocalPdf($path . '/verify-file', $token, ['sig' => $document['signature'], 'file' => new CURLFile($entry['pdf_path'], 'application/pdf', 'verifica.pdf')]);
            if (($result['valid'] ?? false) !== true || ($result['checksum_ok'] ?? false) !== true) throw new RuntimeException('Verifica copia caricata fallita.');
            $checks++;
            $result = verifyLocalPdf($path . '/verify?sig=' . str_repeat('0', 24), $token);
            if (($result['valid'] ?? true) !== false || ($result['checksum_ok'] ?? false) !== true) throw new RuntimeException('Codice errato accettato.');
            $checks++;
            $result = verifyLocalPdf($path . '/verify-file', $token, ['sig' => $document['signature'], 'file' => new CURLFile($root . '/storage/private/branding/fixtures/source.pdf', 'application/pdf', 'altra-copia.pdf')]);
            if (($result['valid'] ?? true) !== false || ($result['checksum_ok'] ?? true) !== false) throw new RuntimeException('Copia diversa accettata.');
            $checks++;
        }
    }
} finally {
    $delete = $pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ? AND device_name = ?');
    $delete->execute([$hash, 'test-local-pdf-branding']);
}
echo "OK: {$checks} verifiche HTTP locali, copie caricate e codici errati. Token di prova rimosso; account e documenti invariati.\n";
