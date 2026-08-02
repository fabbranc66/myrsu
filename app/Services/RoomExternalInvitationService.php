<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\RoomExternalAccessRepository;
use App\Repositories\UserRepository;

final class RoomExternalInvitationService
{
    public function __construct(
        private readonly RoomExternalAccessRepository $access,
        private readonly UserRepository $users,
        private readonly EmailSmtpService $smtp
    ) {
    }

    public function begin(array $invitation, string $name, string $email, ?string $phone): array
    {
        if ($invitation['verification_sent_at'] !== null || $invitation['registered_at'] !== null) {
            throw new HttpException(409, 'Invito già utilizzato. Usa il link ricevuto via e-mail.');
        }
        $user = $this->users->findByEmail($email);
        $localIdentifier = $user === null ? $this->localIdentifier() : null;
        $personalToken = bin2hex(random_bytes(32));
        $url = $this->externalUrl($personalToken);
        $this->smtp->send([
            'to_emails' => $email,
            'cc_emails' => '',
            'bcc_emails' => '',
            'subject' => 'Accesso al Tavolo MyRSU: ' . (string)$invitation['room_title'],
            'body' => "Ciao {$name},\n\nconferma la tua identità e accedi al Tavolo MyRSU:\n{$url}\n\nIl link è personale e non deve essere condiviso.",
        ]);

        return $this->access->beginRegistration(
            (int)$invitation['id'], $name, $email, $phone, $localIdentifier,
            $user === null ? null : (int)$user['id'], hash('sha256', $personalToken)
        );
    }

    public function confirm(array $invitation): array
    {
        if ($invitation['registered_at'] !== null) return $invitation;
        if ($invitation['verification_sent_at'] === null) return $invitation;
        return array_merge($invitation, $this->access->confirm((int)$invitation['id']));
    }

    private function localIdentifier(): string
    {
        return 'EXT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(5)));
    }

    private function externalUrl(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        return $scheme . '://' . $host . '/tavolo/?token=' . urlencode($token);
    }
}
