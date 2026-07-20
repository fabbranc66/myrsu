<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UnionPermitRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allocations(?int $userId = null, ?int $year = null): array
    {
        $where = [];
        $values = [];
        if ($userId !== null) {
            $where[] = 'a.user_id = ?';
            $values[] = $userId;
        }
        if ($year !== null) {
            $where[] = 'a.year = ?';
            $values[] = $year;
        }

        $sql = "SELECT a.*, u.name AS user_name, u.email AS user_email
                FROM union_permit_allocations a
                INNER JOIN users u ON u.id = a.user_id";
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY a.year DESC, u.name, a.permit_type';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll();
    }

    public function upsertAllocation(array $data): array
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO union_permit_allocations (user_id, year, permit_type, annual_hours, used_hours, created_by)
             VALUES (?, ?, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE annual_hours = VALUES(annual_hours), updated_at = NOW()"
        );
        $stmt->execute([
            $data['user_id'],
            $data['year'],
            $data['permit_type'],
            $data['annual_hours'],
            $data['created_by'],
        ]);

        return $this->findAllocation((int)$data['user_id'], (int)$data['year'], (string)$data['permit_type']) ?? [];
    }

    public function findAllocation(int $userId, int $year, string $type): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM union_permit_allocations WHERE user_id = ? AND year = ? AND permit_type = ? LIMIT 1'
        );
        $stmt->execute([$userId, $year, $type]);
        return $stmt->fetch() ?: null;
    }

    public function requests(?int $userId = null): array
    {
        $sql = "SELECT r.*, u.name AS user_name, d.pdf_public_path, d.signature,
                       pe.protocol_number
                FROM union_permit_requests r
                INNER JOIN users u ON u.id = r.user_id
                LEFT JOIN documents d ON d.id = r.document_id
                LEFT JOIN protocol_entries pe ON pe.document_id = d.id";
        $values = [];
        if ($userId !== null) {
            $sql .= ' WHERE r.user_id = ?';
            $values[] = $userId;
        }
        $sql .= ' ORDER BY r.created_at DESC, r.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll();
    }

    public function createRequest(array $data): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE union_permit_allocations
                 SET used_hours = used_hours + ?
                 WHERE id = ? AND annual_hours - used_hours >= ?'
            );
            $stmt->execute([$data['hours'], $data['allocation_id'], $data['hours']]);
            if ($stmt->rowCount() !== 1) {
                $this->pdo->rollBack();
                return [];
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO union_permit_requests
                 (user_id, allocation_id, request_scope, permit_type, union_name, company_recipient, subject,
                  request_date, start_at, end_at, hours, notes, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $data['user_id'],
                $data['allocation_id'],
                $data['request_scope'],
                $data['permit_type'],
                $data['union_name'],
                $data['company_recipient'],
                $data['subject'],
                $data['request_date'],
                $data['start_at'],
                $data['end_at'],
                $data['hours'],
                $data['notes'],
                $data['created_by'],
            ]);
            $id = (int)$this->pdo->lastInsertId();
            $this->pdo->commit();
            return $this->findRequest($id) ?? [];
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function attachDocument(int $id, int $documentId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE union_permit_requests SET document_id = ? WHERE id = ?');
        $stmt->execute([$documentId, $id]);
        return $this->findRequest($id);
    }

    public function updateRequest(array $current, array $data): array
    {
        $this->pdo->beginTransaction();
        try {
            $oldAllocation = (int)$current['allocation_id'];
            $newAllocation = (int)$data['allocation_id'];
            $oldHours = (float)$current['hours'];
            $newHours = (float)$data['hours'];

            $stmt = $this->pdo->prepare('UPDATE union_permit_allocations SET used_hours = GREATEST(0, used_hours - ?) WHERE id = ?');
            $stmt->execute([$oldHours, $oldAllocation]);
            $stmt = $this->pdo->prepare(
                'UPDATE union_permit_allocations
                 SET used_hours = used_hours + ?
                 WHERE id = ? AND annual_hours - used_hours >= ?'
            );
            $stmt->execute([$newHours, $newAllocation, $newHours]);
            if ($stmt->rowCount() !== 1) {
                $this->pdo->rollBack();
                return [];
            }

            $stmt = $this->pdo->prepare(
                'UPDATE union_permit_requests
                 SET allocation_id = ?, request_scope = ?, permit_type = ?, union_name = ?, company_recipient = ?,
                     subject = ?, request_date = ?, start_at = ?, end_at = ?, hours = ?, notes = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $newAllocation,
                $data['request_scope'],
                $data['permit_type'],
                $data['union_name'],
                $data['company_recipient'],
                $data['subject'],
                $data['request_date'],
                $data['start_at'],
                $data['end_at'],
                $data['hours'],
                $data['notes'],
                $current['id'],
            ]);
            $this->pdo->commit();
            return $this->findRequest((int)$current['id']) ?? [];
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function cancelRequest(array $request, int $userId, bool $restoreHours): array
    {
        $this->pdo->beginTransaction();
        try {
            if ($restoreHours) {
                $stmt = $this->pdo->prepare(
                    'UPDATE union_permit_allocations SET used_hours = GREATEST(0, used_hours - ?) WHERE id = ?'
                );
                $stmt->execute([$request['hours'], $request['allocation_id']]);
            }

            $stmt = $this->pdo->prepare(
                "UPDATE union_permit_requests
                 SET status = 'canceled', canceled_by = ?, canceled_at = NOW()
                 WHERE id = ? AND status <> 'canceled'"
            );
            $stmt->execute([$userId, $request['id']]);
            $this->pdo->commit();
            return $this->findRequest((int)$request['id']) ?? [];
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function deleteRequestAndRestore(array $request): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE union_permit_allocations SET used_hours = GREATEST(0, used_hours - ?) WHERE id = ?'
            );
            $stmt->execute([$request['hours'], $request['allocation_id']]);
            $stmt = $this->pdo->prepare('DELETE FROM union_permit_requests WHERE id = ?');
            $stmt->execute([$request['id']]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function findRequest(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM union_permit_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
