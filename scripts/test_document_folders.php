<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\HttpException;
use App\Controllers\Api\EmailController;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$basePath = dirname(__DIR__);
require $basePath . '/app/Core/helpers.php';
load_env($basePath . '/.env');
spl_autoload_register(static function (string $class) use ($basePath): void {
    if (str_starts_with($class, 'App\\')) require $basePath . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
});
require $basePath . '/vendor/autoload.php';
$config = require $basePath . '/config/database.php';
if (!in_array($config['host'], ['127.0.0.1', 'localhost'], true) || $config['database'] !== 'myrsu') {
    throw new RuntimeException('Test consentito soltanto sul database locale myrsu.');
}
$app = new Application($basePath);
$app->bootDatabase();
$pdo = $app->database->pdo();
$prefix = '__folder_test_' . bin2hex(random_bytes(6));
$folderIds = [];
$practiceIds = [];
$practiceFolderIds = [];
$tokens = [];
$documentId = null;
$paths = [];
$checks = 0;
$documentsHash = hash('sha256', json_encode($pdo->query('SELECT * FROM documents ORDER BY id')->fetchAll()));
$linksHash = hash('sha256', json_encode($pdo->query('SELECT * FROM practice_links ORDER BY id')->fetchAll()));
$logStart = (int)$pdo->query('SELECT COALESCE(MAX(id), 0) FROM activity_logs')->fetchColumn();

function check(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

function request(string $method, string $path, ?string $token = null, ?array $data = null, int $expected = 200, bool $multipart = false): array
{
    $curl = curl_init('http://127.0.0.1/myrsu/' . ltrim($path, '/'));
    $headers = $token === null ? [] : ['Authorization: Bearer ' . $token];
    if ($data !== null && !$multipart) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 15,
    ]);
    if ($data !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $multipart ? $data : json_encode($data));
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    check($status === $expected, $method . ' ' . $path . ': HTTP ' . $status . ', atteso ' . $expected . ' ' . $error . ($status >= 500 ? ' ' . $body : ''));
    return ['body' => $body, 'data' => json_decode((string)$body, true)['data'] ?? null];
}

try {
    $roleUsers = [];
    foreach (['admin', 'rls', 'delegato'] as $role) {
        $statement = $pdo->prepare(
            "SELECT u.id FROM users u JOIN role_user ru ON ru.user_id = u.id JOIN roles r ON r.id = ru.role_id
             WHERE r.name = ? AND (? = 'admin' OR NOT EXISTS
               (SELECT 1 FROM role_user other_ru JOIN roles other_r ON other_r.id = other_ru.role_id
                WHERE other_ru.user_id = u.id AND other_r.name = 'admin')) LIMIT 1"
        );
        $statement->execute([$role, $role]);
        $userId = (int)$statement->fetchColumn();
        check($userId > 0, 'Utente di test esistente per ruolo ' . $role);
        $roleUsers[$role] = ['id' => $userId];
        $tokens[$role] = bin2hex(random_bytes(32));
        $app->tokens->create($userId, $tokens[$role], 'document-folder-test');
    }
    $roles = array_column($app->roles->allRoles(), 'id', 'name');
    request('GET', 'api/v1/health');
    request('GET', 'api/v1/document-folders', null, null, 401);
    request('POST', 'api/v1/document-folders', null, ['name' => 'Denied'], 401);
    $root = request('POST', 'api/v1/document-folders', $tokens['admin'], ['name' => $prefix, 'parent_id' => null], 201)['data'];
    $folderIds[] = (int)$root['id'];
    $child = request('POST', 'api/v1/document-folders', $tokens['admin'], ['name' => 'Verbali qualità', 'parent_id' => (int)$root['id']], 201)['data'];
    $folderIds[] = (int)$child['id'];
    request('POST', 'api/v1/document-folders', $tokens['admin'], ['name' => $prefix], 409);
    foreach (['..', 'a/b', 'a\\b', '', 'a' . chr(1)] as $invalid) {
        request('POST', 'api/v1/document-folders', $tokens['admin'], ['name' => $invalid], 422);
    }
    request('POST', 'api/v1/document-folders', $tokens['admin'], ['name' => 'Missing', 'parent_id' => PHP_INT_MAX], 404);
    request('POST', 'api/v1/documents', $tokens['admin'], ['folder_id' => '0'], 422, true);
    request('POST', 'api/v1/documents', $tokens['admin'], ['folder_id' => (string)PHP_INT_MAX], 404, true);
    request('PATCH', 'api/v1/document-folders/' . $root['id'] . '/permissions', $tokens['admin'], [
        'inherit_permissions' => false, 'permissions' => [['role_id' => (int)$roles['rls'], 'access_level' => 'read']],
    ]);
    $rlsData = request('GET', 'api/v1/document-folders', $tokens['rls'])['data'];
    $visible = array_column($rlsData['folders'], null, 'id');
    check(isset($visible[$child['id']]) && !$visible[$child['id']]['can_manage'], 'Ereditarietà lettura senza gestione');
    check(!$visible[$child['id']]['can_set_permissions'], 'La sola lettura non consente di cambiare i permessi');
    request('POST', 'api/v1/documents', $tokens['rls'], ['folder_id' => (string)$child['id']], 403, true);
    request('POST', 'api/v1/documents', $tokens['delegato'], ['folder_id' => (string)$child['id']], 403, true);
    check($app->documentFolderService->uploadDestination((string)$child['id'], $roleUsers['admin']) === (int)$child['id'], 'Destinazione caricamento validata prima del salvataggio');
    check($app->documentFolderService->uploadDestination('', $roleUsers['admin']) === null, 'Caricamento nella radice compatibile con il flusso precedente');
    request('PATCH', 'api/v1/document-folders/' . $root['id'] . '/permissions', $tokens['rls'], [
        'inherit_permissions' => true, 'permissions' => [],
    ], 403);
    $otherData = request('GET', 'api/v1/document-folders', $tokens['delegato'])['data'];
    check(!in_array((int)$root['id'], array_map('intval', array_column($otherData['folders'], 'id')), true), 'Cartella non esposta a ruoli esclusi');

    $bytes = "%PDF-1.4\n" . $prefix . "\n%%EOF";
    $publicPath = 'public/documents/documenti/' . $prefix . '.pdf';
    $originalName = $prefix . '-original.pdf';
    foreach ([$publicPath, 'storage/private/originals/' . $originalName] as $relative) {
        $path = $basePath . '/' . $relative;
        check(!file_exists($path) && is_dir(dirname($path)), 'Percorso fixture disponibile');
        check(file_put_contents($path, $bytes) === strlen($bytes), 'Scrittura fixture');
        $paths[] = $path;
    }
    $document = $app->documents->create([
        'original_name' => $prefix . '.pdf', 'original_stored_name' => $originalName, 'original_mime_type' => 'application/pdf',
        'original_size_bytes' => strlen($bytes), 'original_checksum_sha256' => hash('sha256', $bytes),
        'category' => 'documenti', 'pdf_public_path' => $publicPath, 'pdf_size_bytes' => strlen($bytes),
        'pdf_checksum_sha256' => hash('sha256', $bytes), 'conversion_status' => 'ready', 'visibility' => 'public',
        'uploaded_by' => $roleUsers['admin']['id'],
    ]);
    $documentId = (int)$document['id'];
    $app->documentFolderAccess->refresh();
    request('PATCH', 'api/v1/documents/' . $documentId . '/folder', $tokens['admin'], ['folder_id' => (int)$child['id']]);
    request('GET', $publicPath, null, null, 403);
    request('GET', 'storage/private/originals/' . $originalName, null, null, 403);
    request('GET', 'api/v1/documents/' . $documentId . '/preview', null, null, 403);
    request('GET', 'api/v1/documents/' . $documentId . '/thumbnail', null, null, 403);
    request('GET', 'api/v1/documents/' . $documentId . '/verify?sig=test', null, null, 403);
    request('POST', 'api/v1/documents/' . $documentId . '/verify-file', null, [], 403);
    request('GET', 'api/v1/documents/' . $documentId . '/comments', null, null, 403);
    request('GET', 'api/v1/documents/' . $documentId, $tokens['delegato'], null, 403);
    request('GET', 'api/v1/documents/' . $documentId . '/download', $tokens['delegato'], null, 403);
    $emailController = new EmailController($app);
    $attachments = new ReflectionMethod($emailController, 'documentAttachments');
    try {
        $attachments->invoke($emailController, ['document_ids' => [$documentId]], $roleUsers['delegato']);
        check(false, 'Esportazione email non autorizzata');
    } catch (HttpException $exception) {
        check($exception->status() === 403, 'Allegati email protetti senza inviare messaggi');
    }
    check(request('GET', 'api/v1/documents/' . $documentId . '/preview', $tokens['rls'])['body'] === $bytes, 'Lettura autorizzata senza alterare il file');
    check(request('GET', $publicPath, $tokens['rls'])['body'] === $bytes, 'Link diretto controllato');
    $publicSections = request('GET', 'api/v1/public/documents')['data']['sections'];
    foreach ($publicSections as $section) check(!in_array($documentId, array_map('intval', array_column($section, 'id')), true), 'Documento escluso dalla bacheca pubblica');
    $otherDocuments = request('GET', 'api/v1/documents', $tokens['delegato'])['data'];
    check(!in_array($documentId, array_map('intval', array_column($otherDocuments, 'id')), true), 'Documento nascosto anche fuori dalla cartella');
    request('PATCH', 'api/v1/documents/' . $documentId . '/folder', $tokens['rls'], ['folder_id' => null], 403);
    request('POST', 'api/v1/document-folders', $tokens['rls'], ['name' => 'Denied', 'parent_id' => (int)$root['id']], 403);
    request('PATCH', 'api/v1/document-folders/' . $child['id'] . '/permissions', $tokens['admin'], [
        'inherit_permissions' => false, 'permissions' => [['role_id' => (int)$roles['delegato'], 'access_level' => 'manage']],
    ]);
    request('GET', 'api/v1/documents/' . $documentId, $tokens['delegato'], null, 403);
    request('GET', 'api/v1/documents/' . $documentId, $tokens['rls'], null, 403);
    request('GET', 'api/v1/documents/' . $documentId, $tokens['admin']);
    request('PATCH', 'api/v1/document-folders/' . $child['id'] . '/permissions', $tokens['admin'], ['inherit_permissions' => true, 'permissions' => []]);
    request('PATCH', 'api/v1/document-folders/' . $root['id'] . '/permissions', $tokens['admin'], [
        'inherit_permissions' => false, 'permissions' => [['role_id' => (int)$roles['rls'], 'access_level' => 'manage']],
    ]);
    request('PATCH', 'api/v1/documents/' . $documentId . '/folder', $tokens['rls'], ['folder_id' => null]);
    check(request('GET', $publicPath)['body'] === $bytes, 'Documento fuori cartella: comportamento precedente invariato');
    request('PATCH', 'api/v1/documents/' . $documentId . '/folder', $tokens['admin'], [], 422);
    request('PATCH', 'api/v1/documents/' . $documentId . '/folder', $tokens['admin'], ['folder_id' => 0], 422);
    check($app->documents->findById($documentId) === $document, 'Nessuna modifica ai metadati, alle firme o ai percorsi');
    foreach (['a', 'b'] as $suffix) {
        $statement = $pdo->prepare(
            "INSERT INTO practices (code, title, type, status, priority, source_type, visibility, created_by, created_at, updated_at)
             VALUES (?, ?, 'collective', 'new', 'medium', 'manual', 'operators', ?, NOW(), NOW())"
        );
        $statement->execute([$prefix . '_' . $suffix, $prefix . ' ' . $suffix, $roleUsers['admin']['id']]);
        $practiceIds[] = (int)$pdo->lastInsertId();
    }
    $practiceData = request('GET', 'api/v1/document-folders', $tokens['admin'])['data'];
    $systemFolders = array_column(array_filter($practiceData['folders'], fn (array $folder): bool => $folder['system_key'] !== null), null, 'system_key');
    $systemRoot = $systemFolders['practices'];
    check($systemRoot['can_set_permissions'], 'Permessi configurabili sulla cartella Documenti pratiche');
    foreach ($practiceIds as $identifier) {
        $folder = $systemFolders['practice:' . $identifier];
        $practiceFolderIds[] = (int)$folder['id'];
        check((bool)$folder['inherit_permissions'] && $folder['can_set_permissions'], 'Nuova pratica: accessi predefiniti invariati e permessi configurabili');
        $pdo->prepare("INSERT INTO practice_links (practice_id, entity_type, entity_id, created_by, created_at) VALUES (?, 'document', ?, ?, NOW())")
            ->execute([$identifier, $documentId, $roleUsers['admin']['id']]);
    }
    $app->documentFolderAccess->refresh();
    $rootAllowsRls = $app->documentFolderAccess->allowsFolder((int)$systemRoot['id'], $roleUsers['rls']);
    request('PATCH', 'api/v1/document-folders/' . $practiceFolderIds[0] . '/permissions', $tokens['admin'], [
        'inherit_permissions' => false, 'permissions' => [['role_id' => (int)$roles['rls'], 'access_level' => 'read']],
    ]);
    request('GET', 'api/v1/documents/' . $documentId . '/preview', $tokens['rls'], null, $rootAllowsRls ? 200 : 403);
    request('GET', 'api/v1/documents/' . $documentId . '/preview', $tokens['delegato'], null, 403);
    request('GET', $publicPath, null, null, 403);
    request('PATCH', 'api/v1/documents/' . $documentId, $tokens['rls'], ['visibility' => 'public'], 403);
    request('PATCH', 'api/v1/documents/' . $documentId . '/folder', $tokens['admin'], ['folder_id' => $practiceFolderIds[0]], 422);
    request('POST', 'api/v1/documents', $tokens['admin'], ['folder_id' => (string)$systemRoot['id']], 422, true);
    request('PATCH', 'api/v1/document-folders/' . $practiceFolderIds[1] . '/permissions', $tokens['admin'], [
        'inherit_permissions' => false, 'permissions' => [['role_id' => (int)$roles['delegato'], 'access_level' => 'manage']],
    ]);
    request('GET', 'api/v1/documents/' . $documentId, $tokens['rls'], null, 403);
    request('GET', 'api/v1/documents/' . $documentId, $tokens['delegato'], null, 403);
    request('GET', 'api/v1/documents/' . $documentId, $tokens['admin']);
    $pdo->beginTransaction();
    $app->documentFolderService->setPermissions((int)$systemRoot['id'], ['inherit_permissions' => false, 'permissions' => []]);
    check(!$app->documentFolderAccess->allowsDocument($document, $roleUsers['rls']), 'Il divieto della cartella pratiche protegge il file');
    check($app->documentFolderAccess->allowsDocument($document, $roleUsers['admin']), 'Recupero amministrativo dei permessi');
    $app->documentFolderService->setPermissions($practiceFolderIds[0], ['inherit_permissions' => true, 'permissions' => []]);
    $app->documentFolderService->setPermissions($practiceFolderIds[1], ['inherit_permissions' => true, 'permissions' => []]);
    check(!$app->documentFolderAccess->allowsDocument($document, $roleUsers['rls']), 'Le singole pratiche non aggirano il divieto del contenitore');
    $pdo->rollBack();
    $app->documentFolderAccess->refresh();
    check($app->documents->findById($documentId) === $document, 'Permessi delle pratiche senza modificare il documento');
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($documentId !== null) $pdo->prepare('DELETE FROM documents WHERE id = ? AND original_name = ?')->execute([$documentId, $prefix . '.pdf']);
    foreach ($practiceFolderIds as $identifier) {
        $pdo->prepare('DELETE FROM document_folders WHERE id = ?')->execute([$identifier]);
        $pdo->prepare('DELETE FROM activity_logs WHERE id > ? AND JSON_EXTRACT(metadata_json, "$.folder_id") = ?')->execute([$logStart, $identifier]);
    }
    foreach ($practiceIds as $index => $identifier) {
        $pdo->prepare('DELETE FROM practices WHERE id = ? AND code = ?')->execute([$identifier, $prefix . '_' . ['a', 'b'][$index]]);
    }
    foreach (array_reverse($folderIds) as $identifier) $pdo->prepare('DELETE FROM document_folders WHERE id = ?')->execute([$identifier]);
    foreach ($tokens as $token) $pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    $statement = $pdo->prepare('DELETE FROM activity_logs WHERE id > ? AND (JSON_EXTRACT(metadata_json, "$.document_id") = ? OR JSON_EXTRACT(metadata_json, "$.folder_id") = ? OR JSON_EXTRACT(metadata_json, "$.folder_id") = ?)');
    $statement->execute([$logStart, $documentId, $folderIds[0] ?? null, $folderIds[1] ?? null]);
    foreach ($paths as $path) {
        $resolved = realpath($path);
        check($resolved !== false && str_starts_with(str_replace('\\', '/', $resolved), str_replace('\\', '/', realpath($basePath)) . '/')
            && str_contains(basename($resolved), $prefix), 'Pulizia limitata alle fixture del test');
        unlink($resolved);
    }
}
check($documentsHash === hash('sha256', json_encode($pdo->query('SELECT * FROM documents ORDER BY id')->fetchAll())), 'Documenti originali invariati');
check($linksHash === hash('sha256', json_encode($pdo->query('SELECT * FROM practice_links ORDER BY id')->fetchAll())), 'Collegamenti alle pratiche invariati');
echo 'OK: ' . $checks . " verifiche API, permessi, ereditarietà, file diretti e integrità; fixture eliminate.\n";
