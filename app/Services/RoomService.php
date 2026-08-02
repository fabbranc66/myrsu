<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\RoomParticipantRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTimelineRepository;
use PDO;
use Throwable;

final class RoomService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly RoomRepository $rooms,
        private readonly RoomParticipantRepository $participants,
        private readonly RoomTimelineRepository $timeline
    ) {
    }

    public function create(array $data, int $userId): array
    {
        $category = $this->rooms->categoryById((int)$data['category_id']);
        if ($category === null) {
            throw new HttpException(422, 'Categoria Tavolo non valida.');
        }
        $this->pdo->beginTransaction();
        try {
            $room = $this->rooms->create([
                'code' => $this->rooms->nextCode((string)$category['code']),
                'title' => trim((string)$data['title']),
                'description' => trim((string)($data['description'] ?? '')) ?: null,
                'category_id' => (int)$category['id'],
                'status' => (string)($data['status'] ?? 'draft'),
                'created_by' => $userId,
                'responsible_id' => $userId,
                'opened_at' => in_array((string)($data['status'] ?? 'draft'), ['open', 'in_progress'], true) ? date('Y-m-d H:i:s') : null,
            ]);
            $this->participants->save((int)$room['id'], $userId, 'manage', 'Responsabile', $userId);
            $this->timeline->event((int)$room['id'], $userId, 'room.created', 'room', (int)$room['id']);
            $this->pdo->commit();
            return $room;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function assertWritable(array $room): void
    {
        if (in_array((string)$room['status'], ['suspended', 'closed', 'archived', 'cancelled'], true)) {
            throw new HttpException(409, 'Il Tavolo è in sola lettura.');
        }
    }
}
