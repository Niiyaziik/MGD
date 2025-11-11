<?php
namespace App\Repository\Contract;

interface UserRepositoryInterface
{
    public function firstOrCreateByPhone(string $phone): array;
    public function find(int $id): array;
    public function all(): array;
    public function update(int $id, array $data): void;
    public function delete(int $id): void;
}
