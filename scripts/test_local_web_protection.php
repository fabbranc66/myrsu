<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

function localProtectionStatus(string $path, string $method): int
{
    $curl = curl_init('http://localhost/miodel/' . $path);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_NOBODY => $method === 'HEAD',
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROXY => '',
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_WRITEFUNCTION => static fn ($handle, string $chunk): int => strlen($chunk),
    ]);
    $result = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($result === false || $status === 0) throw new RuntimeException('Server locale non raggiungibile.');
    return $status;
}

$blocked = [
    '.env', '.ENV', '.env.example', '.env.bak', '%2eenv',
    '.git/config', '.GIT/HEAD', '.github/workflows/probe.yml', '.codex/probe', '.agents/probe',
    'app/Core/Auth.php', 'APP/Core/Auth.php', 'config/database.php', 'config/signing.php',
    'database/schema.sql', 'docs/ROADMAP_MYRSU.md', 'routes/api.php',
    'scripts/test_api_errors.php', 'vendor/autoload.php', 'artifacts/probe',
    'AGENTS.md', 'README.md', 'DEVELOPMENT_RULES.md', 'composer.json', 'composer.lock',
    'storage/private/', 'Storage/Private/security-tests/probe.json',
    'index.php.bak', 'backup.sql', 'debug.LOG', 'file.php~', '.well-known/.env',
];
$checks = 0;
foreach ($blocked as $path) {
    foreach (['HEAD', 'GET'] as $method) {
        $status = localProtectionStatus($path, $method);
        if ($status !== 403) throw new RuntimeException($method . ' ' . $path . ': atteso 403, ricevuto ' . $status);
        $checks++;
    }
}

foreach (['ui/app/index.html', 'ui/documents.html', 'ui/login.html', 'ui/menu.css', 'ui/users.css', 'ui/vendor/jspdf/jspdf.umd.min.js'] as $path) {
    if (localProtectionStatus($path, 'GET') !== 200) throw new RuntimeException('Risorsa operativa bloccata: ' . $path);
    $checks++;
}
if (localProtectionStatus('api/v1/health', 'GET') !== 200) throw new RuntimeException('Health API non disponibile.');
$checks++;
if (localProtectionStatus('.well-known/myrsu-nonexistent-probe', 'GET') !== 404) throw new RuntimeException('Eccezione well-known non preservata.');
$checks++;

echo "OK: {$checks} controlli HTTP locali: file interni 403, UI e API disponibili, vendor UI e well-known preservati. Corpi delle risposte scartati; nessun segreto letto o mostrato, nessun file operativo modificato.\n";
