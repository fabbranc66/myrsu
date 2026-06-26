<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PracticeRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allOpen(): array
    {
        return $this->pdo
            ->query("SELECT id, title, status FROM practices WHERE status = 'open' ORDER BY title ASC")
            ->fetchAll();
    }
}
