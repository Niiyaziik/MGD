<?php
namespace App\Repository\Pdo;

use App\Repository\Contract\UserRepositoryInterface;
use PDO;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function firstOrCreateByPhone(string $phone): array
    {
        $st = $this->pdo->prepare("SELECT * FROM users WHERE phone=? AND deleted_at IS NULL");
        $st->execute([$phone]);
        $u = $st->fetch();
        if ($u) return $u;

        $st = $this->pdo->prepare("INSERT INTO users(phone) VALUES(?)");
        $st->execute([$phone]);
        $id = (int)$this->pdo->lastInsertId();
        return $this->find($id);
    }

    public function find(int $id): array
    {
        $st = $this->pdo->prepare("SELECT * FROM users WHERE id=? AND deleted_at IS NULL");
        $st->execute([$id]);
        $u = $st->fetch();
        if (!$u) throw new \RuntimeException('User not found');
        return $u;
    }

    public function all(): array
    {
        $st = $this->pdo->query("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY id DESC");
        return $st->fetchAll();
    }

    public function update(int $id, array $data): void
    {
        $st = $this->pdo->prepare(
            "UPDATE users SET phone=?, district_id=?, updated_at=NOW()
             WHERE id=? AND deleted_at IS NULL"
        );
        $st->execute([
            $data['phone'] ?? null,
            $data['district_id'] ?? null,
            $id
        ]);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare("UPDATE users SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL");
        $st->execute([$id]);
    }
}
