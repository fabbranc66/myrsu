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

$splitReliableFullText = static function (string $text) use ($clean): array {
    $text = $clean($text);
    $items = [];
    $currentTitle = null;
    $currentLines = [];
    $skip = false;
    $headings = '/^(PREMESSA|SEZIONE\s+[A-ZÀ-Ù]+(?:\s+.*)?|Titolo\s+[IVXLC]+(?:\s+-\s+.*)?|Art\.\s*\d+\.?\s*-\s+.*|DICHIARAZIONE(?:\s+[A-ZÀ-Ù]+)?(?:\s+.*)?|ALLEGATO\s+.*|MODULO PER LA RACCOLTA DELLE FIRME.*)$/u';

    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $trimmed = trim($line);
        if (preg_match($headings, $trimmed)) {
            if ($currentTitle !== null && !$skip) {
                $body = trim(implode("\n", $currentLines));
                if ($body !== '') $items[] = [$currentTitle, $body];
            }
            $currentTitle = mb_substr($trimmed, 0, 240, 'UTF-8');
            $currentLines = [$trimmed];
            $skip = str_contains(mb_strtolower($trimmed, 'UTF-8'), 'esemplificazione profili');
            continue;
        }

        if ($currentTitle === null) {
            continue;
        }

        if (preg_match('/^\d+\s+Sez\./u', $trimmed)) {
            continue;
        }

        if (preg_match('/^ESEMPLIFICAZIONE PROFILI/u', $trimmed)) {
            $skip = true;
            continue;
        }

        $currentLines[] = $line;
    }

    if ($currentTitle !== null && !$skip) {
        $body = trim(implode("\n", $currentLines));
        if ($body !== '') $items[] = [$currentTitle, $body];
    }

    return $items;
};

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM normative_documenti WHERE titolo_breve = 'CCNL-METALMECCANICI'");
    $stmt = $pdo->prepare("INSERT INTO normative_documenti
        (titolo, titolo_breve, tipo_documento, ente_emittente, stato_vigenza, versione, descrizione, stato_importazione, needs_review, created_at)
        VALUES (?, 'CCNL-METALMECCANICI', 'ccnl', 'Federmeccanica/Assistal e OO.SS.', 'da_verificare', '2021', ?, 'completata', 1, NOW())");
    $stmt->execute(['CCNL Metalmeccanici coordinato 2021-2025-2026', 'Importazione da blocchi consolidati 2021-2025-2026 e testo completo storico 2021.']);
    $documentId = (int)$pdo->lastInsertId();

    $job = $pdo->prepare("INSERT INTO normative_importazioni
        (documento_id, stato, fase, avanzamento_percentuale, messaggio, iniziata_il, completata_il, created_at)
        VALUES (?, 'completata', 'migrazione_ccnl_conservativa', 100, 'Importazione CCNL conservativa', NOW(), NOW(), NOW())");
    $job->execute([$documentId]);
    $importId = (int)$pdo->lastInsertId();

    $nodeInsert = $pdo->prepare('INSERT INTO normative_nodi (documento_id, tipo_nodo, codice, titolo, ordinamento, livello) VALUES (?, ?, ?, ?, ?, ?)');
    $articleInsert = $pdo->prepare('INSERT INTO normative_articoli (documento_base_id, nodo_id, numero, numero_normalizzato, rubrica, slug, ordinamento, articolo_logico_key, stato) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $versionInsert = $pdo->prepare('INSERT INTO normative_articoli_versioni (articolo_id, documento_fonte_id, versione, testo_integrale, testo_normalizzato, stato_vigenza, tipo_modifica, hash_testo, needs_review, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
    $unitInsert = $pdo->prepare('INSERT INTO normative_unita_testuali (versione_articolo_id, tipo_unita, testo, testo_normalizzato, ordinamento, livello, anchor, hash_testo) VALUES (?, ?, ?, ?, ?, 0, ?, ?)');
    $indexInsert = $pdo->prepare('INSERT INTO normative_search_index (entity_type, entity_id, documento_id, titolo, contenuto, contenuto_normalizzato, metadati_json, stato_vigenza) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    $articleOrder = 1;
    $blockFiles = glob($basePath . '/docs/ccnl_work/clean/[0-9][0-9]_blocco_*.md') ?: [];
    sort($blockFiles);

    foreach ($blockFiles as $blockIndex => $blockPath) {
        $blockCode = substr(basename($blockPath), 0, 2);
        $blockText = trim($clean((string)file_get_contents($blockPath)));
        $blockTitle = trim(preg_replace('/^#\s*/u', '', strtok($blockText, "\n")) ?: basename($blockPath));
        $nodeInsert->execute([$documentId, 'titolo', $blockCode, mb_substr($blockTitle, 0, 240, 'UTF-8'), $blockIndex + 1, 1]);
        $nodeId = (int)$pdo->lastInsertId();
        $key = 'ccnl-coordinato-' . $blockCode;
        $articleInsert->execute([$documentId, $nodeId, $blockCode, $blockCode, $blockTitle, $slug($blockTitle . '-' . $blockCode), $articleOrder++, $key, 'vigente']);
        $articleId = (int)$pdo->lastInsertId();
        $versionInsert->execute([$articleId, $documentId, '2021-2025-2026', $blockText, $normalize($blockText), 'vigente', 'coordinato', hash('sha256', $blockText)]);
        $versionId = (int)$pdo->lastInsertId();

        foreach (preg_split('/\n(?=##\s+)/u', $blockText) ?: [] as $unitIndex => $unitText) {
            $unitText = trim($unitText);
            if ($unitText === '') continue;
            $unitTitle = preg_match('/^##\s+(.+)$/m', $unitText, $matches) ? $matches[1] : $blockTitle;
            $unitInsert->execute([$versionId, 'sezione', $unitText, $normalize($unitText), $unitIndex + 1, $key . '-' . ($unitIndex + 1), hash('sha256', $unitText)]);
            $unitId = (int)$pdo->lastInsertId();
            $indexInsert->execute(['unita_testuale', $unitId, $documentId, $unitTitle, $unitText, $normalize($unitTitle . ' ' . $unitText), json_encode(['ambito' => 'ccnl', 'block_code' => $blockCode, 'block_title' => $blockTitle, 'hierarchy_label' => $blockTitle, 'article_id' => $articleId, 'version_id' => $versionId], JSON_UNESCAPED_UNICODE), 'vigente']);
        }
    }

    $nodeInsert->execute([$documentId, 'appendice', '99', 'Testo completo storico CCNL 2021', 99, 1]);
    $nodeId = (int)$pdo->lastInsertId();
    $sourcePath = $basePath . '/docs/ccnl_work/clean/ccnl_2021_testo_completo_ricercabile.md';
    $sections = $splitReliableFullText((string)file_get_contents($sourcePath));

    foreach ($sections as $sectionIndex => [$sectionTitle, $sectionText]) {
        $key = 'ccnl-metalmeccanici-2021-' . ($sectionIndex + 1);
        $articleInsert->execute([$documentId, $nodeId, (string)($sectionIndex + 1), (string)($sectionIndex + 1), $sectionTitle, $slug($sectionTitle . '-' . $sectionIndex), $articleOrder++, $key, 'vigente']);
        $articleId = (int)$pdo->lastInsertId();
        $versionInsert->execute([$articleId, $documentId, '2021', $sectionText, $normalize($sectionText), 'da_verificare', 'originario', hash('sha256', $sectionText)]);
        $versionId = (int)$pdo->lastInsertId();

        foreach (preg_split('/\n\s*\n/u', $sectionText) ?: [] as $unitIndex => $unitText) {
            $unitText = trim($unitText);
            if ($unitText === '') continue;
            $unitInsert->execute([$versionId, 'capoverso', $unitText, $normalize($unitText), $unitIndex + 1, $key . '-' . ($unitIndex + 1), hash('sha256', $unitText)]);
            $unitId = (int)$pdo->lastInsertId();
            $indexInsert->execute(['unita_testuale', $unitId, $documentId, $sectionTitle, $unitText, $normalize($sectionTitle . ' ' . $unitText), json_encode(['ambito' => 'ccnl', 'block_code' => '99', 'block_title' => 'Testo completo storico CCNL 2021', 'hierarchy_label' => 'Testo completo storico CCNL 2021', 'article_id' => $articleId, 'version_id' => $versionId], JSON_UNESCAPED_UNICODE), 'storico']);
        }
    }

    $pdo->prepare('UPDATE normative_importazioni SET elementi_totali = ?, elementi_elaborati = ?, risultato_json = ? WHERE id = ?')
        ->execute([count($sections) + count($blockFiles), $articleOrder - 1, json_encode(['primary' => 'blocchi consolidati 2021-2025-2026', 'appendix' => 'testo completo storico 2021'], JSON_UNESCAPED_UNICODE), $importId]);
    $pdo->commit();
    echo 'Importazione CCNL coordinata completata: ' . ($articleOrder - 1) . ' sezioni' . PHP_EOL;
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
