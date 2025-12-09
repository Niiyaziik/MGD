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
        $st = $this->pdo->prepare("
        UPDATE users SET
            surname     = :surname,
            name        = :name,
            patronymic  = :patronymic,
            district_id = :district_id,
            street_id   = :street_id,
            house_id    = :house_id
        WHERE id = :id
        ");

        $st->execute([
            ':surname'     => $data['surname']     ?? null,
            ':name'        => $data['name']        ?? null,
            ':patronymic'  => $data['patronymic']  ?? null,
            ':district_id' => $data['district_id'] ?? null,
            ':street_id'   => $data['street_id']   ?? null,
            ':house_id'    => $data['house_id']    ?? null,
            ':id'          => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare("UPDATE users SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL");
        $st->execute([$id]);
    }

    public function getAdminUsers(int $deleted = 0): array
    {
        // подстрой под реальные имена полей/таблиц
        $whereDeleted = $deleted === 1
            ? 'u.deleted_at IS NOT NULL'
            : 'u.deleted_at IS NULL';

        error_log("User deleted? ={$whereDeleted}");

        $sql = "
            SELECT
                u.id,
                u.created_at           AS registration_date,
                u.auth_method,
                u.surname,
                u.name,
                u.patronymic,
                u.phone,
                u.link_vk,
                s.street                    AS street,
                h.house                     AS house,
                u.district_id               AS district
            FROM users u
            LEFT JOIN streets   s ON u.street_id   = s.id
            LEFT JOIN house    h ON u.house_id    = h.id
            LEFT JOIN districts d ON u.district_id = d.id
            WHERE $whereDeleted
            ORDER BY u.id DESC
        ";
        
        error_log("User deleted? ={$whereDeleted}");
        $st = $this->pdo->query($sql);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateAdminUser(int $id, array $data): void
    {
        $sql = "
            UPDATE users
            SET
                surname     = :surname,
                name        = :name,
                patronymic  = :patronymic,
                phone       = :phone,
                link_vk     = :link_vk,
                district_id = :district_id,
                street_id   = :street_id,
                house_id    = :house_id,
                auth_method = :auth_method,
                updated_at  = NOW()
            WHERE id = :id
              AND deleted_at IS NULL
        ";

        $st = $this->pdo->prepare($sql);
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
            ':id'          => $id,
        ]);
    }

    public function getUserDistrictByAddress(string $street, string $house): ?array
    {
        $sql = "
            SELECT d.id, d.number
            FROM house h
            INNER JOIN streets   s ON h.street_id   = s.id
            INNER JOIN districts d ON s.district_id = d.id
            WHERE s.street = :street
              AND h.house  = :house
              AND h.deleted_at IS NULL
            LIMIT 1
        ";

        $st = $this->pdo->prepare($sql);
        $st->execute([
            ':street' => $street,
            ':house'  => $house,
        ]);

        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateProfile(int $id, array $data): void
    {
        $st = $this->pdo->prepare("
            UPDATE users SET
                surname     = :surname,
                name        = :name,
                patronymic  = :patronymic,
                district_id = :district_id,
                street_id   = :street_id,
                house_id    = :house_id
            WHERE id = :id
        ");

        $st->execute([
            ':surname'     => $data['surname']     ?? null,
            ':name'        => $data['name']        ?? null,
            ':patronymic'  => $data['patronymic']  ?? null,
            ':district_id' => $data['district_id'] ?? null,
            ':street_id'   => $data['street_id']   ?? null,
            ':house_id'    => $data['house_id']    ?? null,
            ':id'          => $id,
        ]);
    }
}
