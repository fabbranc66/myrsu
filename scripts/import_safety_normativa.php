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

$split = static function (string $text, string $mode) use ($clean): array {
    $text = $clean($text);
    $pattern = $mode === 'articles'
        ? '/^(Articolo\s+\d+(?:-[a-z]+)?\s+-\s+.+|TITOLO\s+[IVXLC]+(?:\s+-\s+.+)?|CAPO\s+[IVXLC]+(?:\s+-\s+.+)?)$/u'
        : '/^##\s+.+$/u';
    $items = [];
    $title = null;
    $lines = [];
    $contextTitle = '';
    $contextChapter = '';
    $currentContext = ['title' => '', 'chapter' => ''];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $trimmed = trim($line);
        if (preg_match($pattern, $trimmed)) {
            if ($mode === 'articles' && preg_match('/^TITOLO\s+/u', $trimmed)) {
                $contextTitle = $trimmed;
                continue;
            }
            if ($mode === 'articles' && preg_match('/^CAPO\s+/u', $trimmed)) {
                $contextChapter = $trimmed;
                continue;
            }
            if ($title !== null && trim(implode("\n", $lines)) !== '') $items[] = [$title, trim(implode("\n", $lines)), $currentContext];
            $title = mb_substr(preg_replace('/^##\s+/u', '', $trimmed) ?? $trimmed, 0, 240, 'UTF-8');
            $lines = [$trimmed];
            $currentContext = ['title' => $contextTitle, 'chapter' => $contextChapter];
            continue;
        }
        if ($title === null && $trimmed !== '' && !str_starts_with($trimmed, '#')) $title = 'Premessa';
        if ($title !== null && $trimmed !== '') $lines[] = $line;
    }
    if ($title !== null && trim(implode("\n", $lines)) !== '') $items[] = [$title, trim(implode("\n", $lines)), $currentContext];
    return $items;
};

$sources = [
    ['00', 'Indice operativo sicurezza 81/08', 'indice', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/00_INDICE_SICUREZZA_81_08.md'],
    ['03', 'Blocco operativo RSU/RLS sicurezza 81/08', 'operativo', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/03_BLOCCO_OPERATIVO_RSU_RLS_SICUREZZA_81_08.md'],
    ['04', 'Mappa articoli e temi sicurezza 81/08', 'mappa', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/04_MAPPA_ARTICOLI_TEMI_SICUREZZA_81_08.md'],
    ['05', 'RLS, consultazione e accesso documenti', 'operativo', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/05_BLOCCO_RLS_CONSULTAZIONE_ACCESSO_DOCUMENTI.md'],
    ['06', 'DVR e valutazione dei rischi', 'operativo', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/06_BLOCCO_DVR_VALUTAZIONE_RISCHI.md'],
    ['07', 'Formazione, informazione e addestramento', 'operativo', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/07_BLOCCO_FORMAZIONE_INFORMAZIONE_ADDESTRAMENTO.md'],
    ['08', 'Preposto, obblighi e responsabilità', 'operativo', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/08_BLOCCO_PREPOSTO_OBBLIGHI_RESPONSABILITA.md'],
    ['09', 'Appalti, DUVRI e interferenze', 'operativo', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/09_BLOCCO_APPALTI_DUVRI_INTERFERENZE.md'],
    ['10', 'Sorveglianza sanitaria e medico competente', 'operativo', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/10_BLOCCO_SORVEGLIANZA_SANITARIA_MEDICO_COMPETENTE.md'],
    ['11', 'Vigilanza, sospensione e organi di controllo', 'operativo', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/11_BLOCCO_VIGILANZA_SOSPENSIONE_ORGANI_CONTROLLO.md'],
    ['12', 'Emergenze, antincendio e primo soccorso', 'operativo', 'vigente', 'sections', $basePath . '/docs/safety_work/clean/12_BLOCCO_EMERGENZE_ANTINCENDIO_PRIMO_SOCCORSO.md'],
    ['15', 'ISO 45001 - sicurezza lavoro', 'tecnica', 'collegato', 'sections', $basePath . '/docs/safety_work/clean/15_ISO_45001_SICUREZZA_LAVORO.md'],
    ['16', 'ISO 14001 - ambiente RSU', 'tecnica', 'collegato', 'sections', $basePath . '/docs/safety_work/clean/16_ISO_14001_AMBIENTE_RSU.md'],
    ['17', 'Mappa ISO - 81/08', 'tecnica', 'collegato', 'sections', $basePath . '/docs/safety_work/clean/17_MAPPA_ISO_81_08.md'],
    ['99', 'D.Lgs. 81/2008 - testo vigente pulito', 'testo_vigente', 'vigente', 'articles', $basePath . '/docs/safety_work/clean/01_D_LGS_81_2008_TESTO_VIGENTE_PULITO.md'],
];

$pdo->beginTransaction();
try {
    $pdo->exec("DELETE FROM normative_documenti WHERE titolo_breve = 'SICUREZZA-81-08'");
    $document = $pdo->prepare("INSERT INTO normative_documenti
        (titolo, titolo_breve, tipo_documento, ente_emittente, stato_vigenza, versione, descrizione, stato_importazione, needs_review, created_at)
        VALUES (?, 'SICUREZZA-81-08', 'safety', 'INL / normativa sicurezza lavoro', 'vigente', '2026', ?, 'completata', 1, NOW())");
    $document->execute(['Sicurezza lavoro - D.Lgs. 81/2008 coordinato operativo', 'Catena 626/1994, 81/2008, 106/2009, D.L. 146/2021, L. 215/2021 e aggiornamenti successivi. Accordo Stato-Regioni escluso.']);
    $documentId = (int)$pdo->lastInsertId();

    $nodeInsert = $pdo->prepare('INSERT INTO normative_nodi (documento_id, tipo_nodo, codice, titolo, ordinamento, livello) VALUES (?, ?, ?, ?, ?, ?)');
    $articleInsert = $pdo->prepare('INSERT INTO normative_articoli (documento_base_id, nodo_id, numero, numero_normalizzato, rubrica, slug, ordinamento, articolo_logico_key, stato) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $versionInsert = $pdo->prepare('INSERT INTO normative_articoli_versioni (articolo_id, documento_fonte_id, versione, testo_integrale, testo_normalizzato, stato_vigenza, tipo_modifica, hash_testo, needs_review, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
    $unitInsert = $pdo->prepare('INSERT INTO normative_unita_testuali (versione_articolo_id, tipo_unita, testo, testo_normalizzato, ordinamento, livello, anchor, hash_testo) VALUES (?, ?, ?, ?, ?, 0, ?, ?)');
    $indexInsert = $pdo->prepare('INSERT INTO normative_search_index (entity_type, entity_id, documento_id, titolo, contenuto, contenuto_normalizzato, metadati_json, stato_vigenza) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    $order = 1;
    $total = 0;
    foreach ($sources as [$code, $title, $type, $state, $mode, $path]) {
        $text = $clean((string)file_get_contents($path));
        $nodeInsert->execute([$documentId, $type, $code, $title, $order, 1]);
        $nodeId = (int)$pdo->lastInsertId();
        foreach ($split($text, $mode) as $index => [$sectionTitle, $sectionText, $context]) {
            $key = 'safety-' . $code . '-' . ($index + 1);
            $articleInsert->execute([$documentId, $nodeId, (string)($index + 1), (string)($index + 1), $sectionTitle, $slug($sectionTitle . '-' . $key), $order++, $key, 'vigente']);
            $articleId = (int)$pdo->lastInsertId();
            $versionInsert->execute([$articleId, $documentId, '2026', $sectionText, $normalize($sectionText), $state, $type, hash('sha256', $sectionText)]);
            $versionId = (int)$pdo->lastInsertId();
            $unitInsert->execute([$versionId, $mode === 'articles' ? 'articolo' : 'sezione', $sectionText, $normalize($sectionText), $index + 1, $key, hash('sha256', $sectionText)]);
            $unitId = (int)$pdo->lastInsertId();
            $hierarchy = implode(' - ', array_values(array_unique(array_filter([$title, $context['title'] ?? '', $context['chapter'] ?? '']))));
            $indexInsert->execute(['unita_testuale', $unitId, $documentId, $sectionTitle, $sectionText, $normalize($sectionTitle . ' ' . $sectionText), json_encode(['ambito' => 'safety', 'block_code' => $code, 'block_title' => $title, 'context_title' => $context['title'] ?? '', 'context_chapter' => $context['chapter'] ?? '', 'hierarchy_label' => $hierarchy, 'article_id' => $articleId, 'version_id' => $versionId], JSON_UNESCAPED_UNICODE), $state]);
            $total++;
        }
    }

    $pdo->prepare("INSERT INTO normative_importazioni (documento_id, stato, fase, avanzamento_percentuale, elementi_totali, elementi_elaborati, messaggio, risultato_json, iniziata_il, completata_il, created_at)
        VALUES (?, 'completata', 'migrazione_safety', 100, ?, ?, 'Importazione sicurezza 81/08 completata', ?, NOW(), NOW(), NOW())")
        ->execute([$documentId, $total, $total, json_encode(['excluded' => ['Accordo Stato-Regioni formazione']], JSON_UNESCAPED_UNICODE)]);
    $pdo->commit();
    echo 'Importazione sicurezza 81/08 completata: ' . $total . ' sezioni' . PHP_EOL;
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
