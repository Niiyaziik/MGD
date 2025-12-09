<?php
declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Repository\Contract\AdminRepositoryInterface;
use PDO;

class AdminRepository implements AdminRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function findByLogin(string $login): ?array
    {
        $sql = "
            SELECT *
            FROM admins
            WHERE login = :login
              AND deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':login' => $login]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function find(int $id): ?array
    {
        $sql = "
            SELECT *
            FROM admins
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
