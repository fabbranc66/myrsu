<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\ConfigurationSql;
use App\Repositories\SavedConfigurationRepository;
use App\Services\ConfigurationCatalogService;
use App\Services\ConfigurationFilesService;

if (PHP_SAPI !== 'cli') exit('Solo CLI.');
$root = dirname(__DIR__);
require $root . '/app/Core/helpers.php';
load_env($root . '/.env');
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) require $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
$config = require $root . '/config/database.php';
if (!in_array($config['host'], ['localhost', '127.0.0.1'], true) || $config['database'] !== 'myrsu') throw new RuntimeException('Solo database locale myrsu.');
$master = new Database($config);
$pdo = $master->pdo();
$repository = new SavedConfigurationRepository($pdo);
$files = new ConfigurationFilesService($root);
$initial = $repository->operational();
$prefix = '__configuration_test_' . bin2hex(random_bytes(4));
$created = [];
$tokens = [];
$checks = 0;
$logsStart = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM activity_logs')->fetchColumn();
$hashes = [];
foreach (['users', 'roles', 'permissions', 'role_user', 'permission_role', 'gdpr_consents', 'institutional_contacts', 'documents', 'practices', 'practice_links', 'reports', 'document_comments'] as $table) {
    $hashes[$table] = hash('sha256', json_encode($pdo->query('SELECT * FROM `' . $table . '` ORDER BY 1')->fetchAll()));
}
function check(bool $valid, string $message): void
{
    global $checks;
    if (!$valid) throw new RuntimeException($message);
    $checks++;
}
function api(string $method, string $path, ?string $token, ?array $data = null, int $expected = 200, ?int $context = null): array
{
    $curl = curl_init('http://127.0.0.1/myrsu/api/v1/' . ltrim($path, '/'));
    $headers = ['Content-Type: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    if ($context !== null) $headers[] = 'X-Configuration-Id: ' . $context;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 90]);
    if ($data !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    check($status === $expected, $method . ' ' . $path . ': ' . $status . ', atteso ' . $expected . ' ' . ($status !== $expected ? $body : ''));
    return json_decode((string)$body, true)['data'] ?? [];
}
try {
    $adminId = (int)$pdo->query("SELECT ru.user_id FROM role_user ru JOIN roles r ON r.id = ru.role_id WHERE r.name = 'admin' LIMIT 1")->fetchColumn();
    $memberId = (int)$pdo->query("SELECT u.id FROM users u WHERE NOT EXISTS (SELECT 1 FROM role_user ru JOIN roles r ON r.id = ru.role_id WHERE ru.user_id = u.id AND r.name = 'admin') LIMIT 1")->fetchColumn();
    check($adminId > 0 && $memberId > 0, 'Servono utenti admin e non admin già esistenti.');
    foreach ([$adminId, $adminId, $memberId] as $userId) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, created_at) VALUES (?, ?, NOW())')->execute([$userId, hash('sha256', $token)]);
        $tokens[] = $token;
    }
    [$admin, $otherSession, $member] = $tokens;
    api('GET', 'admin/configurations', null, null, 401);
    api('GET', 'admin/configurations', $member, null, 403);
    api('POST', 'admin/configurations/selection', $member, ['configuration_id' => 1], 403);
    api('POST', 'admin/configurations', $admin, ['name' => '', 'mode' => 'empty'], 422);
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => 'bad'], 422);
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => 9999999], 404);
    $first = api('POST', 'admin/configurations', $admin, ['name' => $prefix . '_A', 'mode' => 'empty'], 201);
    $created[] = $first['id'];
    api('POST', 'admin/configurations', $admin, ['name' => $first['name'], 'mode' => 'empty'], 409);
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $first['id']]);
    $me = api('GET', 'me', $admin);
    check($me['configuration']['id'] === $first['id'] && !$me['configuration']['is_operational'], 'Selezione admin non corretta.');
    $other = api('GET', 'me', $otherSession);
    check($other['configuration']['id'] === (int)$initial['id'], 'La selezione ha modificato un’altra sessione.');
    check(api('GET', 'practices?scope=all', $admin) === [], 'La nuova configurazione non è vuota.');
    check(api('GET', 'documents', $admin) === [], 'I documenti demo sono visibili nella configurazione vuota.');
    check(api('GET', 'reports?status=all', $admin) === [], 'Le segnalazioni demo sono presenti nella configurazione vuota.');
    api('POST', 'emails/send', $admin, [], 409);
    api('POST', 'emails/sync', $admin, [], 409);
    api('POST', 'rooms/1/external-participants', $admin, [], 409);
    api('PATCH', 'profile', $admin, ['name' => 'Test'], 409);
    api('POST', 'institutional-contacts', $admin, ['name' => 'Test'], 409);
    foreach (['calls', 'emails', 'union-meetings', 'workers-assemblies', 'votings', 'rooms', 'protocol', 'reminders?status=all'] as $endpoint) {
        api('GET', $endpoint, $admin);
    }
    $practice = api('POST', 'practices', $admin, ['title' => $prefix, 'summary' => 'Test configurazioni', 'status' => 'new'], 201)['practice'];
    $firstRow = $repository->find($first['id']);
    $firstDb = new Database($config, ConfigurationCatalogService::tables($pdo), $firstRow['table_prefix']);
    $scoped = $firstDb->pdo();
    check((int)$scoped->query('SELECT COUNT(*) FROM practices')->fetchColumn() === 1, 'La pratica non è nella configurazione selezionata.');
    check((int)$scoped->query('SELECT COUNT(*) FROM users')->fetchColumn() === (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(), 'Utenti non condivisi.');
    $name = $prefix . '.pdf';
    $content = '%PDF-1.4 configurazione di prova';
    $directory = $files->root($firstRow) . '/public/documents/documenti';
    if (!is_dir($directory)) mkdir($directory, 0775, true);
    file_put_contents($directory . '/' . $name, $content);
    $originals = $files->root($firstRow) . '/storage/private/originals';
    file_put_contents($originals . '/' . $name, $content);
    foreach (['report-attachments', 'room-attachments/7', 'email-attachments/7'] as $attachment) {
        $attachmentPath = $files->root($firstRow) . '/storage/private/' . $attachment;
        if (!is_dir($attachmentPath)) mkdir($attachmentPath, 0775, true);
        file_put_contents($attachmentPath . '/fixture.bin', $content);
    }
    $checksum = hash('sha256', $content);
    $scoped->prepare("INSERT INTO documents (original_name, stored_name, mime_type, size_bytes, checksum_sha256, original_stored_name, original_checksum_sha256, category, pdf_public_path, pdf_size_bytes, pdf_checksum_sha256, conversion_status, signature, visibility, uploaded_by, created_at) VALUES (?, ?, 'application/pdf', ?, ?, ?, ?, 'documenti', ?, ?, ?, 'ready', ?, 'public', ?, NOW())")->execute([$name, $name, strlen($content), $checksum, $name, $checksum, 'public/documents/documenti/' . $name, strlen($content), $checksum, str_repeat('A', 24), $adminId]);
    $documentId = (int)$scoped->lastInsertId();
    $scoped->prepare("INSERT INTO practice_links (practice_id, entity_type, entity_id, created_by, created_at) VALUES (?, 'document', ?, ?, NOW())")->execute([$practice['id'], $documentId, $adminId]);
    api('GET', 'document-folders', $admin);
    check(api('GET', 'documents/' . $documentId . '/verify?sig=' . str_repeat('A', 24), $admin)['valid'], 'La verifica del documento non usa la configurazione selezionata.');
    $curl = curl_init('http://127.0.0.1/myrsu/api/v1/documents/' . $documentId . '/preview');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $admin]]);
    check(curl_exec($curl) === $content, 'Anteprima proveniente da una configurazione diversa.');
    check((int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 200, 'Anteprima della configurazione non accessibile.');
    curl_close($curl);
    $curl = curl_init('http://127.0.0.1/myrsu/storage/private/configurations/' . $first['id'] . '/public/documents/documenti/' . $name);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_exec($curl);
    check((int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE) === 403, 'File archiviato accessibile direttamente senza API.');
    curl_close($curl);
    $countBefore = count($repository->all());
    api('PATCH', 'admin/configurations/' . $first['id'], $admin, ['name' => $prefix . '_saved', 'confirm' => 'wrong'], 422);
    $first = api('PATCH', 'admin/configurations/' . $first['id'], $admin, ['name' => $prefix . '_saved', 'confirm' => $first['name']]);
    check(count($repository->all()) === $countBefore, 'Salva ha creato una copia automatica.');
    $second = api('POST', 'admin/configurations', $admin, ['name' => $prefix . '_B', 'mode' => 'copy'], 201);
    $created[] = $second['id'];
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $second['id']]);
    $copied = api('GET', 'practices/' . $practice['id'], $admin)['practice'];
    check($copied['status'] === 'new', 'La copia ha chiuso una pratica aperta.');
    check($copied['title'] === $prefix, 'Titolo della pratica non preservato.');
    $secondRow = $repository->find($second['id']);
    $secondDb = new Database($config, ConfigurationCatalogService::tables($pdo), $secondRow['table_prefix']);
    check((int)$secondDb->pdo()->query('SELECT COUNT(*) FROM practice_links')->fetchColumn() === 1, 'Collegamenti alla pratica non copiati.');
    check((int)$secondDb->pdo()->query('SELECT COUNT(*) FROM document_folder_items')->fetchColumn() === 0, 'Collocazioni documenti non corrette.');
    $secondPath = $files->root($secondRow) . '/public/documents/documenti/' . $name;
    check(hash_file('sha256', $secondPath) === $checksum, 'PDF della copia differente.');
    foreach (['report-attachments', 'room-attachments/7', 'email-attachments/7'] as $attachment) {
        check(hash_file('sha256', $files->root($secondRow) . '/storage/private/' . $attachment . '/fixture.bin') === $checksum, 'Allegati non copiati: ' . $attachment);
    }
    file_put_contents($secondPath, 'modifica solo B');
    check(hash_file('sha256', $directory . '/' . $name) === $checksum, 'La modifica di B ha alterato i file di A.');
    api('POST', 'practices', $admin, ['title' => 'Scheda obsoleta'], 409, $first['id']);
    api('POST', 'admin/configurations', $admin, ['name' => $prefix . '_stale', 'mode' => 'copy'], 409, $first['id']);
    api('PATCH', 'practices/' . $practice['id'], $admin, ['title' => 'Solo B', 'status' => 'closed']);
    check($scoped->query('SELECT status FROM practices LIMIT 1')->fetchColumn() === 'new', 'La chiusura in B ha chiuso la pratica in A.');
    $incomplete = count($repository->all());
    api('POST', 'admin/configurations', $admin, ['name' => $prefix . '_invalid', 'mode' => 'copy'], 409);
    check(count($repository->all()) === $incomplete, 'Una copia fallita è visibile nel catalogo.');
    $pdo->prepare('UPDATE saved_configurations SET tables_json = ? WHERE id = ?')->execute(['[]', $second['id']]);
    api('GET', 'documents', $admin, null, 409);
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $first['id']]);
    $pdo->prepare('UPDATE saved_configurations SET tables_json = ? WHERE id = ?')->execute([$secondRow['tables_json'], $second['id']]);
    api('POST', 'admin/configurations/' . $first['id'] . '/operational', $admin, ['confirm' => 'wrong'], 422);
    api('POST', 'admin/configurations/' . $first['id'] . '/operational', $admin, ['confirm' => $first['name']]);
    $public = api('GET', 'public/documents', null);
    check(count($public['sections']['documenti'] ?? []) === 1, 'Gli ospiti non seguono la nuova configurazione operativa.');
    $other = api('GET', 'me', $otherSession);
    check($other['configuration']['id'] === $first['id'], 'Le sessioni non selezionate non seguono l’Operativo.');
    $repository->makeOperational((int)$initial['id']);
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => null]);
    check(api('GET', 'me', $admin)['configuration']['id'] === (int)$initial['id'], 'Ritorno all’Operativo non riuscito.');
    $sample = "SELECT documents.id, 'documents', \"documents\" FROM `documents` JOIN users ON users.id = documents.uploaded_by WHERE documents.original_name = :documents /* documents */";
    $mapped = ConfigurationSql::map($sample, ['documents'], 'cfg_99_');
    check(str_contains($mapped, "'documents'") && str_contains($mapped, '"documents"') && str_contains($mapped, ':documents') && str_contains($mapped, '/* documents */'), 'Stringhe, parametri o commenti SQL modificati.');
    check(str_contains($mapped, 'FROM `cfg_99_documents` JOIN users'), 'Isolamento SQL non corretto.');
    echo 'OK: ' . $checks . ' verifiche configurazioni, isolamento sessioni/dati/file, pratiche aperte, salvataggio senza duplicati, permessi e rollback.' . PHP_EOL;
} finally {
    $repository->makeOperational((int)$initial['id']);
    foreach ($tokens as $token) {
        $repository->select($token, null);
        $pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (array_reverse($created) as $id) {
        $row = $repository->find($id);
        if ($row === null || !str_starts_with($row['name'], $prefix)) continue;
        foreach (json_decode($row['tables_json'], true) ?: [] as $table) $pdo->exec('DROP TABLE IF EXISTS `' . $row['table_prefix'] . $table . '`');
        $pdo->prepare('DELETE FROM saved_configurations WHERE id = ?')->execute([$id]);
        $files->remove($row);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->prepare('DELETE FROM activity_logs WHERE id > ? AND user_id = ? AND (action LIKE ? OR action = ?)')->execute([$logsStart, $adminId ?? 0, 'configurations.%', 'auth.login']);
    foreach ($hashes as $table => $hash) check(hash('sha256', json_encode($pdo->query('SELECT * FROM `' . $table . '` ORDER BY 1')->fetchAll())) === $hash, 'Dati originali alterati: ' . $table);
}
