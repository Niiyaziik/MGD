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

    public function getAdminAddresses(int $deleted = 0): array;
    public function deleteAddress(int $id): void;
    public function findAddressDuplicates(): array;
    public function suggest(string $query): array;
    public function findDistrictByStreetAndHouse(string $streetName, string $houseValue): ?array;
}
