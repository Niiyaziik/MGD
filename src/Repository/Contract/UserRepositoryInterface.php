<?php
namespace App\Repository\Contract;

interface UserRepositoryInterface
{
    public function firstOrCreateByPhone(string $phone): array;
    public function find(int $id): array;
    public function all(): array;
    public function findByPhone(string $phone): ?array;
    public function create(array $data): int;
    public function update(int $id, array $data): void;
    public function delete(int $id): void;
    public function getAdminUsers(int $deleted = 0): array;
    public function updateAdminUser(int $id, array $data): void;
    public function getUserDistrictByAddress(string $street, string $house): ?array;

}
