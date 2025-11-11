<?php
namespace App\Repository\Contract;

interface CandidateRepositoryInterface
{
    /** Вернёт всех кандидатов (опционально по округу) с полем votes_count */
    public function all(?int $districtId = null): array;

    /** Найти кандидата по id (с votes_count) */
    public function find(int $id): array;
    
    public function first(int $limit = 5): array;

    /** Создать кандидата, вернёт его id */
    public function create(array $data): int;

    /** Обновить кандидата */
    public function update(int $id, array $data): void;

    /** Удалить кандидата (жёстко) */
    public function delete(int $id): void;
}
