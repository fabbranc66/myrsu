<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\RsuElectionDraftRepository;

final class RsuElectionDraftService
{
    private const FIELDS = ['electors', 'voters', 'valid_votes', 'blank_votes', 'null_votes', 'seats'];

    public function __construct(private readonly RsuElectionDraftRepository $repository)
    {
    }

    public function load(int $userId): ?array
    {
        return $this->repository->findForUser($userId);
    }

    public function save(int $userId, array $data): array
    {
        $form = [];
        foreach (self::FIELDS as $field) {
            $form[$field] = $this->number($data[$field] ?? '', $field === 'seats' ? 1 : 0);
        }
        $rows = $data['lists'] ?? null;
        if (!is_array($rows) || count($rows) !== 2) throw new HttpException(422, 'Inserire le liste CGIL e CISL.');
        $lists = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null) || !in_array($row['name'], ['CGIL', 'CISL'], true) || isset($lists[$row['name']])) {
                throw new HttpException(422, 'Liste non valide.');
            }
            $candidates = $row['candidates'] ?? [];
            if (!is_array($candidates) || count($candidates) > 5) throw new HttpException(422, 'Massimo 5 candidati per lista.');
            $normalized = [];
            foreach ($candidates as $candidate) {
                if (!is_array($candidate) || !is_string($candidate['name'] ?? null) || mb_strlen($candidate['name']) > 160) {
                    throw new HttpException(422, 'Nome candidato non valido, massimo 160 caratteri.');
                }
                $normalized[] = ['name' => $candidate['name'], 'preferences' => $this->number($candidate['preferences'] ?? '')];
            }
            $lists[$row['name']] = [
                'name' => $row['name'],
                'votes' => $this->number($row['votes'] ?? ''),
                'presentation_order' => $this->number($row['presentation_order'] ?? '', 1),
                'candidates' => $normalized,
            ];
        }
        $form['lists'] = [$lists['CGIL'], $lists['CISL']];
        return $this->repository->saveForUser($userId, $form);
    }

    private function number(mixed $value, int $minimum = 0): string
    {
        if (!is_string($value) && !is_int($value)) throw new HttpException(422, 'Valore numerico non valido.');
        $value = trim((string)$value);
        if ($value !== '' && (!preg_match('/^[0-9]{1,10}$/D', $value) || (int)$value < $minimum || (int)$value > 2147483647)) {
            throw new HttpException(422, 'Inserire un numero intero valido oppure lasciare il campo vuoto.');
        }
        return $value;
    }
}
