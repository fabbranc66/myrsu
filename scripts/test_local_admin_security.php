<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
require $root . '/app/Core/HttpException.php';
require $root . '/app/Repositories/TokenRepository.php';
load_env($root . '/.env');
$config = require $root . '/config/database.php';
$hosting = require $root . '/config/hosting.php';
if (!in_array($config['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true) || ($config['database'] ?? '') !== 'myrsu') throw new RuntimeException('Test consentito solo sul database myrsu locale.');
if (trim((string)($hosting['documents_endpoint'] ?? '')) !== '' || trim((string)($hosting['documents_token'] ?? '')) !== '' || env_value('EMAIL_SMTP_HOST', null) !== '' || env_value('EMAIL_IMAP_HOST', null) !== '') throw new RuntimeException('Disabilitare prima hosting upload, SMTP e IMAP nel locale.');
$mode = $argv[1] ?? '';
if (!in_array($mode, ['--baseline', '--verify', '--verify-documents'], true)) throw new RuntimeException('Specificare --baseline, --verify oppure --verify-documents percorso-baseline.json.');
$pdo = new PDO('mysql:host=' . $config['host'] . ';dbname=myrsu;charset=utf8mb4', $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

function localSnapshot(PDO $pdo, string $root): array
{
    $tables = [];
    foreach ($pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'myrsu' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^(?:cfg_[0-9]+_)?(?:documents|document_folders|document_folder_items|document_folder_permissions|practices|practice_links|practice_notes|practice_ccnl_links|protocol_entries|reports|report_attachments|document_comments)$/D', $table)) continue;
        $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
        $encoded = array_map(static fn (array $row): string => json_encode($row, JSON_THROW_ON_ERROR), $rows);
        sort($encoded);
        $tables[$table] = hash('sha256', implode("\n", $encoded));
    }
    $files = [];
    foreach ($pdo->query("SELECT id, table_prefix FROM saved_configurations WHERE state = 'ready' ORDER BY id") as $configuration) {
        $prefix = (string)$configuration['table_prefix'];
        if ($prefix !== '' && !preg_match('/^cfg_[0-9]+_$/D', $prefix)) throw new RuntimeException('Prefisso configurazione inatteso.');
        $base = $prefix === '' ? $root : $root . '/storage/private/configurations/' . (int)$configuration['id'];
        foreach ($pdo->query('SELECT id, conversion_status, pdf_public_path, original_stored_name FROM `' . $prefix . 'documents` ORDER BY id') as $document) {
            $paths = ['original' => $base . '/storage/private/originals/' . basename((string)$document['original_stored_name'])];
            if ($document['conversion_status'] === 'ready') $paths['pdf'] = $base . '/' . $document['pdf_public_path'];
            foreach ($paths as $type => $path) {
                if (!is_file($path)) throw new RuntimeException('File mancante: configurazione ' . $configuration['id'] . ', documento ' . $document['id'] . ', ' . $type);
                $files[$configuration['id'] . ':' . $document['id'] . ':' . $type] = hash_file('sha256', $path);
            }
        }
    }
    $accounts = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
    $roles = $pdo->query('SELECT * FROM role_user ORDER BY user_id, role_id')->fetchAll();
    return ['tables' => $tables, 'files' => $files, 'accounts_hash' => hash('sha256', json_encode([$accounts, $roles], JSON_THROW_ON_ERROR))];
}

function localRequest(string $path, ?string $token = null, string $method = 'GET', ?array $data = null): array
{
    $headers = ['Accept: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_PROXY => '', CURLOPT_HTTPHEADER => $headers, CURLOPT_CUSTOMREQUEST => $method];
    if ($data !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data, JSON_THROW_ON_ERROR);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
    }
    $curl = curl_init('http://localhost/miodel/api/v1/' . $path);
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($body === false || $status === 0) throw new RuntimeException('API locale non raggiungibile: ' . $path);
    return ['status' => $status, 'hash' => hash('sha256', $body)];
}

$snapshot = localSnapshot($pdo, $root);
$operational = $pdo->query("SELECT table_prefix FROM saved_configurations WHERE operational_key = 1 AND state = 'ready'")->fetchColumn();
if (!is_string($operational) || ($operational !== '' && !preg_match('/^cfg_[0-9]+_$/D', $operational))) throw new RuntimeException('Configurazione operativa non disponibile.');
$sample = $pdo->query("SELECT id, signature FROM `" . $operational . "documents` WHERE conversion_status = 'ready' AND visibility = 'rsu' ORDER BY id DESC LIMIT 1")->fetch();
if (!$sample) throw new RuntimeException('Serve un documento pronto e visibile ai delegati per la regressione.');
$profiles = ['guest' => null];
foreach ($pdo->query("SELECT u.id, GROUP_CONCAT(r.name ORDER BY r.name) AS roles FROM users u JOIN role_user ru ON ru.user_id = u.id JOIN roles r ON r.id = ru.role_id WHERE u.status = 'active' GROUP BY u.id ORDER BY u.id") as $user) {
    $roles = explode(',', $user['roles']);
    $profile = in_array('admin', $roles, true) ? 'admin' : (in_array('delegato', $roles, true) ? (in_array('rls', $roles, true) ? 'dual' : 'delegato') : null);
    if ($profile !== null && !isset($profiles[$profile])) $profiles[$profile] = (int)$user['id'];
}
if (!isset($profiles['admin'], $profiles['delegato'], $profiles['dual'])) throw new RuntimeException('Servono account attivi admin, delegato e Delegato + RLS.');
$reads = [];
$securityChecks = 0;
$tokens = [];
try {
    foreach ($profiles as $profile => $userId) {
        $token = null;
        if ($userId !== null) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            (new App\Repositories\TokenRepository($pdo))->create($userId, $token, 'local-security-regression');
            $tokens[] = ['id' => (int)$pdo->lastInsertId(), 'hash' => $hash];
        }
        $paths = $profile === 'guest' ? ['public/documents'] : ['documents', 'practices?scope=all', 'document-folders', 'protocol', 'archive/shared?type=document'];
        foreach ($paths as $path) {
            $response = localRequest($path, $token);
            if ($response['status'] !== 200) throw new RuntimeException($profile . ': lettura non riuscita su ' . $path . ' (HTTP ' . $response['status'] . ').');
            $reads[$profile . ':' . $path] = $response;
        }
        foreach (['preview', 'download', 'verify?sig=' . rawurlencode((string)$sample['signature'])] as $action) {
            $path = 'documents/' . $sample['id'] . '/' . $action;
            $response = localRequest($path, $token);
            $expected = $profile === 'guest' ? ($action === 'preview' || $action === 'download' ? 401 : 403) : 200;
            if ($response['status'] !== $expected) throw new RuntimeException($profile . ': risposta inattesa su ' . $path . ' (HTTP ' . $response['status'] . ').');
            $reads[$profile . ':' . $path] = $response;
        }
        if ($mode !== '--baseline') {
            $probes = [
                ['POST', 'users', [], 422],
                ['PATCH', 'users/2147483647', ['email' => 'invalid'], 422],
                ['DELETE', 'users/2147483647', null, 404],
                ['POST', 'users/2147483647/union-logo', [], 404],
                ['POST', 'users/2147483647/roles', ['roles' => ['__INVALID_AUDIT_ROLE__']], 422],
            ];
            foreach ($probes as [$method, $path, $data, $adminStatus]) {
                $expected = $profile === 'guest' ? 401 : ($profile === 'admin' ? $adminStatus : 403);
                $response = localRequest($path, $token, $method, $data);
                if ($response['status'] !== $expected) throw new RuntimeException($profile . ': protezione amministrativa inattesa su ' . $path . ' (HTTP ' . $response['status'] . ', atteso ' . $expected . ').');
                $securityChecks++;
            }
        }
    }
} finally {
    foreach ($tokens as $token) {
        $pdo->prepare('DELETE FROM configuration_sessions WHERE token_id = ?')->execute([$token['id']]);
        $pdo->prepare('DELETE FROM api_tokens WHERE id = ? AND token_hash = ?')->execute([$token['id'], $token['hash']]);
    }
}
if ($snapshot !== localSnapshot($pdo, $root)) throw new RuntimeException('Dati documentali, file o account modificati durante la verifica: fermarsi e controllare.');
if ($mode === '--baseline') {
    $directory = $root . '/storage/private/security-tests';
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Cartella privata di test non disponibile.');
    $path = $directory . '/baseline_' . date('Ymd_His') . '.json';
    $handle = fopen($path, 'xb');
    if ($handle === false) throw new RuntimeException('Creazione baseline fallita.');
    fwrite($handle, json_encode(['snapshot' => $snapshot, 'reads' => $reads], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    fclose($handle);
    echo 'BASELINE=' . $path . "\n";
} else {
    $path = realpath($argv[2] ?? '');
    $directory = realpath($root . '/storage/private/security-tests');
    if ($path === false || $directory === false || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $directory) . '/')) throw new RuntimeException('Baseline privata non valida.');
    $baseline = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $expectedSnapshot = $baseline['snapshot'];
    if ($mode === '--verify-documents') {
        if ($expectedSnapshot['accounts_hash'] !== $snapshot['accounts_hash']) echo "NOTA: account diversi dalla baseline dopo test manuali; confronto storico account escluso esplicitamente, verifica di invarianza durante questo test mantenuta.\n";
        unset($expectedSnapshot['accounts_hash']);
        $documentSnapshot = $snapshot;
        unset($documentSnapshot['accounts_hash']);
    } else {
        $documentSnapshot = $snapshot;
    }
    if ($expectedSnapshot !== $documentSnapshot || $baseline['reads'] !== $reads) {
        $differences = [];
        foreach (['tables', 'files'] as $section) {
            $keys = array_unique(array_merge(array_keys($expectedSnapshot[$section]), array_keys($documentSnapshot[$section])));
            foreach ($keys as $key) {
                if (($expectedSnapshot[$section][$key] ?? null) !== ($documentSnapshot[$section][$key] ?? null)) $differences[$section][] = $key;
            }
        }
        foreach (array_unique(array_merge(array_keys($baseline['reads']), array_keys($reads))) as $key) {
            if (($baseline['reads'][$key] ?? null) !== ($reads[$key] ?? null)) $differences['responses'][] = $key;
        }
        if (isset($expectedSnapshot['accounts_hash']) && $expectedSnapshot['accounts_hash'] !== $documentSnapshot['accounts_hash']) $differences['accounts'] = true;
        throw new RuntimeException('Regressione rispetto alla baseline, soli riferimenti senza contenuti: ' . json_encode($differences, JSON_THROW_ON_ERROR));
    }
}
echo 'OK: ' . count($snapshot['files']) . ' file originali/PDF, ' . count($snapshot['tables']) . ' tabelle documentali, ' . count($reads) . ' risposte API; ' . $securityChecks . " controlli amministrativi. Solo token temporanei e log di lettura locali; account e documenti invariati durante il test.\n";
