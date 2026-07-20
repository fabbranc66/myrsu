<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class RsuElectionAnalysisService
{
    public function analyze(array $data): array
    {
        $electors = $this->int($data, 'electors');
        $voters = $this->int($data, 'voters');
        $validVotes = $this->int($data, 'valid_votes');
        $blankVotes = $this->int($data, 'blank_votes');
        $nullVotes = $this->int($data, 'null_votes');
        $seats = $this->int($data, 'seats');
        $lists = $this->lists($data['lists'] ?? []);
        $listVotes = array_sum(array_column($lists, 'votes'));
        $turnoutRequired = intdiv($electors, 2) + 1;
        $quorum = $seats > 0 ? $voters / $seats : 0;

        return [
            'checks' => [
                'turnout_required' => $turnoutRequired,
                'turnout_ok' => $voters >= $turnoutRequired,
                'ballots_ok' => $voters === ($validVotes + $blankVotes + $nullVotes),
                'list_votes_ok' => $validVotes === $listVotes,
            ],
            'summary' => [
                'electors' => $electors,
                'voters' => $voters,
                'valid_votes' => $validVotes,
                'blank_votes' => $blankVotes,
                'null_votes' => $nullVotes,
                'seats' => $seats,
                'quorum' => round($quorum, 6),
            ],
            'lists' => $this->allocateSeats($lists, $seats, $quorum),
        ];
    }

    private function allocateSeats(array $lists, int $seats, float $quorum): array
    {
        $assigned = 0;
        foreach ($lists as &$list) {
            $list['seats_base'] = $quorum > 0 ? (int)floor($list['votes'] / $quorum) : 0;
            $list['remainder'] = $quorum > 0 ? $list['votes'] - ($list['seats_base'] * $quorum) : 0;
            $list['seats'] = $list['seats_base'];
            $assigned += $list['seats'];
        }
        unset($list);

        usort($lists, static fn (array $a, array $b): int => [$b['remainder'], $b['votes'], $a['presentation_order']] <=> [$a['remainder'], $a['votes'], $b['presentation_order']]);
        $remainingSeats = $seats - $assigned;
        for ($index = 0; $index < $remainingSeats && isset($lists[$index]); $index++) {
            $next = $lists[$index + 1] ?? null;
            $tied = $next !== null
                && abs($lists[$index]['remainder'] - $next['remainder']) < 0.000001
                && $lists[$index]['votes'] === $next['votes'];
            $lists[$index]['assignment_note'] = $tied
                ? 'Seggio assegnato per priorita di presentazione lista.'
                : 'Seggio assegnato con maggior resto.';
            $lists[$index]['seats']++;
        }

        foreach ($lists as &$list) {
            usort($list['candidates'], static fn (array $a, array $b): int => [$b['preferences'], $a['name']] <=> [$a['preferences'], $b['name']]);
            $list['elected'] = array_slice($list['candidates'], 0, $list['seats']);
        }
        unset($list);

        usort($lists, static fn (array $a, array $b): int => [$b['seats'], $b['votes'], $a['name']] <=> [$a['seats'], $a['votes'], $b['name']]);
        return $lists;
    }

    private function lists(mixed $rows): array
    {
        if (!is_array($rows) || count($rows) !== 2) {
            throw new HttpException(422, 'Inserire solo liste CGIL e CISL.');
        }

        $lists = array_map(function (array $row): array {
            $name = trim((string)($row['name'] ?? ''));
            if (!in_array($name, ['CGIL', 'CISL'], true)) {
                throw new HttpException(422, 'Lista non valida.');
            }

            return [
                'name' => $name,
                'votes' => max(0, (int)($row['votes'] ?? 0)),
                'presentation_order' => max(1, (int)($row['presentation_order'] ?? 1)),
                'candidates' => $this->candidates($row['candidates'] ?? []),
            ];
        }, $rows);
        usort($lists, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $lists;
    }

    private function candidates(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $candidates = array_values(array_filter(array_map(static fn (array $row): array => [
            'name' => trim((string)($row['name'] ?? '')),
            'preferences' => max(0, (int)($row['preferences'] ?? 0)),
        ], $rows), static fn (array $row): bool => $row['name'] !== ''));
        if (count($candidates) > 3) {
            throw new HttpException(422, 'Massimo 3 candidati per lista.');
        }

        return $candidates;
    }

    private function int(array $data, string $key): int
    {
        $value = (int)($data[$key] ?? 0);
        if ($value < 0 || ($key === 'seats' && $value < 1)) {
            throw new HttpException(422, 'Valore non valido: ' . $key);
        }

        return $value;
    }
}
