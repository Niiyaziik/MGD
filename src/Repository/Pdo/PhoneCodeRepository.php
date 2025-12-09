<?php
declare(strict_types=1);

namespace App\Repository\Pdo;

use App\Repository\Contract\PhoneCodeRepositoryInterface;
use PDO;

class PhoneCodeRepository implements PhoneCodeRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function createCode(string $phone, string $code, ?string $ip = null): int
    {
        // подчистим старый мусор по этому телефону
        $stDel = $this->pdo->prepare("
            DELETE FROM phone_code
            WHERE phone = :phone
              AND (used_at IS NOT NULL OR created_at < (NOW() - INTERVAL 1 DAY))
        ");
        $stDel->execute([':phone' => $phone]);

        $st = $this->pdo->prepare("
            INSERT INTO phone_code (phone, code, created_at)
            VALUES (:phone, :code, NOW())
        ");
        $st->execute([
            ':phone' => $phone,
            ':code'  => $code,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findValid(string $phone, string $code, int $ttlSeconds = 600): ?array
    {
        $st = $this->pdo->prepare("
            SELECT *
            FROM phone_code
            WHERE phone = :phone
              AND code  = :code
              AND used_at IS NULL
              AND created_at >= (NOW() - INTERVAL :ttl SECOND)
            ORDER BY id DESC
            LIMIT 1
        ");
        $st->bindValue(':phone', $phone);
        $st->bindValue(':code',  $code);
        $st->bindValue(':ttl',   $ttlSeconds, PDO::PARAM_INT);
        $st->execute();

        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markUsed(int $id): void
    {
        $st = $this->pdo->prepare("
            UPDATE phone_code
            SET used_at = NOW()
            WHERE id = :id AND used_at IS NULL
        ");
        $st->execute([':id' => $id]);
    }
}
