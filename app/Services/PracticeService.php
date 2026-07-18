<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\PracticeRepository;
use App\Repositories\UserRepository;

final class PracticeService
{
    private const STATUSES = ['new', 'under_review', 'assigned', 'company_discussion', 'awaiting_response', 'suspended', 'resolved', 'closed', 'archived', 'closed_positive', 'closed_negative'];
    private const TYPES = ['collective', 'personal', 'personal_restricted'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    private const SOURCES = ['manual', 'anonymous_report', 'mail', 'member', 'delegate', 'document', 'communication', 'meeting'];
    private const VISIBILITIES = ['operators', 'authorized', 'public_summary'];

    public function __construct(
        private readonly PracticeRepository $practices,
        private readonly UserRepository $users
    )
    {
    }

    public function create(array $data, int $userId): array
    {
        return $this->practices->create($this->validated($data), $userId);
    }

    public function update(array $practice, array $data): array
    {
        return $this->practices->update((int)$practice['id'], $this->validated($data, $practice)) ?? $practice;
    }

    private function validated(array $data, array $current = []): array
    {
        $values = [
            'title' => trim((string)($data['title'] ?? $current['title'] ?? '')),
            'summary' => trim((string)($data['summary'] ?? $current['summary'] ?? '')),
            'type' => (string)($data['type'] ?? $current['type'] ?? 'collective'),
            'status' => (string)($data['status'] ?? $current['status'] ?? 'new'),
            'priority' => (string)($data['priority'] ?? $current['priority'] ?? 'medium'),
            'source_type' => (string)($data['source_type'] ?? $current['source_type'] ?? 'manual'),
            'assigned_user_id' => $this->nullableId($data['assigned_user_id'] ?? $current['assigned_user_id'] ?? null),
            'visibility' => (string)($data['visibility'] ?? $current['visibility'] ?? 'operators'),
            'due_date' => trim((string)($data['due_date'] ?? $current['due_date'] ?? '')) ?: null,
        ];
        if ($values['title'] === '') throw new HttpException(422, 'Titolo pratica obbligatorio.');
        if (mb_strlen($values['title']) > 255) throw new HttpException(422, 'Titolo pratica troppo lungo.');
        $this->allowed($values['type'], self::TYPES, 'Tipo pratica non valido.');
        $this->allowed($values['status'], self::STATUSES, 'Stato pratica non valido.');
        $this->allowed($values['priority'], self::PRIORITIES, 'Priorita pratica non valida.');
        $this->allowed($values['source_type'], self::SOURCES, 'Origine pratica non valida.');
        $this->allowed($values['visibility'], self::VISIBILITIES, 'Visibilita pratica non valida.');
        if ($values['due_date'] !== null && !$this->validDate($values['due_date'])) {
            throw new HttpException(422, 'Scadenza non valida.');
        }
        if ($values['assigned_user_id'] !== null && !in_array($values['assigned_user_id'], array_map('intval', array_column($this->users->operators(), 'id')), true)) {
            throw new HttpException(422, 'Assegnatario non valido.');
        }
        return $values;
    }

    private function allowed(string $value, array $allowed, string $message): void
    {
        if (!in_array($value, $allowed, true)) throw new HttpException(422, $message);
    }

    private function nullableId(mixed $value): ?int
    {
        $id = (int)$value;
        return $id > 0 ? $id : null;
    }

    private function validDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
