<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class FundRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function contracts(): array
    {
        return $this->pdo->query(
            'SELECT c.*, d.original_name document_name
             FROM vending_contracts c
             LEFT JOIN documents d ON d.id = c.document_id
             ORDER BY c.start_date DESC, c.id DESC'
        )->fetchAll();
    }

    public function createContract(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO vending_contracts
             (supplier_name, contract_number, start_date, end_date, status, notes, document_id, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['supplier_name'],
            $data['contract_number'],
            $data['start_date'],
            $data['end_date'],
            $data['status'],
            $data['notes'],
            $data['document_id'],
            $data['created_by'],
        ]);

        return $this->findContract((int)$this->pdo->lastInsertId()) ?? [];
    }

    public function findContract(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vending_contracts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function updateContract(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE vending_contracts
             SET supplier_name = ?, contract_number = ?, start_date = ?, end_date = ?, status = ?,
                 notes = ?, document_id = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['supplier_name'],
            $data['contract_number'],
            $data['start_date'],
            $data['end_date'],
            $data['status'],
            $data['notes'],
            $data['document_id'],
            $id,
        ]);

        return $this->findContract($id);
    }

    public function deleteContract(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM vending_contracts WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function movements(): array
    {
        return $this->pdo->query(
            'SELECT m.*, c.supplier_name, d.original_name document_name
             FROM fund_movements m
             LEFT JOIN vending_contracts c ON c.id = m.contract_id
             LEFT JOIN documents d ON d.id = m.document_id
             ORDER BY m.movement_date DESC, m.id DESC'
        )->fetchAll();
    }

    public function createMovement(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO fund_movements
             (contract_id, movement_date, movement_type, amount, reason, notes, document_id, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['contract_id'],
            $data['movement_date'],
            $data['movement_type'],
            $data['amount'],
            $data['reason'],
            $data['notes'],
            $data['document_id'],
            $data['created_by'],
        ]);

        return $this->findMovement((int)$this->pdo->lastInsertId()) ?? [];
    }

    public function findMovement(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM fund_movements WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function updateMovement(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE fund_movements
             SET contract_id = ?, movement_date = ?, movement_type = ?, amount = ?, reason = ?,
                 notes = ?, document_id = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['contract_id'],
            $data['movement_date'],
            $data['movement_type'],
            $data['amount'],
            $data['reason'],
            $data['notes'],
            $data['document_id'],
            $id,
        ]);

        return $this->findMovement($id);
    }

    public function deleteMovement(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM fund_movements WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function balance(): array
    {
        $row = $this->pdo->query(
            "SELECT
                COALESCE(SUM(CASE WHEN movement_type = 'income' THEN amount ELSE 0 END), 0) income,
                COALESCE(SUM(CASE WHEN movement_type = 'expense' THEN amount ELSE 0 END), 0) expense
             FROM fund_movements"
        )->fetch();

        return [
            'income' => (float)$row['income'],
            'expense' => (float)$row['expense'],
            'balance' => (float)$row['income'] - (float)$row['expense'],
        ];
    }
}
