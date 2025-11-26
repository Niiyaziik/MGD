<?php
namespace App\Repository\Contract;

interface DistrictRepositoryInterface
{
    public function all(): array;
    public function find(int $id): array;
    public function createStreet(int $districtId, string $street): void;
    public function renameStreet(int $districtId, string $oldStreet, string $newStreet): int;
    public function createHouse(int $districtId, string $street, string $house): void;
    public function updateHouse(int $districtId, string $street, string $oldHouse, string $newHouse): int;
    public function deleteHouse(int $districtId, string $street, string $house): int;

    /** Поиск дублей по street+house (если у тебя есть такие поля) */
    public function findDuplicates(): array;
}
