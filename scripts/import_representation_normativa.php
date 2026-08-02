<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\NormativaTextCleaner;

require __DIR__ . '/../app/Core/helpers.php';
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Services/NormativaTextCleaner.php';
require __DIR__ . '/../vendor/autoload.php';

$basePath = dirname(__DIR__);
$config = require $basePath . '/config/database.php';
$pdo = (new Database($config))->pdo();

$normalize = static fn (string $text): string => trim(preg_replace('/\s+/u', ' ', mb_strtolower($text, 'UTF-8')) ?? $text);
$slug = static fn (string $text): string => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text)) ?? '', '-');
$clean = static fn (string $text): string => NormativaTextCleaner::clean($text);

$splitSections = static function (string $text) use ($clean): array {
    $text = $clean($text);
    $items = [];
    $currentTitle = null;
    $currentLines = [];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $trimmed = trim($line);
        $isHeading = preg_match('/^##\s+.+/u', $trimmed)
            || preg_match('/^\d{1,2}\.\s+\S.+/u', $trimmed)
            || preg_match('/^MODULO PER LA RACCOLTA DELLE FIRME/u', $trimmed);
        if ($isHeading) {
            if ($currentTitle !== null && trim(implode("\n", $currentLines)) !== '') {
                $items[] = [$currentTitle, trim(implode("\n", $currentLines))];
            }
            $currentTitle = mb_substr(preg_replace('/^##\s+/u', '', $trimmed) ?? $trimmed, 0, 240, 'UTF-8');
            $currentLines = [$trimmed];
            continue;
        }
        if ($currentTitle === null && $trimmed !== '' && !str_starts_with($trimmed, '#')) {
            $currentTitle = 'Premessa';
        }
        if ($currentTitle !== null && $trimmed !== '') {
            $currentLines[] = $line;
        }
    }
    if ($currentTitle !== null && trim(implode("\n", $currentLines)) !== '') {
        $items[] = [$currentTitle, trim(implode("\n", $currentLines))];
    }
    return $items;
};

$sources = [
    ['01', 'Testo coordinato operativo rappresentanza', 'coordinato', 'vigente', $basePath . '/docs/representation_work/clean/01_TESTO_COORDINATO_OPERATIVO_RAPPRESENTANZA_LUGLIO_2026.md'],
    ['02', 'Allegato 3 RSU Metalmeccanici 2017', 'fonte_settoriale', 'vigente', $basePath . '/docs/representation_work/clean/02_ALLEGATO_3_RSU_METALMECCANICI_2017.md'],
];

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM normative_documenti WHERE titolo_breve = 'TU-RAPPRESENTANZA'");
    $document = $pdo->prepare("INSERT INTO normative_documenti
        (titolo, titolo_breve, tipo_documento, ente_emittente, stato_vigenza, versione, descrizione, stato_importazione, needs_review, created_at)
        VALUES (?, 'TU-RAPPRESENTANZA', 'representation', 'Parti sociali / settore metalmeccanico', 'vigente', '2026', ?, 'completata', 1, NOW())");
    $document->execute(['Testo Unico Rappresentanza - coordinato operativo', 'Importazione catena rappresentanza: TU, accordi interconfederali e Allegato 3 metalmeccanici.']);
    $documentId = (int)$pdo->lastInsertId();

    $nodeInsert = $pdo->prepare('INSERT INTO normative_nodi (documento_id, tipo_nodo, codice, titolo, ordinamento, livello) VALUES (?, ?, ?, ?, ?, ?)');
    $articleInsert = $pdo->prepare('INSERT INTO normative_articoli (documento_base_id, nodo_id, numero, numero_normalizzato, rubrica, slug, ordinamento, articolo_logico_key, stato) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $versionInsert = $pdo->prepare('INSERT INTO normative_articoli_versioni (articolo_id, documento_fonte_id, versione, testo_integrale, testo_normalizzato, stato_vigenza, tipo_modifica, hash_testo, needs_review, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
    $unitInsert = $pdo->prepare('INSERT INTO normative_unita_testuali (versione_articolo_id, tipo_unita, testo, testo_normalizzato, ordinamento, livello, anchor, hash_testo) VALUES (?, ?, ?, ?, ?, 0, ?, ?)');
    $indexInsert = $pdo->prepare('INSERT INTO normative_search_index (entity_type, entity_id, documento_id, titolo, contenuto, contenuto_normalizzato, metadati_json, stato_vigenza) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    $order = 1;
    $total = 0;
    foreach ($sources as [$code, $title, $type, $state, $path]) {
        $text = $clean((string)file_get_contents($path));
        $nodeInsert->execute([$documentId, 'titolo', $code, $title, $order, 1]);
        $nodeId = (int)$pdo->lastInsertId();
        foreach ($splitSections($text) as $index => [$sectionTitle, $sectionText]) {
            $key = 'representation-' . $code . '-' . ($index + 1);
            $articleInsert->execute([$documentId, $nodeId, (string)($index + 1), (string)($index + 1), $sectionTitle, $slug($sectionTitle . '-' . $key), $order++, $key, 'vigente']);
            $articleId = (int)$pdo->lastInsertId();
            $versionInsert->execute([$articleId, $documentId, '2026', $sectionText, $normalize($sectionText), $state, $type, hash('sha256', $sectionText)]);
            $versionId = (int)$pdo->lastInsertId();
            $unitInsert->execute([$versionId, 'sezione', $sectionText, $normalize($sectionText), $index + 1, $key, hash('sha256', $sectionText)]);
            $unitId = (int)$pdo->lastInsertId();
            $indexInsert->execute(['unita_testuale', $unitId, $documentId, $sectionTitle, $sectionText, $normalize($sectionTitle . ' ' . $sectionText), json_encode(['ambito' => 'representation', 'block_code' => $code, 'block_title' => $title, 'hierarchy_label' => $title, 'article_id' => $articleId, 'version_id' => $versionId], JSON_UNESCAPED_UNICODE), $state]);
            $total++;
        }
    }

    $pdo->prepare("INSERT INTO normative_importazioni (documento_id, stato, fase, avanzamento_percentuale, elementi_totali, elementi_elaborati, messaggio, risultato_json, iniziata_il, completata_il, created_at)
        VALUES (?, 'completata', 'migrazione_rappresentanza', 100, ?, ?, 'Importazione rappresentanza completata', ?, NOW(), NOW(), NOW())")
        ->execute([$documentId, $total, $total, json_encode(['sources' => array_column($sources, 1)], JSON_UNESCAPED_UNICODE)]);
    $pdo->commit();
    echo 'Importazione rappresentanza completata: ' . $total . ' sezioni' . PHP_EOL;
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
