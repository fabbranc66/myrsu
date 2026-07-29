<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InstitutionalContactRepository;
use App\Repositories\UserRepository;

final class EmailContactService
{
    public function __construct(
        private readonly InstitutionalContactRepository $contacts,
        private readonly UserRepository $users
    ) {
    }

    public function storeMissing(array $email, ?int $userId): void
    {
        $this->storeOne((string)($email['from_email'] ?? ''), (string)($email['from_name'] ?? ''), $userId);
        foreach (['to_emails', 'cc_emails', 'bcc_emails'] as $field) {
            foreach ($this->addresses((string)($email[$field] ?? '')) as $address) {
                $this->storeOne($address, '', $userId);
            }
        }
    }

    private function storeOne(string $email, string $name, ?int $userId): void
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $this->exists($email)) return;
        $this->contacts->create([
            'type' => 'esterno',
            'name' => trim($name) ?: $email,
            'role' => '',
            'organization' => '',
            'email' => $email,
            'phone' => '',
            'notes' => 'Creato automaticamente da e-mail.',
        ], $userId);
    }

    private function exists(string $email): bool
    {
        foreach ($this->contacts->all() as $contact) {
            if (strcasecmp((string)($contact['email'] ?? ''), $email) === 0) return true;
        }
        foreach ($this->users->all() as $user) {
            if (strcasecmp((string)($user['email'] ?? ''), $email) === 0) return true;
        }
        return false;
    }

    private function addresses(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;]/', $value) ?: [])));
    }
}
