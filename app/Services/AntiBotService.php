<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

final class AntiBotService
{
    public function validate(array $data): void
    {
        if (trim((string)($data['website'] ?? '')) !== '') {
            throw new HttpException(400, 'Controllo antibot non valido.');
        }

        $a = (int)($data['antibot_a'] ?? -1);
        $b = (int)($data['antibot_b'] ?? -1);
        $answer = (int)($data['antibot_answer'] ?? -1);

        if ($a < 0 || $b < 0 || $answer !== $a + $b) {
            throw new HttpException(400, 'Controllo antibot non valido.');
        }
    }
}
