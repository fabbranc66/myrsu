<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\HttpException;
use PDO;
use Throwable;

final class AdminAccountProtectionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function run(int $userId, bool $removesAdmin, callable $operation): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec('SAVEPOINT myrsu_admin_account_guard');
        }

        try {
            $roleId = $this->pdo->query("SELECT id FROM roles WHERE name = 'admin' FOR UPDATE")->fetchColumn();
            if ($roleId === false) {
                throw new HttpException(409, 'Ruolo amministratore non disponibile. Operazione annullata.');
            }

            if ($removesAdmin) {
                $statement = $this->pdo->prepare(
                    "SELECT u.id FROM users u
                     INNER JOIN role_user ru ON ru.user_id = u.id
                     WHERE ru.role_id = ? AND u.status = 'active'
                     ORDER BY u.id FOR UPDATE"
                );
                $statement->execute([(int)$roleId]);
                $activeAdminIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
                if (count($activeAdminIds) === 1 && in_array($userId, $activeAdminIds, true)) {
                    throw new HttpException(409, 'Deve rimanere almeno un amministratore attivo. Attiva prima un altro amministratore.');
                }
            }

            $result = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec('RELEASE SAVEPOINT myrsu_admin_account_guard');
            }
            return $result;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                if ($ownsTransaction) {
                    $this->pdo->rollBack();
                } else {
                    $this->pdo->exec('ROLLBACK TO SAVEPOINT myrsu_admin_account_guard');
                    $this->pdo->exec('RELEASE SAVEPOINT myrsu_admin_account_guard');
                }
            }
            throw $exception;
        }
    }
}
