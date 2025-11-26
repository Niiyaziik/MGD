<?php
namespace App\Repository\Pdo;

use App\Repository\Contract\DistrictRepositoryInterface;
use PDO;

class DistrictRepository implements DistrictRepositoryInterface
{
    public function __construct(private PDO $pdo) {}

    public function all(): array
    {
        $st = $this->pdo->query("SELECT * FROM districts WHERE deleted_at IS NULL ORDER BY id DESC");
        return $st->fetchAll();
    }

    public function find(int $district): array
    {
        error_log("District find(): districtId = {$district}");
        $st = $this->pdo->prepare("
            SELECT
                s.street AS street,
                h.house  AS house
            FROM districts d
            JOIN streets s
                ON s.district_id = d.id
            AND s.deleted_at IS NULL
            LEFT JOIN house h
                ON h.street_id = s.id
            AND h.deleted_at IS NULL
            WHERE d.id = :id
            AND d.deleted_at IS NULL
            ORDER BY 
            s.id,
            h.id
        ");
    $st->execute(['id' => $district]);
    error_log("District find(): executing with id={$district}");
    return $st->fetchAll();
    }

    public function findAdmin(int $district): array
    {
        error_log("District find(): districtId = {$district}");
        $st = $this->pdo->prepare("
            SELECT
            d.id      AS district_id,
            s.id      AS street_id,
            s.street  AS street,
            h.id      AS house_id,
            h.house   AS house
        FROM districts d
        LEFT JOIN streets s
            ON s.district_id = d.id
           AND s.deleted_at IS NULL
        LEFT JOIN house h
            ON h.street_id = s.id
           AND h.deleted_at IS NULL
        WHERE d.id = :id
          AND d.deleted_at IS NULL
        ORDER BY
            s.id,
            h.id
        ");
    $st->execute(['id' => $district]);
    error_log("District find(): executing with id={$district}");
    return $st->fetchAll();
    }

    /**
     * Создать улицу (если ещё нет активной записи в streets).
     */
    public function createStreet(int $districtId, string $street): void
    {
        $street = trim($street);
        if ($street === '') {
            return;
        }

        // Проверяем, есть ли уже такая улица в этом округе (и не удалена)
        $st = $this->pdo->prepare("
            SELECT id
            FROM streets
            WHERE district_id = :district_id
              AND street      = :street
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([
            ':district_id' => $districtId,
            ':street'      => $street,
        ]);

        if ($st->fetch()) {
            // уже есть — ничего не делаем
            return;
        }

        // Создаём новую улицу
        $st = $this->pdo->prepare("
            INSERT INTO streets (district_id, street, created_at, updated_at)
            VALUES (:district_id, :street, NOW(), NOW())
        ");
        $st->execute([
            ':district_id' => $districtId,
            ':street'      => $street,
        ]);
    }

    /**
     * Переименовать улицу по названию.
     */
    public function renameStreet(int $districtId, string $oldStreet, string $newStreet): int
    {
        $oldStreet = trim($oldStreet);
        $newStreet = trim($newStreet);

        if ($oldStreet === '' || $newStreet === '' || $oldStreet === $newStreet) {
            return 0;
        }

        $st = $this->pdo->prepare("
            UPDATE streets
            SET street = :new_street,
                updated_at = NOW()
            WHERE district_id = :district_id
              AND street      = :old_street
              AND deleted_at IS NULL
        ");
        $st->execute([
            ':district_id' => $districtId,
            ':old_street'  => $oldStreet,
            ':new_street'  => $newStreet,
        ]);

        return $st->rowCount();
    }

    /**
     * Внутренний helper: получить id улицы (создать при необходимости).
     */
    private function getOrCreateStreetId(int $districtId, string $street): ?int
    {
        $street = trim($street);
        if ($street === '') {
            return null;
        }

        // пробуем найти активную улицу
        $st = $this->pdo->prepare("
            SELECT id
            FROM streets
            WHERE district_id = :district_id
              AND street      = :street
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([
            ':district_id' => $districtId,
            ':street'      => $street,
        ]);
        $row = $st->fetch();
        if ($row && isset($row['id'])) {
            return (int)$row['id'];
        }

        // если нет — создаём
        $st = $this->pdo->prepare("
            INSERT INTO streets (district_id, street, created_at, updated_at)
            VALUES (:district_id, :street, NOW(), NOW())
        ");
        $st->execute([
            ':district_id' => $districtId,
            ':street'      => $street,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Создать дом на улице.
     */
    public function createHouse(int $districtId, string $street, string $house): void
    {
        $house = trim($house);
        if ($house === '') {
            return;
        }

        $streetId = $this->getOrCreateStreetId($districtId, $street);
        if ($streetId === null) {
            return;
        }

        // Проверяем, что такого дома ещё нет (и он не удалён)
        $st = $this->pdo->prepare("
            SELECT id
            FROM house
            WHERE street_id  = :street_id
              AND house      = :house
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([
            ':street_id' => $streetId,
            ':house'     => $house,
        ]);
        if ($st->fetch()) {
            return;
        }

        // Создаём дом
        $st = $this->pdo->prepare("
            INSERT INTO house (street_id, house, created_at, updated_at)
            VALUES (:street_id, :house, NOW(), NOW())
        ");
        $st->execute([
            ':street_id' => $streetId,
            ':house'     => $house,
        ]);
    }

    /**
     * Обновить дом на улице (по старому и новому значению номера).
     */
    public function updateHouse(int $districtId, string $street, string $oldHouse, string $newHouse): int
    {
        $oldHouse = trim($oldHouse);
        $newHouse = trim($newHouse);

        if ($oldHouse === '' || $newHouse === '' || $oldHouse === $newHouse) {
            return 0;
        }

        $streetId = $this->getOrCreateStreetId($districtId, $street);
        if ($streetId === null) {
            return 0;
        }

        $st = $this->pdo->prepare("
            UPDATE house
            SET house      = :new_house,
                updated_at = NOW()
            WHERE street_id  = :street_id
              AND house      = :old_house
              AND deleted_at IS NULL
        ");
        $st->execute([
            ':street_id' => $streetId,
            ':old_house' => $oldHouse,
            ':new_house' => $newHouse,
        ]);

        return $st->rowCount();
    }

    /**
     * Мягкое удаление дома (deleted_at = NOW()).
     */
    public function deleteHouse(int $districtId, string $street, string $house): int
    {
        $house = trim($house);
        if ($house === '') {
            return 0;
        }

        $streetId = $this->getOrCreateStreetId($districtId, $street);
        if ($streetId === null) {
            return 0;
        }

        $st = $this->pdo->prepare("
            UPDATE house
            SET deleted_at = NOW()
            WHERE street_id  = :street_id
              AND house      = :house
              AND deleted_at IS NULL
        ");
        $st->execute([
            ':street_id' => $streetId,
            ':house'     => $house,
        ]);

        return $st->rowCount();
    }

    public function findDuplicates(): array
    {
        // если есть поля street+house — верни дубли, иначе – пусто
        // Под свой кейс можешь заменить запрос
        return [];
    }
}
