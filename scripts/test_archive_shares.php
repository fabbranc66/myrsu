<?php

declare(strict_types=1);

use App\Core\Database;
use App\Repositories\DocumentFolderRepository;
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
$pdo = (new Database($config))->pdo();
$repository = new SavedConfigurationRepository($pdo);
$files = new ConfigurationFilesService($root);
$initial = $repository->operational();
$prefix = '__archive_test_' . bin2hex(random_bytes(5));
$created = [];
$tokens = [];
$checks = 0;
$backups = [];
$hashes = [];
foreach ($repository->all() as $configuration) {
    foreach (['practices', 'practice_links', 'documents', 'protocol_entries'] as $name) {
        $table = $configuration['table_prefix'] . $name;
        $hashes[$table] = hash('sha256', json_encode($pdo->query('SELECT * FROM `' . $table . '` ORDER BY id')->fetchAll()));
    }
}
foreach (['users', 'roles', 'permissions', 'role_user', 'permission_role', 'gdpr_consents', 'institutional_contacts', 'archive_shares', 'configuration_sessions'] as $table) {
    $hashes[$table] = hash('sha256', json_encode($pdo->query('SELECT * FROM `' . $table . '` ORDER BY 1, 2')->fetchAll()));
}
function check(bool $valid, string $message): void
{
    global $checks;
    if (!$valid) throw new RuntimeException($message);
    $checks++;
}
function api(string $method, string $path, ?string $token, ?array $data = null, int $expected = 200, ?int $context = null): array
{
    $curl = curl_init('http://127.0.0.1/myrsu/api/v1/' . $path);
    $headers = ['Content-Type: application/json'];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    if ($context !== null) $headers[] = 'X-Configuration-Id: ' . $context;
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 45]);
    if ($data !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    check($status === $expected, $method . ' ' . $path . ': ' . $status . ', atteso ' . $expected . ($status !== $expected ? ' ' . $body : ''));
    return ['data' => json_decode((string)$body, true)['data'] ?? [], 'body' => $body];
}
function share(string $token, int $source, string $type, int $id, bool $enabled = true, int $expected = 200): void
{
    api('POST', 'admin/archive/shares', $token, ['source_configuration_id' => $source, 'entity_type' => $type, 'entity_id' => $id, 'enabled' => $enabled], $expected);
}
function verifyUpload(string $path, string $token, string $file, string $signature, int $expected = 200): array
{
    $curl = curl_init('http://127.0.0.1/myrsu/api/v1/' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token], CURLOPT_POSTFIELDS => ['sig' => $signature, 'file' => new CURLFile($file, 'application/pdf', 'fixture.pdf')], CURLOPT_TIMEOUT => 20]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    check($status === $expected, 'Verifica copia PDF: HTTP ' . $status . ', atteso ' . $expected);
    return json_decode((string)$body, true)['data'] ?? [];
}
function fixture(PDO $scoped, array $row, int $adminId, string $label): void
{
    global $files;
    $scoped->prepare("INSERT INTO practices (code, title, summary, status, created_by, created_at, updated_at) VALUES ('PRA-2026-0001', ?, ?, 'new', ?, NOW(), NOW()), ('PRA-2026-0002', ?, 'Archivio', 'archived', ?, NOW(), NOW())")->execute([$label, 'Sintesi ' . $label, $adminId, 'Chiusa ' . $label, $adminId]);
    $scoped->prepare("INSERT INTO practice_notes (practice_id, body, created_by, created_at) VALUES (1, ?, ?, NOW())")->execute(['Nota ' . $label, $adminId]);
    $directory = $files->root($row) . '/public/documents/documenti';
    if (!is_dir($directory)) mkdir($directory, 0775, true);
    $originals = $files->root($row) . '/storage/private/originals';
    if (!is_dir($originals)) mkdir($originals, 0775, true);
    foreach ([1, 2, 3] as $id) {
        $content = '%PDF-1.4 ' . $label . ' documento ' . $id;
        $name = 'fixture-' . $id . '.pdf';
        file_put_contents($directory . '/' . $name, $content);
        file_put_contents($originals . '/' . $name, $content);
        $scoped->prepare("INSERT INTO documents (id, original_name, stored_name, mime_type, size_bytes, checksum_sha256, original_stored_name, original_checksum_sha256, category, pdf_public_path, pdf_size_bytes, pdf_checksum_sha256, conversion_status, signature, visibility, uploaded_by, created_at) VALUES (?, ?, ?, 'application/pdf', ?, ?, ?, ?, 'documenti', ?, ?, ?, 'ready', ?, 'public', ?, NOW())")->execute([$id, $label . '-' . $name, $name, strlen($content), hash('sha256', $content), $name, hash('sha256', $content), 'public/documents/documenti/' . $name, strlen($content), hash('sha256', $content), str_repeat('A', 24), $adminId]);
    }
    $scoped->prepare("INSERT INTO protocol_entries (protocol_number, direction, type_code, year, sequence, subject, document_id, created_by, created_at) VALUES ('IN-DOC-2026-0001', 'IN', 'DOC', 2026, 1, ?, 2, ?, NOW())")->execute(['Protocollo ' . $label, $adminId]);
    $scoped->prepare("INSERT INTO practice_links (practice_id, entity_type, entity_id, created_by, created_at) VALUES (1, 'document', 1, ?, NOW()), (1, 'document', 3, ?, NOW()), (1, 'protocol', 1, ?, NOW())")->execute([$adminId, $adminId, $adminId]);
    $folders = new DocumentFolderRepository($scoped);
    $folders->ensurePracticeFolders();
    $restricted = $folders->create('Solo admin', null, $adminId);
    $folders->setPermissions((int)$restricted['id'], false, []);
    $folders->place(3, (int)$restricted['id'], $adminId);
}
try {
    $adminId = (int)$pdo->query("SELECT ru.user_id FROM role_user ru JOIN roles r ON r.id = ru.role_id WHERE r.name = 'admin' LIMIT 1")->fetchColumn();
    $readerId = (int)$pdo->query("SELECT ru.user_id FROM role_user ru JOIN roles r ON r.id = ru.role_id WHERE r.name = 'delegato' AND NOT EXISTS (SELECT 1 FROM role_user ar JOIN roles a ON a.id = ar.role_id WHERE ar.user_id = ru.user_id AND a.name = 'admin') LIMIT 1")->fetchColumn();
    check($adminId > 0 && $readerId > 0, 'Servono admin e delegato esistenti.');
    foreach ([$adminId, $readerId] as $userId) {
        $token = bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO api_tokens (user_id, token_hash, created_at) VALUES (?, ?, NOW())')->execute([$userId, hash('sha256', $token)]);
        $tokens[] = $token;
    }
    [$admin, $reader] = $tokens;
    api('GET', 'admin/archive', null, null, 401);
    api('GET', 'admin/archive', $reader, null, 403);
    api('GET', 'archive/shared', null, null, 401);
    api('POST', 'admin/archive/shares', $reader, [], 403);
    api('GET', 'admin/archive?source%5B%5D=1', $admin, null, 422);
    api('GET', 'admin/archive?type=report', $admin, null, 422);
    foreach (['A', 'B', 'Target'] as $label) {
        $row = api('POST', 'admin/configurations', $admin, ['name' => $prefix . '_' . $label, 'mode' => 'empty'], 201)['data'];
        $created[] = (int)$row['id'];
        $rows[$label] = $repository->find((int)$row['id']);
        $connections[$label] = (new Database($config, ConfigurationCatalogService::tables($pdo), $rows[$label]['table_prefix']))->pdo();
        fixture($connections[$label], $rows[$label], $adminId, $prefix . '_' . $label);
    }
    [$source, $second, $target] = $created;
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $target]);
    $catalogue = api('GET', 'admin/archive?q=' . $prefix, $admin)['data'];
    check(count($catalogue['items']) === 6, 'Catalogo incompleto: pratiche aperte/archiviate di tutti gli ambienti.');
    check(count(array_filter($catalogue['items'], static fn (array $item): bool => $item['is_current'])) === 2, 'Archivio attivo non riconosciuto.');
    api('GET', 'archive/sources/' . $source . '/practice/1', $reader, null, 403);
    api('GET', 'archive/sources/' . $source . '/documents/1/file', $reader, null, 403);
    api('GET', 'archive/sources/' . $source . '/practice/1', $admin);
    share($admin, $target, 'practice', 1, true, 422);
    share($admin, $source, 'practice', 9999999, true, 404);
    api('POST', 'admin/archive/shares', $admin, ['enabled' => 'true'], 422);
    api('POST', 'admin/archive/shares', $admin, ['enabled' => true], 422);
    api('POST', 'admin/archive/shares', $admin, ['enabled' => true], 409, $source);
    share($admin, $source, 'practice', 1);
    share($admin, $source, 'practice', 1);
    check(count(api('GET', 'archive/shared?type=practice', $admin)['data']['items']) === 1, 'Condivisione duplicata.');
    check(count(api('GET', 'archive/shared?type=document', $admin)['data']['items']) === 3, 'Documenti diretti e protocollati non disponibili.');
    $item = api('GET', 'archive/sources/' . $source . '/practice/1', $admin)['data'];
    check($item['practice']['title'] === $prefix . '_A' && count($item['notes']) === 1 && count($item['documents']) === 3, 'Lettura originale pratica errata.');
    check(count($item['protocols']) === 1 && $item['protocols'][0]['protocol_number'] === 'IN-DOC-2026-0001', 'Protocollo originale perso.');
    check(api('GET', 'archive/sources/' . $source . '/documents/1/file', $admin)['body'] === '%PDF-1.4 ' . $prefix . '_A documento 1', 'File della configurazione sbagliata.');
    check(api('GET', 'archive/sources/' . $source . '/documents/1/verify?sig=' . str_repeat('A', 24), $admin)['data']['valid'], 'Verifica originale fallita.');
    check(!api('GET', 'archive/sources/' . $source . '/documents/1/verify?sig=BAD', $admin)['data']['valid'], 'Firma errata accettata.');
    api('GET', 'archive/sources/' . $source . '/documents/1/verify?sig%5B%5D=BAD', $admin, null, 422);
    check((int)$connections['Target']->query('SELECT COUNT(*) FROM practices')->fetchColumn() === 2, 'Pratica copiata nella destinazione.');
    check((int)$connections['Target']->query('SELECT COUNT(*) FROM documents')->fetchColumn() === 3, 'Documento copiato nella destinazione.');
    check($connections['A']->query('SELECT status FROM practices WHERE id = 1')->fetchColumn() === 'new', 'Pratica aperta modificata.');
    check($connections['Target']->query('SELECT title FROM practices WHERE id = 1')->fetchColumn() === $prefix . '_Target', 'Collisione ID nella destinazione.');
    $connections['A']->exec("UPDATE practices SET title = 'Modifica originale' WHERE id = 1");
    check(api('GET', 'archive/sources/' . $source . '/practice/1', $admin)['data']['item']['name'] === 'Modifica originale', 'La condivisione non riflette la modifica originale.');
    api('PATCH', 'archive/sources/' . $source . '/practice/1', $admin, ['status' => 'closed'], 404);
    share($admin, $second, 'document', 1);
    check(count(api('GET', 'archive/shared?type=document', $admin)['data']['items']) === 4, 'ID uguali fra origini confusi.');
    $copy = api('POST', 'admin/configurations', $admin, ['name' => $prefix . '_Copy', 'mode' => 'copy'], 201)['data'];
    $created[] = (int)$copy['id'];
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $copy['id']]);
    check(count(api('GET', 'archive/shared?type=document', $admin)['data']['items']) === 4, 'Riferimenti persi nella copia esplicita della configurazione.');
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $initial['id']]);
    share($admin, $source, 'practice', 1);
    $items = api('GET', 'archive/shared?type=document', $reader)['data']['items'];
    $sourcePdf = $files->root($rows['A']) . '/public/documents/documenti/fixture-1.pdf';
    $otherPdf = $files->root($rows['B']) . '/public/documents/documenti/fixture-1.pdf';
    $verifyPath = 'archive/sources/' . $source . '/documents/1/verify-file';
    check(verifyUpload($verifyPath, $reader, $sourcePdf, str_repeat('A', 24))['valid'], 'Copia del documento originale non verificata.');
    check(!verifyUpload($verifyPath, $reader, $otherPdf, str_repeat('A', 24))['valid'], 'Copia da altra configurazione accettata con lo stesso ID.');
    check(!verifyUpload($verifyPath, $reader, $sourcePdf, 'BAD')['valid'], 'Firma errata accettata nella verifica copia.');
    api('POST', $verifyPath, $reader, ['sig' => str_repeat('A', 24)], 400);
    $visible = array_filter($items, static fn (array $item): bool => $item['source_configuration_id'] === $source);
    check(count($visible) === 2, 'ACL originale: documento riservato visibile o documento consentito assente.');
    api('GET', 'archive/sources/' . $source . '/documents/3/file', $reader, null, 403);
    $readerPractice = api('GET', 'archive/sources/' . $source . '/practice/1', $reader)['data'];
    check(count($readerPractice['documents']) === 2, 'Documenti riservati inclusi nei riferimenti della pratica.');
    check(api('GET', 'archive/sources/' . $source . '/documents/2/file', $reader)['body'] === '%PDF-1.4 ' . $prefix . '_A documento 2', 'Documento via protocollo non accessibile.');
    $protocols = api('GET', 'archive/protocols', $reader)['data']['items'];
    check(count(array_filter($protocols, static fn (array $item): bool => $item['source_configuration_id'] === $source && $item['protocol_number'] === 'IN-DOC-2026-0001')) === 1, 'Riferimento di protocollo non disponibile al delegato.');
    share($admin, $source, 'document', 1);
    share($admin, $source, 'practice', 1, false);
    api('GET', 'archive/sources/' . $source . '/practice/1', $reader, null, 403);
    api('GET', 'archive/sources/' . $source . '/documents/2/file', $reader, null, 403);
    $single = api('GET', 'archive/sources/' . $source . '/document/1', $reader)['data'];
    check(!isset($single['practice']) && !isset($single['notes']), 'Condivisione documento espone la pratica.');
    share($admin, $source, 'document', 1, false);
    api('GET', 'archive/sources/' . $source . '/documents/1/file', $reader, null, 403);
    verifyUpload($verifyPath, $reader, $sourcePdf, str_repeat('A', 24), 403);
    share($admin, $source, 'document', 3);
    api('GET', 'archive/sources/' . $source . '/documents/3/file', $reader, null, 403);
    share($admin, $source, 'document', 3, false);
    $sourceDb = $connections['A'];
    $sourceDb->prepare("INSERT INTO reports (tracking_code, subject, message, reply, document_id, status, created_at, updated_at) VALUES (?, 'Segnalazione collegata', 'Contenuto originale', 'Risposta originale', 1, 'approved', NOW(), NOW())")->execute([$prefix]);
    $sourceDb->prepare("INSERT INTO document_comments (document_id, subject, message, reply, status, created_at, updated_at) VALUES (1, 'Commento originale', 'Commento da conservare', 'Risposta commento', 'approved', NOW(), NOW())")->execute();
    $sourceDb->prepare("INSERT INTO practice_links (practice_id, entity_type, entity_id, created_by, created_at) VALUES (1, 'report', 1, ?, NOW()), (1, 'comment', 1, ?, NOW()), (2, 'report', 1, ?, NOW())")->execute([$adminId, $adminId, $adminId]);
    $sourceDb->prepare("INSERT INTO protocol_entries (protocol_number, direction, type_code, year, sequence, subject, created_by, created_at) VALUES ('IN-DOC-2026-0002', 'IN', 'DOC', 2026, 2, 'Protocollo senza documento', ?, NOW())")->execute([$adminId]);
    $sourceDb->prepare("INSERT INTO practice_links (practice_id, entity_type, entity_id, created_by, created_at) VALUES (1, 'protocol', 2, ?, NOW())")->execute([$adminId]);
    $attachmentRoot = $files->root($rows['A']) . '/storage/private/report-attachments';
    if (!is_dir($attachmentRoot)) mkdir($attachmentRoot, 0775, true);
    file_put_contents($attachmentRoot . '/fixture-attachment', 'allegato originale');
    $sourceDb->exec("INSERT INTO report_attachments (report_id, original_name, stored_name, mime_type, size_bytes, checksum_sha256, created_at) VALUES (1, 'immagine.png', 'fixture-attachment', 'image/png', 18, '" . hash('sha256', 'allegato originale') . "', NOW())");
    $originals = [];
    foreach (['practices', 'documents', 'document_comments', 'reports', 'report_attachments', 'practice_links', 'protocol_entries', 'practice_notes'] as $table) $originals[$table] = hash('sha256', json_encode($sourceDb->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll()));
    api('GET', 'admin/archive/practices/' . $source . '/1/transfer', null, null, 401);
    api('POST', 'admin/archive/practices/' . $source . '/1/transfer', $reader, ['confirm' => 'PRA-2026-0001'], 403);
    share($admin, $source, 'practice', 1);
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $target]);
    $preview = api('GET', 'admin/archive/practices/' . $source . '/1/transfer', $admin)['data'];
    check($preview['target']['id'] === $target && $preview['practice']['code'] === 'PRA-2026-0001', 'Destinazione anteprima non corretta.');
    api('POST', 'admin/archive/practices/' . $source . '/1/transfer', $admin, ['confirm' => 'errata'], 422);
    api('GET', 'admin/archive/practices/' . $target . '/1/transfer', $admin, null, 422);
    api('POST', 'admin/archive/practices/' . $source . '/1/transfer', $admin, ['confirm' => 'PRA-2026-0001'], 409, $source);
    $trigger = 'cfg_' . $target . '_transfer_rollback';
    $pdo->exec('CREATE TRIGGER `' . $trigger . '` BEFORE INSERT ON `' . $rows['Target']['table_prefix'] . "activity_logs` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Verifica rollback trasferimento'");
    try {
        api('POST', 'admin/archive/practices/' . $source . '/1/transfer', $admin, ['confirm' => 'PRA-2026-0001'], 500);
        check((int)$connections['Target']->query('SELECT COUNT(*) FROM practices')->fetchColumn() === 2, 'Rollback lascia una pratica parziale.');
        $statement = $pdo->prepare('SELECT COUNT(*) FROM practice_transfers WHERE source_configuration_id = ? AND source_practice_id = 1');
        $statement->execute([$source]);
        check((int)$statement->fetchColumn() === 0, 'Rollback lascia il trasferimento registrato.');
    } finally {
        $pdo->exec('DROP TRIGGER IF EXISTS `' . $trigger . '`');
    }
    $moved = api('POST', 'admin/archive/practices/' . $source . '/1/transfer', $admin, ['confirm' => 'PRA-2026-0001'])['data'];
    $backups[] = $moved['backup_name'];
    check($moved['code'] === 'PRA-2026-0003' && is_file($root . '/storage/private/backups/' . $moved['backup_name']), 'Collisione codice o backup non gestiti.');
    $practiceAfter = api('GET', 'practices/' . $moved['practice_id'], $admin)['data'];
    check($practiceAfter['practice']['title'] === 'Modifica originale' && $practiceAfter['practice']['status'] === 'new', 'Stato e titolo non preservati.');
    check(count(array_filter($practiceAfter['timeline'], static fn (array $item): bool => $item['type'] === 'report' && str_contains($item['summary'], 'Risposta originale'))) === 1, 'Segnalazione o risposta perse.');
    check(count(array_filter($practiceAfter['timeline'], static fn (array $item): bool => $item['type'] === 'comment')) === 1, 'Commento non mantenuto nel fascicolo.');
    check(count(array_filter($practiceAfter['timeline'], static fn (array $item): bool => $item['type'] === 'note')) === 1, 'Note precedenti perse.');
    check((int)$connections['Target']->query('SELECT COUNT(*) FROM documents')->fetchColumn() === 3, 'Spostamento ha copiato documenti.');
    api('POST', 'practices/' . $moved['practice_id'] . '/notes', $admin, ['body' => 'Nota nella destinazione'], 201);
    api('PATCH', 'practices/' . $moved['practice_id'], $admin, ['summary' => 'Gestione nella destinazione']);
    $document = api('GET', 'archive/sources/' . $source . '/document/1', $reader)['data'];
    check(count($document['comments']) === 1 && $document['comments'][0]['reply'] === 'Risposta commento', 'Commenti del documento non accessibili.');
    check(api('GET', 'archive/sources/' . $source . '/documents/1/file', $reader)['body'] === '%PDF-1.4 ' . $prefix . '_A documento 1', 'Accesso al documento dopo spostamento perso.');
    api('GET', 'archive/sources/' . $source . '/documents/3/file', $reader, null, 403);
    check(api('GET', 'archive/sources/' . $source . '/attachments/1/file', $reader)['body'] === 'allegato originale', 'Allegato della segnalazione perso.');
    $protocolReferences = api('GET', 'archive/protocols', $reader)['data']['items'];
    check(count(array_filter($protocolReferences, static fn (array $item): bool => $item['source_configuration_id'] === $source)) === 2, 'Riferimenti di protocollo incompleti dopo il trasferimento.');
    $sharedAfter = api('GET', 'archive/shared?type=practice', $reader)['data']['items'];
    check(count(array_filter($sharedAfter, static fn (array $item): bool => $item['source_configuration_id'] === $target && $item['id'] === $moved['practice_id'])) === 1, 'Condivisioni non aggiornate alla nuova origine.');
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $source]);
    api('GET', 'practices/1', $admin, null, 404);
    api('PATCH', 'practices/1', $admin, ['title' => 'Tentativo su traccia'], 404);
    check(count(api('GET', 'practices?scope=all', $admin)['data']) === 1, 'Pratica ancora operativa nell’origine.');
    api('GET', 'practices/2', $admin);
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $second]);
    $movedAgain = api('POST', 'admin/archive/practices/' . $target . '/' . $moved['practice_id'] . '/transfer', $admin, ['confirm' => $moved['code']])['data'];
    $backups[] = $movedAgain['backup_name'];
    $again = api('GET', 'practices/' . $movedAgain['practice_id'], $admin)['data'];
    check(count(array_filter($again['timeline'], static fn (array $item): bool => $item['type'] === 'note')) === 2, 'Secondo spostamento perde o duplica note.');
    check($again['practice']['summary'] === 'Gestione nella destinazione', 'Modifiche intermedie non mantenute.');
    $snapshot = api('POST', 'admin/configurations', $admin, ['name' => $prefix . '_TransferredCopy', 'mode' => 'copy'], 201)['data'];
    $created[] = (int)$snapshot['id'];
    api('POST', 'admin/configurations/selection', $admin, ['configuration_id' => $snapshot['id']]);
    $snapshotPractice = api('GET', 'practices/' . $movedAgain['practice_id'], $admin)['data'];
    check(count(array_filter($snapshotPractice['timeline'], static fn (array $item): bool => $item['type'] === 'note')) === 2, 'Copia della configurazione perde il fascicolo trasferito.');
    check(api('GET', 'archive/sources/' . $source . '/attachments/1/file', $reader)['body'] === 'allegato originale', 'Secondo spostamento interrompe gli allegati.');
    foreach ($originals as $table => $hash) check(hash('sha256', json_encode($sourceDb->query('SELECT * FROM ' . $table . ' ORDER BY id')->fetchAll())) === $hash, 'Spostamento ha modificato gli originali: ' . $table);
    api('POST', 'admin/archive/practices/' . $source . '/1/transfer', $admin, ['confirm' => 'PRA-2026-0001'], 404);
    check((int)$repository->operational()['id'] === (int)$initial['id'], 'Configurazione operativa modificata.');
    echo 'OK: ' . $checks . ' verifiche condivisioni e spostamenti, rollback, ACL originali, fascicolo completo e documenti non copiati.' . PHP_EOL;
} finally {
    foreach ($tokens as $token) {
        $repository->select($token, null);
        $pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    }
    foreach ($created as $id) {
        $pdo->prepare('DELETE FROM practice_archive_links WHERE configuration_id = ? OR source_configuration_id = ?')->execute([$id, $id]);
        $pdo->prepare('DELETE FROM practice_transfers WHERE source_configuration_id = ? OR target_configuration_id = ?')->execute([$id, $id]);
        $pdo->prepare('DELETE FROM archive_shares WHERE source_configuration_id = ? OR target_configuration_id = ?')->execute([$id, $id]);
        foreach ($repository->all() as $configuration) {
            $pdo->prepare('DELETE FROM `' . $configuration['table_prefix'] . 'activity_logs` WHERE JSON_UNQUOTE(JSON_EXTRACT(metadata_json, ?)) = ?')->execute(['$.source_configuration_id', (string)$id]);
            $pdo->prepare('DELETE FROM `' . $configuration['table_prefix'] . 'activity_logs` WHERE user_id = ? AND action LIKE ? AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json, ?)) = ?')->execute([$adminId ?? 0, 'configurations.%', '$.configuration_id', (string)$id]);
        }
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
    foreach ($backups as $name) {
        if (preg_match('/^practice_transfer_[0-9]{8}_[0-9]{6}_[a-f0-9]{12}\.sql$/D', $name)) unlink($root . '/storage/private/backups/' . $name);
    }
    foreach ($hashes as $table => $hash) check(hash('sha256', json_encode($pdo->query('SELECT * FROM `' . $table . '` ORDER BY 1' . (in_array($table, ['users', 'roles', 'permissions', 'role_user', 'permission_role', 'gdpr_consents', 'institutional_contacts', 'archive_shares', 'configuration_sessions'], true) ? ', 2' : ''))->fetchAll())) === $hash, 'Dati esistenti alterati: ' . $table);
}
