<?php
namespace App\Repository\Contract;

interface DistrictRepositoryInterface
{
    public function all(): array;
    public function find(int $id): array;
    public function create(array $data): int;
    public function update(int $id, array $data): void;
    public function delete(int $id): void;

    /** Поиск дублей по street+house (если у тебя есть такие поля) */
    public function findDuplicates(): array;
}
