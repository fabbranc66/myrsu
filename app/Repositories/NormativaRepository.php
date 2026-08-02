<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class NormativaRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function search(string $query, string $scope = 'ccnl', int $limit = 20, int $offset = 0): array
    {
        $query = $this->normalizeSearchText($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $termGroups = $this->terms($query);
        $literalTerms = $this->literalTerms($query);
        $searchParts = [];
        $params = [];

        foreach ($termGroups as $group) {
            foreach ($group as $term) {
                $searchParts[] = 'si.contenuto_normalizzato REGEXP ?';
                $regex = $this->wordRegex($term);
                $params[] = $regex;
            }
        }

        $sql = "SELECT si.*, d.titolo document_title, d.tipo_documento document_type, a.ordinamento document_order,
                       JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.block_code')) block_code,
                       JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.block_title')) block_title,
                       JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.context_title')) context_title,
                       JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.context_chapter')) context_chapter,
                       JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.hierarchy_label')) hierarchy_label,
                       JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.article_id')) article_id,
                       JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.version_id')) version_id,
                       si.entity_id article_unit_id
                FROM normative_search_index si
                JOIN normative_documenti d ON d.id = si.documento_id
                LEFT JOIN normative_articoli a ON a.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.article_id')) AS UNSIGNED)
                WHERE 1 = 1";
        if ($scope !== 'all') {
            $sql .= ' AND d.tipo_documento = ?';
            $params = [$scope, ...$params];
        }

        if ($searchParts !== []) {
            $sql .= ' AND (' . implode(' OR ', $searchParts) . ')';
        }

        $fetchLimit = $scope === 'all' ? $limit * 200 : $limit * 20;
        $sql .= ' ORDER BY si.updated_at DESC, si.id ASC LIMIT ' . $fetchLimit . ' OFFSET ' . $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_slice($this->groupResults($stmt->fetchAll(), $termGroups, $literalTerms, $query), 0, $limit);
    }

    public function meaningfulTermCount(string $query): int
    {
        return count($this->literalTerms($query));
    }

    public function unit(int $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.*, av.articolo_id, a.rubrica, a.numero, d.titolo document_title,
                    JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.block_code')) block_code,
                    JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.block_title')) block_title,
                    JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.context_title')) context_title,
                    JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.context_chapter')) context_chapter,
                    JSON_UNQUOTE(JSON_EXTRACT(si.metadati_json, '$.hierarchy_label')) hierarchy_label
             FROM normative_unita_testuali u
             JOIN normative_articoli_versioni av ON av.id = u.versione_articolo_id
             JOIN normative_articoli a ON a.id = av.articolo_id
             JOIN normative_documenti d ON d.id = av.documento_fonte_id
             LEFT JOIN normative_search_index si ON si.entity_type = 'unita_testuale' AND si.entity_id = u.id
             WHERE u.id = ?"
        );
        $stmt->execute([$id]);
        $unit = $stmt->fetch() ?: [];
        if ($unit !== []) {
            $unit['hierarchy_label'] = $this->dbHierarchyLabel($unit);
        }
        return $unit;
    }

    private function formatResult(array $row, array $termGroups): array
    {
        $terms = array_merge(...$termGroups);
        return [
            'id' => (int)$row['entity_id'],
            'entity_type' => $row['entity_type'],
            'document_title' => $row['document_title'],
            'document_type' => $row['document_type'],
            'block_code' => $row['block_code'],
            'block_title' => $row['block_title'],
            'section_title' => $this->safeTitle($row),
            'context' => $this->context($row),
            'context_label' => $this->contextLabel($row),
            'hierarchy_label' => $this->dbHierarchyLabel($row),
            'excerpt' => $this->excerpt($this->contentWithoutHeading($row), $terms),
            'stato_vigenza' => $row['stato_vigenza'],
            'article_id' => isset($row['article_id']) ? (int)$row['article_id'] : null,
            'version_id' => isset($row['version_id']) ? (int)$row['version_id'] : null,
            'article_unit_id' => isset($row['article_unit_id']) ? (int)$row['article_unit_id'] : (int)$row['entity_id'],
            'document_order' => isset($row['document_order']) ? (int)$row['document_order'] : 0,
        ];
    }

    private function context(array $row): array
    {
        return [
            'document' => (string)$row['document_title'],
            'block' => (string)($row['block_title'] ?? ''),
            'title' => (string)($row['context_title'] ?? ''),
            'chapter' => (string)($row['context_chapter'] ?? ''),
            'section' => $this->safeTitle($row),
        ];
    }

    private function contextLabel(array $row): string
    {
        $parts = array_filter([
            $row['block_title'] ?? '',
            $row['context_title'] ?? '',
            $row['context_chapter'] ?? '',
            $this->safeTitle($row),
        ], static fn ($part): bool => trim((string)$part) !== '');

        return implode(' > ', array_values(array_unique(array_map('strval', $parts))));
    }

    private function hierarchyLabel(array $row): string
    {
        $parts = array_filter([
            $row['block_title'] ?? '',
            $row['context_title'] ?? '',
            $row['context_chapter'] ?? '',
        ], static fn ($part): bool => trim((string)$part) !== '');

        return implode(' - ', array_values(array_unique(array_map('strval', $parts))));
    }

    private function dbHierarchyLabel(array $row): string
    {
        $label = trim((string)($row['hierarchy_label'] ?? ''));
        return $label !== '' ? $label : $this->hierarchyLabel($row);
    }

    private function safeTitle(array $row): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', (string)$row['titolo']) ?? (string)$row['titolo']);
        if ($title === '' || mb_strlen($title, 'UTF-8') > 160 || preg_match('/^\d+[.)]\s+.{80,}/u', $title) === 1) {
            $fallback = trim((string)($row['context_chapter'] ?: $row['block_title'] ?: $row['document_title']));
            return $fallback !== '' ? $fallback : 'Riferimento normativa';
        }
        return $title;
    }

    private function groupResults(array $rows, array $termGroups, array $literalTerms, string $query): array
    {
        $groups = [];
        foreach ($rows as $row) {
            if (!$this->hasSearchableContent($row) || !$this->matchesContentTerms($row, $termGroups)) {
                continue;
            }

            $key = ($row['block_code'] === '99')
                ? 'full:' . (string)$row['titolo']
                : 'article:' . (string)($row['article_id'] ?: $row['titolo']);
            if (!isset($groups[$key])) {
                $groups[$key] = $this->formatResult($row, $termGroups);
                $groups[$key]['match_count'] = 0;
                $groups[$key]['matched_groups'] = [];
                $groups[$key]['literal_groups'] = [];
                $groups[$key]['rank_score'] = 0;
                $groups[$key]['first_position'] = PHP_INT_MAX;
                $groups[$key]['source_priority'] = $this->sourcePriority($row);
                $groups[$key]['matches'] = [];
            }
            $groups[$key]['match_count']++;
            $groups[$key]['rank_score'] = max($groups[$key]['rank_score'], $this->rankScore($row, $termGroups, $literalTerms, $query));
            $groups[$key]['first_position'] = min($groups[$key]['first_position'], $this->firstMatchPosition($row, $literalTerms));
            foreach ($this->matchedLiteralTerms($row, $literalTerms) as $literalIndex) {
                $groups[$key]['literal_groups'][$literalIndex] = true;
            }
            foreach ($this->matchedGroups($row, $termGroups) as $groupIndex) {
                $groups[$key]['matched_groups'][$groupIndex] = true;
            }
            $groups[$key]['matches'][] = [
                'id' => isset($row['article_unit_id']) ? (int)$row['article_unit_id'] : (int)$row['entity_id'],
                'title' => $this->safeTitle($row),
                'context_label' => $this->contextLabel($row),
                'hierarchy_label' => $this->dbHierarchyLabel($row),
                'excerpt' => $this->excerpt($this->contentWithoutHeading($row), array_merge(...$termGroups)),
            ];
        }

        $results = array_values(array_map(static function (array $group): array {
            $group['coverage'] = count($group['matched_groups']);
            $group['literal_coverage'] = count($group['literal_groups']);
            unset($group['matched_groups']);
            unset($group['literal_groups']);
            return $group;
        }, $groups));

        usort($results, static function (array $first, array $second): int {
            return ($second['literal_coverage'] <=> $first['literal_coverage'])
                ?: ($second['coverage'] <=> $first['coverage'])
                ?: (($first['stato_vigenza'] === 'storico' ? 1 : 0) <=> ($second['stato_vigenza'] === 'storico' ? 1 : 0))
                ?: ($first['source_priority'] <=> $second['source_priority'])
                ?: ($first['document_order'] <=> $second['document_order'])
                ?: ($first['first_position'] <=> $second['first_position'])
                ?: ($second['rank_score'] <=> $first['rank_score'])
                ?: ($second['match_count'] <=> $first['match_count'])
                ?: ($first['section_title'] <=> $second['section_title']);
        });

        return $results;
    }

    private function rankScore(array $row, array $termGroups, array $literalTerms, string $query): int
    {
        $title = $this->normalizeSearchText((string)$row['titolo']);
        $content = $this->normalizeSearchText($this->contentWithoutHeading($row));
        $text = $title . ' ' . $content;
        $normalizedQuery = $this->normalizeSearchText($query);
        $score = 0;

        if ($normalizedQuery !== '' && str_contains($text, $normalizedQuery)) {
            $score += 80;
        }
        if ($normalizedQuery !== '' && str_contains($title, $normalizedQuery)) {
            $score += 120;
        }
        foreach ($literalTerms as $term) {
            if (preg_match($this->phpWordRegex($term), $title) === 1) {
                $score += 60;
            }
            if (preg_match($this->phpWordRegex($term), $content) === 1) {
                $score += 40;
            }
        }
        if ($this->looksLikeDataTable($content)) {
            $score += 60;
        }
        foreach ($termGroups as $group) {
            foreach ($group as $term) {
                if (preg_match($this->phpWordRegex($term), $title) === 1) {
                    $score += 30;
                    break;
                }
            }
        }

        return $score;
    }

    private function matchedLiteralTerms(array $row, array $literalTerms): array
    {
        $text = $this->normalizeSearchText($this->contentWithoutHeading($row));
        $matched = [];
        foreach ($literalTerms as $index => $term) {
            if (preg_match($this->phpWordRegex($term), $text) === 1) {
                $matched[] = $index;
            }
        }
        return $matched;
    }

    private function firstMatchPosition(array $row, array $literalTerms): int
    {
        $content = $this->normalizeSearchText($this->contentWithoutHeading($row));
        $best = PHP_INT_MAX;
        foreach ($literalTerms as $term) {
            $position = mb_strpos($content, $term, 0, 'UTF-8');
            if ($position !== false) {
                $best = min($best, $position);
            }
        }
        return $best;
    }

    private function sourcePriority(array $row): int
    {
        $blockCode = (string)($row['block_code'] ?? '');
        $blockTitle = $this->normalizeSearchText((string)($row['block_title'] ?? ''));
        if (str_contains($blockTitle, 'storico')) {
            return 4;
        }
        if (($blockCode === '99' || str_contains($blockTitle, 'testo vigente')) && (string)$row['stato_vigenza'] !== 'storico') {
            return 0;
        }
        if (str_contains($blockTitle, 'indice') || str_contains($blockTitle, 'mappa')) {
            return 3;
        }
        return 1;
    }

    private function looksLikeDataTable(string $content): bool
    {
        return preg_match('/\b(Livello|Tabella|Importi|Minimi|Elemento retributivo)\b/iu', $content) === 1
            && preg_match('/\b\d{1,3}(?:\.\d{3})*,\d{2}\b/u', $content) === 1;
    }

    private function matchedGroups(array $row, array $termGroups): array
    {
        $text = $this->normalizeSearchText($this->contentWithoutHeading($row));
        $matched = [];
        foreach ($termGroups as $index => $group) {
            foreach ($group as $term) {
                if (preg_match($this->phpWordRegex($term), $text) === 1) {
                    $matched[] = $index;
                    break;
                }
            }
        }
        return $matched;
    }

    private function terms(string $query): array
    {
        $normalized = $this->normalizeSearchText($query);
        $words = preg_split('/\s+/u', $normalized) ?: [];
        $words = $this->meaningfulWords($words);

        return array_map(fn (string $word): array => array_values(array_unique(array_filter([
            $word,
            str_ends_with($word, 'i') ? substr($word, 0, -1) . 'o' : null,
            str_ends_with($word, 'i') ? substr($word, 0, -1) . 'e' : null,
            str_ends_with($word, 'e') ? substr($word, 0, -1) . 'i' : null,
            str_ends_with($word, 'o') ? substr($word, 0, -1) . 'i' : null,
            str_ends_with($word, 'a') ? substr($word, 0, -1) . 'e' : null,
        ], static fn (?string $term): bool => $term !== null && mb_strlen($term) > 1))), $words);
    }

    private function literalTerms(string $query): array
    {
        $normalized = $this->normalizeSearchText($query);
        $words = preg_split('/\s+/u', $normalized) ?: [];
        return $this->meaningfulWords($words);
    }

    private function excerpt(string $text, array $terms): string
    {
        $clean = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $position = 0;
        foreach ($terms as $term) {
            if (preg_match($this->phpWordRegexInsensitive($term), $clean, $matches, PREG_OFFSET_CAPTURE) === 1) {
                $prefix = substr($clean, 0, (int)$matches[0][1]);
                $position = max(0, mb_strlen($prefix, 'UTF-8') - 90);
                break;
            }
        }
        return trim(mb_substr($clean, $position, 260));
    }

    private function wordRegex(string $term): string
    {
        return '(^|[^[:alnum:]_])' . preg_quote($term, '/') . '([^[:alnum:]_]|$)';
    }

    private function phpWordRegex(string $term): string
    {
        return '~(^|[^\p{L}\p{N}_])' . preg_quote($term, '~') . '([^\p{L}\p{N}_]|$)~u';
    }

    private function phpWordRegexInsensitive(string $term): string
    {
        return '~(^|[^\p{L}\p{N}_])' . preg_quote($term, '~') . '([^\p{L}\p{N}_]|$)~iu';
    }

    private function normalizeSearchText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');
        $text = strtr($text, [
            '«' => ' ', '»' => ' ', '"' => ' ', "'" => ' ', '`' => ' ',
            '“' => ' ', '”' => ' ', '‘' => ' ', '’' => ' ',
            '‹' => ' ', '›' => ' ', '„' => ' ', '‚' => ' ',
        ]);
        return trim(preg_replace('/[^\p{L}\p{N}_]+/u', ' ', $text) ?? $text);
    }

    private function hasSearchableContent(array $row): bool
    {
        $content = $this->normalizeSearchText($this->contentWithoutHeading($row));
        return mb_strlen($content, 'UTF-8') >= 20;
    }

    private function matchesContentTerms(array $row, array $termGroups): bool
    {
        $content = $this->normalizeSearchText($this->contentWithoutHeading($row));
        $requiredMatches = 1;
        $matchedGroups = 0;
        foreach ($termGroups as $group) {
            foreach ($group as $term) {
                if (preg_match($this->phpWordRegex($term), $content) === 1) {
                    $matchedGroups++;
                    break;
                }
            }
        }
        return $matchedGroups >= $requiredMatches;
    }

    private function contentWithoutHeading(array $row): string
    {
        $title = (string)$row['titolo'];
        $content = trim((string)$row['contenuto']);
        $lines = preg_split('/\R/u', $content) ?: [];
        if ($lines !== [] && $this->normalizeSearchText((string)$lines[0]) === $this->normalizeSearchText($title)) {
            array_shift($lines);
            return trim(implode("\n", $lines));
        }
        if ($lines !== [] && $this->normalizeSearchText((string)$lines[0]) === $this->normalizeSearchText('## ' . $title)) {
            array_shift($lines);
            return trim(implode("\n", $lines));
        }
        return $content;
    }

    private function meaningfulWords(array $words): array
    {
        $stopwords = [
            'a', 'ad', 'al', 'allo', 'alla', 'ai', 'agli', 'alle',
            'con', 'da', 'dal', 'dallo', 'dalla', 'dai', 'dagli', 'dalle',
            'de', 'del', 'dello', 'della', 'dei', 'degli', 'delle', 'di',
            'e', 'ed', 'il', 'lo', 'la', 'i', 'gli', 'le',
            'in', 'nel', 'nello', 'nella', 'nei', 'negli', 'nelle',
            'o', 'per', 'su', 'sul', 'sullo', 'sulla', 'sui', 'sugli', 'sulle',
            'tra', 'fra', 'un', 'uno', 'una',
            'che', 'chi', 'cui', 'come', 'quando', 'dove', 'quale', 'quali',
            'sono', 'essere', 'avere', 'fare', 'faccio', 'posso', 'puo', 'devo', 'deve',
        ];

        return array_values(array_unique(array_filter($words, static fn (string $word): bool =>
            mb_strlen($word, 'UTF-8') > 1 && !in_array($word, $stopwords, true)
        )));
    }
}
