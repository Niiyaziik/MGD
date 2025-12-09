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

    /*
      Создать улицу (если ещё нет активной записи в streets).
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

    /*
     получить id улицы (создать при необходимости).
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

    public function getAdminAddresses(int $deleted = 0): array
    {
        $whereDeleted = $deleted === 1
            ? 'h.deleted_at IS NOT NULL'
            : 'h.deleted_at IS NULL';

        $sql = "
            SELECT
                h.id,
                d.district        AS district,
                s.street          AS street,
                h.house         AS house
            FROM house h
            LEFT JOIN streets   s ON h.street_id   = s.id
            LEFT JOIN districts d ON s.district_id = d.id
            WHERE $whereDeleted
            ORDER BY d.district ASC, s.street ASC, h.house ASC
        ";

        $st = $this->pdo->query($sql);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

        public function deleteAddress(int $id): void
    {
        $st = $this->pdo->prepare("
            UPDATE house
            SET deleted_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $st->execute([':id' => $id]);
    }

    public function findAddressDuplicates(): array
    {
        $sql = "
            SELECT
                LOWER(TRIM(s.street)) AS street,
                LOWER(TRIM(h.house))  AS house,
                GROUP_CONCAT(DISTINCT d.district ORDER BY d.district SEPARATOR ', ') AS districts,
                GROUP_CONCAT(h.id ORDER BY h.id SEPARATOR ',') AS row_ids,
                COUNT(DISTINCT d.district) AS district_count
            FROM house h
            INNER JOIN streets   s ON h.street_id = s.id
            INNER JOIN districts d ON s.district_id = d.id
            WHERE h.deleted_at IS NULL
            GROUP BY
                LOWER(TRIM(s.street)),
                LOWER(TRIM(h.house))
            HAVING COUNT(DISTINCT d.district) > 1;
        ";

        $st = $this->pdo->query($sql);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        // вернём только нужные поля
        return array_map(static function (array $r): array {
            return [
                'street'    => $r['street'],
                'house'     => $r['house'],
                'districts' => $r['districts'],
                'row_ids'   => $r['row_ids'],
            ];
        }, $rows);
    }

    public function suggest(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $like = '%' . mb_strtolower($query, 'UTF-8') . '%';

        // под твою схему:
        //   house  (id, street_id, house, deleted_at)
        //   streets(id, street, ...)
        $sql = "
            SELECT DISTINCT
                s.street AS street,
                h.house  AS house
            FROM house h
            INNER JOIN streets s ON h.street_id = s.id
            WHERE h.deleted_at IS NULL
              AND (
                    LOWER(s.street) LIKE :q
                 OR LOWER(h.house)  LIKE :q
              )
            ORDER BY s.street ASC, h.house ASC
            LIMIT 20
        ";

        $st = $this->pdo->prepare($sql);
        $st->execute([':q' => $like]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        // Нормализуем структуру (на случай лишних полей)
        return array_map(static fn(array $row): array => [
            'street' => (string)($row['street'] ?? ''),
            'house'  => (string)($row['house'] ?? ''),
        ], $rows);
    }

    public function findDistrictByStreetAndHouse(string $streetName, string $houseValue): ?array
    {
        $streetNorm = mb_strtolower(trim($streetName));
        $houseNorm  = mb_strtolower(trim($houseValue));

        // 1) Сначала пробуем найти конкретный дом
        $sqlExact = "
            SELECT
                d.id        AS district_id,
                d.district  AS district_number,
                s.id        AS street_id,
                h.id        AS house_id
            FROM streets s
            JOIN house   h ON h.street_id = s.id
            JOIN districts d ON d.id = s.district_id
            WHERE
                s.deleted_at IS NULL
                AND h.deleted_at IS NULL
                AND d.deleted_at IS NULL
                AND TRIM(LOWER(s.street)) = :street
                AND TRIM(LOWER(h.house))  = :house
            LIMIT 1
        ";

        $st = $this->pdo->prepare($sqlExact);
        $st->execute([
            ':street' => $streetNorm,
            ':house'  => $houseNorm,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return [
                'district_id'     => (int)$row['district_id'],
                'district_number' => (string)$row['district_number'],
                'street_id'       => (int)$row['street_id'],
                'house_id'        => (int)$row['house_id'],
            ];
        }

        // 2) Фолбэк: если точный дом не найден, ищем запись "все дома" для этой улицы
        $sqlAllHouses = "
            SELECT
                d.id        AS district_id,
                d.district  AS district_number,
                s.id        AS street_id,
                h.id        AS house_id
            FROM streets s
            JOIN house   h ON h.street_id = s.id
            JOIN districts d ON d.id = s.district_id
            WHERE
                s.deleted_at IS NULL
                AND h.deleted_at IS NULL
                AND d.deleted_at IS NULL
                AND TRIM(LOWER(s.street)) = :street
                AND TRIM(LOWER(h.house))  = 'все дома'
            LIMIT 1
        ";

        $st = $this->pdo->prepare($sqlAllHouses);
        $st->execute([
            ':street' => $streetNorm,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'district_id'     => (int)$row['district_id'],
            'district_number' => (string)$row['district_number'],
            'street_id'       => (int)$row['street_id'],
            'house_id'        => (int)$row['house_id'],
        ];
    }
}
