<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use App\Repositories\ActivityLogRepository;
use App\Repositories\TokenRepository;
use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TokenRepository $tokens,
        private readonly ActivityLogRepository $activityLogs
    ) {
    }

    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
            throw new HttpException(401, 'Credenziali non valide.');
        }

        if (($user['status'] ?? '') !== 'active') {
            throw new HttpException(403, 'Utente non attivo.');
        }

        $plainToken = bin2hex(random_bytes(32));
        $this->tokens->create((int)$user['id'], $plainToken, $deviceName);
        $this->activityLogs->write((int)$user['id'], 'auth.login', ['device_name' => $deviceName]);

        unset($user['password_hash']);

        return [
            'token_type' => 'Bearer',
            'access_token' => $plainToken,
            'user' => $user,
        ];
    }

    public function logout(string $plainToken, int $userId): void
    {
        $this->tokens->revokeByPlainToken($plainToken);
        $this->activityLogs->write($userId, 'auth.logout');
    }
}
