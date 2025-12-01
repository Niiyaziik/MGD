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

    public function findByPhone(string $phone): ?array
    {
        $st = $this->pdo->prepare("
            SELECT *
            FROM users
            WHERE phone = :phone
            AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([':phone' => $phone]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $st = $this->pdo->prepare("
            INSERT INTO users (surname, name, patronymic, phone, link_vk,
                            district_id, street_id, house_id, auth_method, created_at)
            VALUES (:surname, :name, :patronymic, :phone, :link_vk,
                    :district_id, :street_id, :house_id, :auth_method, NOW())
        ");

        $st->execute([
            ':surname'     => $data['surname']     ?? null,
            ':name'        => $data['name']        ?? null,
            ':patronymic'  => $data['patronymic']  ?? null,
            ':phone'       => $data['phone']       ?? null,
            ':link_vk'     => $data['link_vk']     ?? null,
            ':district_id' => $data['district_id'] ?? null,
            ':street_id'   => $data['street_id']   ?? null,
            ':house_id'    => $data['house_id']    ?? null,
            ':auth_method' => $data['auth_method'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
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
