<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class EmailRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function all(array $filters = []): array
    {
        $where = [];
        $params = [];
        foreach (['direction', 'read_status', 'handling_status', 'practice_id'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $where[] = "e.{$field} = ?";
                $params[] = $filters[$field];
            }
        }

        $sql = 'SELECT e.*, p.title practice_title, u.name managed_by_name
                FROM emails e
                LEFT JOIN practices p ON p.id = e.practice_id
                LEFT JOIN users u ON u.id = e.managed_by';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY e.message_at DESC, e.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT e.*, p.title practice_title, u.name managed_by_name
             FROM emails e
             LEFT JOIN practices p ON p.id = e.practice_id
             LEFT JOIN users u ON u.id = e.managed_by
             WHERE e.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function externalExists(string $externalId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM emails WHERE external_id = ?');
        $stmt->execute([$externalId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function create(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO emails
             (external_id, import_source, direction, read_status, handling_status, from_name, from_email, to_emails, cc_emails,
              subject, body, message_at, practice_id, contact_id, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['external_id'] ?? null, $data['import_source'] ?? 'manual',
            $data['direction'], $data['read_status'], $data['handling_status'], $data['from_name'],
            $data['from_email'], $data['to_emails'], $data['cc_emails'], $data['subject'],
            $data['body'], $data['message_at'], $data['practice_id'], $data['contact_id'], $data['created_by'],
        ]);

        return $this->find((int)$this->pdo->lastInsertId()) ?? [];
    }

    public function update(int $id, array $data): ?array
    {
        $stmt = $this->pdo->prepare(
            'UPDATE emails SET direction = ?, read_status = ?, handling_status = ?, from_name = ?,
              from_email = ?, to_emails = ?, cc_emails = ?, subject = ?, body = ?, message_at = ?,
              practice_id = ?, contact_id = ?, updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $data['direction'], $data['read_status'], $data['handling_status'], $data['from_name'],
            $data['from_email'], $data['to_emails'], $data['cc_emails'], $data['subject'],
            $data['body'], $data['message_at'], $data['practice_id'], $data['contact_id'], $id,
        ]);

        return $this->find($id);
    }

    public function manage(int $id, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            "UPDATE emails SET handling_status = 'managed', read_status = 'read',
              managed_by = ?, managed_at = NOW(), updated_at = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$userId, $id]);

        return $this->find($id);
    }

    public function linkPractice(int $id, ?int $practiceId): ?array
    {
        $stmt = $this->pdo->prepare('UPDATE emails SET practice_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$practiceId, $id]);

        return $this->find($id);
    }

    public function notes(int $emailId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, u.name created_by_name
             FROM email_notes n JOIN users u ON u.id = n.created_by
             WHERE n.email_id = ? ORDER BY n.created_at DESC, n.id DESC'
        );
        $stmt->execute([$emailId]);

        return $stmt->fetchAll();
    }

    public function addNote(int $emailId, string $body, int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_notes (email_id, body, created_by, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$emailId, $body, $userId]);

        return ['id' => (int)$this->pdo->lastInsertId(), 'email_id' => $emailId, 'body' => $body];
    }

    public function attachments(int $emailId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_attachments WHERE email_id = ? ORDER BY id ASC');
        $stmt->execute([$emailId]);

        return $stmt->fetchAll();
    }

    public function findAttachment(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, e.id email_id FROM email_attachments a JOIN emails e ON e.id = a.email_id WHERE a.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch() ?: null;
    }

    public function addAttachment(int $emailId, array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_attachments
             (email_id, original_name, stored_name, storage_path, mime_type, size_bytes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $emailId, $data['original_name'], $data['stored_name'], $data['storage_path'],
            $data['mime_type'], $data['size_bytes'],
        ]);

        return ['id' => (int)$this->pdo->lastInsertId()] + $data;
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM emails WHERE id = ?');
        $stmt->execute([$id]);
    }
}
