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

    public function find(int $id): array
    {
        $st = $this->pdo->prepare("SELECT * FROM districts WHERE id=? AND deleted_at IS NULL");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) throw new \RuntimeException('District not found');
        return $row;
    }

    public function create(array $data): int
    {
        $st = $this->pdo->prepare("INSERT INTO districts(name) VALUES(?)");
        $st->execute([$data['name'] ?? null]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $st = $this->pdo->prepare("UPDATE districts SET name=?, updated_at=NOW() WHERE id=? AND deleted_at IS NULL");
        $st->execute([$data['name'] ?? null, $id]);
    }

    public function delete(int $id): void
    {
        $st = $this->pdo->prepare("UPDATE districts SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL");
        $st->execute([$id]);
    }

    public function findDuplicates(): array
    {
        // если есть поля street+house — верни дубли, иначе – пусто
        // Под свой кейс можешь заменить запрос
        return [];
    }
}
